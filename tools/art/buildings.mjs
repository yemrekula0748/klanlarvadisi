/**
 * Klanlar Vadisi — 32 yapının izometrik sprite tanımı.
 *
 * Her yapı, `lib/parts.mjs` ve `lib/iso.mjs` parçalarından kurulur;
 * böylece hepsi aynı ışık yönünü, aynı paleti ve aynı izometrik
 * projeksiyonu paylaşır.
 *
 * Kullanım: `node build.mjs` bütün sprite'ları SVG + PNG olarak üretir.
 */

import * as iso from './lib/iso.mjs';
import { PALETTE as P } from './lib/palette.mjs';
import { damga, kilimBand, kocboynuzu } from './lib/ornament.mjs';
import * as parts from './lib/parts.mjs';
import { wrap } from './lib/render.mjs';

const { project, shade } = iso;
const f = (n) => Math.round(n * 100) / 100;

/* ------------------------------------------------------------------ */
/* Ortak yardımcılar                                                   */
/* ------------------------------------------------------------------ */

const shadow = (w, d, o = 0.3) => iso.groundShadow({ w, d, opacity: o });
const base = (w, d, h = 7, color = P.tasKoyu) =>
  iso.platform({ w: w - 0.7, d: d - 0.7, h, color });

/* ------------------------------------------------------------------ */
/* MERKEZ                                                              */
/* ------------------------------------------------------------------ */

export const otag = () => {
  const w = 4, d = 4;
  // wallH > eave*0.5 olmalı, yoksa çatı duvarı yutar.
  const y = iso.yurt({
    w, d, z: 9, wallH: 84, roofH: 100,
    felt: P.kece, roof: shade(P.kece, -0.05),
    trim: P.kizil, crown: P.ahsapKoyu, seams: 24, fill: 0.50,
  });

  const body = `
    ${shadow(w, d, 0.34)}
    ${iso.banner({ x: 0.55, y: 0.55, z: 9, height: 132, color: P.lacivert })}
    ${base(w, d, 9)}
    <g filter="url(#keceDoku)">${y.svg}</g>
    ${iso.yurtDoor({ cx: y.cx, cy: y.cy + y.ry * 0.72, w: 40, h: 54,
      frame: P.ahsapKoyu, curtain: P.kizilKoyu })}
    ${damga({ cx: y.cx, cy: y.cy + y.ry * 0.72 - 30, key: 'konrul', size: 1.2, color: P.altin, width: 2 })}
    ${iso.banner({ x: w - 0.55, y: 0.55, z: 9, height: 108, color: P.kizil })}
    ${iso.banner({ x: 0.55, y: d - 0.55, z: 9, height: 96, color: P.altin })}
  `;
  return wrap({ body, w, d, height: 280 });
};

/* ------------------------------------------------------------------ */
/* KAYNAK ÜRETİMİ                                                      */
/* ------------------------------------------------------------------ */

export const altin_madeni = () => {
  const w = 3, d = 3;
  const [cx, cy] = project(w / 2, d / 2, 0);
  const body = `
    ${shadow(w, d)}
    ${iso.box({ w: w - 0.5, d: d - 0.5, h: 7, color: P.toprak })}
    ${parts.rocks({ cx: cx - 18, cy: cy - 14, scale: 1.05, color: P.kaya, count: 4 })}
    <!-- Maden ağzı -->
    <path d="M${f(cx - 34)},${f(cy - 6)} q34,-44 68,0 Z" fill="${shade(P.kaya, -0.55)}"/>
    <path d="M${f(cx - 28)},${f(cy - 6)} q28,-36 56,0 Z" fill="#120d06"/>
    <!-- Ahşap destek kirişleri -->
    <path d="M${f(cx - 30)},${f(cy - 6)} l0,-26 M${f(cx + 30)},${f(cy - 6)} l0,-26
             M${f(cx - 34)},${f(cy - 32)} h68" stroke="${P.ahsapKoyu}" stroke-width="5"
      stroke-linecap="round" fill="none"/>
    ${parts.resourcePile({ cx: cx + 26, cy: cy + 14, kind: 'gold', scale: 0.95 })}
    <!-- Kazma -->
    <g transform="translate(${f(cx - 44)},${f(cy + 12)}) rotate(-28)">
      <rect x="-2" y="-26" width="4" height="30" rx="1.5" fill="${P.ahsap}"/>
      <path d="M-13,-26 q13,-7 26,0 q-13,5 -26,0 Z" fill="${P.demir}"/>
    </g>
  `;
  return wrap({ body, w, d, height: 130 });
};

export const tarla = () => {
  const w = 3, d = 3;
  const rows = [];
  for (let i = 0.3; i < d - 0.1; i += 0.44) {
    const a = project(0.2, i, 0);
    const b = project(w - 0.2, i, 0);
    rows.push(`<path d="M${f(a[0])},${f(a[1])} L${f(b[0])},${f(b[1])}"
      stroke="${shade(P.toprak, -0.28)}" stroke-width="8" stroke-linecap="round" opacity="0.9"/>`);
    rows.push(`<path d="M${f(a[0])},${f(a[1] - 5)} L${f(b[0])},${f(b[1] - 5)}"
      stroke="${P.grain}" stroke-width="6" stroke-linecap="round"/>`);
    for (let t = 0.1; t < 1; t += 0.1) {
      const px = a[0] + (b[0] - a[0]) * t;
      const py = a[1] + (b[1] - a[1]) * t - 5;
      rows.push(`<path d="M${f(px)},${f(py)} l0,-11 M${f(px)},${f(py - 6)} l-3.4,-3.4
        M${f(px)},${f(py - 6)} l3.4,-3.4" stroke="${shade(P.grain, 0.22)}"
        stroke-width="1.6" stroke-linecap="round"/>`);
    }
  }

  const body = `
    ${shadow(w, d, 0.2)}
    ${iso.box({ w: w - 0.3, d: d - 0.3, h: 6, color: P.toprak })}
    <g>${rows.join('')}</g>
    ${iso.box({ w: 0.1, d, h: 15, z: 5, color: P.ahsapKoyu })}
    ${iso.box({ w, d: 0.1, h: 15, z: 5, color: P.ahsap })}
    <!-- Korkuluk -->
    <g transform="translate(${f(project(w - 0.5, 0.5, 0)[0])},${f(project(w - 0.5, 0.5, 0)[1])})">
      <rect x="-2" y="-40" width="4" height="42" fill="${P.ahsapKoyu}"/>
      <rect x="-16" y="-30" width="32" height="4" fill="${P.ahsapKoyu}"/>
      <circle cx="0" cy="-44" r="8" fill="${P.kum}"/>
      <path d="M-4,-46 h2 M2,-46 h2" stroke="${P.cizgi}" stroke-width="1.6"/>
    </g>
  `;
  return wrap({ body, w, d, height: 130 });
};

