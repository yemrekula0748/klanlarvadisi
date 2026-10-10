/**
 * Arazi döşemeleri ve harita süsleri.
 *
 * Dünya haritası Unity'de aynı tohumdan üretilir; burada her biyom için
 * bir izometrik döşeme ve birkaç süs (ağaç, kaya, dalga) üretilir.
 * Döşemeler kenarlarda kusursuz birleşecek şekilde tam eşkenar dörtgendir.
 */

import { PALETTE as P, BIOME_COLORS } from './lib/palette.mjs';
import * as iso from './lib/iso.mjs';
import { tree, rocks } from './lib/parts.mjs';
import { wrapFlat } from './lib/render.mjs';

const TW = iso.TILE_W, TH = iso.TILE_H;
const f = (n) => Math.round(n * 100) / 100;

/** Tam bir izometrik döşeme (eşkenar dörtgen). */
function tile(colors, extra = '', seed = 1) {
  const [base, light, dark] = colors;
  const cx = TW / 2, cy = TH / 2;

  // Hafif doku: döşemenin içine serpiştirilmiş benekler
  const specks = [];
  let r = seed * 9301;
  const rnd = () => ((r = (r * 9301 + 49297) % 233280) / 233280);
  for (let i = 0; i < 14; i++) {
    const u = rnd(), v = rnd();
    // Eşkenar dörtgen içinde kalan nokta
    const px = cx + (u - v) * (TW / 2) * 0.86;
    const py = cy + (u + v - 1) * (TH / 2) * 0.86;
    specks.push(`<ellipse cx="${f(px)}" cy="${f(py)}" rx="${f(2 + rnd() * 5)}" ry="${f(1 + rnd() * 2.4)}"
      fill="${rnd() > 0.5 ? light : dark}" opacity="0.4"/>`);
  }

  return wrapFlat({
    width: TW, height: TH,
    body: `
      <path d="M${f(cx)},0 L${TW},${f(cy)} L${f(cx)},${TH} L0,${f(cy)} Z" fill="${base}"/>
      ${specks.join('')}
      <path d="M${f(cx)},0 L${TW},${f(cy)} L${f(cx)},${TH} L0,${f(cy)} Z"
        fill="none" stroke="${dark}" stroke-width="1" opacity="0.22"/>
      ${extra}
    `,
  });
}

const C = BIOME_COLORS;

export const deniz = () => tile(C.deniz, `
  ${[0.3, 0.55, 0.78].map((t, i) => `<path d="M${f(TW * 0.22)},${f(TH * t)}
    q${f(TW * 0.14)},${f(-TH * 0.08)} ${f(TW * 0.28)},0
    q${f(TW * 0.14)},${f(TH * 0.08)} ${f(TW * 0.28)},0"
    stroke="${C.deniz[1]}" stroke-width="2" fill="none" opacity="${0.5 - i * 0.1}"/>`).join('')}`, 2);

export const kiyi = () => tile(C.kiyi, '', 3);
export const bozkir = () => tile(C.bozkir, `
  ${[[0.35, 0.4], [0.6, 0.62], [0.45, 0.72]].map(([u, v]) => `
    <path d="M${f(TW * u)},${f(TH * v)} l0,-7 M${f(TW * u - 3)},${f(TH * v)} l1,-5
      M${f(TW * u + 3)},${f(TH * v)} l-1,-5" stroke="${C.bozkir[2]}" stroke-width="1.3"
      stroke-linecap="round" opacity="0.75"/>`).join('')}`, 4);

export const cayir = () => tile(C.cayir, `
  ${[[0.3, 0.45], [0.52, 0.35], [0.62, 0.66], [0.42, 0.72]].map(([u, v]) => `
    <path d="M${f(TW * u)},${f(TH * v)} l-2,-8 M${f(TW * u)},${f(TH * v)} l2,-9"
      stroke="${C.cayir[2]}" stroke-width="1.4" stroke-linecap="round" opacity="0.8"/>`).join('')}`, 5);

export const orman = () => tile(C.orman, '', 6);
export const tayga = () => tile(C.tayga, '', 7);
export const dag = () => tile(C.dag, `
  <path d="M${f(TW * 0.5)},${f(TH * 0.2)} L${f(TW * 0.78)},${f(TH * 0.52)}
    L${f(TW * 0.5)},${f(TH * 0.82)} L${f(TW * 0.22)},${f(TH * 0.52)} Z"
    fill="${C.dag[1]}" opacity="0.5"/>`, 8);

export const kar = () => tile(C.kar, '', 9);
export const col = () => tile(C.col, `
  ${[0.35, 0.58].map((t, i) => `<path d="M${f(TW * 0.25)},${f(TH * t)}
    q${f(TW * 0.12)},${f(-TH * 0.06)} ${f(TW * 0.25)},0
    q${f(TW * 0.12)},${f(TH * 0.06)} ${f(TW * 0.25)},0"
    stroke="${C.col[2]}" stroke-width="1.6" fill="none" opacity="0.5"/>`).join('')}`, 10);

export const bataklik = () => tile(C.bataklik, `
  ${[[0.38, 0.48], [0.58, 0.6]].map(([u, v]) => `
    <ellipse cx="${f(TW * u)}" cy="${f(TH * v)}" rx="9" ry="4.5" fill="${C.deniz[1]}" opacity="0.55"/>`).join('')}`, 11);

