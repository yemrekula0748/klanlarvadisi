using System.Collections.Generic;
using KlanlarVadisi.Net;
using KlanlarVadisi.Util;
using UnityEngine;

namespace KlanlarVadisi.Battle
{
    /// <summary>
    /// Sunucuda hesaplanmış savaşı canlandırır.
    ///
    /// İstemci HİÇBİR savaş kararı vermez: sonuç, yıldız, yıkım ve ganimet
    /// zaten sunucudan gelmiştir. Burada yalnızca olay zaman çizgisi
    /// oynatılır. Bu yüzden hile imkânsızdır ve saldıran ile savunan aynı
    /// savaşı birebir aynı izler.
    ///
    /// Oynatma hızı değiştirilebilir (1x, 2x, 4x) ve ileri sarılabilir.
    /// </summary>
    public class SavasTekrari : MonoBehaviour
    {
        public float Hiz = 1f;
        public bool Oynuyor { get; private set; }
        public float GecenSure { get; private set; }
        public float ToplamSure { get; private set; }

        /// <summary>Yıkım yüzdesi değiştiğinde (canlı skor tahtası için).</summary>
        public System.Action<float, int> SkorDegisti;
        public System.Action Bitti;

        private Tekrar _tekrar;
        private int _olayIndeksi;
        private Transform _binaKok;
        private Transform _askerKok;

        private readonly Dictionary<int, BinaKutusu> _binalar = new();
        private readonly Dictionary<int, AskerKutusu> _askerler = new();

        private int _sayilanBina;
        private int _yikilanBina;

        /* -------------------------- İç durum ---------------------------- */

        private class BinaKutusu
        {
            public TekrarBina Veri;
            public SpriteRenderer Cizici;
            public GameObject Nesne;
            public CanCubugu Can;
            public bool Yasiyor = true;
        }

        private class AskerKutusu
        {
            public TekrarAsker Veri;
            public SpriteRenderer Cizici;
            public GameObject Nesne;
            public CanCubugu Can;
            public bool Yasiyor;
            public bool Indi;

            // Yürüyüş
            public List<Vector2> Yol = new();
            public int YolIndeksi;
            public Vector2 Konum;
            public float Hiz = 1.6f;
        }

        /* ---------------------------------------------------------------- */

        public void Yukle(Tekrar tekrar)
        {
            Temizle();

            _tekrar = tekrar;
            _olayIndeksi = 0;
            GecenSure = 0f;
            ToplamSure = tekrar.duration_ticks / (float)Mathf.Max(1, tekrar.tick_rate);

            _binaKok = new GameObject("TekrarBinalar").transform;
            _binaKok.SetParent(transform, false);

            _askerKok = new GameObject("TekrarAskerler").transform;
            _askerKok.SetParent(transform, false);

            foreach (var b in tekrar.buildings)
            {
                var go = new GameObject($"b{b.id}_{b.key}");
                go.transform.SetParent(_binaKok, false);
                go.transform.position = Izometri.IzgaradanDunyaya(b.x, b.y);

                var sr = go.AddComponent<SpriteRenderer>();
                sr.sprite = SpriteKutuphanesi.Bina(b.key);
                sr.sortingOrder = Izometri.SiralamaDegeri(b.x + b.w * 0.5f, b.y + b.h * 0.5f);

                var kutu = new BinaKutusu { Veri = b, Cizici = sr, Nesne = go };

                // Duvar ve tuzaklar yıkım yüzdesine girmez
                if (b.cat != "duvar" && b.cat != "tuzak") _sayilanBina++;

                if (b.cat != "duvar" && b.cat != "tuzak")
                    kutu.Can = CanCubugu.Olustur(go.transform, new Vector3(0f, 0.55f, 0f));

                _binalar[b.id] = kutu;
            }

            foreach (var u in tekrar.units)
            {
                var go = new GameObject($"u{u.id}_{u.key}");
                go.transform.SetParent(_askerKok, false);
                go.SetActive(false);

                var sr = go.AddComponent<SpriteRenderer>();
                sr.sprite = SpriteKutuphanesi.Asker(u.key);
                sr.sortingOrder = 5000;

                // Savunan askerler kırmızı tonlanır
                if (u.side == "savunan") sr.color = new Color(1f, 0.72f, 0.72f);

                _askerler[u.id] = new AskerKutusu
                {
                    Veri = u,
                    Cizici = sr,
                    Nesne = go,
                    Hiz = Mathf.Max(0.4f, u.spd),
                    Can = CanCubugu.Olustur(go.transform, new Vector3(0f, 0.42f, 0f), 0.5f),
                };
            }
        }

        public void Oynat() => Oynuyor = true;
        public void Duraklat() => Oynuyor = false;

        public void Temizle()
        {
            if (_binaKok != null) Destroy(_binaKok.gameObject);
            if (_askerKok != null) Destroy(_askerKok.gameObject);

            _binalar.Clear();
            _askerler.Clear();
            _sayilanBina = 0;
            _yikilanBina = 0;
            Oynuyor = false;
        }

