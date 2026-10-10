/**
 * Asker ve kahraman sprite'ları.
 *
 * Askerler izometrik değil, hafif üstten bakışla çizilir (Unity'de
 * billboard olarak kullanılır ve yöne göre yatay çevrilir). Hepsi aynı
 * gövde iskeletinden türetilir; böylece ordu tek bir çizim dili konuşur.
 */

import { PALETTE as P } from './lib/palette.mjs';
import { shade } from './lib/iso.mjs';
import { damga } from './lib/ornament.mjs';
import { wrapFlat } from './lib/render.mjs';

const W = 96, H = 112;         // sprite tuvali
const GX = W / 2, GY = H - 10; // ayak noktası
const f = (n) => Math.round(n * 100) / 100;

/* ------------------------------------------------------------------ */
/* İnsan gövdesi iskeleti                                              */
/* ------------------------------------------------------------------ */

/**
 * @param {object} o
 * @param {number} o.h       boy (piksel)
 * @param {string} o.skin    ten rengi
 * @param {string} o.cloth   kaftan rengi
 * @param {string} o.trim    kenar süsü
 * @param {string} o.armor   zırh rengi (yoksa null)
 * @param {string} o.hat     başlık türü: 'bork' | 'migfer' | 'kam' | 'yok'
 */
function warrior({
  h = 62, skin = '#e6b98f', cloth = P.kizil, trim = P.altin,
  armor = null, hat = 'bork', hatColor = null, beard = null,
  cape = null, scale = 1,
}) {
  const bodyH = h * 0.52;
  const headR = h * 0.17;
  const hipY = GY - h * 0.40;
  const shoulderY = GY - h * 0.78;
  const headY = GY - h * 0.95 - headR * 0.4;
  const hc = hatColor ?? shade(cloth, -0.25);

  const parts = [];

  // Pelerin (arkada)
  if (cape) {
    parts.push(`<path d="M${f(GX - 14)},${f(shoulderY + 2)}
      q-9,22 -5,42 l38,0 q4,-20 -5,-42 Z" fill="${cape}" opacity="0.95"/>`);
  }

  // Bacaklar
  parts.push(`<path d="M${f(GX - 8)},${f(hipY)} l-2,${f(h * 0.38)} l8,0 l2,${f(-h * 0.38)} Z"
      fill="${shade(cloth, -0.35)}"/>`);
  parts.push(`<path d="M${f(GX + 2)},${f(hipY)} l2,${f(h * 0.38)} l8,0 l-2,${f(-h * 0.38)} Z"
      fill="${shade(cloth, -0.22)}"/>`);
  // Çizmeler
  parts.push(`<path d="M${f(GX - 12)},${f(GY - 3)} h12 v5 h-14 Z" fill="${P.ahsapKoyu}"/>`);
  parts.push(`<path d="M${f(GX + 2)},${f(GY - 3)} h12 v5 h-14 Z" fill="${shade(P.ahsapKoyu, 0.12)}"/>`);

  // Gövde (kaftan)
  parts.push(`<path d="M${f(GX - 13)},${f(shoulderY)}
      q13,-5 26,0 l3,${f(bodyH)} q-16,6 -32,0 Z" fill="${cloth}"/>`);
  // Kaftan kenarı
  parts.push(`<path d="M${f(GX)},${f(shoulderY - 2)} l0,${f(bodyH + 4)}"
      stroke="${trim}" stroke-width="2.4"/>`);
  parts.push(`<path d="M${f(GX - 14)},${f(shoulderY + bodyH * 0.62)} q14,6 28,0"
      stroke="${trim}" stroke-width="3" fill="none"/>`);

  // Zırh
  if (armor) {
    parts.push(`<path d="M${f(GX - 12)},${f(shoulderY + 3)} q12,-4 24,0 l1,${f(bodyH * 0.55)}
        q-13,5 -26,0 Z" fill="${armor}" opacity="0.95"/>`);
    parts.push(`<circle cx="${f(GX)}" cy="${f(shoulderY + bodyH * 0.25)}" r="4" fill="${trim}"/>`);
  }

  // Kollar
  parts.push(`<path d="M${f(GX - 13)},${f(shoulderY + 3)} q-8,10 -7,24 l7,2 q2,-14 6,-22 Z"
      fill="${shade(cloth, -0.12)}"/>`);
  parts.push(`<path d="M${f(GX + 13)},${f(shoulderY + 3)} q8,10 7,24 l-7,2 q-2,-14 -6,-22 Z"
      fill="${shade(cloth, 0.08)}"/>`);

  // Baş
  parts.push(`<ellipse cx="${f(GX)}" cy="${f(headY)}" rx="${f(headR)}" ry="${f(headR * 1.08)}"
      fill="${skin}"/>`);
  parts.push(`<ellipse cx="${f(GX - headR * 0.35)}" cy="${f(headY)}" rx="${f(headR * 0.55)}"
      ry="${f(headR * 0.9)}" fill="${shade(skin, 0.1)}" opacity="0.5"/>`);
  // Gözler
  parts.push(`<circle cx="${f(GX - 3.4)}" cy="${f(headY + 1)}" r="1.5" fill="#2b1a10"/>`);
  parts.push(`<circle cx="${f(GX + 3.4)}" cy="${f(headY + 1)}" r="1.5" fill="#2b1a10"/>`);

  if (beard) {
    parts.push(`<path d="M${f(GX - headR * 0.8)},${f(headY + headR * 0.35)}
        q${f(headR * 0.8)},${f(headR * 1.5)} ${f(headR * 1.6)},0
        q${f(-headR * 0.8)},${f(headR * 0.6)} ${f(-headR * 1.6)},0 Z" fill="${beard}"/>`);
  }

  // Başlık
  if (hat === 'bork') {
    // Börk: Türk keçe külahı
    parts.push(`<path d="M${f(GX - headR * 1.08)},${f(headY - headR * 0.5)}
        q${f(headR * 1.08)},${f(-headR * 1.9)} ${f(headR * 2.16)},0 Z" fill="${hc}"/>`);
    parts.push(`<path d="M${f(GX - headR * 1.2)},${f(headY - headR * 0.5)}
        h${f(headR * 2.4)} v3.4 h${f(-headR * 2.4)} Z" fill="${trim}"/>`);
  } else if (hat === 'migfer') {
    // Miğfer: tepeliği ve burunluğu olan konik başlık
    parts.push(`<path d="M${f(GX - headR * 1.05)},${f(headY - headR * 0.3)}
        q${f(headR * 1.05)},${f(-headR * 2.2)} ${f(headR * 2.1)},0 Z" fill="${P.demir}"/>`);
    parts.push(`<path d="M${f(GX)},${f(headY - headR * 2.1)} l0,-7" stroke="${P.demirKoyu}"
        stroke-width="2.4"/>`);
    parts.push(`<path d="M${f(GX)},${f(headY - headR * 0.6)} l0,${f(headR * 1.1)}"
        stroke="${P.demirKoyu}" stroke-width="2.2"/>`);
  } else if (hat === 'kam') {
    // Kam başlığı: tüyler ve boncuklar
    parts.push(`<path d="M${f(GX - headR * 1.1)},${f(headY - headR * 0.4)}
        q${f(headR * 1.1)},${f(-headR * 1.3)} ${f(headR * 2.2)},0 Z" fill="${hc}"/>`);
    [-1, 0, 1].forEach((i) => parts.push(`<path d="M${f(GX + i * 5)},${f(headY - headR * 1.1)}
        q${f(i * 3)},-14 ${f(i * 6)},-20" stroke="${P.kece}" stroke-width="2"
        fill="none" stroke-linecap="round"/>`));
  }

  return { parts, headY, headR, shoulderY, hipY, bodyH };
}