export const nehir = () => tile(C.nehir, `
  <path d="M${f(TW * 0.5)},0 L${TW},${f(TH * 0.5)} L${f(TW * 0.5)},${TH} L0,${f(TH * 0.5)} Z"
    fill="${C.nehir[1]}" opacity="0.45"/>`, 12);

/* --- Süsler (harita üstüne serpilir) ------------------------------ */

const deco = (body, w = 72, h = 80) => wrapFlat({ width: w, height: h, body: `
  <ellipse cx="${w / 2}" cy="${h - 8}" rx="16" ry="6" fill="#1b1206" opacity="0.22"
    filter="url(#hafifYumusak)"/>${body}` });

export const sus_cam = () => deco(tree({ cx: 36, cy: 70, scale: 1.0, kind: 'cam' }));
export const sus_agac = () => deco(tree({ cx: 36, cy: 70, scale: 1.0, kind: 'yaprak' }));
export const sus_kaya = () => deco(rocks({ cx: 36, cy: 58, scale: 1.0, color: P.kaya, count: 4 }), 72, 72);
export const sus_kar_cam = () => deco(`
  ${tree({ cx: 36, cy: 70, scale: 1.0, kind: 'cam' })}
  <path d="M16,56 L36,34 L56,56 Z" fill="${P.kar}" opacity="0.72"/>
  <path d="M21,43 L36,24 L51,43 Z" fill="${P.kar}" opacity="0.8"/>`);
export const sus_kaktus = () => deco(`
  <rect x="31" y="34" width="10" height="38" rx="5" fill="#4f7a3a"/>
  <rect x="19" y="46" width="8" height="18" rx="4" fill="#467030"/>
  <rect x="22" y="44" width="14" height="7" rx="3.5" fill="#467030"/>
  <rect x="45" y="40" width="8" height="22" rx="4" fill="#4f7a3a"/>
  <rect x="38" y="38" width="14" height="7" rx="3.5" fill="#4f7a3a"/>`, 72, 80);

/* --- Harita işaretleri -------------------------------------------- */

const marker = (body) => wrapFlat({ width: 64, height: 72, body: `
  <ellipse cx="32" cy="64" rx="13" ry="5" fill="#1b1206" opacity="0.3"
    filter="url(#hafifYumusak)"/>${body}` });

export const isaret_oba = () => marker(`
  <path d="M10,52 q22,-34 44,0 Z" fill="${P.kece}"/>
  <path d="M10,52 q22,-34 44,0 Z" fill="none" stroke="${P.kizil}" stroke-width="2.4"/>
  <rect x="8" y="50" width="48" height="8" rx="3" fill="${P.ahsapKoyu}"/>
  <rect x="30" y="10" width="3" height="16" fill="${P.ahsapKoyu}"/>
  <path d="M33,11 l14,4 l-14,4 Z" fill="${P.kizil}"/>`);

export const isaret_kamp = () => marker(`
  <path d="M14,54 l18,-30 l18,30 Z" fill="#4a3344"/>
  <path d="M14,54 l18,-30 l4,30 Z" fill="#5f4359"/>
  <circle cx="26" cy="44" r="3" fill="#ff5d5d"/>
  <circle cx="38" cy="44" r="3" fill="#ff5d5d"/>
  <path d="M24,52 l4,-4 l4,4 l4,-4 l4,4" stroke="#e8e2d2" stroke-width="2" fill="none"/>`);

export const isaret_damar = () => marker(`
  <path d="M12,54 l8,-22 l24,0 l8,22 Z" fill="${P.kaya}"/>
  <path d="M12,54 l8,-22 l12,0 l0,22 Z" fill="${iso.shade(P.kaya, 0.16)}"/>
  <circle cx="26" cy="42" r="4" fill="${P.gold}"/>
  <circle cx="38" cy="46" r="3.4" fill="${P.gold}"/>
  <circle cx="32" cy="36" r="3" fill="${P.gold}"/>`);

export const isaret_harabe = () => marker(`
  <path d="M16,54 l2,-24 l9,0 l1,24 Z" fill="${P.tas}"/>
  <path d="M30,54 l1,-16 l8,0 l1,16 Z" fill="${iso.shade(P.tas, -0.15)}"/>
  <path d="M42,54 l2,-28 l8,0 l1,28 Z" fill="${iso.shade(P.tas, 0.1)}"/>
  <path d="M16,32 l12,-5 M42,28 l10,4" stroke="${iso.shade(P.tas, -0.3)}" stroke-width="2"/>`);

export const isaret_kutsal = () => marker(`
  <path d="M32,8 L44,28 L32,54 L20,28 Z" fill="${P.altin}" opacity="0.9"/>
  <path d="M32,8 L44,28 L32,34 Z" fill="#fff" opacity="0.5"/>
  <ellipse cx="32" cy="54" rx="16" ry="7" fill="${P.altin}" opacity="0.28"
    filter="url(#yumusak)"/>`);

export const ALL = {
  deniz, kiyi, bozkir, cayir, orman, tayga, dag, kar, col, bataklik, nehir,
  sus_cam, sus_agac, sus_kaya, sus_kar_cam, sus_kaktus,
  isaret_oba, isaret_kamp, isaret_damar, isaret_harabe, isaret_kutsal,
};
