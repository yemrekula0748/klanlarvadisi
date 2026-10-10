using KlanlarVadisi.Net;
using KlanlarVadisi.Util;
using UnityEngine;
using UnityEngine.UI;

namespace KlanlarVadisi.UI
{
    /// <summary>
    /// Ekranın üstündeki kaynak çubuğu: altın, tahıl, demir, kut, gök taşı,
    /// kupa ve usta sayısı. Kaynaklar sunucudan gelen üretim hızıyla
    /// istemci tarafında yumuşakça artar (sunucu yine tek doğruluk kaynağıdır;
    /// bu yalnızca gösterimdir).
    /// </summary>
    public class UstBar : MonoBehaviour
    {
        private readonly string[] _kaynaklar = { "gold", "grain", "iron", "kut" };
        private readonly Text[] _metinler = new Text[4];
        private readonly Image[] _doluluk = new Image[4];
        private Text _gokTasi;
        private Text _kupa;
        private Text _usta;
        private Text _oyuncuAdi;

        private Kaynaklar _mevcut = new();
        private Kaynaklar _kapasite = new();
        private Kaynaklar _uretim = new();
        private readonly float[] _kesir = new float[4];

        public static UstBar Olustur(Transform tuval)
        {
            var kok = ArayuzFabrikasi.Kutu(tuval, "UstBar",
                new Vector2(0f, 74f), new Vector2(0f, 1f), Vector2.zero,
                new Color(0.1f, 0.09f, 0.08f, 0.88f));

            kok.anchorMin = new Vector2(0f, 1f);
            kok.anchorMax = new Vector2(1f, 1f);
            kok.pivot = new Vector2(0.5f, 1f);
            kok.offsetMin = new Vector2(0f, -74f);
            kok.offsetMax = Vector2.zero;

            var bar = kok.gameObject.AddComponent<UstBar>();
            bar.Kur(kok);

            return bar;
        }

        private void Kur(RectTransform kok)
        {
            // Oyuncu adı (sol)
            _oyuncuAdi = ArayuzFabrikasi.Yazi(kok, "", new Vector2(220f, 30f),
                new Vector2(0f, 0.5f), new Vector2(16f, 10f), 20, TextAnchor.MiddleLeft);
            _oyuncuAdi.fontStyle = FontStyle.Bold;

            _kupa = ArayuzFabrikasi.Yazi(kok, "0", new Vector2(120f, 24f),
                new Vector2(0f, 0.5f), new Vector2(44f, -14f), 17, TextAnchor.MiddleLeft,
                ArayuzFabrikasi.Altin);
            ArayuzFabrikasi.Resim(kok, SpriteKutuphanesi.Arayuz("ikon_kupa"),
                new Vector2(24f, 24f), new Vector2(0f, 0.5f), new Vector2(16f, -14f));

            // Kaynaklar (sağa doğru)
            var x = -16f;
            for (var i = _kaynaklar.Length - 1; i >= 0; i--)
            {
                var kutu = ArayuzFabrikasi.Kutu(kok, _kaynaklar[i],
                    new Vector2(132f, 54f), new Vector2(1f, 0.5f), new Vector2(x, 0f),
                    new Color(0f, 0f, 0f, 0.35f));

                ArayuzFabrikasi.Resim(kutu, SpriteKutuphanesi.KaynakIkonu(_kaynaklar[i]),
                    new Vector2(34f, 34f), new Vector2(0f, 0.5f), new Vector2(6f, 0f));

                _metinler[i] = ArayuzFabrikasi.Yazi(kutu, "0", new Vector2(86f, 24f),
                    new Vector2(0f, 0.5f), new Vector2(44f, 7f), 18, TextAnchor.MiddleLeft);

                // Doluluk çubuğu
                var zemin = ArayuzFabrikasi.Kutu(kutu, "zemin", new Vector2(84f, 6f),
                    new Vector2(0f, 0.5f), new Vector2(44f, -11f), new Color(0f, 0f, 0f, 0.5f));
                _doluluk[i] = ArayuzFabrikasi.Kutu(zemin, "dolu", new Vector2(84f, 6f),
                    new Vector2(0f, 0.5f), Vector2.zero, ArayuzFabrikasi.Altin)
                    .GetComponent<Image>();
                _doluluk[i].rectTransform.pivot = new Vector2(0f, 0.5f);
                _doluluk[i].rectTransform.anchorMin = new Vector2(0f, 0.5f);
                _doluluk[i].rectTransform.anchorMax = new Vector2(0f, 0.5f);

                x -= 140f;
            }

            // Gök taşı
            var gokKutu = ArayuzFabrikasi.Kutu(kok, "gok_tasi",
                new Vector2(120f, 54f), new Vector2(1f, 0.5f), new Vector2(x, 0f),
                new Color(0f, 0f, 0f, 0.35f));
            ArayuzFabrikasi.Resim(gokKutu, SpriteKutuphanesi.Arayuz("ikon_gok_tasi"),
                new Vector2(34f, 34f), new Vector2(0f, 0.5f), new Vector2(6f, 0f));
            _gokTasi = ArayuzFabrikasi.Yazi(gokKutu, "0", new Vector2(74f, 28f),
                new Vector2(0f, 0.5f), new Vector2(44f, 0f), 18, TextAnchor.MiddleLeft);

            // Usta
            x -= 128f;
            var ustaKutu = ArayuzFabrikasi.Kutu(kok, "usta",
                new Vector2(96f, 54f), new Vector2(1f, 0.5f), new Vector2(x, 0f),
                new Color(0f, 0f, 0f, 0.35f));
            ArayuzFabrikasi.Resim(ustaKutu, SpriteKutuphanesi.Arayuz("ikon_usta"),
                new Vector2(32f, 32f), new Vector2(0f, 0.5f), new Vector2(6f, 0f));
            _usta = ArayuzFabrikasi.Yazi(ustaKutu, "0/0", new Vector2(56f, 28f),
                new Vector2(0f, 0.5f), new Vector2(42f, 0f), 18, TextAnchor.MiddleLeft);
        }

