/**
 * EasyRest - Booking Price JS (Version 2.0 - Securise)
 * 
 * Recupere le prix depuis Booking.com via AJAX WordPress
 * 
 * Securite:
 * - Utilise text() au lieu de html() pour eviter XSS
 * - Validation des donnees cote client
 * - Gestion des erreurs robuste
 */

(function($) {
    'use strict';

    var EasyRestBooking = {
        
        // Configuration depuis wp_localize_script
        config: window.easyrestConfig || {},
        
        // Etat
        state: {
            isLoading: false,
            priceCache: {},
            requestCount: 0
        },

        /**
         * Initialisation
         */
        init: function() {
            // Verifier que la config est presente
            if (!this.config.ajaxUrl || !this.config.nonce) {
                console.warn('EasyRest: Configuration manquante');
                return;
            }
            
            this.bindEvents();
            this.setMinDates();
        },

        /**
         * Bindage des evenements
         */
        // Max total guests for the apartment
        MAX_GUESTS: 4,
        MAX_CHILDREN: 2,

        bindEvents: function() {
            var self = this;

            // Changement de dates
            $('#checkin, #checkout').on('change', function() {
                self.validateDates();
            });

            // Bouton verifier disponibilite
            $('#check-availability').on('click', function(e) {
                e.preventDefault();
                self.checkPriceAndAvailability();
            });

            // MAJ checkout min quand checkin change
            $('#checkin').on('change', function() {
                var checkin = $(this).val();
                if (checkin) {
                    var minCheckout = self.addDays(new Date(checkin), 1);
                    $('#checkout').attr('min', self.formatDate(minCheckout));

                    var currentCheckout = $('#checkout').val();
                    if (currentCheckout && new Date(currentCheckout) <= new Date(checkin)) {
                        $('#checkout').val('');
                    }
                }
            });

            // Enforce max guests: adjust children options when adults change, and vice versa
            $('#adults').on('change', function() {
                self.enforceMaxGuests();
            });
            $('#children').on('change', function() {
                self.enforceMaxGuests();
            });

            // Initialize on load
            this.enforceMaxGuests();
        },

        /**
         * Enforce max 4 guests total by capping children options
         */
        enforceMaxGuests: function() {
            var adults = parseInt($('#adults').val(), 10) || 1;
            var maxChildren = Math.min(this.MAX_GUESTS - adults, this.MAX_CHILDREN);
            var currentChildren = parseInt($('#children').val(), 10) || 0;

            // Rebuild children options
            var $children = $('#children');
            $children.empty();
            for (var i = 0; i <= maxChildren; i++) {
                $children.append($('<option>').val(i).text(i));
            }

            // Restore selection if still valid, otherwise cap it
            if (currentChildren > maxChildren) {
                $children.val(maxChildren);
            } else {
                $children.val(currentChildren);
            }
        },

        /**
         * Definir les dates minimales
         */
        setMinDates: function() {
            var today = new Date();
            var tomorrow = this.addDays(today, 1);
            
            $('#checkin').attr('min', this.formatDate(today));
            $('#checkout').attr('min', this.formatDate(tomorrow));
        },

        /**
         * Valider les dates
         */
        validateDates: function() {
            var checkin = $('#checkin').val();
            var checkout = $('#checkout').val();
            
            if (!checkin || !checkout) {
                return false;
            }
            
            var checkinDate = new Date(checkin);
            var checkoutDate = new Date(checkout);
            
            if (checkoutDate <= checkinDate) {
                this.showNotification(this.config.i18n.invalidDates || 'Invalid dates', 'error');
                return false;
            }
            
            return true;
        },

        /**
         * Verifier prix et disponibilite
         */
        checkPriceAndAvailability: function() {
            var self = this;
            
            // Recuperer les valeurs
            var checkin = $('#checkin').val();
            var checkout = $('#checkout').val();
            var adults = $('#adults').val();
            var children = $('#children').val();
            
            // Validation
            if (!checkin || !checkout) {
                this.showNotification(this.config.i18n.selectDates || 'Please select dates', 'error');
                return;
            }
            
            if (!this.validateDates()) {
                return;
            }

            // Validate guest count
            var totalGuests = parseInt(adults, 10) + parseInt(children, 10);
            if (totalGuests > this.MAX_GUESTS) {
                this.showNotification('Maximum ' + this.MAX_GUESTS + ' guests allowed.', 'error');
                return;
            }

            // Rate limiting cote client (protection basique)
            this.state.requestCount++;
            if (this.state.requestCount > 20) {
                this.showNotification(this.config.i18n.tooManyRequests || 'Too many requests', 'error');
                return;
            }
            
            // Verifier le cache
            var cacheKey = checkin + '_' + checkout + '_' + adults + '_' + children;
            if (this.state.priceCache[cacheKey]) {
                this.displayPrice(this.state.priceCache[cacheKey]);
                return;
            }
            
            // Afficher le loading
            this.showLoading();
            
            // Calculer le nombre de nuits
            var nights = this.calculateNights(checkin, checkout);
            
            // Appel AJAX
            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'easyrest_get_price',
                    nonce: this.config.nonce,
                    checkin: checkin,
                    checkout: checkout,
                    adults: parseInt(adults, 10),
                    children: parseInt(children, 10)
                },
                success: function(response) {
                    if (response.success && response.data) {
                        var priceData = {
                            bookingPrice: parseFloat(response.data.booking_price) || 0,
                            directPrice: parseFloat(response.data.direct_price) || 0,
                            discount: parseInt(response.data.discount, 10) || 15,
                            nights: parseInt(response.data.nights, 10) || nights,
                            currency: response.data.currency || 'EUR',
                            checkin: checkin,
                            checkout: checkout,
                            adults: adults,
                            children: children,
                            meta: response.data.meta || { source: 'booking', cached: false, warnings: [] }
                        };
                        
                        // Valider les donnees
                        if (priceData.bookingPrice > 0 && priceData.directPrice > 0) {
                            // Mettre en cache
                            self.state.priceCache[cacheKey] = priceData;
                            self.displayPrice(priceData);
                            
                            // Afficher warnings si présents
                            if (priceData.meta.warnings && priceData.meta.warnings.length > 0) {
                                console.info('EasyRest warnings:', priceData.meta.warnings);
                            }
                        } else {
                            self.showPriceError();
                        }
                    } else {
                        // Erreur avec code standardisé
                        var errorMsg = response.data ? (response.data.message || response.data.error) : null;
                        self.showPriceError(errorMsg);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('EasyRest AJAX error:', status, error);
                    self.showPriceError();
                },
                complete: function() {
                    self.state.isLoading = false;
                }
            });
        },

        /**
         * Afficher le loading
         */
        showLoading: function() {
            this.state.isLoading = true;
            $('#price-section').show();
            $('#price-loading').show();
            $('#price-result').hide();
            $('#price-error').hide();
            $('#booking-cta').hide();
        },

        /**
         * Afficher le prix (SECURISE - utilise text() pas html())
         */
        displayPrice: function(data) {
            var currency = this.config.currency || '€';
            
            // Prix par nuit
            var bookingPerNight = data.bookingPrice / data.nights;
            var directPerNight = data.directPrice / data.nights;
            
            // Formater les prix de maniere securisee
            var bookingPriceText = currency + bookingPerNight.toFixed(2) + (this.config.i18n.perNight || '/nuit');
            var directPriceText = currency + directPerNight.toFixed(2) + (this.config.i18n.perNight || '/nuit');
            
            // Utiliser text() au lieu de html() pour securite XSS
            $('#booking-price').empty().append(
                $('<s>').text(currency + bookingPerNight.toFixed(2))
            ).append(document.createTextNode(this.config.i18n.perNight || '/nuit'));
            
            $('#direct-price').text(directPriceText);
            $('#savings-badge').text('-' + data.discount + '%');
            
            // Total avec dates
            var nightsText = data.nights > 1 ?
                (this.config.i18n.nights || 'nuits') :
                (this.config.i18n.night || 'nuit');
            var totalText = currency + data.directPrice.toFixed(2) + ' pour ' + data.nights + ' ' + nightsText;
            $('#total-price').text(totalText);

            // Supprimer l'ancien element s'il existe
            $('#price-total-row .savings-total').remove();
            $('#price-total-row .booking-dates').remove();

            // Afficher les dates de réservation
            var datesText = this.formatDateShort(data.checkin) + ' → ' + this.formatDateShort(data.checkout);
            $('<div>')
                .addClass('booking-dates')
                .text(datesText)
                .appendTo('#price-total-row');

            // Economies en petit
            var savings = data.bookingPrice - data.directPrice;
            if (savings > 0) {
                var savingsText = '(' + (this.config.i18n.savings || 'Économie') + ': ' + currency + savings.toFixed(2) + ')';

                $('<div>')
                    .addClass('savings-total')
                    .text(savingsText)
                    .appendTo('#price-total-row');
            }
            
            // Masquer loading, afficher resultat
            $('#price-loading').hide();
            $('#price-result').show();
            $('#price-error').hide();
            
            // Afficher CTA
            this.showBookingCTA(data);
        },

        /**
         * Afficher erreur prix
         */
        showPriceError: function(message) {
            $('#price-loading').hide();
            $('#price-result').hide();
            
            if (message) {
                $('#price-error span').text(message);
            }
            
            $('#price-error').show();
            $('#booking-cta').hide();
        },

        /**
         * Afficher CTA reservation (SECURISE)
         */
        showBookingCTA: function(data) {
            var whatsappNumber = this.config.whatsappNumber || '33612345678'; // Numéro par défaut
            var bookingUrl = this.config.bookingUrl || 'https://www.booking.com/hotel/it/easyrest-milan.html';

            // Construire message WhatsApp (encode pour securite)
            var messageLines = [
                'Bonjour, je souhaite reserver l\'appartement EasyRest Milan:',
                'Du ' + this.formatDateFR(data.checkin) + ' au ' + this.formatDateFR(data.checkout),
                data.adults + ' adulte(s)' + (parseInt(data.children, 10) > 0 ? ', ' + data.children + ' enfant(s)' : ''),
                'Prix: ' + this.config.currency + data.directPrice.toFixed(2) + ' (' + data.nights + ' nuits)',
                '',
                'Merci de confirmer la disponibilite.'
            ];
            var message = encodeURIComponent(messageLines.join('\n'));

            // MAJ lien WhatsApp
            var whatsappUrl = 'https://wa.me/' + whatsappNumber.replace(/[^0-9]/g, '') + '?text=' + message;
            $('#whatsapp-booking').attr('href', whatsappUrl).show();

            // MAJ lien Booking.com avec parametres de dates
            var bookingUrlWithDates = bookingUrl;
            // Ajouter les parametres de dates si URL Booking.com
            if (bookingUrl.indexOf('booking.com') !== -1) {
                var separator = bookingUrl.indexOf('?') !== -1 ? '&' : '?';
                bookingUrlWithDates = bookingUrl + separator +
                    'checkin=' + data.checkin +
                    '&checkout=' + data.checkout +
                    '&group_adults=' + data.adults +
                    '&group_children=' + data.children;
            }
            $('#booking-com-btn').attr('href', bookingUrlWithDates);

            // Afficher CTA
            $('#booking-cta').fadeIn();
        },

        /**
         * Notification utilisateur
         */
        showNotification: function(message, type) {
            // TODO: Remplacer par une notification plus elegante
            if (type === 'error') {
                alert(message);
            }
        },

        /**
         * Calculer nombre de nuits
         */
        calculateNights: function(checkin, checkout) {
            var start = new Date(checkin);
            var end = new Date(checkout);
            var diff = end - start;
            return Math.ceil(diff / (1000 * 60 * 60 * 24));
        },

        /**
         * Ajouter des jours a une date
         */
        addDays: function(date, days) {
            var result = new Date(date);
            result.setDate(result.getDate() + days);
            return result;
        },

        /**
         * Formater une date en YYYY-MM-DD
         */
        formatDate: function(date) {
            return date.toISOString().split('T')[0];
        },

        /**
         * Formater une date en francais
         */
        formatDateFR: function(dateStr) {
            var date = new Date(dateStr);
            var options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
            return date.toLocaleDateString('fr-FR', options);
        },

        /**
         * Formater une date courte (ex: 15 jan.)
         */
        formatDateShort: function(dateStr) {
            var date = new Date(dateStr);
            var options = { day: 'numeric', month: 'short' };
            return date.toLocaleDateString('fr-FR', options);
        }
    };

    // Initialiser au chargement DOM
    $(document).ready(function() {
        EasyRestBooking.init();
    });

})(jQuery);
