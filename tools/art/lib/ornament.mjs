/**
 * Türk süsleme öğeleri: kilim desenleri, Göktürk damgaları, keçe nakışları.
 *
 * Bunlar binaların ve arayüzün üzerine konur; oyunun "Türk mitolojisi"
 * kimliğini taşıyan en belirgin görsel katmandır.
 */

import { PALETTE } from './palette.mjs';

/**
 * Kilim şeridi: yatay bir bant üzerine tekrar eden eşkenar dörtgen ve
 * çengel (koçboynuzu) motifi.
 */
export function kilimBand({ x, y, width, height = 10, color = PALETTE.kizil, accent = PALETTE.altin, step = 14 }) {
  const parts = [`<rect x="${x}" y="${y}" width="${width}" height="${height}" fill="${color}"/>`];
  const cy = y + height / 2;

  for (let px = x + step / 2; px < x + width; px += step) {
    const r = height * 0.34;
    // Eşkenar dörtgen (göz motifi)
    parts.push(`<path d="M${px},${cy - r} L${px + r},${cy} L${px},${cy + r} L${px - r},${cy} Z"
      fill="${accent}"/>`);
  }

  // Üst ve alt ince çizgi
  parts.push(`<rect x="${x}" y="${y}" width="${width}" height="1.4" fill="${accent}" opacity="0.75"/>`);
  parts.push(`<rect x="${x}" y="${y + height - 1.4}" width="${width}" height="1.4" fill="${accent}" opacity="0.6"/>`);

  return `<g>${parts.join('')}</g>`;
}

/**
 * Koçboynuzu (çengel) motifi — bereket ve güç simgesi.
 */
export function kocboynuzu({ cx, cy, size = 14, color = PALETTE.altin, width = 2.4 }) {
  const s = size;
  return `<path d="M${cx - s},${cy + s * 0.4}
                   C${cx - s},${cy - s * 0.5} ${cx - s * 0.2},${cy - s * 0.7} ${cx},${cy - s * 0.2}
                   C${cx + s * 0.2},${cy - s * 0.7} ${cx + s},${cy - s * 0.5} ${cx + s},${cy + s * 0.4}"
      fill="none" stroke="${color}" stroke-width="${width}" stroke-linecap="round"/>`;
}

/**
 * Göktürk damgaları — her boyun kendi damgası vardır.
 * Basit, taş üzerine kazınmış görünümlü çizgisel simgeler.
 */
export const DAMGALAR = {
  // Kurt başı
  kurt:  'M-7,4 L-7,-2 L-3,-6 L3,-6 L7,-2 L7,4 M-3,-1 L-3,1 M3,-1 L3,1 M-2,4 L2,4',
  // Ok ve yay
  ok:    'M-8,6 L8,-6 M4,-6 L8,-6 L8,-2 M-8,-6 C-2,-6 -6,6 0,6',
  // Gök (çift başlı kartal şematiği)
  konrul:'M0,-7 L0,5 M-8,-3 L0,-6 L8,-3 M-5,3 L0,0 L5,3 M-3,-7 L0,-4 L3,-7',
  // Ay-yıldız öncesi: ay ve nokta
  ay:    'M-4,-6 A7,7 0 1 0 -4,6 A5.5,5.5 0 1 1 -4,-6 Z M4,0 L6,0',
  // Dağ (Ergenekon)
  dag:   'M-8,5 L-3,-4 L0,1 L4,-6 L8,5 Z',
  // Yaşam ağacı
  agac:  'M0,7 L0,-6 M0,-2 L-5,-6 M0,-2 L5,-6 M0,2 L-4,-1 M0,2 L4,-1',
  // Su / dalga
  su:    'M-8,-2 q4,-4 8,0 t8,0 M-8,4 q4,-4 8,0 t8,0',
  // Göz (nazar)
  goz:   'M-8,0 C-4,-5 4,-5 8,0 C4,5 -4,5 -8,0 Z M0,0 L0,0',
};

export function damga({ cx, cy, key = 'kurt', size = 1, color = PALETTE.altin, width = 2 }) {
  const d = DAMGALAR[key] ?? DAMGALAR.kurt;
  return `<g transform="translate(${cx},${cy}) scale(${size})">
      <path d="${d}" fill="none" stroke="${color}" stroke-width="${width / size}"
        stroke-linecap="round" stroke-linejoin="round"/>
    </g>`;
}

/**
 * Keçe çadır nakışı: kubbenin üzerine binen eğri şeritler.
 */