        private void Update()
        {
            if (!Oynuyor || _tekrar == null) return;

            GecenSure += Time.deltaTime * Hiz;
            var adim = Mathf.FloorToInt(GecenSure * _tekrar.tick_rate);

            // Bu ana kadarki bütün olayları uygula
            while (_olayIndeksi < _tekrar.events.Count && _tekrar.events[_olayIndeksi].t <= adim)
            {
                OlayiUygula(_tekrar.events[_olayIndeksi]);
                _olayIndeksi++;
            }

            AskerleriYurut(Time.deltaTime * Hiz);

            if (GecenSure >= ToplamSure + 0.6f)
            {
                Oynuyor = false;
                Bitti?.Invoke();
            }
        }

        /* ---------------------------------------------------------------- */
        /* Olaylar                                                           */
        /* ---------------------------------------------------------------- */

        private void OlayiUygula(TekrarOlay o)
        {
            switch (o.e)
            {
                case "dep":   AskerIndir(o); break;
                case "spawn": AskerDogur(o); break;
                case "mv":    YolVer(o); break;
                case "atk":   VurusEfekti(o); break;
                case "atku":  VurusEfekti(o); break;
                case "def":   SavunmaAtesi(o); break;
                case "bdie":  BinaYik(o.b); break;
                case "udie":  AskerOldur(o.u); break;
                case "hp":    CanlariGuncelle(o); break;
                case "trap":  TuzakPatlat(o.b); break;
                case "spell": TilsimEfekti(o); break;
                case "abil":  YetenekEfekti(o); break;
            }
        }

        private void AskerIndir(TekrarOlay o)
        {
            if (!_askerler.TryGetValue(o.u, out var a)) return;

            a.Indi = true;
            a.Yasiyor = true;
            a.Konum = new Vector2(o.x, o.y);
            a.Nesne.SetActive(true);
            a.Nesne.transform.position = Izometri.IzgaradanDunyaya(o.x, o.y, 0.1f);

            Efekt.InisTozu(a.Nesne.transform.position);
        }

        private void AskerDogur(TekrarOlay o)
        {
            // Savunmada doğan askerler (Boy Otağı, Kurt İni) tekrar listesinde
            // bulunmayabilir; eksikse burada yaratılır.
            if (!_askerler.TryGetValue(o.u, out var a))
            {
                var go = new GameObject($"u{o.u}_{o.k}");
                go.transform.SetParent(_askerKok, false);

                var sr = go.AddComponent<SpriteRenderer>();
                sr.sprite = SpriteKutuphanesi.Asker(string.IsNullOrEmpty(o.k) ? "boru" : o.k);
                sr.sortingOrder = 5000;
                if (o.side == "savunan") sr.color = new Color(1f, 0.72f, 0.72f);

                a = new AskerKutusu
                {
                    Veri = new TekrarAsker { id = o.u, key = o.k, hp = o.hp, side = o.side, air = o.air },
                    Cizici = sr,
                    Nesne = go,
                    Hiz = 2f,
                    Can = CanCubugu.Olustur(go.transform, new Vector3(0f, 0.42f, 0f), 0.5f),
                };

                _askerler[o.u] = a;
            }

            a.Indi = true;
            a.Yasiyor = true;
            a.Konum = new Vector2(o.x, o.y);
            a.Nesne.SetActive(true);
            a.Nesne.transform.position = Izometri.IzgaradanDunyaya(o.x, o.y, 0.1f);

            Efekt.Cagri(a.Nesne.transform.position);
        }

        private void YolVer(TekrarOlay o)
        {
            if (!_askerler.TryGetValue(o.u, out var a) || o.p == null) return;

            a.Yol.Clear();
            foreach (var p in o.p) a.Yol.Add(new Vector2(p[0], p[1]));
            a.YolIndeksi = 0;
        }

        private void VurusEfekti(TekrarOlay o)
        {
            if (!_askerler.TryGetValue(o.u, out var a) || !a.Yasiyor) return;

            // Saldıran askeri hedefe doğru çevir
            Vector3 hedef;
            if (o.b > 0 && _binalar.TryGetValue(o.b, out var b))
                hedef = b.Nesne.transform.position;
            else if (o.v > 0 && _askerler.TryGetValue(o.v, out var v))
                hedef = v.Nesne.transform.position;
            else return;

            var yon = hedef - a.Nesne.transform.position;
            if (Mathf.Abs(yon.x) > 0.01f)
                a.Cizici.flipX = yon.x < 0f;

            Efekt.Vurus(a.Nesne.transform.position, hedef);
        }

        private void SavunmaAtesi(TekrarOlay o)
        {
            if (!_binalar.TryGetValue(o.b, out var b) || !b.Yasiyor) return;
            if (!_askerler.TryGetValue(o.u, out var a) || !a.Yasiyor) return;

            Efekt.Mermi(
                b.Nesne.transform.position + Vector3.up * 0.4f,
                a.Nesne.transform.position);
        }

