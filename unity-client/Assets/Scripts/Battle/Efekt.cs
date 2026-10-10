using System.Collections;
using KlanlarVadisi.Util;
using UnityEngine;

namespace KlanlarVadisi.Battle
{
    /// <summary>
    /// Savaş efektleri: vuruş kıvılcımı, mermi, patlama, tılsım halkası.
    ///
    /// Hepsi kod içinde üretilir (hazır prefab yok) ve kısa ömürlüdür.
    /// Amaç, sunucunun gönderdiği olayları gözle okunabilir kılmak.
    /// </summary>
    public static class Efekt
    {
        private static Transform _kok;

        private static Transform Kok()
        {
            if (_kok != null) return _kok;

            var go = new GameObject("Efektler");
            _kok = go.transform;

            return _kok;
        }

        private static GameObject Yarat(Vector3 konum, Sprite sprite, Color renk, float olcek, int sira = 9000)
        {
            var go = new GameObject("efekt");
            go.transform.SetParent(Kok(), false);
            go.transform.position = konum;
            go.transform.localScale = Vector3.one * olcek;

            var sr = go.AddComponent<SpriteRenderer>();
            sr.sprite = sprite;
            sr.color = renk;
            sr.sortingOrder = sira;

            return go;
        }

        private static Sprite Daire()
        {
            return SpriteKutuphanesi.Arayuz("ikon_yildiz");
        }

        /// <summary>Askerin indiği yerde toz bulutu.</summary>
        public static void InisTozu(Vector3 konum)
        {
            var go = Yarat(konum, Daire(), new Color(0.85f, 0.8f, 0.7f, 0.5f), 0.25f, 8800);
            EfektSurucu.Baslat(go, 0.35f, (t, tr, sr) =>
            {
                tr.localScale = Vector3.one * (0.25f + t * 0.4f);
                sr.color = new Color(0.85f, 0.8f, 0.7f, 0.5f * (1f - t));
            });
        }

        /// <summary>Yakın dövüş vuruşu: hedefe doğru kısa bir kıvılcım.</summary>
        public static void Vurus(Vector3 kaynak, Vector3 hedef)
        {
            var yon = (hedef - kaynak).normalized;
            var konum = kaynak + yon * 0.22f;

            var go = Yarat(konum, Daire(), new Color(1f, 0.92f, 0.6f, 0.9f), 0.14f, 9200);
            EfektSurucu.Baslat(go, 0.16f, (t, tr, sr) =>
            {
                tr.localScale = Vector3.one * (0.14f * (1f - t * 0.5f));
                sr.color = new Color(1f, 0.92f, 0.6f, 0.9f * (1f - t));
            });
        }

        /// <summary>Savunma kulesinin attığı mermi.</summary>
        public static void Mermi(Vector3 kaynak, Vector3 hedef)
        {
            var go = Yarat(kaynak, Daire(), new Color(1f, 0.75f, 0.35f, 0.95f), 0.11f, 9300);
            EfektSurucu.Baslat(go, 0.22f, (t, tr, sr) =>
            {
                tr.position = Vector3.Lerp(kaynak, hedef, t);
                sr.color = new Color(1f, 0.75f, 0.35f, 1f - t * 0.4f);
            });
        }

        public static void Patlama(Vector3 konum, float olcek = 1f)
        {
            var go = Yarat(konum, Daire(), new Color(1f, 0.55f, 0.25f, 0.85f), 0.2f * olcek, 9400);
            EfektSurucu.Baslat(go, 0.4f, (t, tr, sr) =>
            {
                tr.localScale = Vector3.one * olcek * (0.2f + t * 1.1f);
                sr.color = new Color(1f, 0.55f - t * 0.2f, 0.25f, 0.85f * (1f - t));
            });
        }

