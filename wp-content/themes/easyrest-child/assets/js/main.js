/**
 * EasyRest - Main JS
 * Scripts principaux du site
 */

(function($) {
    'use strict';

    const EasyRest = {
        
        /**
         * Initialisation
         */
        init: function() {
            this.initSmoothScroll();
            this.initGalleryLightbox();
            this.initAnimations();
            this.initMobileMenu();
            this.initStickyHeader();
        },

        /**
         * Smooth scroll pour les ancres
         */
        initSmoothScroll: function() {
            $('a[href^="#"]').on('click', function(e) {
                const target = $(this.getAttribute('href'));
                if (target.length) {
                    e.preventDefault();
                    $('html, body').animate({
                        scrollTop: target.offset().top - 80
                    }, 600);
                }
            });
        },

        /**
         * Lightbox pour la galerie
         */
        initGalleryLightbox: function() {
            if (!$('.easyrest-gallery').length) return;
            
            // Créer le conteneur lightbox
            $('body').append(`
                <div class="easyrest-lightbox" id="easyrest-lightbox">
                    <button class="lightbox-close" aria-label="Fermer">&times;</button>
                    <button class="lightbox-prev" aria-label="Précédent">&#10094;</button>
                    <button class="lightbox-next" aria-label="Suivant">&#10095;</button>
                    <div class="lightbox-content">
                        <img src="" alt="">
                        <div class="lightbox-caption"></div>
                    </div>
                    <div class="lightbox-counter"></div>
                </div>
            `);
            
            const lightbox = $('#easyrest-lightbox');
            const lightboxImg = lightbox.find('img');
            const lightboxCaption = lightbox.find('.lightbox-caption');
            const lightboxCounter = lightbox.find('.lightbox-counter');
            
            let currentIndex = 0;
            let images = [];
            
            // Ouvrir lightbox
            $('.easyrest-gallery-item a.lightbox').on('click', function(e) {
                e.preventDefault();
                
                // Collecter toutes les images
                images = [];
                $('.easyrest-gallery-item a.lightbox').each(function(i) {
                    images.push({
                        src: $(this).attr('href'),
                        caption: $(this).data('caption') || ''
                    });
                    if ($(this).is(e.currentTarget)) {
                        currentIndex = i;
                    }
                });
                
                showImage(currentIndex);
                lightbox.addClass('active');
                $('body').addClass('lightbox-open');
            });
            
            // Fermer lightbox
            lightbox.find('.lightbox-close').on('click', closeLightbox);
            lightbox.on('click', function(e) {
                if ($(e.target).is(lightbox)) {
                    closeLightbox();
                }
            });
            
            // Navigation
            lightbox.find('.lightbox-prev').on('click', function() {
                currentIndex = (currentIndex - 1 + images.length) % images.length;
                showImage(currentIndex);
            });
            
            lightbox.find('.lightbox-next').on('click', function() {
                currentIndex = (currentIndex + 1) % images.length;
                showImage(currentIndex);
            });
            
            // Clavier
            $(document).on('keydown', function(e) {
                if (!lightbox.hasClass('active')) return;
                
                if (e.key === 'Escape') closeLightbox();
                if (e.key === 'ArrowLeft') lightbox.find('.lightbox-prev').click();
                if (e.key === 'ArrowRight') lightbox.find('.lightbox-next').click();
            });
            
            function showImage(index) {
                lightboxImg.attr('src', images[index].src);
                lightboxCaption.text(images[index].caption);
                lightboxCounter.text(`${index + 1} / ${images.length}`);
            }
            
            function closeLightbox() {
                lightbox.removeClass('active');
                $('body').removeClass('lightbox-open');
            }
        },

        /**
         * Animations au scroll
         */
        initAnimations: function() {
            const animatedElements = $('.animate-on-scroll');
            
            if (!animatedElements.length) return;
            
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('animated');
                        observer.unobserve(entry.target);
                    }
                });
            }, {
                threshold: 0.1,
                rootMargin: '0px 0px -50px 0px'
            });
            
            animatedElements.each(function() {
                observer.observe(this);
            });
        },

        /**
         * Menu mobile
         */
        initMobileMenu: function() {
            const menuToggle = $('.menu-toggle');
            const mainNav = $('.main-navigation');
            
            menuToggle.on('click', function() {
                mainNav.toggleClass('toggled');
                $(this).attr('aria-expanded', mainNav.hasClass('toggled'));
            });
            
            // Fermer le menu au clic sur un lien
            mainNav.find('a').on('click', function() {
                if ($(window).width() < 768) {
                    mainNav.removeClass('toggled');
                    menuToggle.attr('aria-expanded', 'false');
                }
            });
        },

        /**
         * Header sticky
         */
        initStickyHeader: function() {
            const header = $('.site-header');
            const headerHeight = header.outerHeight();
            let lastScroll = 0;
            
            $(window).on('scroll', function() {
                const currentScroll = $(this).scrollTop();
                
                // Ajouter classe scrolled
                if (currentScroll > 50) {
                    header.addClass('scrolled');
                } else {
                    header.removeClass('scrolled');
                }
                
                // Hide/show header on scroll
                if (currentScroll > lastScroll && currentScroll > headerHeight) {
                    header.addClass('header-hidden');
                } else {
                    header.removeClass('header-hidden');
                }
                
                lastScroll = currentScroll;
            });
        }
    };

    // Initialiser au chargement
    $(document).ready(function() {
        EasyRest.init();
    });

})(jQuery);
