/**
 * Yeniden kullanılan yapı parçaları: taş kule, ahşap sundurma, çadır
 * dizisi, ocak ateşi, kuyu, çukur vb. Binalar bunlardan kurulur; böylece
 * 32 yapı tutarlı bir görsel dil paylaşır.
 */

import * as iso from './iso.mjs';
import { PALETTE as P } from './palette.mjs';
import { damga, kocboynuzu } from './ornament.mjs';

const { project, shade, TILE_W } = iso;
const fmt = (n) => Math.round(n * 100) / 100;

/** Taş kule: silindir gövde + mazgallı tepe. */
export function stoneTower({ w, d, z = 0, h = 90, color = P.tas, bands = 3, merlons = 6, merlonColor }) {
  const [cx, cy] = project(w / 2, d / 2, z);
  const rx = (w + d) * (TILE_W / 4) * 0.46;
  const parts = [iso.cylinder({ w, d, h, z, color, bands, fill: 0.46 })];

  const topY = cy - h;
  const mc = merlonColor ?? shade(color, 0.1);
  for (let i = 0; i < merlons; i++) {
    const a = (i / merlons) * Math.PI * 2;
    const px = cx + Math.cos(a) * rx;
    const py = topY + Math.sin(a) * rx * 0.5;
    const lum = Math.cos(a) > 0 ? -0.28 : 0.14;
    parts.push(`<rect x="${fmt(px - 7)}" y="${fmt(py - 16)}" width="14" height="18" rx="2"
        fill="${shade(mc, lum)}"/>`);
  }

  return { svg: `<g>${parts.join('')}</g>`, cx, cy, topY, rx };
}

/** Ahşap sundurma / baraka: eğimli çatılı basit yapı. */
export function woodShed({ w, d, z = 0, h = 34, roofH = 34, body = P.ahsap, roof = P.ahsapKoyu }) {
  return `
    <g>
      ${iso.box({ w, d, h, z, color: body })}
      ${iso.tentRoof({ w, d, h: roofH, z: z + h, color: roof, overhang: 0.14 })}
    </g>`;
}

/** Küçük keçe çadır (ordugâh, kışla için). */
export function smallTent({ x = 0, y = 0, z = 0, size = 1.1, felt = P.kece, trim = P.kizil }) {
  const yy = iso.yurt({
    w: size, d: size, z,
    wallH: 22, roofH: 30, felt, roof: shade(felt, -0.06),
    trim, crown: P.ahsapKoyu, seams: 12, fill: 0.5,
  });
  const [ox, oy] = project(x, y, 0);
  return `<g transform="translate(${fmt(ox)},${fmt(oy)})">${yy.svg}</g>`;
}

/** Ocak ateşi: alev dilleri + ışık halesi. */
export function fire({ cx, cy, scale = 1, glow = true }) {
  const s = scale;
  return `
    <g transform="translate(${fmt(cx)},${fmt(cy)})">
      ${glow ? `<ellipse cx="0" cy="0" rx="${fmt(34 * s)}" ry="${fmt(20 * s)}" fill="url(#atesIsik)"/>` : ''}
      <path d="M0,${fmt(-26 * s)} C${fmt(-13 * s)},${fmt(-13 * s)} ${fmt(-11 * s)},${fmt(-3 * s)} 0,${fmt(2 * s)}
               C${fmt(11 * s)},${fmt(-3 * s)} ${fmt(13 * s)},${fmt(-13 * s)} 0,${fmt(-26 * s)} Z"
        fill="${P.ates}"/>
      <path d="M0,${fmt(-16 * s)} C${fmt(-7 * s)},${fmt(-8 * s)} ${fmt(-6 * s)},${fmt(-1 * s)} 0,${fmt(1 * s)}
               C${fmt(6 * s)},${fmt(-1 * s)} ${fmt(7 * s)},${fmt(-8 * s)} 0,${fmt(-16 * s)} Z"
        fill="${P.atesIc}"/>
      <!-- Odunlar -->
      <path d="M${fmt(-14 * s)},${fmt(3 * s)} L${fmt(14 * s)},${fmt(-2 * s)}
               M${fmt(-13 * s)},${fmt(-2 * s)} L${fmt(14 * s)},${fmt(3 * s)}"
        stroke="${P.ahsapKoyu}" stroke-width="${fmt(4 * s)}" stroke-linecap="round"/>
    </g>`;
}