export const demir_ocagi = () => {
  const w = 3, d = 3;
  const [cx, cy] = project(w / 2, d / 2, 0);
  const body = `
    ${shadow(w, d)}
    ${base(w, d, 8, P.tasKoyu)}
    <g filter="url(#tasDoku)">
      ${iso.box({ w: w - 1.3, d: d - 1.3, h: 52, z: 8, color: P.tas })}
    </g>
    <!-- Baca -->
    ${iso.box({ w: 0.7, d: 0.7, h: 76, z: 52, color: shade(P.tas, -0.12) })}
    <!-- Ocak ağzı ve alev -->
    <path d="M${f(cx - 24)},${f(cy + 6)} q24,-30 48,0 Z" fill="#160e05"/>
    ${parts.fire({ cx, cy: cy + 2, scale: 1.1 })}
    <!-- Duman -->
    <g opacity="0.45">
      <circle cx="${f(cx + 2)}" cy="${f(cy - 104)}" r="11" fill="#c9c0b2"/>
      <circle cx="${f(cx + 11)}" cy="${f(cy - 120)}" r="8" fill="#c9c0b2"/>
      <circle cx="${f(cx - 4)}" cy="${f(cy - 132)}" r="6" fill="#c9c0b2"/>
    </g>
    ${parts.resourcePile({ cx: cx + 38, cy: cy + 22, kind: 'iron', scale: 0.8 })}
  `;
  return wrap({ body, w, d, height: 200 });
};

export const kam_cadiri = () => {
  const w = 3, d = 3;
  const y = iso.yurt({
    w, d, z: 7, wallH: 56, roofH: 66, felt: '#d8dde6',
    roof: '#c6cedb', trim: P.lacivert, crown: P.ahsapKoyu, seams: 18, fill: 0.46,
  });
  const body = `
    ${shadow(w, d)}
    ${base(w, d, 7, P.tasKoyu)}
    <g filter="url(#keceDoku)">${y.svg}</g>
    ${iso.yurtDoor({ cx: y.cx, cy: y.cy + y.ry * 0.72, w: 28, h: 36,
      frame: P.ahsapKoyu, curtain: P.lacivert })}
    ${damga({ cx: y.cx, cy: y.cy + y.ry * 0.72 - 20, key: 'ay', size: 0.9, color: P.kut, width: 1.8 })}
    <!-- Kam davulu -->
    <g transform="translate(${f(y.cx - 52)},${f(y.cy + 14)})">
      <ellipse cx="0" cy="0" rx="15" ry="15" fill="${P.kece}" stroke="${P.ahsapKoyu}" stroke-width="3"/>
      ${damga({ cx: 0, cy: 0, key: 'agac', size: 0.75, color: P.kizil, width: 1.6 })}
    </g>
    <!-- Kut ışığı (tepe halkasından göğe) -->
    ${parts.magicGlow({ cx: y.cx, cy: y.peakY - 6, scale: 0.9, color: P.kut })}
  `;
  return wrap({ body, w, d, height: 200 });
};

/* ------------------------------------------------------------------ */
/* DEPOLAR                                                             */
/* ------------------------------------------------------------------ */

export const hazine = () => {
  const w = 3, d = 3;
  const [cx, cy] = project(w / 2, d / 2, 0);
  const body = `
    ${shadow(w, d)}
    ${base(w, d, 7)}
    <g filter="url(#tasDoku)">
      ${iso.box({ w: w - 1.2, d: d - 1.2, h: 48, z: 7, color: P.tas })}
    </g>
    <!-- Sandık kapağı (gövdeye oturur) -->
    ${iso.box({ w: w - 1.15, d: d - 1.15, h: 12, z: 55, color: P.ahsap })}
    ${iso.tentRoof({ w: w - 1.15, d: d - 1.15, h: 26, z: 67, color: P.ahsapKoyu, overhang: 0.03 })}
    <path d="M${f(cx - 46)},${f(cy - 54)} h92" stroke="${P.altinKoyu}" stroke-width="4"/>
    <!-- Altın taşması -->
    ${parts.resourcePile({ cx, cy: cy - 50, kind: 'gold', scale: 0.85 })}
    <!-- Demir kuşaklar -->
    <path d="M${f(cx - 28)},${f(cy + 4)} l0,-48 M${f(cx + 28)},${f(cy + 4)} l0,-48"
      stroke="${P.demirKoyu}" stroke-width="5"/>
    ${kocboynuzu({ cx, cy: cy - 14, size: 15, color: P.altin, width: 2.6 })}
  `;
  return wrap({ body, w, d, height: 160 });
};

