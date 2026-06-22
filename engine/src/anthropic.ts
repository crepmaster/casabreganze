import Anthropic from '@anthropic-ai/sdk';
import { MODELS, type Lang } from './config.js';
import { articleSystemPrompt, articleUserPrompt, seoUserPrompt, translateSystemPrompt } from './prompts.js';
import type { GeneratedSeo } from './types.js';

// Client construit paresseusement : le mode --dry-run ne touche jamais à l'API ni à la clé.
let _client: Anthropic | null = null;
function client(): Anthropic {
  if (!_client) _client = new Anthropic(); // lit ANTHROPIC_API_KEY dans l'environnement
  return _client;
}

function textOf(content: Anthropic.ContentBlock[]): string {
  return content
    .filter((b): b is Anthropic.TextBlock => b.type === 'text')
    .map((b) => b.text)
    .join('')
    .trim();
}

/** Génère le corps de l'article (long → streaming, conformément aux bonnes pratiques SDK). */
export async function generateArticleBody(lang: Lang, weekLabel: string): Promise<string> {
  const stream = client().messages.stream({
    model: MODELS.article,
    max_tokens: 8000,
    thinking: { type: 'adaptive' },
    system: articleSystemPrompt(lang),
    messages: [{ role: 'user', content: articleUserPrompt(weekLabel) }],
  });
  const msg = await stream.finalMessage();
  return textOf(msg.content);
}

/** Métadonnées SEO via sortie structurée (JSON garanti par output_config.format). */
export async function generateSeo(lang: Lang, body: string): Promise<GeneratedSeo> {
  const res = await client().messages.create({
    model: MODELS.seo,
    max_tokens: 1024,
    messages: [{ role: 'user', content: seoUserPrompt(lang, body) }],
    output_config: {
      format: {
        type: 'json_schema',
        schema: {
          type: 'object',
          properties: {
            title: { type: 'string' },
            description: { type: 'string' },
            focusKeyword: { type: 'string' },
            tags: { type: 'array', items: { type: 'string' } },
            coverAlt: { type: 'string' },
          },
          required: ['title', 'description', 'focusKeyword', 'tags', 'coverAlt'],
          additionalProperties: false,
        },
      },
    },
  } as Anthropic.MessageCreateParamsNonStreaming);
  return JSON.parse(textOf(res.content)) as GeneratedSeo;
}

/** Traduit le corps Markdown depuis la langue canonique vers `targetLang`. */
export async function translateBody(targetLang: Lang, body: string): Promise<string> {
  const stream = client().messages.stream({
    model: MODELS.translation,
    max_tokens: 8000,
    system: translateSystemPrompt(targetLang),
    messages: [{ role: 'user', content: body }],
  });
  const msg = await stream.finalMessage();
  return textOf(msg.content);
}
