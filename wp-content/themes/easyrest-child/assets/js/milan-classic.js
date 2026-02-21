/**
 * EasyRest Milan Classic - JavaScript
 * Gallery tabs slider + Google Maps lazy loading
 * @version 1.0.0
 */

(function() {
    'use strict';

    const root = document.querySelector('.easyrest-milan-classic');
    if (!root) return;

    // Note: language selector is now handled by the global language-switcher.js

    // ========================================================================
    // GALLERY TABS SLIDER
    // ========================================================================

    const gallerySection = root.querySelector('.gallery-section');
    if (gallerySection) {
        const tabButtons = gallerySection.querySelectorAll('.tab-btn');
        const galleryTabs = gallerySection.querySelectorAll('.gallery-tab');

        // Track current slide index per tab
        const slideIndices = {};

        // Initialize slide indices and create indicators
        galleryTabs.forEach(tab => {
            const tabId = tab.id;
            slideIndices[tabId] = 0;

            // Create indicators based on number of slides
            const items = tab.querySelectorAll('.gallery-item');
            const indicatorContainer = tab.querySelector('.slider-indicators');
            if (indicatorContainer && items.length > 1) {
                indicatorContainer.innerHTML = '';
                items.forEach((_, i) => {
                    const dot = document.createElement('button');
                    dot.className = 'indicator' + (i === 0 ? ' active' : '');
                    dot.setAttribute('aria-label', 'Image ' + (i + 1));
                    indicatorContainer.appendChild(dot);
                });
            }
        });

        // Function to show a specific tab
        function showTab(tabName) {
            const tabId = tabName + '-gallery';

            // Update button states
            tabButtons.forEach(btn => {
                btn.classList.toggle('active', btn.dataset.tab === tabName);
            });

            // Show/hide tabs
            galleryTabs.forEach(tab => {
                tab.classList.toggle('active', tab.id === tabId);
            });

            // Show first slide if tab has slides
            const activeTab = gallerySection.querySelector('#' + tabId);
            if (activeTab) {
                showSlide(tabId, slideIndices[tabId] || 0);
            }
        }

        // Function to show a specific slide within a tab
        function showSlide(tabId, index) {
            const tab = gallerySection.querySelector('#' + tabId);
            if (!tab) return;

            const items = tab.querySelectorAll('.gallery-item');
            const indicators = tab.querySelectorAll('.indicator');

            if (items.length === 0) return;

            // Wrap index
            if (index >= items.length) index = 0;
            if (index < 0) index = items.length - 1;

            slideIndices[tabId] = index;

            // Hide all items
            items.forEach(item => item.classList.remove('active'));

            // Show current item
            items[index].classList.add('active');

            // Update indicators
            indicators.forEach((ind, i) => {
                ind.classList.toggle('active', i === index);
            });
        }

        // Function to change slide (next/prev)
        function changeSlide(tabId, direction) {
            const currentIndex = slideIndices[tabId] || 0;
            showSlide(tabId, currentIndex + direction);
        }

        // Bind tab button clicks
        tabButtons.forEach(btn => {
            btn.addEventListener('click', () => {
                const tabId = btn.dataset.tab;
                showTab(tabId);
            });
        });

        // Bind navigation buttons
        galleryTabs.forEach(tab => {
            const tabId = tab.id;
            const prevBtn = tab.querySelector('.prev-btn');
            const nextBtn = tab.querySelector('.next-btn');
            const indicators = tab.querySelectorAll('.indicator');

            if (prevBtn) {
                prevBtn.addEventListener('click', () => changeSlide(tabId, -1));
            }

            if (nextBtn) {
                nextBtn.addEventListener('click', () => changeSlide(tabId, 1));
            }

            // Indicator clicks
            indicators.forEach((ind, index) => {
                ind.addEventListener('click', () => showSlide(tabId, index));
            });
        });

        // Keyboard navigation
        document.addEventListener('keydown', (e) => {
            const activeTab = gallerySection.querySelector('.gallery-tab.active');
            if (!activeTab) return;

            const tabId = activeTab.id;
            if (e.key === 'ArrowLeft') {
                changeSlide(tabId, -1);
            } else if (e.key === 'ArrowRight') {
                changeSlide(tabId, 1);
            }
        });

        // Initialize first tab
        if (tabButtons.length > 0) {
            const firstTabId = tabButtons[0].dataset.tab;
            showTab(firstTabId);
        }
    }

    // ========================================================================
    // SECTION ANIMATIONS (Intersection Observer)
    // ========================================================================

    const sections = root.querySelectorAll('section');
    if (sections.length > 0 && 'IntersectionObserver' in window) {
        const sectionObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('animated');
                    sectionObserver.unobserve(entry.target);
                }
            });
        }, {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        });

        sections.forEach(section => {
            sectionObserver.observe(section);
        });
    } else {
        // Fallback: show all sections immediately
        sections.forEach(section => section.classList.add('animated'));
    }

    // ========================================================================
    // GOOGLE MAPS LAZY LOADING
    // ========================================================================

    const mapContainer = root.querySelector('.map-container');
    if (mapContainer && 'IntersectionObserver' in window) {
        const mapObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    loadGoogleMap();
                    mapObserver.unobserve(entry.target);
                }
            });
        }, {
            threshold: 0.1,
            rootMargin: '100px 0px'
        });

        mapObserver.observe(mapContainer);
    }

    function loadGoogleMap() {
        if (!mapContainer) return;

        // Check if already loaded
        if (mapContainer.querySelector('iframe')) return;

        // Get config from localized script
        const config = window.easyrestMilanClassic || {};
        const apiKey = config.googleMapsApiKey || '';
        const lat = config.apartmentLat || 45.4572;
        const lng = config.apartmentLng || 9.1458;

        // Create iframe with embed URL (no API key needed for embed)
        const iframe = document.createElement('iframe');
        iframe.src = `https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d2798.0!2d${lng}!3d${lat}!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x0%3A0x0!2zNDXCsDI3JzI2LjAiTiA5wrAwOCc0NS4wIkU!5e0!3m2!1sfr!2sit!4v1`;
        iframe.width = '100%';
        iframe.height = '100%';
        iframe.style.border = '0';
        iframe.loading = 'lazy';
        iframe.allowFullscreen = true;
        iframe.setAttribute('referrerpolicy', 'no-referrer-when-downgrade');
        iframe.setAttribute('aria-label', 'Google Maps - Apartment location');

        mapContainer.appendChild(iframe);
    }

    // ========================================================================
    // SMOOTH SCROLL FOR CTA BUTTONS
    // ========================================================================

    const ctaLinks = root.querySelectorAll('a[href^="#"]');
    ctaLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            const targetId = this.getAttribute('href').substring(1);
            const target = document.getElementById(targetId);

            if (target) {
                e.preventDefault();
                const offset = 20;
                const pos = target.getBoundingClientRect().top + window.pageYOffset - offset;
                window.scrollTo({ top: pos, behavior: 'smooth' });

                if (history.pushState) {
                    history.pushState(null, null, '#' + targetId);
                }
            }
        });
    });

    // Handle direct hash navigation on page load
    if (window.location.hash) {
        setTimeout(function() {
            const targetId = window.location.hash.substring(1);
            const target = document.getElementById(targetId);
            if (target) {
                const pos = target.getBoundingClientRect().top + window.pageYOffset - 20;
                window.scrollTo({ top: pos, behavior: 'smooth' });
            }
        }, 300);
    }

    // ========================================================================
    // IMAGE MODAL (Lightbox)
    // ========================================================================

    const modal = root.querySelector('.modal');
    const modalImg = root.querySelector('.modal-content');
    const modalClose = root.querySelector('.modal-close');

    if (modal && modalImg) {
        // Click on gallery images to open modal
        const galleryImages = root.querySelectorAll('.gallery-item img');
        galleryImages.forEach(img => {
            img.style.cursor = 'pointer';
            img.addEventListener('click', () => {
                modal.classList.add('active');
                modalImg.src = img.src;
                modalImg.alt = img.alt;
                document.body.style.overflow = 'hidden';
            });
        });

        // Close modal on click
        if (modalClose) {
            modalClose.addEventListener('click', closeModal);
        }

        // Close on background click
        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                closeModal();
            }
        });

        // Close on Escape key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && modal.classList.contains('active')) {
                closeModal();
            }
        });

        function closeModal() {
            modal.classList.remove('active');
            document.body.style.overflow = '';
        }
    }

})();