/** Büyü ışığı: yükselen parçacıklar. */
export function magicGlow({ cx, cy, scale = 1, color = P.buyu }) {
  const s = scale;
  const motes = [];
  for (let i = 0; i < 7; i++) {
    const a = (i / 7) * Math.PI * 2;
    const r = 16 * s + (i % 3) * 7 * s;
    motes.push(`<circle cx="${fmt(cx + Math.cos(a) * r)}" cy="${fmt(cy - 14 * s - (i % 4) * 9 * s)}"
        r="${fmt((1.6 + (i % 3) * 0.7) * s)}" fill="${color}" opacity="${0.5 + (i % 3) * 0.16}"/>`);
  }
  return `
    <g>
      <ellipse cx="${fmt(cx)}" cy="${fmt(cy)}" rx="${fmt(40 * s)}" ry="${fmt(22 * s)}" fill="url(#buyuIsik)"/>
      ${motes.join('')}
    </g>`;
}

/** Taş baba (balbal) — kurganların başında duran atalar heykeli. */
export function stoneBaba({ cx, cy, h = 44, color = P.tas }) {
  return `
    <g>
      <path d="M${fmt(cx - 9)},${fmt(cy)} L${fmt(cx - 8)},${fmt(cy - h + 14)}
               Q${fmt(cx)},${fmt(cy - h - 2)} ${fmt(cx + 8)},${fmt(cy - h + 14)}
               L${fmt(cx + 9)},${fmt(cy)} Z" fill="${color}"/>
      <path d="M${fmt(cx - 9)},${fmt(cy)} L${fmt(cx - 8)},${fmt(cy - h + 14)}
               Q${fmt(cx - 4)},${fmt(cy - h + 2)} ${fmt(cx)},${fmt(cy - h + 1)}
               L${fmt(cx)},${fmt(cy)} Z" fill="${shade(color, 0.16)}"/>
      <!-- yüz -->
      <circle cx="${fmt(cx - 3.4)}" cy="${fmt(cy - h + 20)}" r="1.7" fill="${shade(color, -0.5)}"/>
      <circle cx="${fmt(cx + 3.4)}" cy="${fmt(cy - h + 20)}" r="1.7" fill="${shade(color, -0.5)}"/>
      <path d="M${fmt(cx - 4)},${fmt(cy - h + 27)} q4,3 8,0" stroke="${shade(color, -0.45)}"
        stroke-width="1.5" fill="none"/>
      <!-- elinde kadeh -->
      <path d="M${fmt(cx - 6)},${fmt(cy - h * 0.45)} h12" stroke="${shade(color, -0.35)}"
        stroke-width="2.4" stroke-linecap="round"/>
    </g>`;
}

/** Kaynak yığını: altın, tahıl çuvalı, demir külçe. */
export function resourcePile({ cx, cy, kind = 'gold', scale = 1 }) {
  const s = scale;
  if (kind === 'gold') {
    const coins = [];
    for (let i = 0; i < 9; i++) {
      const px = cx + ((i % 3) - 1) * 11 * s + (i > 5 ? 5 * s : 0);
      const py = cy - Math.floor(i / 3) * 5 * s;
      coins.push(`<ellipse cx="${fmt(px)}" cy="${fmt(py)}" rx="${fmt(7 * s)}" ry="${fmt(3.6 * s)}"
          fill="${P.gold}" stroke="${shade(P.gold, -0.35)}" stroke-width="0.8"/>`);
    }
    return `<g>${coins.join('')}</g>`;
  }
  if (kind === 'grain') {
    return `<g>
      ${[0, 1, 2].map((i) => {
        const px = cx + (i - 1) * 15 * s;
        const py = cy - (i === 1 ? 7 * s : 0);
        return `<path d="M${fmt(px - 9 * s)},${fmt(py)} q${fmt(9 * s)},${fmt(-22 * s)} ${fmt(18 * s)},0 Z"
          fill="${P.grain}" stroke="${shade(P.grain, -0.3)}" stroke-width="0.9"/>`;
      }).join('')}
    </g>`;
  }
  // demir külçe
  return `<g>
    ${[0, 1, 2, 3].map((i) => {
      const px = cx + ((i % 2) - 0.5) * 18 * s;
      const py = cy - Math.floor(i / 2) * 9 * s;
      return `<path d="M${fmt(px - 11 * s)},${fmt(py)} l${fmt(3 * s)},${fmt(-7 * s)}
          h${fmt(16 * s)} l${fmt(3 * s)},${fmt(7 * s)} Z"
        fill="${P.iron}" stroke="${shade(P.iron, -0.35)}" stroke-width="0.9"/>`;
    }).join('')}
  </g>`;
}

