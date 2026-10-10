/**
 * Arayüz görselleri: kaynak ikonları, rozetler, çerçeveler, düğmeler.
 * Hepsi Türk süsleme diliyle (kilim, damga, koçboynuzu) çizilir.
 */

import { PALETTE as P } from './lib/palette.mjs';
import { shade } from './lib/iso.mjs';
import { damga, kilimBand, turkNode, kocboynuzu, DAMGALAR } from './lib/ornament.mjs';
import { wrapFlat } from './lib/render.mjs';

const f = (n) => Math.round(n * 100) / 100;

/** Yuvarlak ikon tabanı: koyu disk + altın kenar. */
function iconBase(inner, { size = 96, ring = P.altin, fill = '#2e2a26' } = {}) {
  const c = size / 2;
  return wrapFlat({
    width: size, height: size,
    body: `
      <circle cx="${c}" cy="${c}" r="${f(c - 3)}" fill="${fill}"/>
      <circle cx="${c}" cy="${c}" r="${f(c - 3)}" fill="none" stroke="${ring}" stroke-width="3.4"/>
      <circle cx="${c}" cy="${c}" r="${f(c - 8)}" fill="none" stroke="${shade(ring, -0.35)}"
        stroke-width="1.2" opacity="0.6"/>
      ${inner}
    `,
  });
}

/* --- Kaynak ikonları ---------------------------------------------- */

export const ikon_altin = () => iconBase(`
  ${[[36, 56], [60, 56], [48, 42]].map(([x, y]) => `
    <ellipse cx="${x}" cy="${y}" rx="15" ry="9" fill="${P.gold}"
      stroke="${shade(P.gold, -0.35)}" stroke-width="2"/>
    <ellipse cx="${x}" cy="${y - 2}" rx="9" ry="5" fill="${shade(P.gold, 0.25)}"/>`).join('')}`);

export const ikon_tahil = () => iconBase(`
  ${[-13, 0, 13].map((dx, i) => `
    <path d="M${48 + dx},${64 - (i === 1 ? 6 : 0)} l0,-26" stroke="${shade(P.grain, -0.3)}"
      stroke-width="2.4" stroke-linecap="round"/>
    ${[0, 1, 2, 3].map(j => `
      <path d="M${48 + dx},${50 - (i === 1 ? 6 : 0) - j * 6} l-7,-5 M${48 + dx},${50 - (i === 1 ? 6 : 0) - j * 6} l7,-5"
        stroke="${P.grain}" stroke-width="3" stroke-linecap="round"/>`).join('')}`).join('')}`);

export const ikon_demir = () => iconBase(`
  <path d="M26,62 l5,-13 h34 l5,13 Z" fill="${P.iron}" stroke="${shade(P.iron, -0.4)}" stroke-width="2"/>
  <path d="M26,62 l5,-13 h17 l0,13 Z" fill="${shade(P.iron, 0.2)}"/>
  <path d="M34,46 l4,-11 h24 l4,11 Z" fill="${shade(P.iron, 0.1)}"
    stroke="${shade(P.iron, -0.4)}" stroke-width="2"/>`);

export const ikon_kut = () => iconBase(`
  <path d="M48,22 L64,46 L48,72 L32,46 Z" fill="${P.kut}" opacity="0.95"/>
  <path d="M48,22 L64,46 L48,50 Z" fill="#ffffff" opacity="0.55"/>
  <path d="M48,22 L32,46 L48,50 Z" fill="${shade(P.kut, -0.2)}"/>
  <circle cx="48" cy="46" r="22" fill="${P.kut}" opacity="0.18" filter="url(#yumusak)"/>`,
  { ring: P.kut });

export const ikon_gok_tasi = () => iconBase(`
  <path d="M48,20 L66,38 L58,68 L38,68 L30,38 Z" fill="${P.gokTasi}"/>
  <path d="M48,20 L66,38 L48,44 Z" fill="#ffffff" opacity="0.5"/>
  <path d="M48,20 L30,38 L48,44 Z" fill="${shade(P.gokTasi, -0.22)}"/>
  <path d="M48,44 L58,68 L38,68 Z" fill="${shade(P.gokTasi, -0.1)}"/>`,
  { ring: P.gokTasi });

/* --- Oyun simgeleri ------------------------------------------------ */

export const ikon_yildiz = () => wrapFlat({ width: 96, height: 96, body: `
  <path d="M48,10 L59,38 L89,40 L66,59 L74,88 L48,72 L22,88 L30,59 L7,40 L37,38 Z"
    fill="${P.altin}" stroke="${shade(P.altin, -0.4)}" stroke-width="3" stroke-linejoin="round"/>
  <path d="M48,10 L59,38 L48,44 Z" fill="#fff5d0" opacity="0.7"/>` });

export const ikon_yildiz_bos = () => wrapFlat({ width: 96, height: 96, body: `
  <path d="M48,10 L59,38 L89,40 L66,59 L74,88 L48,72 L22,88 L30,59 L7,40 L37,38 Z"
    fill="#3a342c" stroke="#5c5346" stroke-width="3" stroke-linejoin="round"/>` });

