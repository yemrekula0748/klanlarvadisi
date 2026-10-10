using KlanlarVadisi.Util;
using UnityEngine;
using UnityEngine.UI;

namespace KlanlarVadisi.UI
{
    /// <summary>
    /// Arayüzü kod içinde kurar.
    ///
    /// Bilinçli bir tercih: hazır .prefab dosyaları yerine her panel
    /// koddan üretilir. Böylece proje sürüm takibinde okunabilir kalır,
    /// birleştirme çakışması yaşanmaz ve arayüz veriyle birlikte değişir.
    /// </summary>
    public static class ArayuzFabrikasi
    {
        public static readonly Color Altin = new(0.878f, 0.651f, 0.235f);
        public static readonly Color Kizil = new(0.718f, 0.212f, 0.173f);
        public static readonly Color Koyu = new(0.169f, 0.149f, 0.125f);
        public static readonly Color KoyuSeffaf = new(0.169f, 0.149f, 0.125f, 0.92f);
        public static readonly Color Metin = new(0.914f, 0.882f, 0.820f);
        public static readonly Color Yesil = new(0.247f, 0.478f, 0.275f);

        private static Font _font;

        public static Font Yazi()
        {
            if (_font != null) return _font;

            // Unity'nin yerleşik yazı tipi: ek varlık gerektirmez.
            _font = Resources.GetBuiltinResource<Font>("LegacyRuntime.ttf");
            if (_font == null) _font = Resources.GetBuiltinResource<Font>("Arial.ttf");

            return _font;
        }

        /* ------------------------------------------------------------------ */

        public static Canvas TuvalKur(string ad = "Arayuz")
        {
            var go = new GameObject(ad);
            var canvas = go.AddComponent<Canvas>();
            canvas.renderMode = RenderMode.ScreenSpaceOverlay;

            var olcek = go.AddComponent<CanvasScaler>();
            olcek.uiScaleMode = CanvasScaler.ScaleMode.ScaleWithScreenSize;
            olcek.referenceResolution = new Vector2(1280f, 720f);
            olcek.matchWidthOrHeight = 0.5f;

            go.AddComponent<GraphicRaycaster>();

            return canvas;
        }

        public static RectTransform Panel(Transform parent, string ad,
            Vector2 enBoy, Vector2 capa, Vector2 konum, Color? renk = null)
        {
            var go = new GameObject(ad, typeof(RectTransform), typeof(Image));
            go.transform.SetParent(parent, false);

            var rt = go.GetComponent<RectTransform>();
            rt.anchorMin = rt.anchorMax = capa;
            rt.pivot = capa;
            rt.sizeDelta = enBoy;
            rt.anchoredPosition = konum;

            var img = go.GetComponent<Image>();
            img.sprite = SpriteKutuphanesi.Arayuz("panel");
            img.type = Image.Type.Sliced;
            img.color = renk ?? Color.white;

            return rt;
        }

        public static RectTransform Kutu(Transform parent, string ad,
            Vector2 enBoy, Vector2 capa, Vector2 konum, Color renk)
        {
            var go = new GameObject(ad, typeof(RectTransform), typeof(Image));
            go.transform.SetParent(parent, false);

            var rt = go.GetComponent<RectTransform>();
            rt.anchorMin = rt.anchorMax = capa;
            rt.pivot = capa;
            rt.sizeDelta = enBoy;
            rt.anchoredPosition = konum;

            go.GetComponent<Image>().color = renk;

            return rt;
        }

        public static Text Yazi(Transform parent, string metin,
            Vector2 enBoy, Vector2 capa, Vector2 konum,
            int boyut = 20, TextAnchor hiza = TextAnchor.MiddleLeft, Color? renk = null)
        {
            var go = new GameObject("yazi", typeof(RectTransform), typeof(Text));
            go.transform.SetParent(parent, false);

            var rt = go.GetComponent<RectTransform>();
            rt.anchorMin = rt.anchorMax = capa;
            rt.pivot = capa;
            rt.sizeDelta = enBoy;
            rt.anchoredPosition = konum;

            var t = go.GetComponent<Text>();
            t.text = metin;
            t.font = Yazi();
            t.fontSize = boyut;
            t.alignment = hiza;
            t.color = renk ?? Metin;
            t.horizontalOverflow = HorizontalWrapMode.Overflow;
            t.verticalOverflow = VerticalWrapMode.Overflow;
            t.raycastTarget = false;

            return t;
        }

