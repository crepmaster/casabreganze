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
  integrations: [
    sitemap({
      // Génère les annotations hreflang dans le sitemap (en plus du <head>).
      i18n: {
        defaultLocale: 'fr',
        locales: { fr: 'fr', en: 'en', it: 'it', es: 'es' },
      },
    }),
  ],
});
