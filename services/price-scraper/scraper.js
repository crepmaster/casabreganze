#!/usr/bin/env node
/**
 * EasyRest Booking.com Price Scraper
 *
 * Usage: node scraper.js --url=BOOKING_URL --checkin=YYYY-MM-DD --checkout=YYYY-MM-DD --adults=2 --children=0
 *
 * Returns JSON to stdout:
 * Success: { "success": true, "price": 450.00, "currency": "EUR" }
 * Error:   { "success": false, "error": "Error message" }
 */

const puppeteer = require('puppeteer');

// Parse command line arguments
function parseArgs() {
    const args = {};
    process.argv.slice(2).forEach(arg => {
        const [key, value] = arg.replace('--', '').split('=');
        args[key] = value;
    });
    return args;
}

// Build Booking.com URL with parameters
function buildUrl(baseUrl, checkin, checkout, adults, children) {
    const url = new URL(baseUrl.split('?')[0]);
    url.searchParams.set('checkin', checkin);
    url.searchParams.set('checkout', checkout);
    url.searchParams.set('group_adults', adults);
    url.searchParams.set('group_children', children);
    url.searchParams.set('no_rooms', '1');
    url.searchParams.set('selected_currency', 'EUR');
    url.searchParams.set('lang', 'fr');
    return url.toString();
}

// Extract price from page
async function extractPrice(page) {
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

        // Method 3: Price in JSON-LD
        const jsonLd = document.querySelector('script[type="application/ld+json"]');
        if (jsonLd) {
            try {
                const data = JSON.parse(jsonLd.textContent);
                if (data.offers && data.offers.price) {
                    return parseFloat(data.offers.price);
                }
            } catch (e) {}
        }

        // Method 4: Look for price in common selectors
        const priceSelectors = [
            '.bui-price-display__value',
            '.prco-valign-middle-helper',
            '[data-testid="price-and-discounted-price"]',
            '.hprt-price-price',
            '.bui-f-font-display_two'
        ];

        for (const selector of priceSelectors) {
            const el = document.querySelector(selector);
            if (el) {
                const text = el.textContent.trim();
                // Extract number from text like "€ 450" or "450 €" or "EUR 450"
                const match = text.match(/[\d\s,.]+/);
                if (match) {
                    // Clean and parse
                    let priceStr = match[0].replace(/\s/g, '').replace(',', '.');
                    // Handle European format (1.234,56)
                    if (priceStr.includes('.') && priceStr.indexOf('.') < priceStr.length - 3) {
                        priceStr = priceStr.replace('.', '').replace(',', '.');
                    }
                    const price = parseFloat(priceStr);
                    if (price > 10) return price;
                }
            }
        }

        // Method 5: Search in page text for price patterns
        const bodyText = document.body.innerText;
        const pricePattern = /(\d{1,3}(?:[.,\s]\d{3})*(?:[.,]\d{2})?)\s*[€EUR]/g;
        const matches = [...bodyText.matchAll(pricePattern)];

        // Filter for reasonable prices (50-10000)
        for (const match of matches) {
            let priceStr = match[1].replace(/\s/g, '').replace(',', '.');
            const price = parseFloat(priceStr);
            if (price >= 50 && price <= 10000) {
                return price;
            }
        }

        return null;
    });
}

// Main scraper function
async function scrapePrice(options) {
    const { url, checkin, checkout, adults = '2', children = '0' } = options;

    if (!url || !checkin || !checkout) {
        return { success: false, error: 'Missing required parameters: url, checkin, checkout' };
    }

    const fullUrl = buildUrl(url, checkin, checkout, adults, children);

    let browser;
    try {
        // Launch browser with stealth settings
        browser = await puppeteer.launch({
            headless: 'new',
            args: [
                '--no-sandbox',
                '--disable-setuid-sandbox',
                '--disable-dev-shm-usage',
                '--disable-accelerated-2d-canvas',
                '--disable-gpu',
                '--window-size=1920,1080',
                '--disable-blink-features=AutomationControlled'
            ]
        });

        const page = await browser.newPage();

        // Set realistic viewport and user agent
        await page.setViewport({ width: 1920, height: 1080 });
        await page.setUserAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');

        // Set extra headers
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

        // Navigate to page
        await page.goto(fullUrl, {
            waitUntil: 'networkidle2',
            timeout: 30000
        });

        // Wait for price to load (Booking.com loads prices dynamically)
        await page.waitForTimeout(3000);

        // Try to close cookie banner if present
        try {
            const cookieButton = await page.$('[id*="cookie"] button, [class*="cookie"] button, #onetrust-accept-btn-handler');
            if (cookieButton) {
                await cookieButton.click();
                await page.waitForTimeout(500);
            }
        } catch (e) {}

        // Extract price
        const price = await extractPrice(page);

        if (price && price > 0) {
            return {
                success: true,
                price: Math.round(price * 100) / 100,
                currency: 'EUR',
                url: fullUrl
            };
        }

        // If no price found, try scrolling and waiting more
        await page.evaluate(() => window.scrollBy(0, 500));
        await page.waitForTimeout(2000);

        const priceRetry = await extractPrice(page);
        if (priceRetry && priceRetry > 0) {
            return {
                success: true,
                price: Math.round(priceRetry * 100) / 100,
                currency: 'EUR',
                url: fullUrl
            };
        }

        return { success: false, error: 'Price not found on page' };

    } catch (error) {
        return { success: false, error: error.message };
    } finally {
        if (browser) {
            await browser.close();
        }
    }
}

// Run if called directly
(async () => {
    const args = parseArgs();
    const result = await scrapePrice(args);
    console.log(JSON.stringify(result));
    process.exit(result.success ? 0 : 1);
})();