        public static Image Resim(Transform parent, Sprite sprite,
            Vector2 enBoy, Vector2 capa, Vector2 konum, Color? renk = null)
        {
            var go = new GameObject("resim", typeof(RectTransform), typeof(Image));
            go.transform.SetParent(parent, false);

            var rt = go.GetComponent<RectTransform>();
            rt.anchorMin = rt.anchorMax = capa;
            rt.pivot = capa;
            rt.sizeDelta = enBoy;
            rt.anchoredPosition = konum;

            var img = go.GetComponent<Image>();
            img.sprite = sprite;
            img.color = renk ?? Color.white;
            img.preserveAspect = true;
            img.raycastTarget = false;

            return img;
        }

        public static Button Dugme(Transform parent, string metin,
            Vector2 enBoy, Vector2 capa, Vector2 konum,
            System.Action tiklama, bool yesil = false, int yaziBoyutu = 20)
        {
            var go = new GameObject("dugme", typeof(RectTransform), typeof(Image), typeof(Button));
            go.transform.SetParent(parent, false);

            var rt = go.GetComponent<RectTransform>();
            rt.anchorMin = rt.anchorMax = capa;
            rt.pivot = capa;
            rt.sizeDelta = enBoy;
            rt.anchoredPosition = konum;

            var img = go.GetComponent<Image>();
            img.sprite = SpriteKutuphanesi.Arayuz(yesil ? "dugme_yesil" : "dugme");
            img.type = Image.Type.Sliced;

            var btn = go.GetComponent<Button>();
            btn.targetGraphic = img;
            if (tiklama != null) btn.onClick.AddListener(() => tiklama());

            var t = Yazi(rt, metin, enBoy, new Vector2(0.5f, 0.5f), Vector2.zero,
                yaziBoyutu, TextAnchor.MiddleCenter);
            t.fontStyle = FontStyle.Bold;

            return btn;
        }

        public static InputField Giris(Transform parent, string ipucu,
            Vector2 enBoy, Vector2 capa, Vector2 konum, bool sifre = false)
        {
            var go = new GameObject("giris", typeof(RectTransform), typeof(Image), typeof(InputField));
            go.transform.SetParent(parent, false);

            var rt = go.GetComponent<RectTransform>();
            rt.anchorMin = rt.anchorMax = capa;
            rt.pivot = capa;
            rt.sizeDelta = enBoy;
            rt.anchoredPosition = konum;

            go.GetComponent<Image>().color = new Color(0.1f, 0.09f, 0.08f, 0.95f);

            var metin = Yazi(rt, "", enBoy - new Vector2(20f, 0f), new Vector2(0.5f, 0.5f),
                Vector2.zero, 20, TextAnchor.MiddleLeft);
            metin.raycastTarget = true;

            var yerTutucu = Yazi(rt, ipucu, enBoy - new Vector2(20f, 0f), new Vector2(0.5f, 0.5f),
                Vector2.zero, 20, TextAnchor.MiddleLeft, new Color(0.6f, 0.57f, 0.52f));

            var alan = go.GetComponent<InputField>();
            alan.textComponent = metin;
            alan.placeholder = yerTutucu;
            if (sifre) alan.contentType = InputField.ContentType.Password;

            return alan;
        }