/** Sprite'ı tuvale sarar ve yere gölge koyar. */
function sprite(inner, { shadowR = 17, glow = null } = {}) {
  return wrapFlat({
    width: W, height: H,
    body: `
      <ellipse cx="${GX}" cy="${GY + 2}" rx="${shadowR}" ry="${f(shadowR * 0.36)}"
        fill="#1b1206" opacity="0.3" filter="url(#hafifYumusak)"/>
      ${glow ? `<ellipse cx="${GX}" cy="${f(GY - 26)}" rx="30" ry="34" fill="url(#${glow})"/>` : ''}
      ${inner}
    `,
  });
}

/* ------------------------------------------------------------------ */
/* Silahlar                                                            */
/* ------------------------------------------------------------------ */

const sword = (x, y, len = 30, color = P.demir) => `
  <g transform="translate(${f(x)},${f(y)}) rotate(-24)">
    <rect x="-2" y="${f(-len)}" width="4" height="${f(len)}" rx="1.6" fill="${color}"/>
    <path d="M-2,${f(-len)} l2,-6 l2,6 Z" fill="${shade(color, 0.25)}"/>
    <rect x="-7" y="0" width="14" height="3.4" rx="1.6" fill="${P.altinKoyu}"/>
    <rect x="-2" y="3" width="4" height="9" rx="1.6" fill="${P.ahsapKoyu}"/>
  </g>`;

const bow = (x, y, size = 24) => `
  <g transform="translate(${f(x)},${f(y)})">
    <path d="M0,${f(-size)} q${f(size * 0.75)},${f(size)} 0,${f(size * 2)}"
      stroke="${P.ahsapKoyu}" stroke-width="3" fill="none"/>
    <path d="M0,${f(-size)} L0,${f(size)}" stroke="${P.kece}" stroke-width="1.4"/>
    <path d="M-3,0 h${f(size * 0.9)}" stroke="${P.ahsap}" stroke-width="1.8"/>
    <path d="M${f(size * 0.9)},0 l-4,-3 l0,6 Z" fill="${P.demir}"/>
  </g>`;

const mace = (x, y) => `
  <g transform="translate(${f(x)},${f(y)}) rotate(18)">
    <rect x="-2.2" y="-26" width="4.4" height="30" rx="2" fill="${P.ahsap}"/>
    <circle cx="0" cy="-30" r="8" fill="${P.demirKoyu}"/>
    ${[0, 1, 2, 3, 4].map((i) => {
      const a = (i / 5) * Math.PI * 2;
      return `<path d="M${f(Math.cos(a) * 7)},${f(-30 + Math.sin(a) * 7)}
        l${f(Math.cos(a) * 5)},${f(Math.sin(a) * 5)}" stroke="${P.demir}" stroke-width="3"
        stroke-linecap="round"/>`;
    }).join('')}
  </g>`;