export const ambar = () => {
  const w = 3, d = 3;
  const [cx, cy] = project(w / 2, d / 2, 0);
  const body = `
    ${shadow(w, d)}
    ${base(w, d, 6, P.ahsapKoyu)}
    ${iso.cylinder({ w: w - 0.6, d: d - 0.6, h: 64, z: 6, color: P.ahsap, bands: 4, fill: 0.5 })}
    ${iso.tentRoof({ w: w - 1.7, d: d - 1.7, h: 52, z: 70, color: P.kizilKoyu, overhang: 0.42 })}
    <!-- Kapak -->
    <path d="M${f(cx - 15)},${f(cy + 2)} l0,-30 h30 l0,30 Z" fill="${shade(P.ahsapKoyu, -0.25)}"/>
    <path d="M${f(cx - 15)},${f(cy - 14)} h30" stroke="${P.demirKoyu}" stroke-width="3"/>
    ${parts.resourcePile({ cx: cx + 40, cy: cy + 18, kind: 'grain', scale: 0.8 })}
  `;
  return wrap({ body, w, d, height: 180 });
};

export const demir_deposu = () => {
  const w = 3, d = 3;
  const [cx, cy] = project(w / 2, d / 2, 0);
  const body = `
    ${shadow(w, d)}
    ${base(w, d, 7)}
    ${parts.woodShed({ w: w - 1.25, d: d - 1.25, z: 7, h: 40, roofH: 36,
      body: P.demirKoyu, roof: P.demir })}
    ${parts.resourcePile({ cx, cy: cy + 10, kind: 'iron', scale: 1.05 })}
    ${parts.resourcePile({ cx: cx - 34, cy: cy + 22, kind: 'iron', scale: 0.72 })}
  `;
  return wrap({ body, w, d, height: 150 });
};

export const kut_tasi = () => {
  const w = 2, d = 2;
  const [cx, cy] = project(w / 2, d / 2, 0);
  const body = `
    ${shadow(w, d)}
    ${base(w, d, 8, P.tasKoyu)}
    <!-- Göktaşı dikilitaş -->
    <path d="M${f(cx - 15)},${f(cy - 8)} L${f(cx - 10)},${f(cy - 92)}
             L${f(cx + 2)},${f(cy - 104)} L${f(cx + 13)},${f(cy - 88)}
             L${f(cx + 16)},${f(cy - 8)} Z" fill="${P.lacivert}"/>
    <path d="M${f(cx - 15)},${f(cy - 8)} L${f(cx - 10)},${f(cy - 92)}
             L${f(cx + 2)},${f(cy - 104)} L${f(cx + 1)},${f(cy - 8)} Z"
      fill="${shade(P.lacivert, 0.2)}"/>
    ${damga({ cx: cx - 4, cy: cy - 58, key: 'goz', size: 0.9, color: P.kut, width: 2 })}
    ${damga({ cx: cx - 4, cy: cy - 30, key: 'su', size: 0.8, color: P.kut, width: 1.8 })}
    ${parts.magicGlow({ cx, cy: cy - 100, scale: 0.85, color: P.kut })}
  `;
  return wrap({ body, w, d, height: 180 });
};

/* ------------------------------------------------------------------ */
/* ORDU YAPILARI                                                       */
/* ------------------------------------------------------------------ */

export const kisla = () => {
  const w = 3, d = 3;
  const [cx, cy] = project(w / 2, d / 2, 0);
  const y = iso.yurt({
    w: w + 0.4, d: d - 0.6, z: 6, wallH: 46, roofH: 52, felt: '#cdbfa2',
    roof: '#b9a98c', trim: P.kizilKoyu, crown: P.ahsapKoyu, seams: 16, fill: 0.46,
  });
  const body = `
    ${shadow(w, d)}
    ${base(w, d, 6, P.ahsapKoyu)}
    <g filter="url(#keceDoku)">${y.svg}</g>
    ${iso.yurtDoor({ cx: y.cx, cy: y.cy + y.ry * 0.7, w: 30, h: 36,
      frame: P.ahsapKoyu, curtain: P.kizilKoyu })}
    ${parts.weaponRack({ cx: cx - 52, cy: cy + 20, scale: 0.95 })}
    ${parts.weaponRack({ cx: cx + 50, cy: cy + 24, scale: 0.85 })}
    ${iso.banner({ x: 0.5, y: d - 0.5, z: 6, height: 74, color: P.kizil })}
  `;
  return wrap({ body, w, d, height: 180 });
};

export const kara_kisla = () => {
  const w = 3, d = 3;
  const [cx, cy] = project(w / 2, d / 2, 0);
  const y = iso.yurt({
    w: w + 0.3, d: d - 0.5, z: 6, wallH: 46, roofH: 54, felt: '#3a3240',
    roof: '#2e2733', trim: P.mor, crown: '#1a1620', seams: 16, fill: 0.46,
  });
  const body = `
    ${shadow(w, d, 0.4)}
    ${base(w, d, 6, '#2a242e')}
    ${y.svg}
    ${iso.yurtDoor({ cx: y.cx, cy: y.cy + y.ry * 0.7, w: 30, h: 38,
      frame: '#1a1620', curtain: P.karanlik })}
    ${damga({ cx: y.cx, cy: y.cy + y.ry * 0.7 - 20, key: 'goz', size: 0.85, color: '#c47ae0', width: 1.8 })}
    ${parts.magicGlow({ cx: cx - 46, cy: cy + 16, scale: 0.75, color: '#a855f7' })}
    ${parts.magicGlow({ cx: cx + 44, cy: cy + 22, scale: 0.6, color: '#a855f7' })}
  `;
  return wrap({ body, w, d, height: 180 });
};

