#!/usr/bin/env node
/**
 * Oba önizlemesi — Unity kurmadan oyunun nasıl göründüğünü gösterir.
 *
 * Gerçek sprite'ları (unity-client/Assets/Resources/Art) ve gerçek sunucu
 * yerleşimini (`php artisan game:onizleme-duzen`) kullanır; Unity'de
 * ObaGorunumu + BinaGorunumu'nun yaptığı işi sharp ile tek PNG'ye birleştirir.
 *
 *   node tools/preview/oba.mjs --otag=10
 *   node tools/preview/oba.mjs --otag=14 --tohum=7
 *   node tools/preview/oba.mjs --json=/tmp/duzen.json
 *   node tools/preview/oba.mjs --yardim
 *
 * İZOMETRİK MATEMATİK
 * -------------------
 * Sprite'lar `tools/art` ile scale=2 üretildi; yani PNG'ler SVG'nin iki
 * katı. Dolayısıyla PNG piksel düzleminde bir kare 256x128'dir ve yarım
 * ölçüler 128 / 64 olur (bkz. tools/art/lib/iso.mjs, TILE_W=128, TILE_H=64).
 *
 *   Izgara köşesi (x,y)   -> ekranX = (x - y) * 128
 *                            ekranY = (x + y) * 64
 *   Karenin (x,y) merkezi -> ((x - y) * 128, (x + y + 1) * 64)
 *
 * Bina sprite'ının çapası (ızgara köşesinin sprite içinde oturduğu nokta),
 * `tools/art/lib/render.mjs::wrap` içindeki offset'in iki katıdır:
 *
 *   capaX = PNG_genislik / 2
 *   capaY = PNG_yukseklik - (128 * g + 24)      // 24 = pad(12) * scale(2)
 *
 * Kontrol: g=3 -> PNG genişliği 256*3+48 = 816  (boy_otagi 816x892 ✓)
 */

import { spawnSync } from 'node:child_process';
import { mkdir, readFile, stat } from 'node:fs/promises';
import { dirname, join, relative } from 'node:path';
import { fileURLToPath } from 'node:url';
import sharp from 'sharp';
import { BIOME_COLORS } from '../art/lib/palette.mjs';

const HERE = dirname(fileURLToPath(import.meta.url));
const ROOT = join(HERE, '..', '..');
const ART = join(ROOT, 'unity-client', 'Assets', 'Resources', 'Art');

/* ------------------------------------------------------------------ */
/* Sabitler                                                            */
/* ------------------------------------------------------------------ */

/** Karenin yarı genişliği/yüksekliği, PNG pikseli (scale=2). */
const YARIM_G = 128;
const YARIM_Y = 64;

/** `wrap()` içindeki pad=12'nin PNG karşılığı. */
const CAPA_PAYI = 24;

/** Süs sprite'ının gölge elipsi: SVG'de cy = h - 8, PNG'de h - 16. */
const SUS_AYAK_PAYI = 16;

/** Tuvalin dört yanındaki boşluk (bina sprite'ları yukarı taşar). */
const PAY_YAN = 640;
const PAY_UST = 1200;
const PAY_ALT = 320;

/** Boş karelere serpilen süsler ve bağıl sıklıkları. */
const SUSLER = [
  { ad: 'sus_agac', agirlik: 5 },
  { ad: 'sus_kaya', agirlik: 2 },
];

/* ------------------------------------------------------------------ */
/* Komut satırı                                                        */
/* ------------------------------------------------------------------ */