        public void Guncelle(ObaDurumu durum)
        {
            if (durum?.village == null) return;

            _mevcut = durum.village.resources;
            _kapasite = durum.village.capacity;
            _uretim = durum.village.production;

            for (var i = 0; i < 4; i++) _kesir[i] = 0f;

            _oyuncuAdi.text = durum.player.name;
            _kupa.text = durum.player.trophies.ToString();
            _gokTasi.text = ArayuzFabrikasi.Sayi(durum.player.gok_tasi);
            _usta.text = $"{durum.village.builders.free}/{durum.village.builders.total}";

            Yansit();
        }

        private void Update()
        {
            if (_uretim == null) return;

            // Üretimi akıcı göster: saatlik hızdan saniyelik pay
            var degisti = false;
            for (var i = 0; i < 4; i++)
            {
                var hiz = _uretim.Al(_kaynaklar[i]) / 3600f;
                if (hiz <= 0f) continue;

                _kesir[i] += hiz * Time.deltaTime;
                if (_kesir[i] < 1f) continue;

                var artis = Mathf.FloorToInt(_kesir[i]);
                _kesir[i] -= artis;

                var kapasite = _kapasite.Al(_kaynaklar[i]);
                switch (_kaynaklar[i])
                {
                    case "gold": _mevcut.gold = System.Math.Min(kapasite, _mevcut.gold + artis); break;
                    case "grain": _mevcut.grain = System.Math.Min(kapasite, _mevcut.grain + artis); break;
                    case "iron": _mevcut.iron = System.Math.Min(kapasite, _mevcut.iron + artis); break;
                    case "kut": _mevcut.kut = System.Math.Min(kapasite, _mevcut.kut + artis); break;
                }

                degisti = true;
            }

            if (degisti) Yansit();
        }

        private void Yansit()
        {
            for (var i = 0; i < 4; i++)
            {
                var deger = _mevcut.Al(_kaynaklar[i]);
                var kapasite = _kapasite.Al(_kaynaklar[i]);

                _metinler[i].text = ArayuzFabrikasi.Sayi(deger);

                var oran = kapasite > 0 ? Mathf.Clamp01(deger / (float)kapasite) : 0f;
                _doluluk[i].rectTransform.sizeDelta = new Vector2(84f * oran, 6f);
                _doluluk[i].color = oran > 0.95f
                    ? new Color(0.9f, 0.4f, 0.3f)
                    : ArayuzFabrikasi.Altin;
            }
        }
    }
}
