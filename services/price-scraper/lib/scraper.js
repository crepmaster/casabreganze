/**
 * Booking.com Scraper Class
 *
 * Singleton browser pattern for optimal performance
 *
 * @version 2.0.0
 */

const puppeteer = require('puppeteer');

class BookingScraper {
    constructor(options = {}) {
        this.browser = null;
        this.timeout = options.timeout || 40000;
        this.debug = options.debug || false;
        this.launching = null; // Promise to prevent concurrent launches
    }

    /**
     * Get or create browser instance (singleton pattern)
     */
    async getBrowser() {
        if (this.browser && this.browser.isConnected()) {
            return this.browser;
        }

        // Prevent concurrent browser launches
        if (this.launching) {
            return this.launching;
        }

        this.launching = this._launchBrowser();
        this.browser = await this.launching;
        this.launching = null;

        return this.browser;
    }

    /**
     * Launch new browser instance
     */
    async _launchBrowser() {
        this.log('Launching browser...');

        const browser = await puppeteer.launch({
            headless: 'new',
            args: [
                '--no-sandbox',
                '--disable-setuid-sandbox',
                '--disable-dev-shm-usage',
                '--disable-accelerated-2d-canvas',
                '--disable-gpu',
                '--window-size=1920,1080',
                '--disable-blink-features=AutomationControlled',
                '--disable-features=IsolateOrigins,site-per-process',
                '--single-process' // Better for shared hosting
            ]
        });

        this.log('Browser launched');

        // Handle browser disconnection
        browser.on('disconnected', () => {
            this.log('Browser disconnected');
            this.browser = null;
        });

        return browser;
    }

    /**
     * Check if browser is active
     */
    isBrowserActive() {
        return this.browser && this.browser.isConnected();
    }

    /**
     * Close browser
     */
    async close() {
        if (this.browser) {
            await this.browser.close();
            this.browser = null;
            this.log('Browser closed');
        }
    }

    /**
     * Scrape price from Booking.com
     *
     * @param {Object} options
     * @param {string} options.url - Hotel URL
     * @param {string} options.checkin - Check-in date (YYYY-MM-DD)
     * @param {string} options.checkout - Check-out date (YYYY-MM-DD)
     * @param {number} options.adults - Number of adults
     * @param {number} options.children - Number of children
     * @param {string} options.currency - Currency code
     * @param {string} options.lang - Language code
     * @returns {Promise<Object>} Result object
     */
    async scrapePrice(options) {
        const {
            url,
            checkin,
            checkout,
            adults = 2,
            children = 0,
            currency = 'EUR',
            lang = 'fr'
        } = options;

        const fullUrl = this.buildUrl(url, checkin, checkout, adults, children, currency, lang);
        this.log(`Scraping: ${fullUrl}`);

        let page;
        try {
            const browser = await this.getBrowser();
            page = await browser.newPage();

            // Configure page
            await this.configurePage(page);

            // Navigate
            await page.goto(fullUrl, {
                waitUntil: 'networkidle2',
                timeout: this.timeout
            });

            // Wait for dynamic content
            await this.waitForContent(page);

            // Close cookie banner if present
            await this.closeCookieBanner(page);

            // Extract price
            const price = await this.extractPrice(page);

            if (price && price > 0) {
                return {
                    success: true,
                    price: Math.round(price * 100) / 100,
                    currency,
                    url: fullUrl
                };
            }

            // Retry with scroll
            this.log('Price not found, trying with scroll...');
            await page.evaluate(() => window.scrollBy(0, 500));
            await this.sleep(2000);

            const priceRetry = await this.extractPrice(page);

            if (priceRetry && priceRetry > 0) {
                return {
                    success: true,
                    price: Math.round(priceRetry * 100) / 100,
                    currency,
                    url: fullUrl
                };
            }

            return {
                success: false,
                code: 'PRICE_NOT_FOUND',
                error: 'Price not found on page'
            };

        } catch (error) {
            this.log(`Error: ${error.message}`);
            return {
                success: false,
                code: 'SCRAPE_ERROR',
                error: error.message
            };
        } finally {
            if (page) {
                await page.close().catch(() => {});
            }
        }
    }

    /**
     * Build Booking.com URL with parameters
     */
    buildUrl(baseUrl, checkin, checkout, adults, children, currency, lang) {
        const url = new URL(baseUrl.split('?')[0]);
        url.searchParams.set('checkin', checkin);
        url.searchParams.set('checkout', checkout);
        url.searchParams.set('group_adults', adults);
        url.searchParams.set('group_children', children);
        url.searchParams.set('no_rooms', '1');
        url.searchParams.set('selected_currency', currency);
        url.searchParams.set('lang', lang);
        return url.toString();
    }

