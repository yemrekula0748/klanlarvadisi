/**
 * İzometrik geometri yardımcıları.
 *
 * Projeksiyon: 2:1 izometrik. Bir ızgara karesi TILE_W x TILE_H ekran
 * pikseline karşılık gelir; yükseklik (z) doğrudan yukarı taşır.
 *
 *   ekranX = (x - y) * TILE_W / 2
 *   ekranY = (x + y) * TILE_H / 2 - z
 *
 * Bütün yüzler ışık yönüne göre farklı parlaklıkta boyanır; bu, düz
 * renkli kutuların bile hacimli görünmesini sağlar.
 */

export const TILE_W = 128;
export const TILE_H = 64;

/** Dünya (ızgara) koordinatını ekran koordinatına çevirir. */
export function project(x, y, z = 0) {
  return [
    (x - y) * (TILE_W / 2),
    (x + y) * (TILE_H / 2) - z,
  ];
}

const fmt = (n) => Math.round(n * 100) / 100;

export function path(points, close = true) {
  return points
    .map(([x, y], i) => `${i === 0 ? 'M' : 'L'}${fmt(x)},${fmt(y)}`)
    .join(' ') + (close ? ' Z' : '');
}

/* ------------------------------------------------------------------ */
/* Renk yardımcıları                                                   */
/* ------------------------------------------------------------------ */

export function hexToRgb(hex) {
  const h = hex.replace('#', '');
  const v = h.length === 3
    ? h.split('').map((c) => c + c).join('')
    : h;
  return [
    parseInt(v.slice(0, 2), 16),
    parseInt(v.slice(2, 4), 16),
    parseInt(v.slice(4, 6), 16),
  ];
}

export function rgbToHex([r, g, b]) {
  const c = (n) => Math.max(0, Math.min(255, Math.round(n)))
    .toString(16).padStart(2, '0');
  return `#${c(r)}${c(g)}${c(b)}`;
}

/** Rengi aydınlatır (t>0) veya karartır (t<0). */
export function shade(hex, t) {
  const [r, g, b] = hexToRgb(hex);
  if (t >= 0) {
    return rgbToHex([r + (255 - r) * t, g + (255 - g) * t, b + (255 - b) * t]);
  }
  return rgbToHex([r * (1 + t), g * (1 + t), b * (1 + t)]);
}

/** İki rengi karıştırır. */
export function mix(a, b, t) {
  const [r1, g1, b1] = hexToRgb(a);
  const [r2, g2, b2] = hexToRgb(b);
  return rgbToHex([
    r1 + (r2 - r1) * t,
    g1 + (g2 - g1) * t,
    b1 + (b2 - b1) * t,
  ]);
}

/* ------------------------------------------------------------------ */
/* Temel katılar                                                       */
/* ------------------------------------------------------------------ */

/**
 * İzometrik dikdörtgen prizma (kutu).
 *
 * Üç görünen yüz çizilir: üst (en aydınlık), sol-ön (orta), sağ-ön (koyu).
 * Işık sol üstten gelir.
 *
 * @param {object} o
 * @param {number} o.w  ızgara genişliği
 * @param {number} o.d  ızgara derinliği
 * @param {number} o.h  piksel yüksekliği
 * @param {number} o.z  taban yüksekliği
 * @param {string} o.color  taban rengi
 */
export function box({ w = 1, d = 1, h = 40, z = 0, color = '#8a7a5e', top, left, right, stroke = null, stdOpacity = 1 }) {
  const topColor = top ?? shade(color, 0.22);
  const leftColor = left ?? shade(color, -0.08);
  const rightColor = right ?? shade(color, -0.32);

  // Köşeler: (0,0) sol, (w,0) arka, (w,d) sağ, (0,d) ön
  const A = project(0, 0, z + h);     // arka-sol üst
  const B = project(w, 0, z + h);     // arka-sağ üst
  const C = project(w, d, z + h);     // ön-sağ üst
  const D = project(0, d, z + h);     // ön-sol üst
  const D0 = project(0, d, z);
  const C0 = project(w, d, z);
  const A0 = project(0, 0, z);

  const strokeAttr = stroke ? ` stroke="${stroke}" stroke-width="1.2" stroke-linejoin="round"` : '';

  return `
    <g opacity="${stdOpacity}">
      <path d="${path([A0, D0, D, A])}" fill="${leftColor}"${strokeAttr}/>
      <path d="${path([D0, C0, C, D])}" fill="${rightColor}"${strokeAttr}/>
      <path d="${path([A, B, C, D])}" fill="${topColor}"${strokeAttr}/>
    </g>`;
}