export const ordugah = () => {
  const w = 4, d = 4;
  const [cx, cy] = project(w / 2, d / 2, 0);
  const body = `
    ${shadow(w, d, 0.26)}
    ${iso.box({ w: w - 0.3, d: d - 0.3, h: 5, color: shade(P.cimKoyu, -0.15) })}
    ${parts.smallTent({ x: 0.75, y: 0.75, z: 4, size: 1.3, felt: P.kece, trim: P.kizil })}
    ${parts.smallTent({ x: 2.6, y: 0.9, z: 4, size: 1.2, felt: '#ddd3bc', trim: P.lacivert })}
    ${parts.smallTent({ x: 0.9, y: 2.6, z: 4, size: 1.2, felt: '#ddd3bc', trim: P.altin })}
    ${parts.smallTent({ x: 2.75, y: 2.75, z: 4, size: 1.35, felt: P.kece, trim: P.kizil })}
    ${parts.fire({ cx, cy: cy + 4, scale: 0.95 })}
    ${iso.banner({ x: w / 2, y: 0.3, z: 4, height: 82, color: P.kizil })}
  `;
  return wrap({ body, w, d, height: 170 });
};

export const demirci = () => {
  const w = 3, d = 3;
  const [cx, cy] = project(w / 2, d / 2, 0);
  const body = `
    ${shadow(w, d)}
    ${base(w, d, 7, P.tasKoyu)}
    ${parts.woodShed({ w: w - 1.2, d: d - 1.7, z: 7, h: 40, roofH: 42,
      body: P.ahsap, roof: P.kizilKoyu })}
    <!-- Örs -->
    <g transform="translate(${f(cx + 6)},${f(cy + 18)})">
      <path d="M-18,0 h36 l-5,-9 h-26 Z" fill="${P.demirKoyu}"/>
      <path d="M-13,-9 h26 l-4,-12 h-18 Z" fill="${P.demir}"/>
      <path d="M-24,-21 q11,-5 22,0 l0,5 q-11,4 -22,0 Z" fill="${P.demir}"/>
    </g>
    <!-- Çekiç -->
    <g transform="translate(${f(cx - 32)},${f(cy + 6)}) rotate(-40)">
      <rect x="-1.8" y="-26" width="3.6" height="30" rx="1.4" fill="${P.ahsap}"/>
      <rect x="-9" y="-32" width="18" height="10" rx="2" fill="${P.demirKoyu}"/>
    </g>
    ${parts.fire({ cx: cx + 42, cy: cy + 4, scale: 0.8 })}
    <!-- Kıvılcımlar -->
    ${[0, 1, 2, 3, 4].map((i) => `<circle cx="${f(cx + 8 + (i - 2) * 9)}" cy="${f(cy - 4 - (i % 3) * 7)}"
      r="${f(1.3 + (i % 2) * 0.8)}" fill="${P.atesIc}" opacity="0.85"/>`).join('')}
  `;
  return wrap({ body, w, d, height: 160 });
};

export const buyu_atolyesi = () => {
  const w = 3, d = 3;
  const [cx, cy] = project(w / 2, d / 2, 0);
  const body = `
    ${shadow(w, d)}
    ${base(w, d, 7, P.tasKoyu)}
    <g filter="url(#tasDoku)">
      ${iso.cylinder({ w: w - 0.7, d: d - 0.7, h: 52, z: 7, color: '#6d6480', bands: 2, fill: 0.5 })}
    </g>
    ${iso.tentRoof({ w: w - 1.8, d: d - 1.8, h: 54, z: 59, color: P.mor, overhang: 0.45 })}
    <!-- Kazan -->
    <g transform="translate(${f(cx)},${f(cy + 16)})">
      <ellipse cx="0" cy="0" rx="26" ry="12" fill="${P.demirKoyu}"/>
      <path d="M-26,0 q0,22 26,22 q26,0 26,-22 Z" fill="${shade(P.demirKoyu, -0.2)}"/>
      <ellipse cx="0" cy="-1" rx="21" ry="9" fill="${P.buyu}" opacity="0.85"/>
    </g>
    ${parts.magicGlow({ cx, cy: cy + 8, scale: 1.1, color: P.buyu })}
    ${damga({ cx, cy: cy - 32, key: 'ay', size: 1, color: P.buyu, width: 2 })}
  `;
  return wrap({ body, w, d, height: 180 });
};

/* ------------------------------------------------------------------ */
/* ÖZEL YAPILAR                                                        */
/* ------------------------------------------------------------------ */

export const kurgan = () => {
  const w = 3, d = 3;
  const [cx, cy] = project(w / 2, d / 2, 0);
  const rx = (w + d) * (iso.TILE_W / 4) * 0.5;
  const body = `
    ${shadow(w, d, 0.3)}
    <!-- Höyük -->
    <ellipse cx="${f(cx)}" cy="${f(cy)}" rx="${f(rx)}" ry="${f(rx * 0.5)}" fill="${shade(P.cimKoyu, -0.2)}"/>
    <path d="M${f(cx - rx)},${f(cy)} Q${f(cx)},${f(cy - 78)} ${f(cx + rx)},${f(cy)} Z"
      fill="${P.cimKoyu}"/>
    <path d="M${f(cx - rx)},${f(cy)} Q${f(cx - rx * 0.4)},${f(cy - 62)} ${f(cx)},${f(cy - 52)}
             L${f(cx)},${f(cy)} Z" fill="${shade(P.cimKoyu, 0.16)}"/>
    <!-- Taş babalar -->
    ${parts.stoneBaba({ cx: cx - 52, cy: cy + 14, h: 46 })}
    ${parts.stoneBaba({ cx: cx + 50, cy: cy + 16, h: 40 })}
    ${parts.stoneBaba({ cx: cx - 6, cy: cy + 26, h: 52, color: shade(P.tas, 0.08) })}
    <!-- Tepe sunağı -->
    ${iso.box({ w: 0.7, d: 0.7, h: 16, z: 56, color: P.tas })}
    ${parts.magicGlow({ cx, cy: cy - 72, scale: 0.75, color: P.altin })}
  `;
  return wrap({ body, w, d, height: 170 });
};

