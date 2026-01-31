/**
 * PM2 Ecosystem Configuration
 *
 * For o2switch and other hosting providers
 *
 * Usage:
 *   pm2 start ecosystem.config.js
 *   pm2 stop easyrest-scraper
 *   pm2 restart easyrest-scraper
 *   pm2 logs easyrest-scraper
 */

module.exports = {
    apps: [{
        name: 'easyrest-scraper',
        script: 'server.js',
        cwd: __dirname,

        // Instances (1 for shared hosting, can increase for VPS)
        instances: 1,
        exec_mode: 'fork',

        // Auto-restart
        autorestart: true,
        watch: false,
        max_memory_restart: '500M',

        // Restart policy
        restart_delay: 5000,
        max_restarts: 10,

        // Environment
        env: {
            NODE_ENV: 'production',
            PORT: 3456
        },

        // Logs
        log_date_format: 'YYYY-MM-DD HH:mm:ss',
        error_file: './logs/error.log',
        out_file: './logs/output.log',
        merge_logs: true,

        // Graceful shutdown
        kill_timeout: 10000,
        wait_ready: true,
        listen_timeout: 10000,
    }]
};