/**
 * İzometrik koni/çadır çatısı — Türk otağının karakteristik biçimi.
 *
 * @param {number} o.w  taban genişliği (ızgara)
 * @param {number} o.d  taban derinliği (ızgara)
 * @param {number} o.h  çatı yüksekliği (piksel)
 * @param {number} o.z  taban yüksekliği
 */
export function tentRoof({ w = 1, d = 1, h = 50, z = 0, color = '#c0392b', overhang = 0.08 }) {
  const ow = w + overhang * 2;
  const od = d + overhang * 2;
  const ox = -overhang;
  const oy = -overhang;

  const A = project(ox, oy, z);
  const B = project(ox + ow, oy, z);
  const C = project(ox + ow, oy + od, z);
  const D = project(ox, oy + od, z);
  const peak = project(w / 2, d / 2, z + h);

  const light = shade(color, 0.26);
  const mid = shade(color, 0.02);
  const dark = shade(color, -0.26);
  const darker = shade(color, -0.42);

  return `
    <g>
      <path d="${path([A, B, peak])}" fill="${light}"/>
      <path d="${path([B, C, peak])}" fill="${dark}"/>
      <path d="${path([C, D, peak])}" fill="${darker}"/>
      <path d="${path([D, A, peak])}" fill="${mid}"/>
    </g>`;
}

/**
 * İzometrik yuvarlak kubbe/yurt (keçe çadır). Eliptik tabandan yükselen,
 * dilimlerle gölgelenmiş kubbe.
 */
export function dome({ w = 1, d = 1, h = 44, z = 0, color = '#e8e2d2', slices = 14 }) {
  const [bx, by] = project(w / 2, d / 2, z);
  const rx = (w + d) * (TILE_W / 4);
  const ry = rx * 0.5;
  const parts = [];

  // Taban elipsi: kubbenin yere oturduğu halka (önce çizilir)
  parts.push(`<ellipse cx="${fmt(bx)}" cy="${fmt(by)}" rx="${fmt(rx)}" ry="${fmt(ry)}"
      fill="${shade(color, -0.26)}"/>`);

  // Kubbe gövdesi: taban elipsinin ön yarısından tepeye uzanan kabuk.
  // Dilimler soldan sağa koyulaşır; bu, tek bir gradyandan daha "keçe"
  // bir doku verir.
  for (let i = 0; i < slices; i++) {
    const t0 = i / slices;
    const t1 = (i + 1) / slices;
    const a0 = Math.PI * (1 - t0);
    const a1 = Math.PI * (1 - t1);

    const x0 = bx + Math.cos(a0) * rx;
    const y0 = by + Math.sin(a0) * ry;   // taban elipsinin ÖN yarısı
    const x1 = bx + Math.cos(a1) * rx;
    const y1 = by + Math.sin(a1) * ry;

    // Tepe noktası
    const tx = bx;
    const ty = by - h;

    // Işık sol üstten: t=0 (sol) aydınlık, t=1 (sağ) koyu
    const lum = 0.30 - t0 * 0.66;

    parts.push(`<path d="M${fmt(x0)},${fmt(y0)}
        A${fmt(rx)},${fmt(ry)} 0 0 0 ${fmt(x1)},${fmt(y1)}
        Q${fmt((x1 + tx) / 2)},${fmt(y1 - h * 0.62)} ${fmt(tx)},${fmt(ty)}
        Q${fmt((x0 + tx) / 2)},${fmt(y0 - h * 0.62)} ${fmt(x0)},${fmt(y0)} Z"
        fill="${shade(color, lum)}"/>`);
  }

  // Tepe parlaması
  parts.push(`<ellipse cx="${fmt(bx - rx * 0.22)}" cy="${fmt(by - h * 0.74)}"
      rx="${fmt(rx * 0.34)}" ry="${fmt(h * 0.20)}"
      fill="#ffffff" opacity="0.14" filter="url(#yumusak)"/>`);

  return `<g>${parts.join('')}</g>`;
}