export const boy_otagi = () => {
  const w = 3, d = 3;
  const y = iso.yurt({
    w, d, z: 9, wallH: 62, roofH: 74, felt: '#e3d9c4',
    roof: shade('#e3d9c4', -0.06), trim: P.altin, crown: P.ahsapKoyu, seams: 20, fill: 0.46,
  });
  const body = `
    ${shadow(w, d)}
    ${iso.banner({ x: 0.5, y: 0.5, z: 9, height: 124, color: P.altin })}
    ${base(w, d, 9, P.tasKoyu)}
    <g filter="url(#keceDoku)">${y.svg}</g>
    ${iso.yurtDoor({ cx: y.cx, cy: y.cy + y.ry * 0.72, w: 32, h: 40,
      frame: P.ahsapKoyu, curtain: P.kizil })}
    ${damga({ cx: y.cx, cy: y.cy + y.ry * 0.72 - 22, key: 'kurt', size: 1, color: P.altin, width: 1.9 })}
    ${iso.banner({ x: w - 0.5, y: d - 0.5, z: 9, height: 92, color: P.kizil })}
  `;
  return wrap({ body, w, d, height: 230 });
};

export const gozcu_kulesi = () => {
  const w = 2, d = 2;
  const [cx, cy] = project(w / 2, d / 2, 0);
  const body = `
    ${shadow(w, d)}
    ${base(w, d, 5, P.ahsapKoyu)}
    <!-- Ahşap ayaklar -->
    ${[[-22, -6], [22, -6], [-22, 10], [22, 10]].map(([dx, dy]) =>
      `<rect x="${f(cx + dx - 3)}" y="${f(cy + dy - 70)}" width="6" height="72"
        fill="${dx < 0 ? P.ahsap : P.ahsapKoyu}"/>`).join('')}
    <path d="M${f(cx - 22)},${f(cy - 36)} L${f(cx + 22)},${f(cy - 22)}
             M${f(cx + 22)},${f(cy - 36)} L${f(cx - 22)},${f(cy - 22)}"
      stroke="${P.ahsapKoyu}" stroke-width="4"/>
    <!-- Platform -->
    ${iso.box({ w: w - 0.1, d: d - 0.1, h: 8, z: 70, color: P.ahsap })}
    ${iso.tentRoof({ w: w - 0.9, d: d - 0.9, h: 42, z: 78, color: P.kizilKoyu, overhang: 0.55 })}
    <!-- Gözcü ateşi -->
    ${parts.fire({ cx, cy: cy - 76, scale: 0.6 })}
    ${iso.banner({ x: w / 2, y: d / 2, z: 108, height: 34, color: P.altin })}
  `;
  return wrap({ body, w, d, height: 200 });
};

export const umay_tapinagi = () => {
  const w = 3, d = 3;
  const [cx, cy] = project(w / 2, d / 2, 0);
  const body = `
    ${shadow(w, d)}
    ${base(w, d, 11, '#b9b2a0')}
    ${iso.box({ w: w - 1.8, d: d - 1.8, h: 10, z: 10, color: '#cfc7b2' })}
    <!-- Kutsal ağaç -->
    <rect x="${f(cx - 6)}" y="${f(cy - 76)}" width="12" height="72" rx="3" fill="${P.ahsapKoyu}"/>
    <ellipse cx="${f(cx)}" cy="${f(cy - 92)}" rx="46" ry="34" fill="${P.cimKoyu}"/>
    <ellipse cx="${f(cx - 15)}" cy="${f(cy - 101)}" rx="27" ry="21" fill="${P.cim}"/>
    <ellipse cx="${f(cx + 19)}" cy="${f(cy - 86)}" rx="20" ry="15" fill="${shade(P.cim, -0.1)}"/>
    <!-- Dilek bezleri -->
    ${[[-30, -78], [14, -70], [-10, -62], [32, -74]].map(([dx, dy], i) =>
      `<path d="M${f(cx + dx)},${f(cy + dy)} q4,12 -2,20 q-6,-9 2,-20 Z"
        fill="${[P.kizil, P.lacivert, P.altin, P.kece][i]}" opacity="0.92"/>`).join('')}
    <!-- Sunak -->
    ${iso.box({ w: 0.5, d: 0.5, h: 12, z: 18, color: P.tas })}
    ${parts.magicGlow({ cx, cy: cy - 26, scale: 0.85, color: '#ffe6a8' })}
  `;
  return wrap({ body, w, d, height: 200 });
};

/* ------------------------------------------------------------------ */
/* SAVUNMA                                                             */
/* ------------------------------------------------------------------ */

export const okcu_kulesi = () => {
  const w = 3, d = 3;
  const t = parts.stoneTower({ w, d, z: 8, h: 94, color: P.tas, bands: 3, merlons: 7 });
  const body = `
    ${shadow(w, d)}
    ${base(w, d, 8)}
    <g filter="url(#tasDoku)">${t.svg}</g>
    ${iso.tentRoof({ w: w - 1.9, d: d - 1.9, h: 54, z: 104, color: P.kizilKoyu, overhang: 0.60 })}
    <!-- Okçu mazgalı -->
    <rect x="${f(t.cx - 4)}" y="${f(t.cy - 62)}" width="8" height="22" rx="3" fill="#140e06"/>
    ${damga({ cx: t.cx, cy: t.cy - 26, key: 'ok', size: 1, color: P.altin, width: 1.9 })}
    ${iso.banner({ x: w / 2, y: d / 2, z: 148, height: 36, color: P.kizil })}
  `;
  return wrap({ body, w, d, height: 240 });
};

