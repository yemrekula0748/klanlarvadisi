// Üretilen sprite'ları tek bir temas sayfasında birleştirir (gözle kontrol için)
import sharp from 'sharp';
import { readdir } from 'node:fs/promises';
import { join } from 'node:path';

const dir = process.argv[2];
const out = process.argv[3];
const cols = Number(process.argv[4] ?? 6);
const cell = Number(process.argv[5] ?? 230);

const files = (await readdir(dir)).filter(f => f.endsWith('.png')).sort();
const rows = Math.ceil(files.length / cols);

const composites = [];
for (let i = 0; i < files.length; i++) {
  const buf = await sharp(join(dir, files[i]))
    .resize(cell - 16, cell - 36, { fit: 'inside', withoutEnlargement: false })
    .toBuffer();
  const meta = await sharp(buf).metadata();
  composites.push({
    input: buf,
    left: (i % cols) * cell + Math.round((cell - meta.width) / 2),
    top: Math.floor(i / cols) * cell + Math.round((cell - 30 - meta.height) / 2) + 6,
  });
  // etiket
  const label = Buffer.from(
    `<svg width="${cell}" height="22"><text x="${cell/2}" y="15" font-family="sans-serif"
     font-size="13" fill="#e8e2d2" text-anchor="middle">${files[i].replace('.png','')}</text></svg>`);
  composites.push({ input: label, left: (i % cols) * cell, top: Math.floor(i / cols) * cell + cell - 24 });
}

await sharp({ create: { width: cols * cell, height: rows * cell, channels: 4,
  background: { r: 46, g: 42, b: 38, alpha: 1 } } })
  .composite(composites).png().toFile(out);
console.log(`${files.length} görsel -> ${out}`);
