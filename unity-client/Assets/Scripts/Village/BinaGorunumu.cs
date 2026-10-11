using System;
using KlanlarVadisi.Net;
using KlanlarVadisi.Util;
using UnityEngine;

namespace KlanlarVadisi.Village
{
    /// <summary>
    /// Tek bir binanın görünümü: sprite, inşaat örtüsü, seviye rozeti ve
    /// dokunma algılama.
    /// </summary>
    public class BinaGorunumu : MonoBehaviour
    {
        public BinaDurumu Veri { get; private set; }
        public event Action Tiklandi;

        private SpriteRenderer _sr;
        private SpriteRenderer _secimHalkasi;
        private GameObject _insaatOrtusu;
        private BoxCollider2D _carpisma;

        public static BinaGorunumu Olustur(Transform parent, BinaDurumu veri)
        {
            var go = new GameObject($"bina_{veri.id}_{veri.key}");
            go.transform.SetParent(parent, false);

            var bg = go.AddComponent<BinaGorunumu>();
            bg.Kur(veri);

            return bg;
        }

        private void Kur(BinaDurumu veri)
        {
            _sr = gameObject.AddComponent<SpriteRenderer>();
            _carpisma = gameObject.AddComponent<BoxCollider2D>();

            // Seçim halkası (başlangıçta gizli)
            var halka = new GameObject("secim");
            halka.transform.SetParent(transform, false);
            _secimHalkasi = halka.AddComponent<SpriteRenderer>();
            _secimHalkasi.sprite = SpriteKutuphanesi.Arazi("cayir");
            _secimHalkasi.color = new Color(1f, 0.85f, 0.3f, 0f);

            Guncelle(veri);
        }

        public void Guncelle(BinaDurumu veri)
        {
            Veri = veri;

            _sr.sprite = SpriteKutuphanesi.Bina(veri.key);

            // Bina sprite'larının pivotu, ayak izinin arka köşesine (yani
            // ızgara (x,y) noktasına) denk gelecek biçimde .meta dosyasında
            // ayarlanır — bkz. tools/unity/gen-meta.mjs -> binaPivotu().
            // Bu yüzden nesne doğrudan o ızgara noktasına konur.
            transform.position = Izometri.IzgaradanDunyaya(veri.x, veri.y);
            _sr.sortingOrder = Izometri.SiralamaDegeri(veri.x + veri.w * 0.5f, veri.y + veri.h * 0.5f);

            // Çarpışma kutusu: ayak izinin izometrik eni/boyu kadar
            var genislik = (veri.w + veri.h) * (Izometri.KareGenislik / Izometri.PikselBirim) * 0.5f;
            var yukseklik = (veri.w + veri.h) * (Izometri.KareYukseklik / Izometri.PikselBirim) * 0.5f;
            _carpisma.size = new Vector2(genislik * 0.8f, yukseklik * 0.9f);
            _carpisma.offset = new Vector2(0f, -yukseklik * 0.5f);

            _secimHalkasi.transform.localPosition = new Vector3(0f, -yukseklik * 0.5f, 0f);
            _secimHalkasi.transform.localScale = new Vector3(veri.w * 0.9f, veri.h * 0.9f, 1f);
            _secimHalkasi.sortingOrder = _sr.sortingOrder - 1;

            InsaatiGoster(veri.constructing);
        }

        private void InsaatiGoster(bool insaatta)
        {
            if (!insaatta)
            {
                if (_insaatOrtusu != null) _insaatOrtusu.SetActive(false);
                _sr.color = Color.white;
                return;
            }

            _sr.color = new Color(0.78f, 0.78f, 0.84f, 0.92f);

            if (_insaatOrtusu == null)
            {
                _insaatOrtusu = new GameObject("insaat");
                _insaatOrtusu.transform.SetParent(transform, false);

                var sr = _insaatOrtusu.AddComponent<SpriteRenderer>();
                sr.sprite = SpriteKutuphanesi.Arayuz("ikon_usta");
                sr.sortingOrder = _sr.sortingOrder + 2;
                _insaatOrtusu.transform.localScale = Vector3.one * 0.35f;
                _insaatOrtusu.transform.localPosition = new Vector3(0f, 0.9f, 0f);
            }

            _insaatOrtusu.SetActive(true);
        }

        public void SecimiGoster(bool secili)
        {
            var c = _secimHalkasi.color;
            _secimHalkasi.color = new Color(c.r, c.g, c.b, secili ? 0.45f : 0f);
        }

        /// <summary>Taşıma kipinde geçici konum gösterimi.</summary>
        public void GeciciKonum(int x, int y, bool gecerli)
        {
            transform.position = Izometri.IzgaradanDunyaya(x, y);
            _sr.color = gecerli ? new Color(1f, 1f, 1f, 0.85f) : new Color(1f, 0.45f, 0.4f, 0.85f);
            _sr.sortingOrder = 8000;
        }

        private void OnMouseUpAsButton()
        {
            Tiklandi?.Invoke();
        }
    }
}
