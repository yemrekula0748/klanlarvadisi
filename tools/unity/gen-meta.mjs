#!/usr/bin/env node
/**
 * Unity .meta dosyalarını üretir.
 *
 * Unity normalde bunları projeyi ilk açtığında kendisi yaratır; ancak
 * sahne dosyası betikleri GUID ile gösterdiğinden GUID'lerin sürüm
 * takibinde sabit olması gerekir. Burada her varlığın GUID'i yolunun
 * MD5'inden türetilir — aynı yol, her zaman aynı GUID.
 *
 *   node tools/unity/gen-meta.mjs
 */

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

/** 9-dilim esnetilen görseller ve kenar kalınlıkları. */
const BORDERS = {
  'Art/UI/panel.png': [32, 32, 32, 32],
  'Art/UI/dugme.png': [24, 20, 24, 20],
  'Art/UI/dugme_yesil.png': [24, 20, 24, 20],
  'Art/UI/kaynak_cubugu.png': [24, 16, 24, 16],
  'Art/UI/can_cubugu.png': [10, 8, 10, 8],
};

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

function dokuMeta(g, relPath) {
  const b = BORDERS[relPath];
  const border = b ? `{x: ${b[0]}, y: ${b[1]}, z: ${b[2]}, w: ${b[3]}}` : '{x: 0, y: 0, z: 0, w: 0}';

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
  alignment: 0
  spritePivot: {x: 0.5, y: 0.5}
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

let yazilan = 0;
let atlanan = 0;

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
      icerik = uzanti === '.cs' ? betikMeta(g)
        : uzanti === '.png' ? dokuMeta(g, rel)
        : varsayilanMeta(g);
    }

    // Var olan .meta dosyalarını koru (Unity düzenlemiş olabilir)
    if (await varMi(metaYol)) { atlanan++; continue; }

    await writeFile(metaYol, icerik, 'utf8');
    yazilan++;
  }
}

await gez(ASSETS);

console.log(`Unity .meta dosyaları: ${yazilan} yazıldı, ${atlanan} korundu.`);

// Sahne dosyasında gereken betik GUID'lerini yazdır
const bootstrapGuid = guid('Scripts/Core/OyunBaslatici.cs');
console.log(`OyunBaslatici GUID: ${bootstrapGuid}`);