/** Kaya kümesi — mağara, maden, in girişleri için. */
export function rocks({ cx, cy, scale = 1, color = P.kaya, count = 5 }) {
  const parts = [];
  for (let i = 0; i < count; i++) {
    const a = (i / count) * Math.PI * 2 + 0.7;
    const px = cx + Math.cos(a) * 26 * scale;
    const py = cy + Math.sin(a) * 13 * scale;
    const r = (10 + (i % 3) * 5) * scale;
    parts.push(`<path d="M${fmt(px - r)},${fmt(py)} l${fmt(r * 0.45)},${fmt(-r * 0.9)}
        l${fmt(r * 1.1)},${fmt(-r * 0.12)} l${fmt(r * 0.45)},${fmt(r * 1.02)} Z"
      fill="${shade(color, Math.cos(a) > 0 ? -0.22 : 0.12)}"/>`);
  }
  return `<g>${parts.join('')}</g>`;
}

/** Ağaç — orman biyomu ve süs için. */
export function tree({ cx, cy, scale = 1, kind = 'cam' }) {
  const s = scale;
  if (kind === 'cam') {
    return `<g>
      <rect x="${fmt(cx - 2.6 * s)}" y="${fmt(cy - 14 * s)}" width="${fmt(5.2 * s)}" height="${fmt(15 * s)}"
        fill="${P.ahsapKoyu}"/>
      ${[0, 1, 2].map((i) => {
        const yy = cy - 14 * s - i * 13 * s;
        const r = (20 - i * 5) * s;
        return `<path d="M${fmt(cx - r)},${fmt(yy)} L${fmt(cx)},${fmt(yy - 22 * s)} L${fmt(cx + r)},${fmt(yy)} Z"
          fill="${shade(P.cimKoyu, i * 0.1)}"/>`;
      }).join('')}
    </g>`;
  }
  return `<g>
    <rect x="${fmt(cx - 3 * s)}" y="${fmt(cy - 16 * s)}" width="${fmt(6 * s)}" height="${fmt(17 * s)}"
      fill="${P.ahsapKoyu}"/>
    <ellipse cx="${fmt(cx)}" cy="${fmt(cy - 26 * s)}" rx="${fmt(22 * s)}" ry="${fmt(17 * s)}" fill="${P.cimKoyu}"/>
    <ellipse cx="${fmt(cx - 7 * s)}" cy="${fmt(cy - 31 * s)}" rx="${fmt(13 * s)}" ry="${fmt(10 * s)}" fill="${P.cim}"/>
  </g>`;
}

/** Silah standı — kışla ve demirci için. */
export function weaponRack({ cx, cy, scale = 1 }) {
  const s = scale;
  return `<g>
    <rect x="${fmt(cx - 20 * s)}" y="${fmt(cy - 3 * s)}" width="${fmt(40 * s)}" height="${fmt(4 * s)}"
      fill="${P.ahsapKoyu}"/>
    ${[-14, -5, 4, 13].map((dx, i) => `
      <path d="M${fmt(cx + dx * s)},${fmt(cy - 3 * s)} l0,${fmt(-26 * s)}"
        stroke="${i % 2 ? P.ahsap : P.demir}" stroke-width="${fmt(2.6 * s)}" stroke-linecap="round"/>
      ${i % 2 === 0 ? `<path d="M${fmt(cx + dx * s - 6 * s)},${fmt(cy - 24 * s)} h${fmt(12 * s)}"
        stroke="${P.demir}" stroke-width="${fmt(2.2 * s)}"/>` : ''}
    `).join('')}
  </g>`;
}
