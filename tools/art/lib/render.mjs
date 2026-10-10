/**
 * SVG -> PNG dönüştürücü. İzometrik sprite'lar şeffaf zeminle, Unity'nin
 * doğrudan içe alabileceği çözünürlükte yazılır.
 */

import sharp from 'sharp';
import { mkdir, writeFile } from 'node:fs/promises';
import { dirname, join } from 'node:path';
import { commonDefs } from './ornament.mjs';
import { TILE_W, TILE_H } from './iso.mjs';

/**
 * Sprite'ı SVG belgesine sarar.
 *
 * İzometrik içerik (0,0) ızgara köşesi merkez olacak şekilde çizilir;
 * burada görünüm alanını binanın boyutuna göre hesaplıyoruz.
 */
export function wrap({ body, w = 1, d = 1, height = 120, pad = 12, extraDefs = '' }) {
  // İzometrik ayak izinin ekran genişliği
  const footW = (w + d) * (TILE_W / 2);
  const footH = (w + d) * (TILE_H / 2);

  const vbW = footW + pad * 2;
  const vbH = footH + height + pad * 2;

  // (0,0) köşesi: sol üst. project() negatif x verebilir, o yüzden kaydır.
  const offsetX = w * (TILE_W / 2) + pad;
  const offsetY = height + pad;

  return `<svg xmlns="http://www.w3.org/2000/svg" width="${vbW}" height="${vbH}"
    viewBox="0 0 ${vbW} ${vbH}">
${commonDefs()}
${extraDefs}
  <g transform="translate(${offsetX},${offsetY})">
${body}
  </g>
</svg>`;
}

/** Düz (izometrik olmayan) sprite: ikon, arayüz parçası, asker. */
export function wrapFlat({ body, width, height, extraDefs = '' }) {
  return `<svg xmlns="http://www.w3.org/2000/svg" width="${width}" height="${height}"
    viewBox="0 0 ${width} ${height}">
${commonDefs()}
${extraDefs}
${body}
</svg>`;
}

/**
 * SVG metnini PNG olarak yazar.
 *
 * @param {string} svg
 * @param {string} outPath  .png yolu
 * @param {number} scale    çözünürlük katsayısı (Unity için 2 iyi denge)
 */
export async function toPng(svg, outPath, scale = 2) {
  await mkdir(dirname(outPath), { recursive: true });

  const meta = /width="([\d.]+)"\s+height="([\d.]+)"/.exec(svg);
  const w = Math.round(parseFloat(meta[1]) * scale);
  const h = Math.round(parseFloat(meta[2]) * scale);

  await sharp(Buffer.from(svg), { density: 72 * scale })
    .resize(w, h, { fit: 'fill' })
    .png({ compressionLevel: 9, palette: false })
    .toFile(outPath);

  return { path: outPath, width: w, height: h };
}

/** Hem SVG hem PNG yazar (SVG kaynak olarak depoda kalır). */
export async function emit(svg, baseDir, name, scale = 2) {
  const svgPath = join(baseDir, 'svg', `${name}.svg`);
  const pngPath = join(baseDir, 'png', `${name}.png`);

  await mkdir(dirname(svgPath), { recursive: true });
  await writeFile(svgPath, svg, 'utf8');

  return toPng(svg, pngPath, scale);
}