        public static void Yikim(Vector3 konum)
        {
            Patlama(konum, 1.4f);

            // Taş parçaları
            for (var i = 0; i < 5; i++)
            {
                var aci = i / 5f * Mathf.PI * 2f;
                var yon = new Vector3(Mathf.Cos(aci), Mathf.Sin(aci) * 0.5f, 0f);
                var go = Yarat(konum, Daire(), new Color(0.5f, 0.45f, 0.4f, 0.9f), 0.09f, 9350);

                EfektSurucu.Baslat(go, 0.5f, (t, tr, sr) =>
                {
                    tr.position = konum + yon * (t * 0.9f) + Vector3.up * (Mathf.Sin(t * Mathf.PI) * 0.4f);
                    sr.color = new Color(0.5f, 0.45f, 0.4f, 0.9f * (1f - t));
                });
            }
        }

        public static void AskerOlumu(Vector3 konum)
        {
            var go = Yarat(konum, Daire(), new Color(0.75f, 0.2f, 0.2f, 0.7f), 0.18f, 9100);
            EfektSurucu.Baslat(go, 0.3f, (t, tr, sr) =>
            {
                tr.position = konum + Vector3.up * (t * 0.3f);
                sr.color = new Color(0.75f, 0.2f, 0.2f, 0.7f * (1f - t));
            });
        }

        public static void Cagri(Vector3 konum)
        {
            var go = Yarat(konum, Daire(), new Color(0.66f, 0.33f, 0.97f, 0.85f), 0.2f, 9150);
            EfektSurucu.Baslat(go, 0.45f, (t, tr, sr) =>
            {
                tr.localScale = Vector3.one * (0.6f - t * 0.4f);
                sr.color = new Color(0.66f, 0.33f, 0.97f, 0.85f * (1f - t));
            });
        }

        public static void Yetenek(Vector3 konum)
        {
            var go = Yarat(konum, Daire(), new Color(1f, 0.85f, 0.3f, 0.9f), 0.3f, 9500);
            EfektSurucu.Baslat(go, 0.7f, (t, tr, sr) =>
            {
                tr.localScale = Vector3.one * (0.3f + t * 1.6f);
                sr.color = new Color(1f, 0.85f, 0.3f, 0.9f * (1f - t));
            });
        }

        /// <summary>Tılsım: türüne göre renkli halka.</summary>
        public static void Tilsim(Vector3 konum, string anahtar, float yaricap)
        {
            var renk = anahtar switch
            {
                "yildirim" => new Color(0.55f, 0.85f, 1f),
                "sifa" => new Color(0.45f, 0.95f, 0.55f),
                "gazap" => new Color(1f, 0.55f, 0.25f),
                "karakis" => new Color(0.75f, 0.92f, 1f),
                "zehir" => new Color(0.55f, 0.85f, 0.35f),
                "golge" => new Color(0.62f, 0.35f, 0.85f),
                _ => new Color(0.9f, 0.8f, 0.4f),
            };

            var go = Yarat(konum, Daire(), renk, 0.2f, 9600);
            EfektSurucu.Baslat(go, 1.2f, (t, tr, sr) =>
            {
                tr.localScale = Vector3.one * (yaricap * 0.6f * Mathf.Min(1f, t * 3f));
                sr.color = new Color(renk.r, renk.g, renk.b, 0.5f * (1f - t));
            });
        }

        /// <summary>Kısa ömürlü efektleri süren yardımcı bileşen.</summary>
        private class EfektSurucu : MonoBehaviour
        {
            private float _sure;
            private float _gecen;
            private System.Action<float, Transform, SpriteRenderer> _adim;
            private SpriteRenderer _sr;

            public static void Baslat(GameObject go, float sure,
                System.Action<float, Transform, SpriteRenderer> adim)
            {
                var s = go.AddComponent<EfektSurucu>();
                s._sure = sure;
                s._adim = adim;
                s._sr = go.GetComponent<SpriteRenderer>();
            }

            private void Update()
            {
                _gecen += Time.deltaTime;
                var t = Mathf.Clamp01(_gecen / _sure);

                _adim?.Invoke(t, transform, _sr);

                if (t >= 1f) Destroy(gameObject);
            }
        }
    }
}
