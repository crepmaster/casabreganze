import rss from '@astrojs/rss';
import { getCollection } from 'astro:content';
import type { APIRoute } from 'astro';
import { langList, type Lang } from '../../i18n/ui';
import { SITE } from '../../config';

export function getStaticPaths() {
  return langList.map((lang) => ({ params: { lang } }));
}

export const GET: APIRoute = async ({ params, site }) => {
  const lang = params.lang as Lang;
  const guides = (await getCollection('guides', (e) => !e.data.draft && e.data.lang === lang)).sort(
    (a, b) => b.data.pubDate.valueOf() - a.data.pubDate.valueOf(),
  );

  return rss({
    title: `${SITE.name} — ${lang}`,
    description: SITE.name,
    site: site ?? SITE.url,
    items: guides.map((g) => ({
      title: g.data.title,
      description: g.data.description,
      pubDate: g.data.pubDate,
      link: `/${lang}/guides/${g.data.translationKey}/`,
    })),
  });
};