export const ates_kulesi = () => {
  const w = 3, d = 3;
  const [cx, cy] = project(w / 2, d / 2, 0);
  const body = `
    ${shadow(w, d)}
    ${base(w, d, 8)}
    <g filter="url(#tasDoku)">
      ${iso.box({ w: w - 1.3, d: d - 1.3, h: 46, z: 8, color: P.tas })}
    </g>
    <!-- Namlu (ejder ağzı) -->
    <g transform="translate(${f(cx)},${f(cy - 44)}) rotate(-22)">
      <rect x="-10" y="-14" width="56" height="26" rx="12" fill="${P.demirKoyu}"/>
      <rect x="-10" y="-14" width="56" height="11" rx="6" fill="${P.demir}"/>
      <ellipse cx="48" cy="-1" rx="7" ry="13" fill="#120c05"/>
      <path d="M42,-14 l6,-7 l3,8 Z M42,12 l6,7 l3,-8 Z" fill="${P.altinKoyu}"/>
    </g>
    ${parts.fire({ cx: cx + 46, cy: cy - 44, scale: 0.72 })}
    <!-- Gülle yığını -->
    ${[[-40, 14], [-30, 18], [-35, 8]].map(([dx, dy]) =>
      `<circle cx="${f(cx + dx)}" cy="${f(cy + dy)}" r="7" fill="${P.demirKoyu}"/>`).join('')}
    ${damga({ cx, cy: cy - 10, key: 'dag', size: 0.85, color: P.altin, width: 1.8 })}
  `;
  return wrap({ body, w, d, height: 180 });
};

export const mancinik = () => {
  const w = 3, d = 3;
  const [cx, cy] = project(w / 2, d / 2, 0);
  const body = `
    ${shadow(w, d)}
    ${iso.box({ w, d, h: 6, color: P.toprak })}
    <!-- Kaide -->
    ${iso.box({ w: w - 1.6, d: d - 1.6, h: 20, z: 6, color: P.ahsapKoyu })}
    <!-- Kollar -->
    <path d="M${f(cx - 30)},${f(cy - 20)} L${f(cx)},${f(cy - 76)} L${f(cx + 30)},${f(cy - 20)}"
      stroke="${P.ahsap}" stroke-width="9" fill="none" stroke-linecap="round"/>
    <!-- Atış kolu -->
    <g transform="translate(${f(cx)},${f(cy - 72)}) rotate(-34)">
      <rect x="-5" y="-6" width="86" height="11" rx="5" fill="${P.ahsapKoyu}"/>
      <path d="M78,-18 q18,10 0,22 q-10,-11 0,-22 Z" fill="${P.ahsap}"/>
      <circle cx="84" cy="-6" r="11" fill="${P.kaya}"/>
    </g>
    <!-- Halat -->
    <path d="M${f(cx - 26)},${f(cy - 24)} Q${f(cx - 6)},${f(cy - 56)} ${f(cx + 14)},${f(cy - 70)}"
      stroke="${P.kum}" stroke-width="2.6" fill="none"/>
    ${[[-44, 16], [-34, 22]].map(([dx, dy]) =>
      `<circle cx="${f(cx + dx)}" cy="${f(cy + dy)}" r="9" fill="${P.kaya}"/>`).join('')}
  `;
  return wrap({ body, w, d, height: 200 });
};

export const yildirim_kulesi = () => {
  const w = 3, d = 3;
  const t = parts.stoneTower({ w, d, z: 8, h: 78, color: '#7a7488', bands: 2, merlons: 5 });
  const [cx, cy] = project(w / 2, d / 2, 0);
  const body = `
    ${shadow(w, d)}
    ${base(w, d, 8, '#4d4857')}
    ${t.svg}
    <!-- Yada taşı -->
    <g transform="translate(${f(cx)},${f(cy - 104)})">
      <path d="M0,-30 L17,-6 L9,24 L-9,24 L-17,-6 Z" fill="${P.kut}" opacity="0.95"/>
      <path d="M0,-30 L17,-6 L0,2 Z" fill="#ffffff" opacity="0.6"/>
    </g>
    ${parts.magicGlow({ cx, cy: cy - 104, scale: 1.2, color: P.kut })}
    <!-- Yıldırım çatalları -->
    <path d="M${f(cx - 36)},${f(cy - 120)} l12,16 l-7,3 l13,17"
      stroke="${P.kut}" stroke-width="2.6" fill="none" stroke-linecap="round" opacity="0.85"/>
    <path d="M${f(cx + 34)},${f(cy - 126)} l-11,15 l7,3 l-12,16"
      stroke="${P.kut}" stroke-width="2.6" fill="none" stroke-linecap="round" opacity="0.7"/>
  `;
  return wrap({ body, w, d, height: 240 });
};

export const gok_okcusu = () => {
  const w = 3, d = 3;
  const [cx, cy] = project(w / 2, d / 2, 0);
  const body = `
    ${shadow(w, d)}
    ${base(w, d, 8)}
    ${iso.box({ w: w - 1.5, d: d - 1.5, h: 32, z: 8, color: P.ahsapKoyu })}
    <!-- Göğe çevrili yay bataryası -->
    ${[-26, 0, 26].map((dx, i) => `
      <g transform="translate(${f(cx + dx)},${f(cy - 36 - (i === 1 ? 10 : 0))}) rotate(${-66 + i * 18})">
        <rect x="-3" y="-44" width="6" height="52" rx="3" fill="${P.ahsap}"/>
        <path d="M-15,-44 q15,-12 30,0" stroke="${P.demir}" stroke-width="3.4" fill="none"/>
        <path d="M0,-52 l0,22" stroke="${P.kece}" stroke-width="2.2"/>
        <path d="M-4,-50 l4,-7 l4,7 Z" fill="${P.demir}"/>
      </g>`).join('')}
    ${damga({ cx, cy: cy + 2, key: 'konrul', size: 0.95, color: P.altin, width: 1.9 })}
  `;
  return wrap({ body, w, d, height: 190 });
};

