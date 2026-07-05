import type { ImageMetadata } from 'astro';
import logo from '../assets/apartment/logo-esayrest.png';

// Import automatique de toutes les photos de l'appartement (optimisées par astro:assets).
type Mod = { default: ImageMetadata };
const files = import.meta.glob<Mod>('../assets/apartment/**/*.{webp,jpg,jpeg,png}', { eager: true });

export type Category = 'living' | 'bedroom' | 'kitchen' | 'bathroom' | 'building';

export interface Photo {
  src: ImageMetadata;
  category: Category;
  slug: string;
}

// Ordre de présentation : le salon d'abord, l'immeuble en dernier.
const ORDER: Category[] = ['living', 'bedroom', 'kitchen', 'bathroom', 'building'];

export const photos: Photo[] = Object.entries(files)
  .filter(([path]) => !/logo/i.test(path))
  .map(([path, mod]) => {
    const parts = path.split('/');
    const slug = (parts.at(-1) ?? '').replace(/\.\w+$/, '');
    const category = (parts.at(-2) ?? 'living') as Category;
    return { src: mod.default, category, slug };
  })
  .sort((a, b) => ORDER.indexOf(a.category) - ORDER.indexOf(b.category));

// Photo phare : vue d'ensemble du salon (repli sur la première si absente).
export const heroPhoto: Photo = photos.find((p) => p.slug === 'livingvuensemble') ?? photos[0];

export const brandLogo = logo;