const spear = (x, y, len = 46) => `
  <g transform="translate(${f(x)},${f(y)}) rotate(-12)">
    <rect x="-1.8" y="${f(-len)}" width="3.6" height="${f(len + 10)}" rx="1.8" fill="${P.ahsap}"/>
    <path d="M-5,${f(-len)} l5,-12 l5,12 Z" fill="${P.demir}"/>
    <path d="M-4,${f(-len + 2)} h8" stroke="${P.altinKoyu}" stroke-width="2"/>
  </g>`;

/* ------------------------------------------------------------------ */
/* Yaya ve atlı askerler                                               */
/* ------------------------------------------------------------------ */

export const ceri = () => {
  const b = warrior({ h: 58, cloth: '#9c6b3f', trim: P.kum, hat: 'bork', hatColor: '#6f4a28' });
  return sprite(`${b.parts.join('')}${sword(GX + 20, b.shoulderY + 26)}`);
};

export const okcu_kiz = () => {
  const b = warrior({ h: 56, cloth: '#3f6b4a', trim: P.altin, hat: 'bork', hatColor: '#2d4c35' });
  return sprite(`
    ${b.parts.join('')}
    <!-- Örgülü saç -->
    <path d="M${f(GX - 9)},${f(b.headY + 2)} q-7,14 -2,24" stroke="#3b2414"
      stroke-width="4" fill="none" stroke-linecap="round"/>
    <!-- Sadak -->
    <rect x="${f(GX + 10)}" y="${f(b.shoulderY + 4)}" width="8" height="18" rx="2.6" fill="${P.ahsapKoyu}"/>
    ${[0, 1, 2].map(i => `<path d="M${f(GX + 12 + i * 2.4)},${f(b.shoulderY + 4)} l0,-8"
      stroke="${P.kece}" stroke-width="1.4"/>`).join('')}
    ${bow(GX - 22, b.shoulderY + 18, 20)}
  `);
};

export const gurzcu = () => {
  const b = warrior({ h: 64, cloth: '#7a4a2e', trim: P.demirKoyu, armor: P.demir,
    hat: 'migfer', beard: '#3b2414' });
  return sprite(`${b.parts.join('')}${mace(GX + 22, b.shoulderY + 24)}`, { shadowR: 19 });
};

export const alp = () => {
  const b = warrior({ h: 66, cloth: P.lacivert, trim: P.altin, armor: P.demir,
    hat: 'migfer', cape: P.kizilKoyu, beard: '#2e1c10' });
  return sprite(`
    ${b.parts.join('')}
    ${sword(GX + 23, b.shoulderY + 24, 34)}
    <!-- Kalkan -->
    <g transform="translate(${f(GX - 22)},${f(b.shoulderY + 24)})">
      <ellipse cx="0" cy="0" rx="12" ry="14" fill="${P.ahsap}" stroke="${P.demirKoyu}" stroke-width="2"/>
      ${damga({ cx: 0, cy: 0, key: 'kurt', size: 0.5, color: P.altin, width: 2.6 })}
    </g>
  `, { shadowR: 20 });
};

export const baksi = () => {
  const b = warrior({ h: 60, cloth: '#5a4a7a', trim: P.kut, hat: 'kam',
    hatColor: '#3f3458', beard: '#d8d2c4' });
  return sprite(`
    ${b.parts.join('')}
    <!-- Davul -->
    <g transform="translate(${f(GX - 22)},${f(b.shoulderY + 22)})">
      <ellipse cx="0" cy="0" rx="13" ry="14" fill="${P.kece}" stroke="${P.ahsapKoyu}" stroke-width="2.6"/>
      ${damga({ cx: 0, cy: 0, key: 'agac', size: 0.5, color: P.kizil, width: 2.4 })}
    </g>
    <!-- Tokmak -->
    <path d="M${f(GX + 16)},${f(b.shoulderY + 20)} l10,-12" stroke="${P.ahsap}"
      stroke-width="3" stroke-linecap="round"/>
  `, { glow: 'buyuIsik' });
};

/* ------------------------------------------------------------------ */
/* Hayvanlar ve canavarlar                                             */
/* ------------------------------------------------------------------ */

/**
 * Dört ayaklı gövde (börü/kurt). Yandan görünüm:
 * sol tarafta baş, sağda kuyruk, altta dört bacak.
 */
