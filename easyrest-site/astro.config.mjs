// @ts-check
import { defineConfig } from 'astro/config';
import sitemap from '@astrojs/sitemap';

// Site EasyRest Milano — Astro statique (Jamstack), remplace WordPress.
// i18n géré via un segment de route [lang] (fr/en/it/es) + hreflang dans le <head>.
export default defineConfig({
  site: 'https://easyrest.eu',
  trailingSlash: 'always',
  redirects: {
    '/': '/fr/',
  },
  integrations: [sitemap()],
});