        private void BinaYik(int id)
        {
            if (!_binalar.TryGetValue(id, out var b) || !b.Yasiyor) return;

            b.Yasiyor = false;
            if (b.Veri.cat != "duvar" && b.Veri.cat != "tuzak") _yikilanBina++;

            Efekt.Yikim(b.Nesne.transform.position);

            b.Cizici.color = new Color(0.32f, 0.28f, 0.24f, 0.55f);
            b.Can?.Gizle();

            SkorDegisti?.Invoke(YikimYuzdesi(), Yildiz());
        }

        private void AskerOldur(int id)
        {
            if (!_askerler.TryGetValue(id, out var a) || !a.Yasiyor) return;

            a.Yasiyor = false;
            Efekt.AskerOlumu(a.Nesne.transform.position);
            a.Nesne.SetActive(false);
        }

        private void CanlariGuncelle(TekrarOlay o)
        {
            if (o.hpB != null)
                foreach (var kv in o.hpB)
                    if (_binalar.TryGetValue(kv.Key, out var b) && b.Can != null)
                        b.Can.Ayarla(kv.Value / (float)Mathf.Max(1, b.Veri.hp));

            if (o.hpU != null)
                foreach (var kv in o.hpU)
                    if (_askerler.TryGetValue(kv.Key, out var a) && a.Can != null)
                        a.Can.Ayarla(kv.Value / (float)Mathf.Max(1, a.Veri.hp));
        }

        private void TuzakPatlat(int id)
        {
            if (!_binalar.TryGetValue(id, out var b)) return;

            b.Yasiyor = false;
            Efekt.Patlama(b.Nesne.transform.position, 1.2f);
            b.Nesne.SetActive(false);
        }

        private void TilsimEfekti(TekrarOlay o)
        {
            var konum = Izometri.IzgaradanDunyaya(o.x, o.y, 0.2f);
            Efekt.Tilsim(konum, o.k, Mathf.Max(1f, o.r));
        }

        private void YetenekEfekti(TekrarOlay o)
        {
            if (!_askerler.TryGetValue(o.u, out var a)) return;
            Efekt.Yetenek(a.Nesne.transform.position);
        }

        /* ---------------------------------------------------------------- */
        /* Hareket                                                           */
        /* ---------------------------------------------------------------- */

        private void AskerleriYurut(float dt)
        {
            foreach (var kv in _askerler)
            {
                var a = kv.Value;
                if (!a.Yasiyor || !a.Indi || a.Yol.Count == 0) continue;
                if (a.YolIndeksi >= a.Yol.Count) continue;

                var kalan = a.Hiz * dt;

                while (kalan > 0.0001f && a.YolIndeksi < a.Yol.Count)
                {
                    var hedef = a.Yol[a.YolIndeksi];
                    var fark = hedef - a.Konum;
                    var mesafe = fark.magnitude;

                    if (mesafe <= kalan)
                    {
                        a.Konum = hedef;
                        a.YolIndeksi++;
                        kalan -= mesafe;
                    }
                    else
                    {
                        a.Konum += fark.normalized * kalan;
                        kalan = 0f;
                    }
                }

                var yukseklik = a.Veri.air ? 0.85f : 0.12f;
                a.Nesne.transform.position = Izometri.IzgaradanDunyaya(a.Konum.x, a.Konum.y, yukseklik);
                a.Cizici.sortingOrder = Izometri.SiralamaDegeri(a.Konum.x, a.Konum.y) + (a.Veri.air ? 3000 : 1000);
            }
        }

        /* ---------------------------------------------------------------- */

        public float YikimYuzdesi()
            => _sayilanBina == 0 ? 0f : _yikilanBina / (float)_sayilanBina * 100f;

        public int Yildiz()
        {
            var yuzde = YikimYuzdesi();
            var yildiz = 0;

            if (yuzde >= 50f) yildiz++;

            if (_binalar.TryGetValue(OtagId(), out var otag) && !otag.Yasiyor) yildiz++;
            if (yuzde >= 100f) yildiz = 3;

            return Mathf.Min(3, yildiz);
        }

        private int OtagId()
        {
            foreach (var kv in _binalar)
                if (kv.Value.Veri.key == "otag") return kv.Key;

            return -1;
        }

        /// <summary>Tekrarı belirli bir ana sarar (çubuk sürüklenince).</summary>
        public void Sar(float saniye)
        {
            if (_tekrar == null) return;

            // Geriye sarmak için sahneyi baştan kur
            Yukle(_tekrar);
            GecenSure = 0f;
            _olayIndeksi = 0;

            var hedefAdim = Mathf.FloorToInt(saniye * _tekrar.tick_rate);
            while (_olayIndeksi < _tekrar.events.Count && _tekrar.events[_olayIndeksi].t <= hedefAdim)
            {
                OlayiUygula(_tekrar.events[_olayIndeksi]);
                _olayIndeksi++;
            }

            GecenSure = saniye;
            Oynuyor = true;
        }
    }
}