function beast({ color = '#6b6b73', belly = '#9a9aa3', h = 34, len = 58, maneColor = null }) {
  const footY = GY - 2;
  const bodyY = footY - h * 0.52;      // gövde ekseni
  const bodyR = h * 0.34;              // gövde kalınlığı
  const headX = GX - len * 0.40;
  const headY = bodyY - h * 0.30;
  const tailX = GX + len * 0.44;
  const dark = shade(color, -0.22);
  const light = shade(color, 0.14);

  return `
    <!-- Arka bacaklar (koyu) -->
    ${[len * 0.10, len * 0.30].map((dx) => `
      <path d="M${f(GX + dx)},${f(bodyY + bodyR * 0.4)}
        q${f(-2)},${f(h * 0.3)} 1,${f(h * 0.5)} l5,0
        q${f(2)},${f(-h * 0.5)} 0,${f(-h * 0.8)} Z" fill="${dark}"/>`).join('')}
    ${[-len * 0.30, -len * 0.12].map((dx) => `
      <path d="M${f(GX + dx)},${f(bodyY + bodyR * 0.4)}
        q${f(2)},${f(h * 0.3)} -1,${f(h * 0.5)} l-5,0
        q${f(-2)},${f(-h * 0.5)} 0,${f(-h * 0.8)} Z" fill="${dark}"/>`).join('')}

    <!-- Gövde -->
    <path d="M${f(headX + 6)},${f(bodyY)}
      C${f(headX + 10)},${f(bodyY - bodyR * 1.5)} ${f(GX + len * 0.2)},${f(bodyY - bodyR * 1.45)}
       ${f(tailX - 4)},${f(bodyY - bodyR * 0.4)}
      C${f(tailX)},${f(bodyY + bodyR * 0.7)} ${f(GX)},${f(bodyY + bodyR * 1.5)}
       ${f(headX + 6)},${f(bodyY + bodyR * 0.6)} Z" fill="${color}"/>
    <!-- Karın -->
    <path d="M${f(headX + 14)},${f(bodyY + bodyR * 0.55)}
      q${f(len * 0.3)},${f(bodyR * 0.8)} ${f(len * 0.6)},${f(-bodyR * 0.1)}
      q${f(-len * 0.3)},${f(bodyR * 0.5)} ${f(-len * 0.6)},${f(bodyR * 0.1)} Z"
      fill="${belly}" opacity="0.8"/>

    <!-- Ön bacaklar (açık) -->
    ${[-len * 0.22, -len * 0.04].map((dx) => `
      <path d="M${f(GX + dx)},${f(bodyY + bodyR * 0.5)}
        q${f(1)},${f(h * 0.3)} -1,${f(h * 0.5)} l5,0
        q${f(1)},${f(-h * 0.5)} 1,${f(-h * 0.8)} Z" fill="${light}"/>`).join('')}

    <!-- Boyun ve baş -->
    <path d="M${f(headX + 12)},${f(bodyY - bodyR * 0.8)}
      q${f(-10)},${f(-h * 0.1)} ${f(-14)},${f(h * 0.25)}
      q${f(2)},${f(h * 0.3)} ${f(14)},${f(h * 0.2)} Z" fill="${light}"/>
    <ellipse cx="${f(headX)}" cy="${f(headY)}" rx="${f(h * 0.30)}" ry="${f(h * 0.26)}" fill="${light}"/>
    <!-- Burun -->
    <path d="M${f(headX - h * 0.26)},${f(headY + h * 0.02)}
      q${f(-h * 0.26)},${f(h * 0.05)} ${f(-h * 0.30)},${f(h * 0.16)}
      q${f(h * 0.2)},${f(h * 0.1)} ${f(h * 0.34)},${f(-h * 0.02)} Z" fill="${color}"/>
    <circle cx="${f(headX - h * 0.52)}" cy="${f(headY + h * 0.14)}" r="${f(h * 0.055)}" fill="#1b1206"/>
    <!-- Ağız -->
    <path d="M${f(headX - h * 0.48)},${f(headY + h * 0.22)} l${f(h * 0.18)},${f(h * 0.03)}"
      stroke="#1b1206" stroke-width="1.3" stroke-linecap="round"/>
    <!-- Göz -->
    <ellipse cx="${f(headX - h * 0.08)}" cy="${f(headY - h * 0.02)}"
      rx="${f(h * 0.075)}" ry="${f(h * 0.055)}" fill="#ffd166"/>
    <circle cx="${f(headX - h * 0.08)}" cy="${f(headY - h * 0.02)}" r="${f(h * 0.028)}" fill="#1b1206"/>
    <!-- Kulaklar -->
    <path d="M${f(headX - h * 0.06)},${f(headY - h * 0.22)} l${f(-h * 0.04)},${f(-h * 0.26)}
      l${f(h * 0.17)},${f(h * 0.14)} Z" fill="${dark}"/>
    <path d="M${f(headX + h * 0.16)},${f(headY - h * 0.19)} l${f(h * 0.05)},${f(-h * 0.24)}
      l${f(h * 0.11)},${f(h * 0.2)} Z" fill="${dark}"/>

    ${maneColor ? `<path d="M${f(headX + 8)},${f(bodyY - bodyR * 1.05)}
      q${f(len * 0.16)},${f(-h * 0.22)} ${f(len * 0.3)},${f(h * 0.02)}
      q${f(-len * 0.15)},${f(h * 0.16)} ${f(-len * 0.3)},${f(-h * 0.02)} Z"
      fill="${maneColor}"/>` : ''}

    <!-- Kuyruk -->
    <path d="M${f(tailX - 6)},${f(bodyY - bodyR * 0.3)}
      q${f(h * 0.5)},${f(-h * 0.25)} ${f(h * 0.62)},${f(-h * 0.7)}
      q${f(-h * 0.1)},${f(h * 0.75)} ${f(-h * 0.45)},${f(h * 0.95)} Z" fill="${dark}"/>
  `;
}

export const boru = () => sprite(beast({ color: '#7d8491', belly: '#b9bfc8', h: 32, len: 58,
  maneColor: '#5e6572' }), { shadowR: 22 });

export const kurt_binici = () => {
  const b = warrior({ h: 42, cloth: '#8a3b2e', trim: P.altin, hat: 'bork', hatColor: '#5e2b21' });
  // Binici, kurdun üstünde
  const lifted = b.parts.join('').replaceAll(`${GY}`, `${GY - 30}`);
  return wrapFlat({
    width: W, height: H,
    body: `
      <ellipse cx="${GX}" cy="${GY + 2}" rx="24" ry="9" fill="#1b1206" opacity="0.3"
        filter="url(#hafifYumusak)"/>
      ${beast({ color: '#6f7784', belly: '#a8aeb8', h: 34, len: 62, maneColor: '#565d69' })}
      <g transform="translate(0,-26)">${b.parts.join('')}</g>
      <g transform="translate(0,-26)">${spear(GX + 20, GY - 18, 40)}</g>
    `,
  });
};

export const tepegoz = () => {
  const b = warrior({ h: 86, skin: '#c9a06f', cloth: '#6b5a3f', trim: '#4a3c28',
    hat: 'yok', scale: 1.2 });
  return wrapFlat({
    width: W, height: H,
    body: `
      <ellipse cx="${GX}" cy="${GY + 2}" rx="24" ry="9" fill="#1b1206" opacity="0.34"
        filter="url(#hafifYumusak)"/>
      <g transform="translate(0,6) scale(1.12) translate(${f(-GX * 0.107)},${f(-GY * 0.107)})">
        ${b.parts.join('')}
        <!-- Tek göz -->
        <circle cx="${f(GX)}" cy="${f(b.headY)}" r="7" fill="#fff8e6"/>
        <circle cx="${f(GX)}" cy="${f(b.headY)}" r="3.4" fill="#8e1f1f"/>
        <circle cx="${f(GX - 1.2)}" cy="${f(b.headY - 1.2)}" r="1.2" fill="#fff"/>
        <!-- Sopa -->
        <g transform="translate(${f(GX + 24)},${f(b.shoulderY + 30)}) rotate(16)">
          <rect x="-4" y="-40" width="8" height="48" rx="4" fill="${P.ahsapKoyu}"/>
          <circle cx="0" cy="-44" r="10" fill="${P.ahsap}"/>
        </g>
      </g>`,
  });
};

export const mancinik_arabasi = () => wrapFlat({
  width: W, height: H,
  body: `
    <ellipse cx="${GX}" cy="${GY + 2}" rx="30" ry="11" fill="#1b1206" opacity="0.34"
      filter="url(#hafifYumusak)"/>
    <!-- Tekerlekler -->
    ${[-20, 20].map((dx) => `
      <circle cx="${f(GX + dx)}" cy="${f(GY - 10)}" r="11" fill="${P.ahsapKoyu}"/>
      <circle cx="${f(GX + dx)}" cy="${f(GY - 10)}" r="4" fill="${P.ahsap}"/>
      ${[0, 1, 2, 3].map(i => {
        const a = (i / 4) * Math.PI;
        return `<path d="M${f(GX + dx - Math.cos(a) * 10)},${f(GY - 10 - Math.sin(a) * 10)}
          L${f(GX + dx + Math.cos(a) * 10)},${f(GY - 10 + Math.sin(a) * 10)}"
          stroke="${P.ahsap}" stroke-width="2"/>`;
      }).join('')}`).join('')}
    <!-- Gövde -->
    <path d="M${f(GX - 30)},${f(GY - 20)} h60 l-5,12 h-50 Z" fill="${P.ahsap}"/>
    <!-- Atış kolu -->
    <g transform="translate(${f(GX - 6)},${f(GY - 22)}) rotate(-42)">
      <rect x="-4" y="-50" width="8" height="54" rx="4" fill="${P.ahsapKoyu}"/>
      <path d="M-11,-56 q11,8 22,0 q-11,14 -22,0 Z" fill="${P.ahsap}"/>
      <circle cx="0" cy="-58" r="8" fill="${P.kaya}"/>
    </g>
    <path d="M${f(GX - 24)},${f(GY - 22)} q18,-18 36,-6" stroke="${P.kum}"
      stroke-width="2.2" fill="none"/>`,
});

/* ------------------------------------------------------------------ */
/* Hava birimleri                                                      */
/* ------------------------------------------------------------------ */

/** Kanat çifti. */
function wings({ cy, span = 34, color, back = null }) {
  const b = back ?? shade(color, -0.22);
  return `
    <path d="M${f(GX - 6)},${f(cy)} q${f(-span * 0.6)},-16 ${f(-span)},-4
             q${f(span * 0.3)},16 ${f(span * 0.9)},12 Z" fill="${b}"/>
    <path d="M${f(GX + 6)},${f(cy)} q${f(span * 0.6)},-16 ${f(span)},-4
             q${f(-span * 0.3)},16 ${f(-span * 0.9)},12 Z" fill="${color}"/>`;
}