const YARDIM = `
Oba önizlemesi — gerçek sprite'lar + gerçek sunucu yerleşimi -> PNG

Kullanım:
  node tools/preview/oba.mjs [seçenekler]

Seçenekler:
  --otag=N            Otağ seviyesi (1-15, varsayılan 10). Düzeni artisan
                      komutundan alır: php artisan game:onizleme-duzen
  --json=YOL          Hazır düzen JSON'u oku ('-' = stdin). artisan'ı
                      çalıştırmaz.
  --cikti=DIZIN       Çıktı dizini (varsayılan docs/onizleme)
  --kucuk-genislik=N  Küçük sürümün genişliği (varsayılan 1600, 0 = üretme)
  --tam-genislik=N    Tam sürümü bu genişliğe sığdır (0 = küçültme, varsayılan)
  --sus-orani=0..1    Boş karelerin süs alma olasılığı (varsayılan 0.07)
  --tohum=N           Süs dağılımının rastgelelik tohumu (varsayılan 1071)
  --kirp=KIP          'icerik' (varsayılan) binaların çevresine kırpar ve
                      kadrajı doldurmak için ızgara dışına da çayır döşer;
                      'zemin' tam olarak 44x44 ızgarayı bırakır
  --pay=N             İçerik kırpmasında çevrede bırakılan kare sayısı (2)
  --palet=0           PNG'yi 256 renge indirgemeyi kapat. Varsayılan açık:
                      düz renkli çizimde gözle fark edilmez ama dosyayı
                      ~3,5 kat küçültür (12,2 MB -> 3,6 MB).
  --siralama=KIP      Derinlik sırası: 'merkez' (Unity ile aynı, varsayılan)
                      ya da 'kose' (x+y)
  --arazi=AD          Zemin karosu (varsayılan cayir)
  --yardim, -y        Bu metni göster

Çıktı:
  docs/onizleme/oba.png         ayrıntılı (varsayılan 3200 px; --tam-genislik=0 ham)
  docs/onizleme/oba-kucuk.png   paylaşmak için küçültülmüş
`.trimStart();

function secenekleriAyristir(argv) {
  const s = {
    otag: 10,
    json: null,
    cikti: join(ROOT, 'docs', 'onizleme'),
    kucukGenislik: 1600,
    tamGenislik: 3200,   // 0 = küçültme yok (ham tuval ~8600 px, ~3,3 MB)
    susOrani: 0.07,
    tohum: 1071,
    siralama: 'merkez',
    arazi: 'cayir',
    kirp: 'icerik',
    pay: 2,
    palet: true,
    yardim: false,
  };

  for (const arg of argv) {
    const [ad, deger = ''] = arg.replace(/^--?/, '').split('=');

    switch (ad) {
      case 'yardim': case 'y': case 'help': case 'h': s.yardim = true; break;
      case 'otag': s.otag = Number(deger); break;
      case 'json': s.json = deger; break;
      case 'cikti': s.cikti = deger.startsWith('/') ? deger : join(ROOT, deger); break;
      case 'kucuk-genislik': s.kucukGenislik = Number(deger); break;
      case 'tam-genislik': s.tamGenislik = Number(deger); break;
      case 'sus-orani': s.susOrani = Number(deger); break;
      case 'tohum': s.tohum = Number(deger); break;
      case 'siralama': s.siralama = deger; break;
      case 'arazi': s.arazi = deger; break;
      case 'kirp': s.kirp = deger; break;
      case 'pay': s.pay = Number(deger); break;
      case 'palet': s.palet = !['0', 'hayir', 'false'].includes(deger); break;
      default:
        throw new Error(`Bilinmeyen seçenek: ${arg}\n\n${YARDIM}`);
    }
  }

  if (!Number.isInteger(s.otag) || s.otag < 1 || s.otag > 15) {
    throw new Error(`--otag 1-15 arasında bir tam sayı olmalı: ${s.otag}`);
  }
  if (!['merkez', 'kose'].includes(s.siralama)) {
    throw new Error(`--siralama 'merkez' ya da 'kose' olmalı: ${s.siralama}`);
  }
  if (!['icerik', 'zemin'].includes(s.kirp)) {
    throw new Error(`--kirp 'icerik' ya da 'zemin' olmalı: ${s.kirp}`);
  }

  return s;
}

/* ------------------------------------------------------------------ */
/* Düzen kaynağı                                                       */
/* ------------------------------------------------------------------ */