export const tilsim_tasi = () => {
  const w = 3, d = 3;
  const [cx, cy] = project(w / 2, d / 2, 0);
  const body = `
    ${shadow(w, d, 0.42)}
    ${base(w, d, 9, '#2c2535')}
    <!-- Kara dikilitaş -->
    <path d="M${f(cx - 22)},${f(cy - 9)} L${f(cx - 15)},${f(cy - 112)}
             L${f(cx + 4)},${f(cy - 128)} L${f(cx + 20)},${f(cy - 106)}
             L${f(cx + 24)},${f(cy - 9)} Z" fill="#241d2e"/>
    <path d="M${f(cx - 22)},${f(cy - 9)} L${f(cx - 15)},${f(cy - 112)}
             L${f(cx + 4)},${f(cy - 128)} L${f(cx + 2)},${f(cy - 9)} Z" fill="#332a40"/>
    <!-- Kazınmış damgalar -->
    ${damga({ cx: cx - 6, cy: cy - 94, key: 'goz', size: 0.8, color: '#e4484f', width: 2 })}
    ${damga({ cx: cx - 6, cy: cy - 62, key: 'kurt', size: 0.75, color: '#e4484f', width: 1.8 })}
    ${damga({ cx: cx - 6, cy: cy - 32, key: 'su', size: 0.7, color: '#e4484f', width: 1.6 })}
    <ellipse cx="${f(cx + 2)}" cy="${f(cy - 128)}" rx="26" ry="15" fill="#e4484f" opacity="0.3"
      filter="url(#yumusak)"/>
  `;
  return wrap({ body, w, d, height: 230 });
};

export const kartal_gozu = () => {
  const w = 4, d = 4;
  const [cx, cy] = project(w / 2, d / 2, 0);
  const body = `
    ${shadow(w, d, 0.36)}
    ${base(w, d, 12)}
    <g filter="url(#tasDoku)">
      ${iso.box({ w: w - 1.9, d: d - 1.9, h: 44, z: 12, color: P.tas })}
    </g>
    <!-- Kartal pençesi mancınık -->
    <g transform="translate(${f(cx)},${f(cy - 52)})">
      ${[-1, 1].map((s) => `
        <path d="M0,0 Q${s * 40},-34 ${s * 58},-74 Q${s * 44},-40 ${s * 14},-6 Z"
          fill="${s < 0 ? shade(P.demir, 0.12) : shade(P.demir, -0.2)}"/>`).join('')}
      <path d="M0,0 Q0,-44 4,-86 Q-6,-44 -6,-4 Z" fill="${P.demir}"/>
      <circle cx="0" cy="-64" r="20" fill="${P.kaya}"/>
      <circle cx="-6" cy="-70" r="7" fill="${shade(P.kaya, 0.2)}"/>
    </g>
    ${damga({ cx, cy: cy - 10, key: 'konrul', size: 1.2, color: P.altin, width: 2.2 })}
    ${iso.banner({ x: 0.6, y: d - 0.6, z: 12, height: 76, color: P.altin })}
  `;
  return wrap({ body, w, d, height: 250 });
};

export const kurt_ini = () => {
  const w = 3, d = 3;
  const [cx, cy] = project(w / 2, d / 2, 0);
  const body = `
    ${shadow(w, d, 0.34)}
    ${iso.box({ w: w - 0.5, d: d - 0.5, h: 7, color: shade(P.toprak, -0.1) })}
    ${parts.rocks({ cx, cy: cy - 10, scale: 1.3, color: P.kaya, count: 6 })}
    <!-- Mağara ağzı -->
    <path d="M${f(cx - 38)},${f(cy + 4)} q38,-52 76,0 Z" fill="${shade(P.kaya, -0.55)}"/>
    <path d="M${f(cx - 31)},${f(cy + 4)} q31,-43 62,0 Z" fill="#0e0a05"/>
    <!-- Kurt gözleri -->
    <ellipse cx="${f(cx - 12)}" cy="${f(cy - 14)}" rx="5" ry="3.4" fill="#ffd166"/>
    <ellipse cx="${f(cx + 12)}" cy="${f(cy - 14)}" rx="5" ry="3.4" fill="#ffd166"/>
    <ellipse cx="${f(cx - 12)}" cy="${f(cy - 14)}" rx="1.6" ry="3.2" fill="#2b1a06"/>
    <ellipse cx="${f(cx + 12)}" cy="${f(cy - 14)}" rx="1.6" ry="3.2" fill="#2b1a06"/>
    <!-- Kemikler -->
    <g opacity="0.75" transform="translate(${f(cx + 44)},${f(cy + 20)}) rotate(18)">
      <rect x="-14" y="-2" width="28" height="4" rx="2" fill="#e6e0d2"/>
      <circle cx="-14" cy="-2" r="3.4" fill="#e6e0d2"/>
      <circle cx="14" cy="2" r="3.4" fill="#e6e0d2"/>
    </g>
    ${damga({ cx: cx - 44, cy: cy - 30, key: 'kurt', size: 0.9, color: P.kece, width: 1.8 })}
  `;
  return wrap({ body, w, d, height: 160 });
};

/* ------------------------------------------------------------------ */
/* DUVAR VE TUZAKLAR                                                   */
/* ------------------------------------------------------------------ */

export const surlar = () => {
  const w = 1, d = 1;
  const [cx, cy] = project(w / 2, d / 2, 0);
  const body = `
    ${shadow(w, d, 0.24)}
    <g filter="url(#tasDoku)">
      ${iso.box({ w, d, h: 34, color: P.tas })}
    </g>
    <!-- Mazgal dişleri -->
    ${iso.box({ w: 0.42, d: 0.42, h: 11, z: 34, color: shade(P.tas, 0.08) })}
    <!-- Taş derzleri -->
    <path d="M${f(cx - 30)},${f(cy - 12)} l30,15 M${f(cx + 30)},${f(cy - 12)} l-30,15"
      stroke="${shade(P.tas, -0.3)}" stroke-width="1.4" opacity="0.5" fill="none"/>
  `;
  return wrap({ body, w, d, height: 90 });
};

