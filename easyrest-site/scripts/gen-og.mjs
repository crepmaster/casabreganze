// Génère public/og.jpg (1200×630) à partir de la photo phare de l'appartement.
// Image de prévisualisation pour les partages sociaux (Open Graph / Twitter Card).
// Lancer : node scripts/gen-og.mjs
import sharp from 'sharp';
import { fileURLToPath } from 'node:url';

const src = fileURLToPath(new URL('../src/assets/apartment/living/livingvuensemble.webp', import.meta.url));
const out = fileURLToPath(new URL('../public/og.jpg', import.meta.url));

await sharp(src)
  .resize(1200, 630, { fit: 'cover', position: 'centre' })
  .jpeg({ quality: 82, mozjpeg: true })
  .toFile(out);

console.log('✓ public/og.jpg généré (1200×630)');
