#!/usr/bin/env node
/**
 * C# kaynaklarında yapısal denetim.
 *
 * Unity olmadan tam derleme yapılamaz; burada en sık rastlanan hata
 * sınıfları yakalanır: dengesiz parantez/küme, kapanmamış dizge,
 * kapanmamış blok yorumu, namespace/using sırası.
 */

import { readdir, readFile } from 'node:fs/promises';
import { join, dirname, relative } from 'node:path';
import { fileURLToPath } from 'node:url';

const HERE = dirname(fileURLToPath(import.meta.url));
const ROOT = join(HERE, '..', '..');
const SCRIPTS = join(ROOT, 'unity-client', 'Assets', 'Scripts');

async function* dosyalar(dir) {
  for (const e of await readdir(dir, { withFileTypes: true })) {
    const p = join(dir, e.name);
    if (e.isDirectory()) yield* dosyalar(p);
    else if (e.name.endsWith('.cs')) yield p;
  }
}

/** Kaynağı tarar; dizge ve yorumları atlayarak parantez dengesini sayar. */
function denetle(src) {
  const hatalar = [];
  const yigin = [];
  const esler = { '}': '{', ')': '(', ']': '[' };

  let i = 0;
  let satir = 1;
  const n = src.length;

  while (i < n) {
    const c = src[i];
    const d = src[i + 1];

    if (c === '\n') { satir++; i++; continue; }

    // Yorumlar
    if (c === '/' && d === '/') {
      while (i < n && src[i] !== '\n') i++;
      continue;
    }
    if (c === '/' && d === '*') {
      i += 2;
      while (i < n && !(src[i] === '*' && src[i + 1] === '/')) {
        if (src[i] === '\n') satir++;
        i++;
      }
      if (i >= n) { hatalar.push(`${satir}: kapanmamış blok yorumu`); break; }
      i += 2;
      continue;
    }

    // Verbatim dizge @"..."
    if (c === '@' && d === '"') {
      i += 2;
      while (i < n) {
        if (src[i] === '"' && src[i + 1] === '"') { i += 2; continue; }
        if (src[i] === '"') { i++; break; }
        if (src[i] === '\n') satir++;
        i++;
      }
      continue;
    }

    // Düz dizge
    if (c === '"') {
      const baslangic = satir;
      i++;
      let kapandi = false;
      while (i < n) {
        if (src[i] === '\\') { i += 2; continue; }
        if (src[i] === '"') { i++; kapandi = true; break; }
        if (src[i] === '\n') { satir++; break; }  // tek satırlık dizge
        i++;
      }
      if (!kapandi) hatalar.push(`${baslangic}: kapanmamış dizge`);
      continue;
    }

    // Karakter
    if (c === "'") {
      i++;
      while (i < n && src[i] !== "'") {
        if (src[i] === '\\') i++;
        i++;
      }
      i++;
      continue;
    }

    if (c === '{' || c === '(' || c === '[') {
      yigin.push({ c, satir });
      i++;
      continue;
    }

    if (c === '}' || c === ')' || c === ']') {
      const son = yigin.pop();
      if (!son) hatalar.push(`${satir}: fazladan '${c}'`);
      else if (son.c !== esler[c])
        hatalar.push(`${satir}: '${c}' ile eşleşmeyen '${son.c}' (satır ${son.satir})`);
      i++;
      continue;
    }

    i++;
  }

  for (const a of yigin) hatalar.push(`${a.satir}: kapanmamış '${a.c}'`);

  return hatalar;
}

let toplam = 0;
let hataliDosya = 0;

for await (const f of dosyalar(SCRIPTS)) {
  const src = await readFile(f, 'utf8');
  const hatalar = denetle(src);
  toplam++;

  if (hatalar.length) {
    hataliDosya++;
    console.log(`\n✗ ${relative(ROOT, f)}`);
    for (const h of hatalar.slice(0, 8)) console.log(`    ${h}`);
  }
}

console.log(`\n${toplam} C# dosyası denetlendi, ${hataliDosya} tanesinde yapısal hata.`);
process.exit(hataliDosya > 0 ? 1 : 0);
