/**
 * EasyRest — Global Language Switcher
 *
 * Applies client-side language switching to every page that includes the
 * shared site-header template part (.easyrest-language-selector).
 *
 * Reads and persists the user's choice via localStorage ('easyrest_lang').
 * Also handles the mobile hamburger toggle.
 *
 * @package EasyRest_Child
 * @version 1.0.0
 */
(function () {
    'use strict';

    var langSelector = document.querySelector('.easyrest-language-selector');
    if (!langSelector) return;

    var buttons   = Array.prototype.slice.call(langSelector.querySelectorAll('button[data-lang]'));
    var supported = {};
    buttons.forEach(function (b) { supported[b.dataset.lang] = true; });

    // ---- Determine initial language ----
    var lang = 'fr'; // default

    // URL parameter ?lang=xx
    try {
        var urlLang = new URLSearchParams(window.location.search).get('lang');
        if (urlLang && supported[urlLang]) lang = urlLang;
    } catch (e) { /* IE / old browsers */ }

    // localStorage takes precedence
    try {
        var savedLang = localStorage.getItem('easyrest_lang');
        if (savedLang && supported[savedLang]) lang = savedLang;
    } catch (e) {}

    // ---- Apply language ----
    function applyLang(newLang) {
        lang = newLang;

        // Toggle active class on buttons
        buttons.forEach(function (b) {
            if (b.dataset.lang === lang) {
                b.classList.add('active');
            } else {
                b.classList.remove('active');
            }
        });

        // Translate all elements with data-{lang} attributes on the page
        var translatable = document.querySelectorAll('[data-fr],[data-en],[data-it],[data-es],[data-pt],[data-zh]');
        translatable.forEach(function (el) {
            var val = el.getAttribute('data-' + lang);
            if (val !== null) el.textContent = val;
        });

        // Update <html lang="">
        document.documentElement.setAttribute('lang', lang);

        // Persist choice
        try { localStorage.setItem('easyrest_lang', lang); } catch (e) {}

        // Update URL without reload
        if (window.history && history.replaceState) {
            var url = new URL(window.location);
            url.searchParams.set('lang', lang);
            history.replaceState(null, '', url);
        }

        // Dispatch event so other scripts can react
        if (typeof CustomEvent === 'function') {
            document.dispatchEvent(new CustomEvent('easyrest:languageChange', {
                detail: { lang: lang }
            }));
        }
    }

    // ---- Bind language buttons ----
    buttons.forEach(function (btn) {
        btn.addEventListener('click', function () {
            applyLang(btn.dataset.lang);
        });
    });

    // ---- Mobile hamburger toggle ----
    var toggle = document.getElementById('easyrest-menu-toggle');
    var nav    = document.getElementById('easyrest-header-nav');

    if (toggle && nav) {
        toggle.addEventListener('click', function () {
            var isOpen = nav.classList.toggle('is-open');
            toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        });

        // Close menu on nav-link click (mobile UX)
        var navLinks = nav.querySelectorAll('.easyrest-nav-menu a');
        navLinks.forEach(function (link) {
            link.addEventListener('click', function () {
                nav.classList.remove('is-open');
                toggle.setAttribute('aria-expanded', 'false');
            });
        });
    }

    // ---- Init ----
    applyLang(lang);
})();