export const togrul = () => sprite(`
  <g transform="translate(0,-16)">
    ${wings({ cy: GY - 30, span: 30, color: '#5a4a6e' })}
    <ellipse cx="${GX}" cy="${f(GY - 28)}" rx="11" ry="14" fill="#4a3c5e"/>
    <ellipse cx="${GX}" cy="${f(GY - 38)}" rx="7.5" ry="7" fill="#584a6c"/>
    <path d="M${f(GX + 6)},${f(GY - 38)} l9,3 l-9,3 Z" fill="${P.altin}"/>
    <circle cx="${f(GX + 2.6)}" cy="${f(GY - 40)}" r="1.9" fill="#ffd166"/>
    <path d="M${f(GX - 3)},${f(GY - 15)} l-2,7 M${f(GX + 3)},${f(GY - 15)} l2,7"
      stroke="${P.altinKoyu}" stroke-width="2" stroke-linecap="round"/>
  </g>`, { shadowR: 13 });

export const konrul = () => sprite(`
  <g transform="translate(0,-14)">
    ${wings({ cy: GY - 36, span: 42, color: '#8a6a3c', back: '#6b4f2a' })}
    <ellipse cx="${GX}" cy="${f(GY - 32)}" rx="14" ry="19" fill="#7a5c32"/>
    <path d="M${f(GX - 13)},${f(GY - 30)} q13,10 26,0 q-13,16 -26,0 Z" fill="${P.kece}" opacity="0.4"/>
    <!-- Çift baş -->
    ${[-9, 9].map((dx) => `
      <ellipse cx="${f(GX + dx)}" cy="${f(GY - 50)}" rx="7" ry="7.5" fill="#8a6a3c"/>
      <path d="M${f(GX + dx + (dx < 0 ? -6 : 6))},${f(GY - 50)} l${f(dx < 0 ? -8 : 8)},3
        l${f(dx < 0 ? 8 : -8)},3 Z" fill="${P.altin}"/>
      <circle cx="${f(GX + dx + (dx < 0 ? -2 : 2))}" cy="${f(GY - 52)}" r="1.8" fill="#ffd166"/>`).join('')}
    <!-- Pençeler -->
    <path d="M${f(GX - 5)},${f(GY - 14)} l-3,8 M${f(GX + 5)},${f(GY - 14)} l3,8"
      stroke="${P.altinKoyu}" stroke-width="2.4" stroke-linecap="round"/>
  </g>`, { shadowR: 20 });