    /**
     * Configure page with realistic browser settings
     */
    async configurePage(page) {
        await page.setViewport({ width: 1920, height: 1080 });

        await page.setUserAgent(
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'
        );

        await page.setExtraHTTPHeaders({
            'Accept-Language': 'fr-FR,fr;q=0.9,en-US;q=0.8,en;q=0.7',
            'Accept': 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8'
        });

        // Hide webdriver
        await page.evaluateOnNewDocument(() => {
            Object.defineProperty(navigator, 'webdriver', { get: () => false });
            Object.defineProperty(navigator, 'plugins', { get: () => [1, 2, 3, 4, 5] });
            Object.defineProperty(navigator, 'languages', { get: () => ['fr-FR', 'fr', 'en-US', 'en'] });
        });
    }

    /**
     * Wait for page content to load
     */
    async waitForContent(page) {
        // Wait a bit for dynamic content
        await this.sleep(3000);

        // Try to wait for price element
        try {
            await page.waitForSelector('[data-price], .bui-price-display__value, .prco-valign-middle-helper', {
                timeout: 5000
            });
        } catch (e) {
            // Element not found, continue anyway
        }
    }

    /**
     * Close cookie banner if present
     */
    async closeCookieBanner(page) {
        try {
            const selectors = [
                '#onetrust-accept-btn-handler',
                '[id*="cookie"] button[id*="accept"]',
                '[class*="cookie"] button[class*="accept"]',
                'button[data-testid="accept-btn"]'
            ];

            for (const selector of selectors) {
                const button = await page.$(selector);
                if (button) {
                    await button.click();
                    await this.sleep(500);
                    break;
                }
            }
        } catch (e) {
            // Ignore cookie banner errors
        }
    }

    /**
     * Extract price from page
     */
    async extractPrice(page) {
        return await page.evaluate(() => {
            // Method 1: data-price attribute
            const priceAttr = document.querySelector('[data-price]');
            if (priceAttr) {
                const price = parseFloat(priceAttr.getAttribute('data-price'));
                if (price > 0) return price;
            }

            // Method 2: data-price-amount attribute
            const priceAmountAttr = document.querySelector('[data-price-amount]');
            if (priceAmountAttr) {
                const price = parseFloat(priceAmountAttr.getAttribute('data-price-amount'));
                if (price > 0) return price;
            }

            // Method 3: JSON-LD
            const jsonLd = document.querySelector('script[type="application/ld+json"]');
            if (jsonLd) {
                try {
                    const data = JSON.parse(jsonLd.textContent);
                    if (data.offers && data.offers.price) {
                        return parseFloat(data.offers.price);
                    }
                } catch (e) {}
            }

            // Method 4: Common selectors
            const priceSelectors = [
                '.bui-price-display__value',
                '.prco-valign-middle-helper',
                '[data-testid="price-and-discounted-price"]',
                '.hprt-price-price',
                '.bui-f-font-display_two',
                '.prco-inline-block-maker-helper'
            ];

            for (const selector of priceSelectors) {
                const el = document.querySelector(selector);
                if (el) {
                    const text = el.textContent.trim();
                    // Extract number from text like "€ 450" or "450 €"
                    const match = text.match(/[\d\s,.]+/);
                    if (match) {
                        let priceStr = match[0].replace(/\s/g, '');
                        // Handle European format (1.234,56)
                        if (priceStr.includes(',') && priceStr.includes('.')) {
                            if (priceStr.indexOf('.') < priceStr.indexOf(',')) {
                                priceStr = priceStr.replace(/\./g, '').replace(',', '.');
                            } else {
                                priceStr = priceStr.replace(',', '');
                            }
                        } else if (priceStr.includes(',')) {
                            priceStr = priceStr.replace(',', '.');
                        }
                        const price = parseFloat(priceStr);
                        if (price > 10) return price;
                    }
                }
            }

            // Method 5: Search in JSON data on page
            const scripts = document.querySelectorAll('script');
            for (const script of scripts) {
                const content = script.textContent;
                if (content.includes('gross_amount')) {
                    const match = content.match(/"gross_amount":\s*([\d.]+)/);
                    if (match) {
                        const price = parseFloat(match[1]);
                        if (price > 10) return price;
                    }
                }
                if (content.includes('total_price')) {
                    const match = content.match(/"total_price":\s*([\d.]+)/);
                    if (match) {
                        const price = parseFloat(match[1]);
                        if (price > 10) return price;
                    }
                }
            }

            return null;
        });
    }

    /**
     * Sleep helper
     */
    sleep(ms) {
        return new Promise(resolve => setTimeout(resolve, ms));
    }

    /**
     * Log helper
     */
    log(message) {
        if (this.debug) {
            console.log(`[Scraper] ${message}`);
        }
    }
}

module.exports = BookingScraper;
