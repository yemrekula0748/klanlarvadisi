#!/usr/bin/env node
/**
 * Unity .meta dosyalarını üretir.
 *
 * Unity normalde bunları projeyi ilk açtığında kendisi yaratır; ancak
 * sahne dosyası betikleri GUID ile gösterdiğinden GUID'lerin sürüm
 * takibinde sabit olması gerekir. Burada her varlığın GUID'i yolunun
 * MD5'inden türetilir — aynı yol, her zaman aynı GUID.
 *
 *   node tools/unity/gen-meta.mjs           (var olanları korur)
 *   node tools/unity/gen-meta.mjs --zorla   (hepsini yeniden yazar)
 *
 * Izgaraya basan sprite'ların (bina, asker, süs, işaret) pivotu zemin
 * noktasına ayarlanır — ayrıntı: pivotSec().
 */

import sharp from 'sharp';
import { ASKER, SUS_ZEMIN_PAYI, zeminPivotu } from '../art/lib/anchors.mjs';
import { createHash } from 'node:crypto';
import { readdir, stat, writeFile, access } from 'node:fs/promises';
import { join, relative, extname, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const HERE = dirname(fileURLToPath(import.meta.url));
const ROOT = join(HERE, '..', '..');
const ASSETS = join(ROOT, 'unity-client', 'Assets');

/** Yoldan deterministik GUID üretir. */
function guid(relPath) {
  return createHash('md5').update('klanlarvadisi:' + relPath.replace(/\\/g, '/')).digest('hex');
}

/**
 * 9-dilim esnetilen görseller ve kenar kalınlıkları.
 *
 * Anahtarlar "Art/..." ile başlar; varlık yolu "Resources/Art/..." olduğu
 * için eşleştirmede sanatKuyrugu() kullanılır.
 */
const BORDERS = {
  'Art/UI/panel.png': [32, 32, 32, 32],
  'Art/UI/dugme.png': [24, 20, 24, 20],
  'Art/UI/dugme_yesil.png': [24, 20, 24, 20],
  'Art/UI/kaynak_cubugu.png': [24, 16, 24, 16],
  'Art/UI/can_cubugu.png': [10, 8, 10, 8],
};

/**
 * Bina sprite'ının pivotu.
 *
 * İzometrik binalar, ızgara köşesi (x,y) tuvalin belirli bir noktasına
 * gelecek biçimde çizilir (bkz. tools/art/lib/render.mjs -> wrap()):
 *
 *     tuval genişliği = 256*g + 48      (g = ayak izi kenarı, kare)
 *     çapa X          = genişlik / 2
 *     çapa Y (alttan) = 128*g + 24
 *
 * BinaGorunumu.cs binayı doğrudan Izometri.IzgaradanDunyaya(x, y)
 * konumuna koyar; yani sprite'ın pivotu TAM O KÖŞE olmalıdır. Pivot
 * merkezde (0.5, 0.5) bırakılırsa her bina, tuval yüksekliğine göre
 * farklı bir miktar kayar: Otağ 38 piksel yukarı, Altın Madeni 62 piksel
 * aşağı. Ölçüldü; bu yüzden pivot sprite başına hesaplanıyor.
 *
 * @returns {{x:number,y:number}|null} ölçü formüle uymazsa null (merkez kalır)
 */
function sanatKuyrugu(rel) {
  const i = rel.indexOf('Art/');
  return i === -1 ? rel : rel.slice(i);
}

/**
 * Bir sprite'ın Unity pivotu.
 *
 * Varsayılan merkez (0.5, 0.5) yalnızca arazi karoları ve arayüz görselleri
 * için doğrudur. Izgaraya basan her şeyin pivotu zemin noktasında olmalıdır;
 * yoksa sprite tuval yüksekliğine göre keyfî biçimde kayar.
 *
 * Zemin payları tools/art/lib/anchors.mjs içinde bir kez tanımlıdır.
 */
function pivotSec(rel, genislikPng, yukseklikPng) {
  const kuyruk = sanatKuyrugu(rel);
  const ad = kuyruk.split('/').pop().replace('.png', '');

  // Binalar: pivot ayak izinin arka köşesinde (ızgara (x,y) noktası)
  if (kuyruk.startsWith('Art/Buildings/')) {
    return binaPivotu(genislikPng, yukseklikPng);
  }

  // Askerler: pivot ayakta
  if (kuyruk.startsWith('Art/Troops/')) {
    return zeminPivotu(yukseklikPng / 2, ASKER.zeminPayi);
  }

  // Süsler ve harita işaretleri: pivot gövde dibinde / iğne ucunda.
  // Arazi karoları (biyomlar, nehir) karenin tam elmasıdır: merkez doğru.
  if (kuyruk.startsWith('Art/Terrain/') && (ad.startsWith('sus_') || ad.startsWith('isaret_'))) {
    return zeminPivotu(yukseklikPng / 2, SUS_ZEMIN_PAYI);
  }

  return null;
}

function binaPivotu(genislik, yukseklik) {
  if ((genislik - 48) % 256 !== 0) return null;

  const g = (genislik - 48) / 256;
  if (g < 1 || g > 8) return null;

  return { x: 0.5, y: (128 * g + 24) / yukseklik };
}

function klasorMeta(g) {
  return `fileFormatVersion: 2
guid: ${g}
folderAsset: yes
DefaultImporter:
  externalObjects: {}
  userData: 
  assetBundleName: 
  assetBundleVariant: 
`;
}

function betikMeta(g) {
  return `fileFormatVersion: 2
guid: ${g}
MonoImporter:
  externalObjects: {}
  serializedVersion: 2
  defaultReferences: []
  executionOrder: 0
  icon: {instanceID: 0}
  userData: 
  assetBundleName: 
  assetBundleVariant: 
`;
}

function dokuMeta(g, relPath, pivot = null) {
  const b = BORDERS[sanatKuyrugu(relPath)];
  const border = b ? `{x: ${b[0]}, y: ${b[1]}, z: ${b[2]}, w: ${b[3]}}` : '{x: 0, y: 0, z: 0, w: 0}';

  // alignment 0 = Center, 9 = Custom (özel pivot kullanıldığında gerekir)
  const hiza = pivot ? 9 : 0;
  const nokta = pivot
    ? `{x: ${pivot.x}, y: ${pivot.y.toFixed(6)}}`
    : '{x: 0.5, y: 0.5}';

  return `fileFormatVersion: 2
guid: ${g}
TextureImporter:
  internalIDToNameTable: []
  externalObjects: {}
  serializedVersion: 12
  mipmaps:
    mipMapMode: 0
    enableMipMap: 0
    sRGBTexture: 1
    linearTexture: 0
    fadeOut: 0
    borderMipMap: 0
    mipMapsPreserveCoverage: 0
    alphaTestReferenceValue: 0.5
    mipMapFadeDistanceStart: 1
    mipMapFadeDistanceEnd: 3
  bumpmap:
    convertToNormalMap: 0
    externalNormalMap: 0
    heightScale: 0.25
    normalMapFilter: 0
  isReadable: 0
  streamingMipmaps: 0
  streamingMipmapsPriority: 0
  vTOnly: 0
  ignoreMasterTextureLimit: 0
  grayScaleToAlpha: 0
  generateCubemap: 6
  cubemapConvolution: 0
  seamlessCubemap: 0
  textureFormat: 1
  maxTextureSize: 2048
  textureSettings:
    serializedVersion: 2
    filterMode: 1
    aniso: 1
    mipBias: 0
    wrapU: 1
    wrapV: 1
    wrapW: 1
  nPOTScale: 0
  lightmap: 0
  compressionQuality: 50
  spriteMode: 1
  spriteExtrude: 1
  spriteMeshType: 1
  alignment: ${hiza}
  spritePivot: ${nokta}
  spritePixelsToUnits: 128
  spriteBorder: ${border}
  spriteGenerateFallbackPhysicsShape: 1
  alphaUsage: 1
  alphaIsTransparency: 1
  spriteTessellationDetail: -1
  textureType: 8
  textureShape: 1
  singleChannelComponent: 0
  flipbookRows: 1
  flipbookColumns: 1
  maxTextureSizeSet: 0
  compressionQualitySet: 0
  textureFormatSet: 0
  ignorePngGamma: 0
  applyGammaDecoding: 0
  swizzle: 50462976
  cookieLightType: 0
  platformSettings:
  - serializedVersion: 3
    buildTarget: DefaultTexturePlatform
    maxTextureSize: 2048
    resizeAlgorithm: 0
    textureFormat: -1
    textureCompression: 1
    compressionQuality: 50
    crunchedCompression: 0
    allowsAlphaSplitting: 0
    overridden: 0
    ignorePlatformSupport: 0
    androidETC2FallbackOverride: 0
    forceMaximumCompressionQuality_BC6H_BC7: 0
  spriteSheet:
    serializedVersion: 2
    sprites: []
    outline: []
    physicsShape: []
    bones: []
    spriteID: 5e97eb03825dee720800000000000000
    internalID: 0
    vertices: []
    indices: 
    edges: []
    weights: []
    secondaryTextures: []
    nameFileIdTable: {}
  mipmapLimitGroupName: 
  pSDRemoveMatte: 0
  userData: 
  assetBundleName: 
  assetBundleVariant: 
`;
}

function varsayilanMeta(g) {
  return `fileFormatVersion: 2
guid: ${g}
DefaultImporter:
  externalObjects: {}
  userData: 
  assetBundleName: 
  assetBundleVariant: 
`;
}

async function varMi(p) {
  try { await access(p); return true; } catch { return false; }
}

const ZORLA = process.argv.includes('--zorla');

let yazilan = 0;
let atlanan = 0;
let pivotlu = 0;

async function gez(dir) {
  const girdiler = await readdir(dir, { withFileTypes: true });

  for (const e of girdiler) {
    if (e.name.endsWith('.meta')) continue;
    if (e.name.startsWith('.')) continue;

    const tam = join(dir, e.name);
    const rel = relative(ASSETS, tam).replace(/\\/g, '/');
    const metaYol = tam + '.meta';
    const g = guid(rel);

    let icerik;
    if (e.isDirectory()) {
      icerik = klasorMeta(g);
      await gez(tam);
    } else {
      const uzanti = extname(e.name).toLowerCase();

      if (uzanti === '.png') {
        // Yalnızca izometrik bina sprite'larının pivotu özel; arazi karoları
        // kare merkezine, asker ve arayüz görselleri de merkeze hizalanır.
        const olcu = await sharp(tam).metadata();
        const pivot = pivotSec(rel, olcu.width, olcu.height);

        if (pivot) pivotlu++;
        else if (sanatKuyrugu(rel).startsWith('Art/Buildings/')) {
          console.warn(`  uyarı: ${rel} ölçüsü (${olcu.width}x${olcu.height}) bina formülüne uymuyor, pivot merkezde bırakıldı.`);
        }

        icerik = dokuMeta(g, rel, pivot);
      } else {
        icerik = uzanti === '.cs' ? betikMeta(g) : varsayilanMeta(g);
      }
    }

    // Var olan .meta dosyalarını koru (Unity düzenlemiş olabilir)
    if (!ZORLA && await varMi(metaYol)) { atlanan++; continue; }

    await writeFile(metaYol, icerik, 'utf8');
    yazilan++;
  }
}

await gez(ASSETS);

console.log(`Unity .meta dosyaları: ${yazilan} yazıldı, ${atlanan} korundu.`);
console.log(`Zemin pivotu hesaplanan sprite: ${pivotlu}`);

// Sahne dosyasında gereken betik GUID'lerini yazdır
const bootstrapGuid = guid('Scripts/Core/OyunBaslatici.cs');
console.log(`OyunBaslatici GUID: ${bootstrapGuid}`);