export const zumrudu_anka = () => sprite(`
  <g transform="translate(0,-14)">
    ${wings({ cy: GY - 38, span: 44, color: '#2fa38a', back: '#1f7a66' })}
    <ellipse cx="${GX}" cy="${f(GY - 34)}" rx="13" ry="18" fill="#28927c"/>
    <ellipse cx="${GX}" cy="${f(GY - 50)}" rx="8" ry="8" fill="#34a88f"/>
    <path d="M${f(GX + 7)},${f(GY - 50)} l10,3 l-10,3 Z" fill="${P.altin}"/>
    <circle cx="${f(GX + 3)}" cy="${f(GY - 52)}" r="1.9" fill="#fff6c9"/>
    <!-- Tepelik -->
    ${[-1, 0, 1].map(i => `<path d="M${f(GX + i * 4)},${f(GY - 57)} q${f(i * 3)},-10 ${f(i * 7)},-15"
      stroke="${P.altin}" stroke-width="2.2" fill="none" stroke-linecap="round"/>`).join('')}
    <!-- Alev kuyruk -->
    ${[-10, 0, 10].map((dx, i) => `<path d="M${f(GX + dx)},${f(GY - 18)}
      q${f(dx * 0.4)},18 ${f(dx * 0.8)},${f(26 + i * 4)}" stroke="${P.ates}"
      stroke-width="3.4" fill="none" stroke-linecap="round" opacity="0.9"/>`).join('')}
  </g>`, { shadowR: 20, glow: 'atesIsik' });

export const yelbegen = () => sprite(`
  <g transform="translate(0,-10)">
    ${wings({ cy: GY - 40, span: 46, color: '#5d2d6b', back: '#45204f' })}
    <ellipse cx="${GX}" cy="${f(GY - 32)}" rx="16" ry="20" fill="#4f2a5c"/>
    <path d="M${f(GX - 14)},${f(GY - 30)} q14,12 28,0 q-14,18 -28,0 Z" fill="#7a4a8a" opacity="0.6"/>
    <!-- Yedi baş (üçü görünür) -->
    ${[[-13, -52], [0, -58], [13, -52]].map(([dx, dy]) => `
      <path d="M${f(GX + dx)},${f(GY + dy + 12)} q${f(dx * 0.2)},-10 ${f(dx * 0.1)},-14"
        stroke="#4f2a5c" stroke-width="7" fill="none" stroke-linecap="round"/>
      <ellipse cx="${f(GX + dx)}" cy="${f(GY + dy)}" rx="7.5" ry="6.5" fill="#5d2d6b"/>
      <circle cx="${f(GX + dx - 2)}" cy="${f(GY + dy - 1)}" r="1.7" fill="#ff5d5d"/>
      <path d="M${f(GX + dx - 5)},${f(GY + dy + 4)} l10,0" stroke="#2b122f" stroke-width="1.6"/>`).join('')}
    <!-- Kuyruk -->
    <path d="M${f(GX + 14)},${f(GY - 24)} q20,8 24,26 q-16,-10 -26,-18 Z" fill="#45204f"/>
  </g>`, { shadowR: 23 });

/* ------------------------------------------------------------------ */
/* Karanlık birimler                                                   */
/* ------------------------------------------------------------------ */