/** İzometrik silindir (kule gövdesi, ocak bacası). */
export function cylinder({ w = 1, d = 1, h = 60, z = 0, color = '#8d8275', bands = 0, fill = 0.52 }) {
  const [cx, cy] = project(w / 2, d / 2, z);
  // `fill`: ayak izine göre doluluk. 1.0 kareyi tamamen kaplar ve yayvan
  // görünür; 0.5 civarı kule/silindir oranı verir.
  const rx = (w + d) * (TILE_W / 4) * fill;
  const ry = rx * 0.5;
  const parts = [];

  // Gövde
  parts.push(`<path d="M${fmt(cx - rx)},${fmt(cy)}
      L${fmt(cx - rx)},${fmt(cy - h)}
      A${fmt(rx)},${fmt(ry)} 0 0 1 ${fmt(cx + rx)},${fmt(cy - h)}
      L${fmt(cx + rx)},${fmt(cy)}
      A${fmt(rx)},${fmt(ry)} 0 0 0 ${fmt(cx - rx)},${fmt(cy)} Z"
      fill="${shade(color, -0.06)}"/>`);

  // Dikey ışık geçişi
  parts.push(`<path d="M${fmt(cx - rx)},${fmt(cy)}
      L${fmt(cx - rx)},${fmt(cy - h)}
      A${fmt(rx)},${fmt(ry)} 0 0 1 ${fmt(cx)},${fmt(cy - h - ry)}
      L${fmt(cx)},${fmt(cy + ry)} Z"
      fill="${shade(color, 0.20)}" opacity="0.6"/>`);
  parts.push(`<path d="M${fmt(cx + rx)},${fmt(cy)}
      L${fmt(cx + rx)},${fmt(cy - h)}
      A${fmt(rx)},${fmt(ry)} 0 0 0 ${fmt(cx)},${fmt(cy - h - ry)}
      L${fmt(cx)},${fmt(cy + ry)} Z"
      fill="${shade(color, -0.34)}" opacity="0.55"/>`);

  // Taş kuşakları
  for (let i = 1; i <= bands; i++) {
    const by = cy - (h * i) / (bands + 1);
    parts.push(`<path d="M${fmt(cx - rx)},${fmt(by)} A${fmt(rx)},${fmt(ry)} 0 0 0 ${fmt(cx + rx)},${fmt(by)}"
        fill="none" stroke="${shade(color, -0.42)}" stroke-width="2" opacity="0.5"/>`);
  }

  // Üst kapak
  parts.push(`<ellipse cx="${fmt(cx)}" cy="${fmt(cy - h)}" rx="${fmt(rx)}" ry="${fmt(ry)}"
      fill="${shade(color, 0.26)}"/>`);

  return `<g>${parts.join('')}</g>`;
}

/** Zemin gölgesi — binayı yere oturtur. */
export function groundShadow({ w = 1, d = 1, opacity = 0.34, blur = 'yumusak' }) {
  const [cx, cy] = project(w / 2, d / 2, 0);
  const rx = (w + d) * (TILE_W / 4) * 0.92;
  const ry = rx * 0.5;

  return `<ellipse cx="${fmt(cx)}" cy="${fmt(cy + 2)}" rx="${fmt(rx)}" ry="${fmt(ry)}"
      fill="#1b1206" opacity="${opacity}" filter="url(#${blur})"/>`;
}

/** Binanın oturduğu izometrik taş platform. */
export function platform({ w = 1, d = 1, h = 7, color = '#6f6554' }) {
  return box({ w, d, h, z: 0, color, top: shade(color, 0.16) });
}

/** Tuğ (sancak) — Türk obasının simgesi. */
export function banner({ x = 0.5, y = 0.5, z = 0, height = 70, color = '#c0392b', pole = '#5a4632' }) {
  const [px, py] = project(x, y, z);
  const topY = py - height;

  return `
    <g>
      <rect x="${fmt(px - 1.6)}" y="${fmt(topY)}" width="3.2" height="${fmt(height)}"
        rx="1.4" fill="${pole}"/>
      <circle cx="${fmt(px)}" cy="${fmt(topY - 3)}" r="3.4" fill="#e0a63c"/>
      <path d="M${fmt(px + 1.6)},${fmt(topY + 4)}
               L${fmt(px + 30)},${fmt(topY + 11)}
               L${fmt(px + 22)},${fmt(topY + 18)}
               L${fmt(px + 30)},${fmt(topY + 25)}
               L${fmt(px + 1.6)},${fmt(topY + 28)} Z"
        fill="${color}"/>
      <path d="M${fmt(px + 1.6)},${fmt(topY + 4)}
               L${fmt(px + 30)},${fmt(topY + 11)}
               L${fmt(px + 22)},${fmt(topY + 18)}"
        fill="${shade(color, 0.22)}" opacity="0.6"/>
      <!-- At kılı püskülü -->
      <path d="M${fmt(px - 1)},${fmt(topY + 2)} q-7,8 -3,16 M${fmt(px - 1)},${fmt(topY + 2)} q-3,9 1,17"
        stroke="#2b2118" stroke-width="1.6" fill="none" opacity="0.8"/>
    </g>`;
}

