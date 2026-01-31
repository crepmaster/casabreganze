/**
 * EasyRest Milan Landing - Language & Navigation
 *
 * Handles:
 * - Smooth scrolling for CTA buttons
 * - Language switching for all data-* elements
 * - localStorage persistence for language preference
 *
 * @package EasyRest_Child
 * @version 3.0.0
 */

(function() {
    'use strict';

    var currentLang = 'fr';

    /**
     * Update ALL elements with data-[lang] attributes
     * @param {string} lang - Language code (fr, en, it, es, pt, zh)
     */
    function updateAllText(lang) {
        currentLang = lang;

        // Select all elements that have the data-[lang] attribute
        var elements = document.querySelectorAll('[data-' + lang + ']');

        elements.forEach(function(el) {
            var text = el.getAttribute('data-' + lang);
            if (text) {
                el.textContent = text;
            }
        });

        // Update language selector active state
        var langButtons = document.querySelectorAll('.language-selector button[data-lang]');
        langButtons.forEach(function(btn) {
            btn.classList.remove('active');
            if (btn.getAttribute('data-lang') === lang) {
                btn.classList.add('active');
            }
        });

        // Save preference
        try {
            localStorage.setItem('easyrest_lang', lang);
        } catch (e) {
            // localStorage not available
        }

        // Dispatch event for other scripts
        document.dispatchEvent(new CustomEvent('easyrest:languageChange', {
            detail: { lang: lang }
        }));
    }

    /**
     * Initialize language selector
     */
    function initLanguageSelector() {
        var langButtons = document.querySelectorAll('.language-selector button[data-lang]');

        langButtons.forEach(function(btn) {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                var lang = this.getAttribute('data-lang');
                if (lang) {
                    updateAllText(lang);
                }
            });
        });

        // Restore saved language preference
        try {
            var savedLang = localStorage.getItem('easyrest_lang');
            if (savedLang && ['fr', 'en', 'it', 'es', 'pt', 'zh'].indexOf(savedLang) !== -1) {
                updateAllText(savedLang);
            }
        } catch (e) {
            // localStorage not available
        }
    }

    /**
     * Initialize smooth scroll for reservation CTA buttons
     */
    function initSmoothScroll() {
        var links = document.querySelectorAll('a[href="#easyrest-reservation"]');

        links.forEach(function(link) {
            link.addEventListener('click', function(e) {
                e.preventDefault();

                var target = document.getElementById('easyrest-reservation');
                if (target) {
                    // No header offset needed for landing pure mode
                    var headerOffset = 20;
                    var elementPosition = target.getBoundingClientRect().top;
                    var offsetPosition = elementPosition + window.pageYOffset - headerOffset;

                    window.scrollTo({
                        top: offsetPosition,
                        behavior: 'smooth'
                    });

                    // Update URL hash without jumping
                    if (history.pushState) {
                        history.pushState(null, null, '#easyrest-reservation');
                    }
                }
            });
        });
    }

    /**
     * Handle direct URL hash navigation
     */
    function handleHashNavigation() {
        if (window.location.hash === '#easyrest-reservation') {
            setTimeout(function() {
                var target = document.getElementById('easyrest-reservation');
                if (target) {
                    var headerOffset = 20;
                    var elementPosition = target.getBoundingClientRect().top;
                    var offsetPosition = elementPosition + window.pageYOffset - headerOffset;

                    window.scrollTo({
                        top: offsetPosition,
                        behavior: 'smooth'
                    });
                }
            }, 300);
        }
    }

    /**
     * Initialize all functionality
     */
    function init() {
        initLanguageSelector();
        initSmoothScroll();
        handleHashNavigation();
    }

    // Run on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    // Expose API for external use
    window.EasyRestLanding = {
        setLanguage: updateAllText,
        getCurrentLang: function() { return currentLang; }
    };

})();