const trapBase = (color, inner) => {
  const w = 1, d = 1;
  const [cx, cy] = project(w / 2, d / 2, 0);
  return { w, d, cx, cy, ground: `
    ${iso.groundShadow({ w, d, opacity: 0.18 })}
    ${iso.box({ w: w - 0.1, d: d - 0.1, h: 4, color: shade(P.toprak, -0.08) })}
    <ellipse cx="${f(cx)}" cy="${f(cy - 3)}" rx="26" ry="13" fill="${color}" opacity="0.9"/>
    <ellipse cx="${f(cx)}" cy="${f(cy - 4)}" rx="19" ry="9" fill="${inner}"/>` };
};

export const kapan = () => {
  const t = trapBase(shade(P.toprak, -0.3), '#1a1206');
  const body = `
    ${t.ground}
    <!-- Demir dişler -->
    ${[0, 1, 2, 3, 4, 5].map((i) => {
      const a = (i / 6) * Math.PI * 2;
      return `<path d="M${f(t.cx + Math.cos(a) * 17)},${f(t.cy - 4 + Math.sin(a) * 8)}
        l${f(Math.cos(a) * 7)},${f(Math.sin(a) * 3 - 9)} l${f(-Math.cos(a) * 4)},${f(-Math.sin(a) * 2)} Z"
        fill="${P.demir}"/>`;
    }).join('')}
  `;
  return wrap({ body, w: t.w, d: t.d, height: 60 });
};

export const sivri_kazik = () => {
  const t = trapBase(shade(P.toprak, -0.25), '#231806');
  const body = `
    ${t.ground}
    ${[-10, 0, 10].map((dx, i) =>
      `<path d="M${f(t.cx + dx - 4)},${f(t.cy - 4)} l4,${f(-22 - (i === 1 ? 7 : 0))} l4,${f(22 + (i === 1 ? 7 : 0))} Z"
        fill="${P.ahsap}" stroke="${P.ahsapKoyu}" stroke-width="0.8"/>`).join('')}
  `;
  return wrap({ body, w: t.w, d: t.d, height: 70 });
};

export const hava_kapani = () => {
  const t = trapBase('#4a5a42', '#20301c');
  const body = `
    ${t.ground}
    <!-- Ağ -->
    ${[0, 1, 2, 3].map((i) => {
      const a = (i / 4) * Math.PI;
      return `<path d="M${f(t.cx - Math.cos(a) * 18)},${f(t.cy - 4 - Math.sin(a) * 9)}
        Q${f(t.cx)},${f(t.cy - 22)} ${f(t.cx + Math.cos(a) * 18)},${f(t.cy - 4 + Math.sin(a) * 9)}"
        stroke="${P.kum}" stroke-width="1.5" fill="none" opacity="0.8"/>`;
    }).join('')}
    <circle cx="${f(t.cx)}" cy="${f(t.cy - 20)}" r="4" fill="${P.demir}"/>
  `;
  return wrap({ body, w: t.w, d: t.d, height: 70 });
};

export const devrilen_tas = () => {
  const w = 2, d = 2;
  const [cx, cy] = project(w / 2, d / 2, 0);
  const body = `
    ${iso.groundShadow({ w, d, opacity: 0.3 })}
    ${iso.box({ w: w - 0.3, d: d - 0.3, h: 5, color: shade(P.toprak, -0.12) })}
    <!-- Dengede duran kaya -->
    <path d="M${f(cx - 34)},${f(cy - 6)} l9,-30 l24,-13 l27,16 l6,27 Z" fill="${P.kaya}"/>
    <path d="M${f(cx - 34)},${f(cy - 6)} l9,-30 l24,-13 l2,25 Z" fill="${shade(P.kaya, 0.16)}"/>
    <!-- Kıl ipi -->
    <path d="M${f(cx - 36)},${f(cy - 2)} q36,10 72,-2" stroke="${P.kum}"
      stroke-width="2" fill="none" stroke-dasharray="4 3"/>
  `;
  return wrap({ body, w, d, height: 100 });
};

export const albiz_tuzagi = () => {
  const t = trapBase('#3a2d44', '#191020');
  const body = `
    ${t.ground}
    ${parts.magicGlow({ cx: t.cx, cy: t.cy - 8, scale: 0.55, color: '#a855f7' })}
    <!-- Kemik -->
    <g transform="translate(${f(t.cx)},${f(t.cy - 8)}) rotate(-12)">
      <rect x="-11" y="-1.8" width="22" height="3.6" rx="1.8" fill="#ded7c6"/>
      <circle cx="-11" cy="-1.8" r="3" fill="#ded7c6"/>
      <circle cx="11" cy="1.8" r="3" fill="#ded7c6"/>
    </g>
  `;
  return wrap({ body, w: t.w, d: t.d, height: 70 });
};

/* ------------------------------------------------------------------ */

export const ALL = {
  otag, altin_madeni, tarla, demir_ocagi, kam_cadiri,
  hazine, ambar, demir_deposu, kut_tasi,
  kisla, kara_kisla, ordugah, demirci, buyu_atolyesi,
  kurgan, boy_otagi, gozcu_kulesi, umay_tapinagi,
  okcu_kulesi, ates_kulesi, mancinik, yildirim_kulesi,
  gok_okcusu, tilsim_tasi, kartal_gozu, kurt_ini,
  surlar, kapan, sivri_kazik, hava_kapani, devrilen_tas, albiz_tuzagi,
};