export function feltStitching({ cx, cy, rx, ry, color = PALETTE.kizil, rows = 3 }) {
  const parts = [];
  for (let i = 1; i <= rows; i++) {
    const t = i / (rows + 1);
    const r = rx * (1 - t * 0.55);
    const yy = cy - ry * t * 1.1;
    parts.push(`<path d="M${cx - r},${yy} A${r},${r * 0.42} 0 0 1 ${cx + r},${yy}"
        fill="none" stroke="${color}" stroke-width="1.8" opacity="0.55"
        stroke-dasharray="5 4"/>`);
  }
  return `<g>${parts.join('')}</g>`;
}

/**
 * Türk düğümü (sonsuzluk düğümü) — arayüz çerçevelerinin köşesinde.
 */
export function turkNode({ cx, cy, size = 16, color = PALETTE.altin, width = 2.2 }) {
  const s = size / 2;
  return `<g transform="translate(${cx},${cy})">
      <path d="M${-s},0 L0,${-s} L${s},0 L0,${s} Z" fill="none"
        stroke="${color}" stroke-width="${width}"/>
      <path d="M${-s * 0.5},0 L0,${-s * 0.5} L${s * 0.5},0 L0,${s * 0.5} Z" fill="none"
        stroke="${color}" stroke-width="${width * 0.8}" opacity="0.8"/>
    </g>`;
}

/** Ortak SVG filtreleri ve desenleri (her dosyanın <defs> bloğu). */
export function commonDefs() {
  return `
  <defs>
    <filter id="yumusak" x="-50%" y="-50%" width="200%" height="200%">
      <feGaussianBlur stdDeviation="4"/>
    </filter>
    <filter id="cokYumusak" x="-50%" y="-50%" width="200%" height="200%">
      <feGaussianBlur stdDeviation="9"/>
    </filter>
    <filter id="hafifYumusak" x="-50%" y="-50%" width="200%" height="200%">
      <feGaussianBlur stdDeviation="1.6"/>
    </filter>
    <filter id="parilti" x="-60%" y="-60%" width="220%" height="220%">
      <feGaussianBlur stdDeviation="6" result="b"/>
      <feMerge>
        <feMergeNode in="b"/>
        <feMergeNode in="SourceGraphic"/>
      </feMerge>
    </filter>

    <!-- Taş dokusu: ince gürültü -->
    <filter id="tasDoku" x="0%" y="0%" width="100%" height="100%">
      <feTurbulence type="fractalNoise" baseFrequency="0.9" numOctaves="3" seed="7" result="n"/>
      <feColorMatrix in="n" type="saturate" values="0" result="g"/>
      <feComposite in="SourceGraphic" in2="g" operator="arithmetic"
        k1="0.18" k2="0.86" k3="0" k4="0"/>
    </filter>

    <!-- Keçe dokusu: yumuşak lekeler -->
    <filter id="keceDoku" x="0%" y="0%" width="100%" height="100%">
      <feTurbulence type="fractalNoise" baseFrequency="0.035" numOctaves="4" seed="3" result="n"/>
      <feColorMatrix in="n" type="saturate" values="0" result="g"/>
      <feComposite in="SourceGraphic" in2="g" operator="arithmetic"
        k1="0.10" k2="0.93" k3="0" k4="0"/>
    </filter>

    <linearGradient id="gokIsik" x1="0" y1="0" x2="0" y2="1">
      <stop offset="0" stop-color="#ffffff" stop-opacity="0.30"/>
      <stop offset="1" stop-color="#ffffff" stop-opacity="0"/>
    </linearGradient>

    <radialGradient id="atesIsik" cx="0.5" cy="0.5" r="0.5">
      <stop offset="0" stop-color="${PALETTE.atesIc}" stop-opacity="0.95"/>
      <stop offset="0.5" stop-color="${PALETTE.ates}" stop-opacity="0.55"/>
      <stop offset="1" stop-color="${PALETTE.ates}" stop-opacity="0"/>
    </radialGradient>

    <radialGradient id="buyuIsik" cx="0.5" cy="0.5" r="0.5">
      <stop offset="0" stop-color="#ffffff" stop-opacity="0.9"/>
      <stop offset="0.45" stop-color="${PALETTE.buyu}" stop-opacity="0.6"/>
      <stop offset="1" stop-color="${PALETTE.buyu}" stop-opacity="0"/>
    </radialGradient>
  </defs>`;
}