export const karakoncolos = () => {
  const b = warrior({ h: 70, skin: '#9fb4c4', cloth: '#2f3f52', trim: '#7fd4e8',
    hat: 'yok', beard: '#dfe9f2' });
  return sprite(`
    ${b.parts.join('')}
    <!-- Boynuzlar: yana ve geriye kıvrık -->
    <path d="M${f(GX - 8)},${f(b.headY - 8)} q-12,-4 -16,-13 q10,2 18,8 Z" fill="#d8e2ea"/>
    <path d="M${f(GX + 8)},${f(b.headY - 8)} q12,-4 16,-13 q-10,2 -18,8 Z" fill="#d8e2ea"/>
    <!-- Buz nefesi -->
    ${[0, 1, 2].map(i => `<circle cx="${f(GX + 14 + i * 7)}" cy="${f(b.headY + 6 + i * 3)}"
      r="${f(2.4 - i * 0.5)}" fill="#bfe8f5" opacity="${0.8 - i * 0.2}"/>`).join('')}
    <!-- Buzdan pençe -->
    <path d="M${f(GX + 20)},${f(b.shoulderY + 24)} l6,-14 l3,14 l5,-10"
      stroke="#bfe8f5" stroke-width="3" fill="none" stroke-linecap="round"/>
  `, { shadowR: 19 });
};

export const albasti = () => {
  const b = warrior({ h: 60, skin: '#dcb3b3', cloth: '#8e1f3f', trim: '#e2a0b4', hat: 'yok' });
  return sprite(`
    ${b.parts.join('')}
    <!-- Uzun kızıl saç (yüzün iki yanından) -->
    <path d="M${f(GX - 10)},${f(b.headY - 6)} q-9,22 -5,34 q8,-12 7,-32 Z" fill="#b03050"/>
    <path d="M${f(GX + 10)},${f(b.headY - 6)} q9,22 5,34 q-8,-12 -7,-32 Z" fill="#8e1f3f"/>
    <path d="M${f(GX - 10)},${f(b.headY - 7)} q10,-7 20,0 q-10,4 -20,0 Z" fill="#b03050"/>
    <!-- Pençeler -->
    ${[-1, 1].map(s => `<path d="M${f(GX + s * 21)},${f(b.shoulderY + 26)}
      l${f(s * 3)},8 m${f(-s * 3)},-8 l${f(s * 6)},5 m${f(-s * 6)},-5 l${f(s * 7)},1"
      stroke="#e2a0b4" stroke-width="2.2" stroke-linecap="round" fill="none"/>`).join('')}
  `, { glow: null });
};

export const albasti_golgesi = () => sprite(`
  <g opacity="0.85">
    <path d="M${f(GX)},${f(GY - 4)} q-16,-10 -13,-34 q3,-22 13,-26 q10,4 13,26 q3,24 -13,34 Z"
      fill="#3a2a4a"/>
    <path d="M${f(GX - 6)},${f(GY - 46)} q6,-6 12,0 q-6,5 -12,0 Z" fill="#1d1428"/>
    <circle cx="${f(GX - 4)}" cy="${f(GY - 44)}" r="2" fill="#c47ae0"/>
    <circle cx="${f(GX + 4)}" cy="${f(GY - 44)}" r="2" fill="#c47ae0"/>
    <path d="M${f(GX - 14)},${f(GY - 30)} q-8,6 -6,16 M${f(GX + 14)},${f(GY - 30)} q8,6 6,16"
      stroke="#3a2a4a" stroke-width="4" fill="none" stroke-linecap="round"/>
  </g>`, { shadowR: 12, glow: 'buyuIsik' });

export const sahmeran = () => sprite(`
  <g transform="translate(0,2) scale(1.25) translate(${f(-GX * 0.2)},${f(-GY * 0.2)})">
    <!-- Yılan gövdesi -->
    <path d="M${f(GX - 24)},${f(GY - 4)} q10,-14 24,-10 q16,5 22,-6 q-6,18 -24,16 q-14,-2 -22,8 Z"
      fill="#2f7a5c"/>
    <path d="M${f(GX - 18)},${f(GY - 8)} q12,-10 22,-6" stroke="#53a87f" stroke-width="3" fill="none"/>
    <!-- Kadın üst gövdesi -->
    <path d="M${f(GX - 10)},${f(GY - 22)} q10,-5 20,0 l2,22 q-12,5 -24,0 Z" fill="#2f7a5c"/>
    <ellipse cx="${f(GX)}" cy="${f(GY - 38)}" rx="9" ry="10" fill="#e6c9a0"/>
    <circle cx="${f(GX - 3.2)}" cy="${f(GY - 38)}" r="1.5" fill="#2b1a10"/>
    <circle cx="${f(GX + 3.2)}" cy="${f(GY - 38)}" r="1.5" fill="#2b1a10"/>
    <!-- Taç -->
    <path d="M${f(GX - 10)},${f(GY - 46)} l3,-8 l4,6 l3,-9 l3,9 l4,-6 l3,8 Z" fill="${P.altin}"/>
    <!-- Yılan başları -->
    ${[-1, 1].map(s => `
      <path d="M${f(GX + s * 11)},${f(GY - 30)} q${f(s * 14)},-6 ${f(s * 16)},-18"
        stroke="#2f7a5c" stroke-width="4.5" fill="none" stroke-linecap="round"/>
      <ellipse cx="${f(GX + s * 27)}" cy="${f(GY - 50)}" rx="5" ry="4" fill="#53a87f"/>
      <circle cx="${f(GX + s * 28)}" cy="${f(GY - 51)}" r="1.3" fill="#ffd166"/>`).join('')}
  </g>`, { shadowR: 20 });

