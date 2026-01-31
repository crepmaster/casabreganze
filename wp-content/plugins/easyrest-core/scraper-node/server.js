#!/usr/bin/env node
/**
 * EasyRest Booking.com Price Scraper - Microservice
 *
 * Express HTTP server with Puppeteer singleton browser
 *
 * Endpoints:
 *   POST /booking/price - Get price from Booking.com
 *   GET  /health        - Health check
 *
 * @version 2.0.0
 */

require('dotenv').config();
const express = require('express');
const BookingScraper = require('./lib/scraper');

// =============================================================================
// Configuration
// =============================================================================

const CONFIG = {
    port: parseInt(process.env.PORT || '3456', 10),
    token: process.env.EASYREST_TOKEN || '',
    defaultHotelUrl: process.env.DEFAULT_HOTEL_URL || '',
    timeout: parseInt(process.env.TIMEOUT || '40000', 10),
    rateLimit: parseInt(process.env.RATE_LIMIT || '10', 10),
    debug: process.env.DEBUG === 'true',
};

// Validate required config
if (!CONFIG.token || CONFIG.token === 'your_secret_token_here') {
    console.error('ERROR: EASYREST_TOKEN is required. Set it in .env file.');
    console.error('Generate one with: node -e "console.log(require(\'crypto\').randomBytes(32).toString(\'hex\'))"');
    process.exit(1);
}

// =============================================================================
// Express App
// =============================================================================

const app = express();
app.use(express.json());

// Rate limiting store (simple in-memory)
const rateLimitStore = new Map();

// =============================================================================
// Middleware
// =============================================================================

/**
 * Request logging middleware
 */
app.use((req, res, next) => {
    const start = Date.now();
    res.on('finish', () => {
        const duration = Date.now() - start;
        const log = `[${new Date().toISOString()}] ${req.method} ${req.path} ${res.statusCode} ${duration}ms`;
        if (CONFIG.debug || res.statusCode >= 400) {
            console.log(log);
        }
    });
    next();
});

/**
 * Token authentication middleware
 */
function authMiddleware(req, res, next) {
    const token = req.headers['x-easyrest-token'] || req.body?.token;

    if (!token) {
        return res.status(401).json({
            success: false,
            code: 'UNAUTHORIZED',
            message: 'Missing authentication token'
        });
    }

    if (token !== CONFIG.token) {
        return res.status(403).json({
            success: false,
            code: 'FORBIDDEN',
            message: 'Invalid authentication token'
        });
    }

    next();
}

/**
 * Rate limiting middleware
 */
function rateLimitMiddleware(req, res, next) {
    const ip = req.ip || req.connection.remoteAddress || 'unknown';
    const now = Date.now();
    const windowMs = 60000; // 1 minute

    // Clean old entries
    const key = `rate_${ip}`;
    const entry = rateLimitStore.get(key) || { count: 0, resetAt: now + windowMs };

    if (now > entry.resetAt) {
        entry.count = 0;
        entry.resetAt = now + windowMs;
    }

    entry.count++;
    rateLimitStore.set(key, entry);

    if (entry.count > CONFIG.rateLimit) {
        return res.status(429).json({
            success: false,
            code: 'RATE_LIMITED',
            message: `Too many requests. Limit: ${CONFIG.rateLimit}/minute`
        });
    }

    next();
}

// =============================================================================
// Scraper Instance (Singleton)
// =============================================================================

const scraper = new BookingScraper({
    timeout: CONFIG.timeout,
    debug: CONFIG.debug,
});

// =============================================================================
// Routes
// =============================================================================

/**
 * Health check endpoint
 */
app.get('/health', (req, res) => {
    res.json({
        status: 'ok',
        service: 'easyrest-scraper',
        version: '2.0.0',
        browserActive: scraper.isBrowserActive(),
        timestamp: new Date().toISOString()
    });
});

/**
 * Get price from Booking.com
 *
 * POST /booking/price
 *
 * Body:
 * {
 *   "url": "https://www.booking.com/hotel/...",
 *   "checkin": "2026-02-10",
 *   "checkout": "2026-02-15",
 *   "adults": 2,
 *   "children": 0,
 *   "currency": "EUR",
 *   "lang": "fr",
 *   "token": "secret"
 * }
 */
