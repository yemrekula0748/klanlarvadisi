/**
 * Klanlar Vadisi renk paleti.
 *
 * Türk-Altay estetiğinden alınır: keçe beyazı, bozkır toprağı, kızıl
 * kilim, demir grisi, gök mavisi, altın sarısı. Bütün binalar ve arayüz
 * aynı palete bağlıdır; böylece oyun tek bir görsel dil konuşur.
 */

export const PALETTE = {
  // Yapı malzemeleri
  kece:        '#e9e1cf',  // keçe (çadır örtüsü)
  keceGolge:   '#cfc4ab',
  ahsap:       '#8a6a44',
  ahsapKoyu:   '#5e472c',
  tas:         '#8e8578',
  tasKoyu:     '#625b50',
  kerpic:      '#b08a5e',
  demir:       '#6c7480',
  demirKoyu:   '#464d57',
  toprak:      '#9c7a4e',

  // Kilim / süsleme renkleri
  kizil:       '#b7362c',
  kizilKoyu:   '#7f2520',
  altin:       '#e0a63c',
  altinKoyu:   '#a8762a',
  turkuaz:     '#2e8b93',
  lacivert:    '#28446b',
  yesil:       '#4f7a3a',
  mor:         '#6b4a7d',

  // Kaynak renkleri (arayüzle aynı)
  gold:        '#f0b63c',
  grain:       '#d9a94e',
  iron:        '#8f9aa8',
  kut:         '#7fd4e8',
  gokTasi:     '#6ee7a8',

  // Doğa
  cim:         '#6f9b4a',
  cimKoyu:     '#4e7334',
  kum:         '#d6bd86',
  su:          '#2d6f93',
  suKoyu:      '#1b4a6b',
  kar:         '#eef3f7',
  kaya:        '#7d7569',

  // Ateş / büyü
  ates:        '#f2763a',
  atesIc:      '#ffd98a',
  buyu:        '#8ad7ff',
  karanlik:    '#4a2d5a',
  kan:         '#8e1f1f',

  // Gölge / çizgi
  golge:       '#1b1206',
  cizgi:       '#2b2118',
};

/** Her biyom için harita rengi (dünya haritası arazi renkleri). */
export const BIOME_COLORS = {
  deniz:    ['#14395c', '#1b4a6b', '#225b80'],
  kiyi:     ['#c9b382', '#d6c192', '#b9a371'],
  bozkir:   ['#8fa05a', '#9cac66', '#7f904e'],
  cayir:    ['#6f9b4a', '#7eac56', '#5f8a3e'],
  orman:    ['#2f5d3a', '#376b44', '#264d30'],
  tayga:    ['#3b6b52', '#44785c', '#315c46'],
  dag:      ['#7d7569', '#8c8476', '#6b645a'],
  kar:      ['#e8eef2', '#f3f7fa', '#d8e2e9'],
  col:      ['#c58a4a', '#d29a5a', '#b57b3e'],
  bataklik: ['#55603a', '#616d44', '#4a5432'],
  nehir:    ['#2d6f93', '#3a7fa3', '#245b7a'],
};

/** Bina kategorisine göre vurgu rengi (arayüzde çerçeve rengi). */
export const CATEGORY_COLORS = {
  otag:    PALETTE.altin,
  kaynak:  PALETTE.yesil,
  depo:    PALETTE.altinKoyu,
  ordu:    PALETTE.lacivert,
  savunma: PALETTE.kizil,
  duvar:   PALETTE.tas,
  tuzak:   PALETTE.mor,
  ozel:    PALETTE.turkuaz,
};
