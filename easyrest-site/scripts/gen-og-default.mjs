// Génère une image Open Graph par défaut (1200×630) sans dépendance externe.
// Placeholder de couleur unie aux teintes de la marque ; à remplacer plus tard par un visuel léché.
// Usage : node scripts/gen-og-default.mjs
import { deflateSync } from 'node:zlib';
import { writeFileSync, mkdirSync } from 'node:fs';

const W = 1200;
const H = 630;
const [R, G, B] = [0x1a, 0x1c, 0x22]; // --ink

// Table CRC32
const crcTable = (() => {
  const t = new Uint32Array(256);
  for (let n = 0; n < 256; n++) {
    let c = n;
    for (let k = 0; k < 8; k++) c = c & 1 ? 0xedb88320 ^ (c >>> 1) : c >>> 1;
    t[n] = c >>> 0;
  }
  return t;
})();
function crc32(buf) {
  let c = 0xffffffff;
  for (let i = 0; i < buf.length; i++) c = crcTable[(c ^ buf[i]) & 0xff] ^ (c >>> 8);
  return (c ^ 0xffffffff) >>> 0;
}
function chunk(type, data) {
  const len = Buffer.alloc(4);
  len.writeUInt32BE(data.length, 0);
  const typeBuf = Buffer.from(type, 'ascii');
  const body = Buffer.concat([typeBuf, data]);
  const crc = Buffer.alloc(4);
  crc.writeUInt32BE(crc32(body), 0);
  return Buffer.concat([len, body, crc]);
}

const sig = Buffer.from([137, 80, 78, 71, 13, 10, 26, 10]);
const ihdr = Buffer.alloc(13);
ihdr.writeUInt32BE(W, 0);
ihdr.writeUInt32BE(H, 4);
ihdr[8] = 8; // bit depth
ihdr[9] = 2; // color type RGB
// 10,11,12 = 0 (compression, filter, interlace)

const row = Buffer.alloc(1 + W * 3);
for (let x = 0; x < W; x++) {
  row[1 + x * 3] = R;
  row[1 + x * 3 + 1] = G;
  row[1 + x * 3 + 2] = B;
}
const raw = Buffer.concat(Array.from({ length: H }, () => row));
const idat = deflateSync(raw, { level: 9 });

const png = Buffer.concat([sig, chunk('IHDR', ihdr), chunk('IDAT', idat), chunk('IEND', Buffer.alloc(0))]);
mkdirSync('public', { recursive: true });
writeFileSync('public/og-default.png', png);
console.log(`Wrote public/og-default.png (${png.length} bytes, ${W}x${H})`);