app.post('/booking/price', authMiddleware, rateLimitMiddleware, async (req, res) => {
    const {
        url,
        checkin,
        checkout,
        adults = 2,
        children = 0,
        currency = 'EUR',
        lang = 'fr'
    } = req.body;

    // Validation
    if (!checkin || !checkout) {
        return res.status(400).json({
            success: false,
            code: 'MISSING_DATES',
            message: 'checkin and checkout are required'
        });
    }

    // Validate date format
    const dateRegex = /^\d{4}-\d{2}-\d{2}$/;
    if (!dateRegex.test(checkin) || !dateRegex.test(checkout)) {
        return res.status(400).json({
            success: false,
            code: 'INVALID_DATE_FORMAT',
            message: 'Dates must be in YYYY-MM-DD format'
        });
    }

    // Validate checkout > checkin
    if (new Date(checkout) <= new Date(checkin)) {
        return res.status(400).json({
            success: false,
            code: 'INVALID_DATE_LOGIC',
            message: 'checkout must be after checkin'
        });
    }

    // Use provided URL or default
    const hotelUrl = url || CONFIG.defaultHotelUrl;

    if (!hotelUrl) {
        return res.status(400).json({
            success: false,
            code: 'MISSING_URL',
            message: 'url is required (or set DEFAULT_HOTEL_URL in config)'
        });
    }

    // Validate Booking.com URL
    try {
        const parsed = new URL(hotelUrl);
        if (!parsed.hostname.endsWith('booking.com')) {
            return res.status(400).json({
                success: false,
                code: 'INVALID_URL',
                message: 'URL must be a booking.com URL'
            });
        }
    } catch (e) {
        return res.status(400).json({
            success: false,
            code: 'INVALID_URL',
            message: 'Invalid URL format'
        });
    }

    // Calculate nights
    const checkinDate = new Date(checkin);
    const checkoutDate = new Date(checkout);
    const nights = Math.ceil((checkoutDate - checkinDate) / (1000 * 60 * 60 * 24));

    try {
        // Scrape with timeout
        const result = await Promise.race([
            scraper.scrapePrice({
                url: hotelUrl,
                checkin,
                checkout,
                adults: parseInt(adults, 10),
                children: parseInt(children, 10),
                currency,
                lang
            }),
            new Promise((_, reject) =>
                setTimeout(() => reject(new Error('Scraping timeout')), CONFIG.timeout)
            )
        ]);

        if (result.success) {
            return res.json({
                success: true,
                price: result.price,
                currency: result.currency || currency,
                nights,
                source: 'booking',
                timestamp: new Date().toISOString()
            });
        } else {
            return res.status(404).json({
                success: false,
                code: result.code || 'PRICE_NOT_FOUND',
                message: result.error || 'Price not found on page'
            });
        }

    } catch (error) {
        console.error('[ERROR] Scraping failed:', error.message);

        return res.status(503).json({
            success: false,
            code: 'SCRAPE_FAILED',
            message: error.message || 'Failed to scrape price'
        });
    }
});

/**
 * 404 handler
 */
app.use((req, res) => {
    res.status(404).json({
        success: false,
        code: 'NOT_FOUND',
        message: `Endpoint ${req.method} ${req.path} not found`
    });
});

/**
 * Error handler
 */
app.use((err, req, res, next) => {
    console.error('[ERROR]', err);
    res.status(500).json({
        success: false,
        code: 'INTERNAL_ERROR',
        message: 'Internal server error'
    });
});

// =============================================================================
// Server Startup
// =============================================================================

const server = app.listen(CONFIG.port, () => {
    console.log('='.repeat(60));
    console.log('EasyRest Booking Scraper Microservice');
    console.log('='.repeat(60));
    console.log(`Server running on http://localhost:${CONFIG.port}`);
    console.log(`Health check: http://localhost:${CONFIG.port}/health`);
    console.log(`Debug mode: ${CONFIG.debug}`);
    console.log('='.repeat(60));
});

// =============================================================================
// Graceful Shutdown
// =============================================================================

async function shutdown(signal) {
    console.log(`\n[${signal}] Shutting down gracefully...`);

    server.close(async () => {
        console.log('HTTP server closed');

        await scraper.close();
        console.log('Browser closed');

        process.exit(0);
    });

    // Force exit after 10s
    setTimeout(() => {
        console.error('Forced shutdown after timeout');
        process.exit(1);
    }, 10000);
}

process.on('SIGTERM', () => shutdown('SIGTERM'));
process.on('SIGINT', () => shutdown('SIGINT'));

// Clean up rate limit store periodically
setInterval(() => {
    const now = Date.now();
    for (const [key, entry] of rateLimitStore.entries()) {
        if (now > entry.resetAt + 60000) {
            rateLimitStore.delete(key);
        }
    }
}, 60000);
