/**
 * Sprite çapaları (pivot) — TEK KAYNAK.
 *
 * Bir sprite'ın "zemin noktası", onun ızgarada hangi piksele basacağını
 * söyler: askerin ayağı, ağacın gövde dibi, binanın arka köşesi. Unity bu
 * bilgiyi .meta dosyasındaki `spritePivot` alanından okur.
 *
 * Bu değerler iki yerde gerekiyor: görselleri çizen SVG kodu
 * (tools/art/*.mjs) ve .meta üreteci (tools/unity/gen-meta.mjs). İkisi
 * ayrı ayrı tanımlandığında kaçınılmaz olarak birbirinden kayıyor —
 * nitekim kaydı: tüm askerler pivotu merkezde kalmış olduğu için ızgaraya
 * yarım beden gömülü duruyordu. O yüzden burada bir kez yazılır.
 *
 * Ölçüler SVG birimidir; PNG'ler 2 kat ölçekle üretilir (render.mjs).
 */

/** Asker sprite tuvali ve ayak noktası. */
export const ASKER = {
  w: 96,
  h: 112,
  /** Ayak noktasının tuval altından yüksekliği. */
  zeminPayi: 10,
};

/** Süs ve harita işaretlerinde gölge, tuval altından bu kadar yukarıdadır. */
export const SUS_ZEMIN_PAYI = 8;

/**
 * Unity pivotu: tuval altından ölçülen zemin payını, normalleştirilmiş
 * (0..1, alttan) pivot değerine çevirir.
 */
export function zeminPivotu(yukseklikSvg, zeminPayi) {
  return { x: 0.5, y: zeminPayi / yukseklikSvg };
}