export const erlik_comezi = () => {
  const b = warrior({ h: 62, skin: '#b9a7c4', cloth: '#32243f', trim: '#a855f7',
    hat: 'kam', hatColor: '#241a2e', cape: '#241a2e' });
  return sprite(`
    ${b.parts.join('')}
    <!-- Asa -->
    <g transform="translate(${f(GX + 22)},${f(b.shoulderY + 26)})">
      <rect x="-2" y="-42" width="4" height="48" rx="2" fill="#3b2a1e"/>
      <circle cx="0" cy="-46" r="7" fill="#a855f7" opacity="0.9"/>
      <circle cx="0" cy="-46" r="3" fill="#fff" opacity="0.75"/>
    </g>
    <!-- Yeraltı dumanı -->
    ${[0, 1, 2].map(i => `<ellipse cx="${f(GX + (i - 1) * 12)}" cy="${f(GY - 4 - i * 3)}"
      rx="${f(9 - i * 2)}" ry="${f(4 - i)}" fill="#6b4a8a" opacity="0.4"/>`).join('')}
  `, { glow: 'buyuIsik' });
};

/* ------------------------------------------------------------------ */
/* Kahramanlar                                                         */
/* ------------------------------------------------------------------ */

export const oguz_kagan = () => {
  const b = warrior({ h: 72, cloth: '#1f4e79', trim: P.altin, armor: '#c9a227',
    hat: 'migfer', cape: '#8e1f1f', beard: '#2e1c10' });
  return sprite(`
    ${b.parts.join('')}
    <!-- Kağan tuğu -->
    <g transform="translate(${f(GX - 24)},${f(b.shoulderY + 14)})">
      <rect x="-2" y="-46" width="4" height="56" rx="2" fill="${P.ahsapKoyu}"/>
      <circle cx="0" cy="-50" r="5" fill="${P.altin}"/>
      <path d="M-1,-46 q-8,10 -4,20 M1,-46 q8,10 4,20" stroke="#2b2118"
        stroke-width="2" fill="none"/>
    </g>
    ${sword(GX + 24, b.shoulderY + 26, 38)}
    <!-- Altın taç bandı -->
    <path d="M${f(GX - 11)},${f(b.headY - 10)} h22" stroke="${P.altin}" stroke-width="3.4"/>
  `, { shadowR: 21, glow: 'atesIsik' });
};

export const asena = () => sprite(`
  <g>
    ${beast({ color: '#aeb8c6', belly: '#e2e8ef', h: 38, len: 64, maneColor: '#8e99a8' })}
    <!-- Gök tüyü/ışık -->
    <path d="M${f(GX - 30)},${f(GY - 42)} q16,-12 30,-6" stroke="${P.kut}"
      stroke-width="2.2" fill="none" opacity="0.8"/>
    <circle cx="${f(GX - 27)}" cy="${f(GY - 24)}" r="2.6" fill="#ffd166"/>
  </g>`, { shadowR: 24, glow: 'buyuIsik' });

export const korkut_ata = () => {
  const b = warrior({ h: 66, skin: '#e0bb92', cloth: '#4a6b4a', trim: P.altin,
    hat: 'kam', hatColor: '#35503a', beard: '#e4ddd0' });
  return sprite(`
    ${b.parts.join('')}
    <!-- Kopuz -->
    <g transform="translate(${f(GX - 20)},${f(b.shoulderY + 24)}) rotate(-18)">
      <ellipse cx="0" cy="6" rx="11" ry="15" fill="${P.ahsap}"/>
      <rect x="-2.4" y="-34" width="4.8" height="34" rx="2" fill="${P.ahsapKoyu}"/>
      <path d="M-5,-4 l10,0 M-5,0 l10,0" stroke="${P.kece}" stroke-width="1.2"/>
      <circle cx="0" cy="4" r="4" fill="${P.ahsapKoyu}"/>
    </g>
  `, { shadowR: 20, glow: 'buyuIsik' });
};

export const tomris_hatun = () => {
  const b = warrior({ h: 70, skin: '#e8c49e', cloth: '#7a1f3d', trim: P.altin,
    armor: '#c9a227', hat: 'yok', cape: '#4a1226' });
  return sprite(`
    ${b.parts.join('')}
    <!-- Saç ve taç -->
    <path d="M${f(GX - 11)},${f(b.headY - 2)} q-9,24 -3,38 q9,-14 7,-36 Z" fill="#2e1c10"/>
    <path d="M${f(GX - 11)},${f(b.headY - 10)} l3,-9 l4,7 l4,-10 l4,10 l4,-7 l3,9 Z" fill="${P.altin}"/>
    ${mace(GX + 24, b.shoulderY + 24)}
  `, { shadowR: 21, glow: 'atesIsik' });
};

/* ------------------------------------------------------------------ */

export const ALL = {
  ceri, okcu_kiz, gurzcu, boru, tepegoz, alp, kurt_binici, baksi,
  togrul, konrul, mancinik_arabasi, zumrudu_anka,
  karakoncolos, albasti, albasti_golgesi, sahmeran, erlik_comezi, yelbegen,
  oguz_kagan, asena, korkut_ata, tomris_hatun,
};