/** Düzeni JSON dosyasından ya da artisan komutundan alır. */
async function duzenAl(s) {
  if (s.json) {
    const ham = s.json === '-'
      ? await new Response(process.stdin).text()
      : await readFile(s.json, 'utf8');

    return { duzen: JSON.parse(ham), kaynak: s.json === '-' ? 'stdin' : s.json };
  }

  const komut = ['artisan', 'game:onizleme-duzen', `--otag=${s.otag}`];
  const r = spawnSync('php', komut, {
    cwd: ROOT,
    encoding: 'utf8',
    maxBuffer: 64 * 1024 * 1024,
  });

  if (r.error) throw r.error;
  if (r.stderr) process.stderr.write(r.stderr);
  if (r.status !== 0) {
    throw new Error(`php ${komut.join(' ')} başarısız (çıkış ${r.status})`);
  }

  return { duzen: JSON.parse(r.stdout), kaynak: `php ${komut.join(' ')}` };
}

/* ------------------------------------------------------------------ */
/* İzometrik yerleştirme                                               */
/* ------------------------------------------------------------------ */

/** Izgara köşesinin (x,y) PNG piksel düzlemindeki yeri. */
const kose = (x, y) => [(x - y) * YARIM_G, (x + y) * YARIM_Y];

/** Karenin (x,y) elmasının merkezi. */
const kareMerkezi = (x, y) => [(x - y) * YARIM_G, (x + y + 1) * YARIM_Y];

/** Bina sprite'ının çapası: ızgara köşesinin sprite içindeki yeri. */
const binaCapasi = (g, pngG, pngY) => [pngG / 2, pngY - (YARIM_G * g + CAPA_PAYI)];

/**
 * İçerik kutusunu `pay` kare genişletip tuvale kıstırır. Kenarlarda bir
 * karo payı bırakılır; kadrajı dolduran karolar tuvalin dışına taşmasın.
 */
function kirpmaKutusu(icerik, pay, tuvalG, tuvalY) {
  const sinir = 2 * YARIM_G;

  const sol = Math.max(sinir, Math.round(icerik.sol - pay * 2 * YARIM_G));
  const ust = Math.max(sinir, Math.round(icerik.ust - pay * 2 * YARIM_Y));
  const sag = Math.min(tuvalG - sinir, Math.round(icerik.sag + pay * 2 * YARIM_G));
  const alt = Math.min(tuvalY - sinir, Math.round(icerik.alt + pay * 2 * YARIM_Y));

  return { left: sol, top: ust, width: sag - sol, height: alt - ust };
}

/** Kırpma kutusunu kaplamak için gereken ızgara aralığı [en küçük, en büyük]. */
function karoAraligi(kutu, kaydirmaX, kaydirmaY) {
  const koseler = [
    [kutu.left, kutu.top],
    [kutu.left + kutu.width, kutu.top],
    [kutu.left, kutu.top + kutu.height],
    [kutu.left + kutu.width, kutu.top + kutu.height],
  ];

  let enKucuk = Infinity;
  let enBuyuk = -Infinity;

  for (const [px, py] of koseler) {
    // kareMerkezi'nin tersi: ekranX = (x-y)*128, ekranY = (x+y+1)*64
    const fark = (px - kaydirmaX) / YARIM_G;
    const toplam = (py - kaydirmaY) / YARIM_Y - 1;

    enKucuk = Math.min(enKucuk, (toplam + fark) / 2, (toplam - fark) / 2);
    enBuyuk = Math.max(enBuyuk, (toplam + fark) / 2, (toplam - fark) / 2);
  }

  return [Math.floor(enKucuk) - 1, Math.ceil(enBuyuk) + 1];
}

/** Öğe, kırpma kutusunun (içeri çekilmiş) sınırları içinde mi? */
const icinde = (o, kutu, pay = 0) =>
  o.sol >= kutu.left + pay && o.ust >= kutu.top + pay
  && o.sol + o.kayit.g <= kutu.left + kutu.width - pay
  && o.ust + o.kayit.y <= kutu.top + kutu.height - pay;

/** İki dikdörtgen kesişiyor mu? */
const kesisiyor = (sol, ust, g, y, kutu) =>
  sol < kutu.left + kutu.width && sol + g > kutu.left
  && ust < kutu.top + kutu.height && ust + y > kutu.top;