export const ikon_kupa = () => iconBase(`
  <path d="M34,28 h28 l-2,22 q-12,10 -24,0 Z" fill="${P.altin}"/>
  <path d="M34,28 h14 l0,30 q-8,-2 -12,-8 Z" fill="${shade(P.altin, 0.25)}"/>
  <path d="M34,32 q-10,2 -8,10 q2,7 9,7" stroke="${P.altin}" stroke-width="3.4" fill="none"/>
  <path d="M62,32 q10,2 8,10 q-2,7 -9,7" stroke="${P.altin}" stroke-width="3.4" fill="none"/>
  <rect x="42" y="56" width="12" height="10" fill="${shade(P.altin, -0.2)}"/>
  <rect x="33" y="64" width="30" height="7" rx="3" fill="${shade(P.altin, -0.3)}"/>`);

export const ikon_kalkan = () => iconBase(`
  <path d="M48,20 l22,9 v20 q0,18 -22,28 q-22,-10 -22,-28 V29 Z"
    fill="${P.turkuaz}" stroke="${shade(P.turkuaz, -0.4)}" stroke-width="2.6"/>
  <path d="M48,20 l22,9 v20 q0,18 -22,28 Z" fill="${shade(P.turkuaz, -0.18)}"/>
  ${damga({ cx: 48, cy: 46, key: 'kurt', size: 1.1, color: '#ffffff', width: 2.4 })}`,
  { ring: P.turkuaz });

export const ikon_usta = () => iconBase(`
  <g transform="translate(48,48) rotate(-35)">
    <rect x="-2.6" y="-24" width="5.2" height="34" rx="2.6" fill="${P.ahsap}"/>
    <rect x="-13" y="-32" width="26" height="14" rx="3" fill="${P.demirKoyu}"/>
    <rect x="-13" y="-32" width="26" height="6" rx="3" fill="${P.demir}"/>
  </g>`);

export const ikon_sure = () => iconBase(`
  <circle cx="48" cy="48" r="24" fill="none" stroke="${P.kece}" stroke-width="4"/>
  <path d="M48,32 v17 l12,7" stroke="${P.altin}" stroke-width="4" fill="none" stroke-linecap="round"/>`);

export const ikon_ordu = () => iconBase(`
  <path d="M30,66 l8,-30 h6 l-6,30 Z" fill="${P.demir}"/>
  <path d="M66,66 l-8,-30 h-6 l6,30 Z" fill="${shade(P.demir, -0.2)}"/>
  <path d="M34,36 l10,-14 l10,14" stroke="${P.altin}" stroke-width="3" fill="none"/>
  <rect x="26" y="64" width="44" height="6" rx="3" fill="${P.ahsapKoyu}"/>`);

export const ikon_boy = () => iconBase(`
  <path d="M48,20 l20,8 v22 q0,16 -20,26 q-20,-10 -20,-26 V28 Z"
    fill="${P.kizil}" stroke="${P.altin}" stroke-width="2.6"/>
  ${damga({ cx: 48, cy: 44, key: 'konrul', size: 1.2, color: P.altin, width: 2.4 })}`);

export const ikon_dunya = () => iconBase(`
  <circle cx="48" cy="48" r="25" fill="${P.lacivert}"/>
  <path d="M26,42 q22,-8 44,0 M26,54 q22,8 44,0" stroke="${P.turkuaz}" stroke-width="2" fill="none"/>
  <path d="M48,23 q-12,25 0,50 M48,23 q12,25 0,50" stroke="${P.turkuaz}" stroke-width="2" fill="none"/>
  <path d="M34,38 q10,-6 18,2 q-6,10 -18,-2 Z" fill="${P.cim}"/>
  <path d="M54,52 q10,-4 12,6 q-10,6 -12,-6 Z" fill="${P.cim}"/>`);

export const ikon_savas = () => iconBase(`
  <g transform="rotate(-45 48 48)"><rect x="45" y="20" width="6" height="42" fill="${P.demir}"/>
    <path d="M45,20 l3,-8 l3,8 Z" fill="${shade(P.demir, 0.25)}"/>
    <rect x="36" y="60" width="24" height="5" rx="2.4" fill="${P.altinKoyu}"/></g>
  <g transform="rotate(45 48 48)"><rect x="45" y="20" width="6" height="42" fill="${shade(P.demir, -0.15)}"/>
    <path d="M45,20 l3,-8 l3,8 Z" fill="${shade(P.demir, 0.15)}"/>
    <rect x="36" y="60" width="24" height="5" rx="2.4" fill="${P.altinKoyu}"/></g>`,
  { ring: P.kizil });

/* --- Boy rozeti parçaları ------------------------------------------ */

/** Rozet kalkanı: Unity'de boy renkleriyle boyanır (beyaz maske). */
export const rozet_kalkan = () => wrapFlat({ width: 128, height: 128, body: `
  <path d="M64,10 l46,18 v44 q0,34 -46,46 q-46,-12 -46,-46 V28 Z" fill="#ffffff"/>` });