        /// <summary>Kaydırılabilir dikey liste. İçerik RectTransform'u döner.</summary>
        public static RectTransform Liste(Transform parent, Vector2 enBoy,
            Vector2 capa, Vector2 konum, float satirYuksekligi = 60f, float aralik = 6f)
        {
            var kok = new GameObject("liste", typeof(RectTransform), typeof(Image),
                typeof(ScrollRect), typeof(Mask));
            kok.transform.SetParent(parent, false);

            var rt = kok.GetComponent<RectTransform>();
            rt.anchorMin = rt.anchorMax = capa;
            rt.pivot = capa;
            rt.sizeDelta = enBoy;
            rt.anchoredPosition = konum;

            kok.GetComponent<Image>().color = new Color(0f, 0f, 0f, 0.25f);
            kok.GetComponent<Mask>().showMaskGraphic = true;

            var icerik = new GameObject("icerik", typeof(RectTransform),
                typeof(VerticalLayoutGroup), typeof(ContentSizeFitter));
            icerik.transform.SetParent(kok.transform, false);

            var irt = icerik.GetComponent<RectTransform>();
            irt.anchorMin = new Vector2(0f, 1f);
            irt.anchorMax = new Vector2(1f, 1f);
            irt.pivot = new Vector2(0.5f, 1f);
            irt.anchoredPosition = Vector2.zero;
            irt.sizeDelta = new Vector2(0f, 0f);

            var yerlesim = icerik.GetComponent<VerticalLayoutGroup>();
            yerlesim.spacing = aralik;
            yerlesim.padding = new RectOffset(8, 8, 8, 8);
            yerlesim.childControlHeight = false;
            yerlesim.childControlWidth = true;
            yerlesim.childForceExpandHeight = false;
            yerlesim.childForceExpandWidth = true;

            var uydur = icerik.GetComponent<ContentSizeFitter>();
            uydur.verticalFit = ContentSizeFitter.FitMode.PreferredSize;

            var kaydir = kok.GetComponent<ScrollRect>();
            kaydir.content = irt;
            kaydir.horizontal = false;
            kaydir.vertical = true;
            kaydir.movementType = ScrollRect.MovementType.Elastic;
            kaydir.scrollSensitivity = 24f;

            return irt;
        }

        /// <summary>Listeye tek satır ekler (ikon + başlık + açıklama + düğme).</summary>
        public static RectTransform ListeSatiri(Transform liste, Sprite ikon,
            string baslik, string aciklama, string dugmeMetni, System.Action tiklama,
            float yukseklik = 72f)
        {
            var go = new GameObject("satir", typeof(RectTransform), typeof(Image),
                typeof(LayoutElement));
            go.transform.SetParent(liste, false);

            var rt = go.GetComponent<RectTransform>();
            rt.sizeDelta = new Vector2(0f, yukseklik);

            go.GetComponent<LayoutElement>().preferredHeight = yukseklik;
            go.GetComponent<Image>().color = new Color(1f, 1f, 1f, 0.06f);

            if (ikon != null)
                Resim(rt, ikon, new Vector2(yukseklik - 14f, yukseklik - 14f),
                    new Vector2(0f, 0.5f), new Vector2(10f, 0f));

            var solBosluk = ikon != null ? yukseklik + 4f : 12f;

            var b = Yazi(rt, baslik, new Vector2(320f, 26f), new Vector2(0f, 0.5f),
                new Vector2(solBosluk, 14f), 20, TextAnchor.MiddleLeft);
            b.fontStyle = FontStyle.Bold;

            if (!string.IsNullOrEmpty(aciklama))
                Yazi(rt, aciklama, new Vector2(400f, 22f), new Vector2(0f, 0.5f),
                    new Vector2(solBosluk, -12f), 16, TextAnchor.MiddleLeft,
                    new Color(0.72f, 0.69f, 0.64f));

            if (!string.IsNullOrEmpty(dugmeMetni))
                Dugme(rt, dugmeMetni, new Vector2(110f, 44f), new Vector2(1f, 0.5f),
                    new Vector2(-10f, 0f), tiklama, yesil: true, yaziBoyutu: 17);

            return rt;
        }

        /// <summary>Sayıları okunur kısaltır: 1.2B, 345B, 12M.</summary>
        public static string Sayi(long deger)
        {
            if (deger >= 1_000_000_000) return (deger / 1_000_000_000f).ToString("0.#") + "Mr";
            if (deger >= 1_000_000) return (deger / 1_000_000f).ToString("0.#") + "M";
            if (deger >= 1_000) return (deger / 1_000f).ToString("0.#") + "B";

            return deger.ToString();
        }

        /// <summary>Saniyeyi "2s 14d" gibi okunur süreye çevirir.</summary>
        public static string Sure(int saniye)
        {
            if (saniye <= 0) return "hazır";

            var gun = saniye / 86400;
            var saat = (saniye % 86400) / 3600;
            var dakika = (saniye % 3600) / 60;
            var sn = saniye % 60;

            if (gun > 0) return $"{gun}g {saat}s";
            if (saat > 0) return $"{saat}s {dakika}d";
            if (dakika > 0) return $"{dakika}d {sn}sn";

            return $"{sn}sn";
        }
    }
}