/** Düz renkli dikdörtgen dolgu. */
const dolguSvg = (g, y, renk) => Buffer.from(
  `<svg xmlns="http://www.w3.org/2000/svg" width="${g}" height="${y}">`
  + `<rect width="${g}" height="${y}" fill="${renk}"/></svg>`
);

/** Izgaranın tamamını kaplayan düz renkli elmas. */
const elmasSvg = (g, y, renk) => Buffer.from(
  `<svg xmlns="http://www.w3.org/2000/svg" width="${g}" height="${y}">`
  + `<path d="M${g / 2},0 L${g},${y / 2} L${g / 2},${y} L0,${y / 2} Z" fill="${renk}"/></svg>`
);

/** Sprite önbelleği: yol -> { girdi (Buffer), g, y }. */
const onbellek = new Map();

async function spriteYukle(grup, ad) {
  const yol = join(ART, grup, `${ad}.png`);
  if (onbellek.has(yol)) return onbellek.get(yol);

  const girdi = await readFile(yol);
  const ustveri = await sharp(girdi).metadata();
  const kayit = { girdi, g: ustveri.width, y: ustveri.height, yol };

  onbellek.set(yol, kayit);

  return kayit;
}

/** mulberry32 — küçük, deterministik rastgele sayı üreteci. */
function rastgele(tohum) {
  let a = tohum >>> 0;

  return () => {
    a = (a + 0x6d2b79f5) >>> 0;
    let t = a;
    t = Math.imul(t ^ (t >>> 15), t | 1);
    t ^= t + Math.imul(t ^ (t >>> 7), t | 61);

    return ((t ^ (t >>> 14)) >>> 0) / 4294967296;
  };
}

/**
 * Binaların kapladığı kareleri işaretler.
 *
 * @returns {Uint8Array} izgara*izgara doluluk haritası
 */
function dolulukHaritasi(binalar, izgara) {
  const dolu = new Uint8Array(izgara * izgara);

  for (const b of binalar) {
    const gy = b.grid_h ?? b.grid;
    for (let dy = 0; dy < gy; dy++) {
      for (let dx = 0; dx < b.grid; dx++) {
        const x = b.x + dx;
        const y = b.y + dy;
        if (x >= 0 && y >= 0 && x < izgara && y < izgara) dolu[y * izgara + x] = 1;
      }
    }
  }

  return dolu;
}

/**
 * Süs konabilir mi?
 *
 * Süs sprite'ı ayak noktasının ~144 piksel ÜSTÜNE uzanır; bu da iki kare
 * ARKAYA denk gelir. Arkadaki bina daha erken çizildiği için süs onun
 * üstüne biner. Bunu önlemek için karenin kendisi ve arkasındaki 2x2 komşu
 * blok boş olmalı.
 */
function susKonabilir(dolu, izgara, x, y) {
  for (let dy = -2; dy <= 0; dy++) {
    for (let dx = -2; dx <= 0; dx++) {
      const nx = x + dx;
      const ny = y + dy;
      if (nx < 0 || ny < 0) continue;
      if (dolu[ny * izgara + nx]) return false;
    }
  }

  return true;
}

/* ------------------------------------------------------------------ */
/* Ana akış                                                            */
/* ------------------------------------------------------------------ */