export const rozet_tug = () => wrapFlat({ width: 128, height: 128, body: `
  <path d="M64,8 l40,22 l-10,54 q-30,22 -60,0 l-10,-54 Z" fill="#ffffff"/>` });

export const rozet_ay = () => wrapFlat({ width: 128, height: 128, body: `
  <path fill-rule="evenodd" fill="#ffffff"
    d="M64,8 A56,56 0 1 1 64,120 A56,56 0 1 1 64,8 Z
       M78,20 A46,46 0 1 0 78,108 A56,56 0 0 1 78,20 Z"/>
  <path d="M96,52 l4,9 l10,1 l-7,7 l2,10 l-9,-5 l-9,5 l2,-10 l-7,-7 l10,-1 Z" fill="#ffffff"/>` });

// Damga simgeleri — rozetin ortasına basılır
const damgaIcon = (key) => () => wrapFlat({ width: 96, height: 96, body:
  `<g transform="translate(48,48) scale(3.2)">
    <path d="${DAMGALAR[key]}" fill="none" stroke="#ffffff" stroke-width="2"
      stroke-linecap="round" stroke-linejoin="round"/>
  </g>` });

export const damga_kurt = damgaIcon('kurt');
export const damga_ok = damgaIcon('ok');
export const damga_konrul = damgaIcon('konrul');
export const damga_ay = damgaIcon('ay');
export const damga_dag = damgaIcon('dag');
export const damga_agac = damgaIcon('agac');
export const damga_su = damgaIcon('su');
export const damga_goz = damgaIcon('goz');

/* --- Çerçeve ve düğme ---------------------------------------------- */

/** 9-dilim paneli: Unity'de Sprite Border ile esnetilir (kenar 32 px). */
export const panel = () => wrapFlat({ width: 160, height: 160, body: `
  <rect x="4" y="4" width="152" height="152" rx="16" fill="#2b2620"
    stroke="${P.altinKoyu}" stroke-width="4"/>
  <rect x="12" y="12" width="136" height="136" rx="10" fill="none"
    stroke="${shade(P.altin, -0.45)}" stroke-width="1.6" opacity="0.8"/>
  ${kilimBand({ x: 12, y: 12, width: 136, height: 12, color: P.kizilKoyu, accent: P.altin, step: 17 })}
  ${kilimBand({ x: 12, y: 136, width: 136, height: 12, color: P.kizilKoyu, accent: P.altin, step: 17 })}
  ${turkNode({ cx: 20, cy: 20, size: 14 })}
  ${turkNode({ cx: 140, cy: 20, size: 14 })}
  ${turkNode({ cx: 20, cy: 140, size: 14 })}
  ${turkNode({ cx: 140, cy: 140, size: 14 })}` });

export const dugme = () => wrapFlat({ width: 160, height: 80, body: `
  <rect x="4" y="4" width="152" height="72" rx="14" fill="${P.kizil}"
    stroke="${P.altin}" stroke-width="3.4"/>
  <rect x="10" y="10" width="140" height="26" rx="10" fill="#ffffff" opacity="0.16"/>
  ${kocboynuzu({ cx: 26, cy: 40, size: 11, color: P.altin, width: 2.2 })}
  ${kocboynuzu({ cx: 134, cy: 40, size: 11, color: P.altin, width: 2.2 })}` });

export const dugme_yesil = () => wrapFlat({ width: 160, height: 80, body: `
  <rect x="4" y="4" width="152" height="72" rx="14" fill="#3f7a46"
    stroke="${P.altin}" stroke-width="3.4"/>
  <rect x="10" y="10" width="140" height="26" rx="10" fill="#ffffff" opacity="0.16"/>` });

/** Kaynak çubuğu arka planı. */
export const kaynak_cubugu = () => wrapFlat({ width: 200, height: 48, body: `
  <rect x="2" y="2" width="196" height="44" rx="22" fill="#241f1a" opacity="0.92"
    stroke="${P.altinKoyu}" stroke-width="2.4"/>` });

/** Can çubuğu (savaşta bina/asker üstünde). */
export const can_cubugu = () => wrapFlat({ width: 120, height: 20, body: `
  <rect x="1" y="1" width="118" height="18" rx="9" fill="#1b1612" stroke="#4a4038" stroke-width="2"/>
  <rect x="4" y="4" width="112" height="12" rx="6" fill="#4caf50"/>` });

export const ALL = {
  ikon_altin, ikon_tahil, ikon_demir, ikon_kut, ikon_gok_tasi,
  ikon_yildiz, ikon_yildiz_bos, ikon_kupa, ikon_kalkan, ikon_usta,
  ikon_sure, ikon_ordu, ikon_boy, ikon_dunya, ikon_savas,
  rozet_kalkan, rozet_tug, rozet_ay,
  damga_kurt, damga_ok, damga_konrul, damga_ay, damga_dag, damga_agac, damga_su, damga_goz,
  panel, dugme, dugme_yesil, kaynak_cubugu, can_cubugu,
};
