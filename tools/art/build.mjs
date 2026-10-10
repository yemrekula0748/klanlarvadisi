#!/usr/bin/env node
/**
 * Bütün oyun görsellerini üretir.
 *
 *   node tools/art/build.mjs            # hepsi
 *   node tools/art/build.mjs buildings  # sadece binalar
 *
 * Çıktı:
 *   unity-client/Assets/Art/<grup>/<ad>.png   (Unity doğrudan içe alır)
 *   tools/art/out/svg/<grup>/<ad>.svg          (kaynak, sürüm takibi için)
 */

import { mkdir, writeFile, rm } from 'node:fs/promises';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { toPng } from './lib/render.mjs';

const HERE = dirname(fileURLToPath(import.meta.url));
const ROOT = join(HERE, '..', '..');
// Unity'de Resources.Load ile erişilebilmesi için Resources altında.
const PNG_ROOT = join(ROOT, 'unity-client', 'Assets', 'Resources', 'Art');
const SVG_ROOT = join(HERE, 'out', 'svg');

const SCALE = Number(process.env.ART_SCALE ?? 2);

async function emitGroup(group, map, scale = SCALE) {
  const names = Object.keys(map);
  let bytes = 0;

  for (const name of names) {
    const svg = map[name]();

    const svgPath = join(SVG_ROOT, group, `${name}.svg`);
    await mkdir(dirname(svgPath), { recursive: true });
    await writeFile(svgPath, svg, 'utf8');

    const pngPath = join(PNG_ROOT, group, `${name}.png`);
    const r = await toPng(svg, pngPath, scale);
    bytes += r.width * r.height;
  }

  console.log(`  ${group.padEnd(12)} ${String(names.length).padStart(3)} görsel`);
  return names.length;
}

const groups = process.argv.slice(2);
const want = (g) => groups.length === 0 || groups.includes(g);

console.log('Klanlar Vadisi — görsel üretimi');
console.log(`Çözünürlük katsayısı: ${SCALE}x\n`);

let total = 0;

if (want('buildings')) {
  const { ALL } = await import('./buildings.mjs');
  total += await emitGroup('Buildings', ALL);
}

if (want('troops')) {
  const { ALL } = await import('./troops.mjs');
  total += await emitGroup('Troops', ALL);
}

if (want('terrain')) {
  const { ALL } = await import('./terrain.mjs');
  total += await emitGroup('Terrain', ALL);
}

if (want('ui')) {
  const { ALL } = await import('./ui.mjs');
  total += await emitGroup('UI', ALL);
}

console.log(`\nToplam ${total} görsel üretildi.`);
console.log(`PNG : ${PNG_ROOT}`);
console.log(`SVG : ${SVG_ROOT}`);