async function main() {
  const s = secenekleriAyristir(process.argv.slice(2));

  if (s.yardim) {
    process.stdout.write(YARDIM);
    return;
  }

  const { duzen, kaynak } = await duzenAl(s);
  const izgara = duzen.grid;
  const binalar = duzen.buildings ?? [];

  if (!Number.isInteger(izgara) || izgara < 1) {
    throw new Error(`Düzen JSON'unda geçerli 'grid' yok: ${izgara}`);
  }
  if (binalar.length === 0) {
    throw new Error('Düzen JSON\'unda hiç bina yok.');
  }

  console.log('Klanlar Vadisi — oba önizlemesi');
  console.log(`  düzen     : ${kaynak}`);
  console.log(`  ızgara    : ${izgara}x${izgara}, ${binalar.length} bina`);
  console.log(`  sıralama  : ${s.siralama}`);

  if (duzen.overlaps?.length) {
    console.log(`  ÇAKIŞMA   : ${duzen.overlaps.length} (sunucu yerleşiminde!)`);
  }

  /* --- Tuval ------------------------------------------------------ */

  const kaydirmaX = izgara * YARIM_G + PAY_YAN;
  const kaydirmaY = PAY_UST;
  const tuvalG = 2 * izgara * YARIM_G + 2 * PAY_YAN;
  const tuvalY = 2 * izgara * YARIM_Y + PAY_UST + PAY_ALT;

  console.log(`  tuval     : ${tuvalG}x${tuvalY}`);

  const katmanlar = [];

  /** Tuval sınırlarını denetleyerek katmana ekler. */
  const koy = (kayit, solF, ustF, etiket) => {
    const sol = Math.round(solF);
    const ust = Math.round(ustF);

    if (sol < 0 || ust < 0 || sol + kayit.g > tuvalG || ust + kayit.y > tuvalY) {
      throw new Error(
        `${etiket} tuvali aşıyor: (${sol},${ust}) ${kayit.g}x${kayit.y} / tuval ${tuvalG}x${tuvalY}`
      );
    }

    katmanlar.push({ input: kayit.girdi, left: sol, top: ust });
  };

  /* --- 1) Süsler ve binalar: tek listede derinlik sırasıyla --------- */

  const ogeler = [];

  for (const b of binalar) {
    const kayit = await spriteYukle('Buildings', b.key);
    const beklenenG = 2 * YARIM_G * b.grid + 2 * CAPA_PAYI;

    if (kayit.g !== beklenenG) {
      throw new Error(
        `${b.key} PNG genişliği ${kayit.g}, g=${b.grid} için beklenen ${beklenenG}`
      );
    }

    const [ax, ay] = kose(b.x, b.y);
    const [cx, cy] = binaCapasi(b.grid, kayit.g, kayit.y);

    // Derinlik: 'merkez' Unity'deki BinaGorunumu.Guncelle ile birebir aynı
    // (SiralamaDegeri(x + w/2, y + h/2)); 'kose' ham (x+y).
    const derinlik = s.siralama === 'merkez' ? b.x + b.y + b.grid : b.x + b.y;

    ogeler.push({
      tur: 'bina',
      derinlik,
      ikincil: b.x,
      kayit,
      sol: kaydirmaX + ax - cx,
      ust: kaydirmaY + ay - cy,
      etiket: `${b.key} ${b.x},${b.y}`,
    });
  }

  const dolu = dolulukHaritasi(binalar, izgara);
  const rnd = rastgele(s.tohum);
  const agirlikToplam = SUSLER.reduce((t, x) => t + x.agirlik, 0);

  for (let y = 0; y < izgara; y++) {
    for (let x = 0; x < izgara; x++) {
      const zar = rnd();
      const sec = rnd() * agirlikToplam;

      if (zar >= s.susOrani) continue;
      if (!susKonabilir(dolu, izgara, x, y)) continue;

      let esik = 0;
      const sus = SUSLER.find((o) => (esik += o.agirlik) > sec) ?? SUSLER[0];
      const kayit = await spriteYukle('Terrain', sus.ad);
      const [mx, my] = kareMerkezi(x, y);

      ogeler.push({
        tur: 'sus',
        derinlik: s.siralama === 'merkez' ? x + y + 1 : x + y,
        ikincil: x,
        kayit,
        sol: kaydirmaX + mx - kayit.g / 2,
        ust: kaydirmaY + my - (kayit.y - SUS_AYAK_PAYI),
        etiket: `${sus.ad} ${x},${y}`,
      });
    }
  }

  // İçerik kutusu: yalnızca BİNALAR. Süsler kenarlara dağıldığı için
  // kırpmayı genişletmemeli.
  const icerik = { sol: Infinity, ust: Infinity, sag: -Infinity, alt: -Infinity };
  for (const b of binalar) {
    const kayit = onbellek.get(join(ART, 'Buildings', `${b.key}.png`));
    const [ax, ay] = kose(b.x, b.y);
    const [cx, cy] = binaCapasi(b.grid, kayit.g, kayit.y);

    icerik.sol = Math.min(icerik.sol, kaydirmaX + ax - cx);
    icerik.ust = Math.min(icerik.ust, kaydirmaY + ay - cy);
    icerik.sag = Math.max(icerik.sag, kaydirmaX + ax - cx + kayit.g);
    icerik.alt = Math.max(icerik.alt, kaydirmaY + ay - cy + kayit.y);
  }

  /* --- 2) Kırpma kutusu ------------------------------------------- */

  // Kutuyu zeminden ÖNCE bilmemiz gerekiyor: 'icerik' kipinde zemin,
  // kadrajı dolduracak kadar karo döşer.
  const kutu = s.kirp === 'icerik'
    ? kirpmaKutusu(icerik, s.pay, tuvalG, tuvalY)
    : null;

  console.log(kutu
    ? `  kırpma    : içerik ${kutu.width}x${kutu.height} (+${s.pay} kare pay)`
    : '  kırpma    : zemin (saydam kenarlar atılır)');

  /* --- 3) Zemin: önce düz dolgu, sonra karolar --------------------- */

  // Karolar elması tam kaplar ama kenarlarda 1 piksellik saydam dikişler
  // kalabilir. Altlarına düz bir dolgu koyarak "zeminde delik" ihtimalini
  // tamamen kaldırıyoruz.
  const zeminRengi = (BIOME_COLORS[s.arazi] ?? BIOME_COLORS.cayir)[0];
  const karo = await spriteYukle('Terrain', s.arazi);

  // ObaGorunumu.ZeminiCiz komşu karelere ton farkı uyguluyor:
  //     var ton = ((x + y) % 2 == 0) ? 1f : 0.96f;
  // Önizlemede bu yoktu, zemin Unity'dekinden daha düz görünüyordu.
  // (Karonun kendi koyu kenar konturu kasıtlıdır — terrain.mjs onu
  // çiziyor ve oyunda da görünür; o yüzden kaldırılmıyor.)
  const karoKoyu = {
    ...karo,
    girdi: await sharp(karo.girdi).linear([0.96, 0.96, 0.96, 1], [0, 0, 0, 0]).png().toBuffer(),
  };

  if (karo.g !== 2 * YARIM_G || karo.y !== 2 * YARIM_Y) {
    throw new Error(`Arazi karosu ${karo.g}x${karo.y}, beklenen ${2 * YARIM_G}x${2 * YARIM_Y}`);
  }

  if (kutu) {
    // Dikdörtgen kadraj: dolgu da dikdörtgen.
    koy(
      { girdi: dolguSvg(kutu.width, kutu.height, zeminRengi), g: kutu.width, y: kutu.height },
      kutu.left, kutu.top, 'zemin dolgusu'
    );
  } else {
    // Tam ızgara: dolgu, 44x44'ün elması.
    const elmasG = 2 * izgara * YARIM_G;
    const elmasY = 2 * izgara * YARIM_Y;

    koy(
      { girdi: elmasSvg(elmasG, elmasY, zeminRengi), g: elmasG, y: elmasY },
      kaydirmaX - izgara * YARIM_G, kaydirmaY, 'zemin elması'
    );
  }

  // 'icerik' kipinde ızgaranın DIŞINA da karo döşenir: kadrajın köşeleri
  // 44x44 elmasının dışına düşer ve saydam kalırdı. Bu karolar yalnızca
  // çerçeveyi doldurur, oyunun ızgarasına dahil değildir.
  const [k0, k1] = kutu
    ? karoAraligi(kutu, kaydirmaX, kaydirmaY)
    : [0, izgara - 1];
  let karoSayisi = 0;

  for (let y = k0; y <= k1; y++) {
    for (let x = k0; x <= k1; x++) {
      const [mx, my] = kareMerkezi(x, y);
      const sol = kaydirmaX + mx - karo.g / 2;
      const ust = kaydirmaY + my - karo.y / 2;

      if (kutu && !kesisiyor(sol, ust, karo.g, karo.y, kutu)) continue;

      koy((x + y) % 2 === 0 ? karo : karoKoyu, sol, ust, `karo ${x},${y}`);
      karoSayisi++;
    }
  }

  /* --- 4) Üst katman: derinlik sırasıyla --------------------------- */

  // Kadrajın kenarına yapışan süs kırpılmış gibi durur: kutunun yarım
  // karo içine çekilmiş sınırları dışında kalan süsleri at. Binalar
  // kutuyu zaten kendileri tanımladığı için hepsi içeride.
  const cizilecek = kutu
    ? ogeler.filter((o) => o.tur === 'bina' || icinde(o, kutu, YARIM_Y))
    : ogeler;

  // Arkadaki önce: derinlik artan, eşitlikte x artan.
  cizilecek.sort((a, b) => a.derinlik - b.derinlik || a.ikincil - b.ikincil);

  for (const o of cizilecek) koy(o.kayit, o.sol, o.ust, o.etiket);

  const susSayisi = cizilecek.filter((o) => o.tur === 'sus').length;
  console.log(`  süs       : ${susSayisi} (oran ${s.susOrani}, tohum ${s.tohum})`);
  console.log(`  karo      : ${karoSayisi}`);
  console.log(`  katman    : ${katmanlar.length}`);

  /* --- 3) Birleştir, kırp, yaz ------------------------------------- */

  await mkdir(s.cikti, { recursive: true });

  const tamYol = join(s.cikti, 'oba.png');
  const kucukYol = join(s.cikti, 'oba-kucuk.png');

  // Birleştirme ve kırpma AYRI geçişler olmak zorunda: sharp işlemleri
  // çağrı sırasına değil kendi sabit sırasına göre uygular ve trim()
  // composite()'ten ÖNCE çalışır — aynı boruda kırpma hiçbir şey yapmaz.
  const hamTuval = await sharp({
    create: {
      width: tuvalG,
      height: tuvalY,
      channels: 4,
      background: { r: 0, g: 0, b: 0, alpha: 0 },
    },
    limitInputPixels: false,
  })
    .composite(katmanlar)
    .png({ compressionLevel: 0 })
    .toBuffer();

  let boru = sharp(hamTuval, { limitInputPixels: false });

  // Kırpma: 'icerik' binaların çevresine, 'zemin' saydam kenarlara göre.
  boru = kutu
    ? boru.extract(kutu)
    : boru.trim({ background: '#00000000', threshold: 0 });

  if (s.tamGenislik > 0) {
    boru = boru.resize({ width: s.tamGenislik, fit: 'inside' });
  }

  // Çizim düz renkli olduğu için 256 renk paleti gözle ayırt edilemez
  // ama dosyayı birkaç kat küçültür (ölçüldü: 12,2 MB -> 3,6 MB).
  const pngSecenek = s.palet
    ? { compressionLevel: 9, palette: true, colours: 256, dither: 1 }
    : { compressionLevel: 9 };

  const tam = await boru.png(pngSecenek).toFile(tamYol);
  console.log(`\n  ${relative(ROOT, tamYol)}  ${tam.width}x${tam.height}  ${kb(await boyut(tamYol))}`);

  if (s.kucukGenislik > 0) {
    const kucuk = await sharp(tamYol, { limitInputPixels: false })
      .resize({ width: s.kucukGenislik, fit: 'inside' })
      .png(pngSecenek)
      .toFile(kucukYol);

    console.log(`  ${relative(ROOT, kucukYol)}  ${kucuk.width}x${kucuk.height}  ${kb(await boyut(kucukYol))}`);
  }
}

const boyut = async (yol) => (await stat(yol)).size;
const kb = (n) => `${(n / 1024 / 1024).toFixed(2)} MB`;

main().catch((e) => {
  process.stderr.write(`\nHATA: ${e.message}\n`);
  process.exit(1);
});