/**
 * Türk otağı / yurt — oyunun en karakteristik yapısı.
 *
 * Gerçek yurt iki parçadır: dik keçe duvar (kerege) ve onun üzerine oturan
 * konik çatı (uuk), tepesinde göğe açılan halka (şanırak/tündük).
 * Tek bir kubbe olarak çizmek "abajur" görüntüsü verdiği için burada
 * ikisi ayrı ayrı modellenir.
 *
 * @param {number} o.w,o.d  ızgara ayak izi
 * @param {number} o.wallH  duvar yüksekliği (piksel)
 * @param {number} o.roofH  çatı yüksekliği (piksel)
 */
export function yurt({
  w = 3, d = 3, z = 0,
  wallH = 46, roofH = 78,
  felt = '#e9e1cf', roof = '#ded5c0',
  trim = '#b7362c', crown = '#5e472c',
  seams = 18, fill = 0.58,
}) {
  const [cx, cy] = project(w / 2, d / 2, z);
  const rx = (w + d) * (TILE_W / 4) * fill;
  const ry = rx * 0.5;

  const wallTop = cy - wallH;
  const peakY = wallTop - roofH;
  // Saçak taşması: ön yarım elips wallH kadar aşağı sarkar, bu yüzden
  // taşma oranı küçük tutulur ve çağıran wallH > eave*0.5 seçer.
  const eave = rx * 1.05;
  const eaveRy = eave * 0.5;
  const crownRx = rx * 0.18;

  const roofParts = [];
  const wallParts = [];

  /* --- Çatı (uuk): konik, dilimli --------------------------------- */
  // ÖNCE çizilir; duvar üstüne gelip çatının alt ön yüzünü kapatır.
  // Aksi hâlde saçak elipsinin ön yarısı duvarı yutar.
  for (let i = 0; i < seams; i++) {
    const t0 = i / seams;
    const t1 = (i + 1) / seams;
    const a0 = Math.PI * (1 - t0);
    const a1 = Math.PI * (1 - t1);

    const x0 = cx + Math.cos(a0) * eave;
    const y0 = wallTop + Math.sin(a0) * eaveRy;
    const x1 = cx + Math.cos(a1) * eave;
    const y1 = wallTop + Math.sin(a1) * eaveRy;

    const k0x = cx + Math.cos(a0) * crownRx;
    const k0y = peakY + Math.sin(a0) * crownRx * 0.5;
    const k1x = cx + Math.cos(a1) * crownRx;
    const k1y = peakY + Math.sin(a1) * crownRx * 0.5;

    const lum = 0.26 - t0 * 0.56;

    roofParts.push(`<path d="M${fmt(x0)},${fmt(y0)}
        A${fmt(eave)},${fmt(eaveRy)} 0 0 0 ${fmt(x1)},${fmt(y1)}
        L${fmt(k1x)},${fmt(k1y)}
        A${fmt(crownRx)},${fmt(crownRx * 0.5)} 0 0 1 ${fmt(k0x)},${fmt(k0y)} Z"
        fill="${shade(roof, lum)}"/>`);
  }

  // Çatı kaburgaları (uuk çıtaları)
  for (let i = 0; i <= seams; i += 2) {
    const a = Math.PI * (1 - i / seams);
    const x0 = cx + Math.cos(a) * eave;
    const y0 = wallTop + Math.sin(a) * eaveRy;
    const x1 = cx + Math.cos(a) * crownRx;
    const y1 = peakY + Math.sin(a) * crownRx * 0.5;
    roofParts.push(`<path d="M${fmt(x0)},${fmt(y0)} L${fmt(x1)},${fmt(y1)}"
        stroke="${shade(roof, -0.28)}" stroke-width="1.3" opacity="0.40"/>`);
  }

  /* --- Duvar (kerege): dikey keçe silindir ------------------------ */
  wallParts.push(`<path d="M${fmt(cx - rx)},${fmt(cy)}
      L${fmt(cx - rx)},${fmt(wallTop)}
      A${fmt(rx)},${fmt(ry)} 0 0 1 ${fmt(cx + rx)},${fmt(wallTop)}
      L${fmt(cx + rx)},${fmt(cy)}
      A${fmt(rx)},${fmt(ry)} 0 0 1 ${fmt(cx - rx)},${fmt(cy)} Z"
      fill="${shade(felt, -0.10)}"/>`);

  for (let i = 0; i < seams; i++) {
    const t0 = i / seams;
    const t1 = (i + 1) / seams;
    const a0 = Math.PI * (1 - t0);
    const a1 = Math.PI * (1 - t1);
    const x0 = cx + Math.cos(a0) * rx;
    const y0 = cy + Math.sin(a0) * ry;
    const x1 = cx + Math.cos(a1) * rx;
    const y1 = cy + Math.sin(a1) * ry;
    const lum = 0.18 - t0 * 0.46;

    wallParts.push(`<path d="M${fmt(x0)},${fmt(y0)}
        A${fmt(rx)},${fmt(ry)} 0 0 0 ${fmt(x1)},${fmt(y1)}
        L${fmt(x1)},${fmt(y1 - wallH)}
        A${fmt(rx)},${fmt(ry)} 0 0 1 ${fmt(x0)},${fmt(y0 - wallH)} Z"
        fill="${shade(felt, lum)}"/>`);
  }

  // Duvar üstü kilim kuşağı — çatının saçağının hemen altında
  wallParts.push(`<path d="M${fmt(cx - rx)},${fmt(wallTop + 2)}
      A${fmt(rx)},${fmt(ry)} 0 0 0 ${fmt(cx + rx)},${fmt(wallTop + 2)}
      L${fmt(cx + rx)},${fmt(wallTop + 11)}
      A${fmt(rx)},${fmt(ry)} 0 0 1 ${fmt(cx - rx)},${fmt(wallTop + 11)} Z"
      fill="${trim}" opacity="0.9"/>`);

  // Saçak alt çizgisi (çatının duvardan taştığı yer)
  wallParts.push(`<path d="M${fmt(cx - eave)},${fmt(wallTop)}
      A${fmt(eave)},${fmt(eaveRy)} 0 0 0 ${fmt(cx + eave)},${fmt(wallTop)}"
      fill="none" stroke="${shade(roof, -0.34)}" stroke-width="2" opacity="0.5"/>`);

  /* --- Tepe halkası (şanırak) ------------------------------------- */
  const crownParts = [
    `<ellipse cx="${fmt(cx)}" cy="${fmt(peakY)}"
      rx="${fmt(crownRx * 1.3)}" ry="${fmt(crownRx * 0.65)}" fill="${crown}"/>`,
    `<ellipse cx="${fmt(cx)}" cy="${fmt(peakY - 1)}"
      rx="${fmt(crownRx * 0.9)}" ry="${fmt(crownRx * 0.45)}" fill="#1b1206" opacity="0.75"/>`,
    `<path d="M${fmt(cx - crownRx * 0.9)},${fmt(peakY - 1)} L${fmt(cx + crownRx * 0.9)},${fmt(peakY - 1)}
      M${fmt(cx)},${fmt(peakY - 1 - crownRx * 0.45)} L${fmt(cx)},${fmt(peakY - 1 + crownRx * 0.45)}"
      stroke="${crown}" stroke-width="1.5" opacity="0.9"/>`,
  ];

  return {
    svg: `<g>${roofParts.join('')}${wallParts.join('')}${crownParts.join('')}</g>`,
    cx, cy, rx, ry, wallTop, peakY, eave,
  };
}

/** Otağ kapısı — keçe perde ve ahşap çerçeve. */
export function yurtDoor({ cx, cy, w = 34, h = 44, frame = '#5e472c', curtain = '#b7362c' }) {
  const half = w / 2;
  return `
    <g>
      <path d="M${fmt(cx - half)},${fmt(cy)} L${fmt(cx - half)},${fmt(cy - h + 10)}
               Q${fmt(cx)},${fmt(cy - h - 4)} ${fmt(cx + half)},${fmt(cy - h + 10)}
               L${fmt(cx + half)},${fmt(cy)} Z" fill="${frame}"/>
      <path d="M${fmt(cx - half + 5)},${fmt(cy)} L${fmt(cx - half + 5)},${fmt(cy - h + 12)}
               Q${fmt(cx)},${fmt(cy - h + 1)} ${fmt(cx + half - 5)},${fmt(cy - h + 12)}
               L${fmt(cx + half - 5)},${fmt(cy)} Z" fill="${curtain}"/>
      <path d="M${fmt(cx - half + 5)},${fmt(cy)} L${fmt(cx)},${fmt(cy - h + 14)}
               L${fmt(cx + half - 5)},${fmt(cy)} Z" fill="#000" opacity="0.18"/>
    </g>`;
}
