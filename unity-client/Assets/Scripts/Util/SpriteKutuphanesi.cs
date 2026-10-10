using System.Collections.Generic;
using UnityEngine;

namespace KlanlarVadisi.Util
{
    /// <summary>
    /// Resources/Art altındaki görselleri yükler ve önbelleğe alır.
    ///
    /// Görseller `tools/art` ile üretilir (SVG kaynak -> PNG). Eksik bir
    /// görsel istenirse oyun çökmez; yerine belirgin bir yer tutucu konur.
    /// </summary>
    public static class SpriteKutuphanesi
    {
        private static readonly Dictionary<string, Sprite> Onbellek = new();
        private static Sprite _yerTutucu;

        public static Sprite Bina(string anahtar) => Yukle($"Art/Buildings/{anahtar}");
        public static Sprite Asker(string anahtar) => Yukle($"Art/Troops/{anahtar}");
        public static Sprite Arazi(string anahtar) => Yukle($"Art/Terrain/{anahtar}");
        public static Sprite Arayuz(string anahtar) => Yukle($"Art/UI/{anahtar}");

        public static Sprite Yukle(string yol)
        {
            if (Onbellek.TryGetValue(yol, out var s)) return s;

            s = Resources.Load<Sprite>(yol);
            if (s == null)
            {
                Debug.LogWarning($"[Sprite] Bulunamadı: {yol}");
                s = YerTutucu();
            }

            Onbellek[yol] = s;

            return s;
        }

        /// <summary>Eksik görseller için mor-siyah dama deseni.</summary>
        private static Sprite YerTutucu()
        {
            if (_yerTutucu != null) return _yerTutucu;

            var tex = new Texture2D(32, 32) { filterMode = FilterMode.Point };
            for (var y = 0; y < 32; y++)
            for (var x = 0; x < 32; x++)
            {
                var kare = ((x / 8) + (y / 8)) % 2 == 0;
                tex.SetPixel(x, y, kare ? new Color(0.8f, 0f, 0.8f) : Color.black);
            }
            tex.Apply();

            _yerTutucu = Sprite.Create(tex, new Rect(0, 0, 32, 32), new Vector2(0.5f, 0.5f), 32f);

            return _yerTutucu;
        }

        /// <summary>Kaynak türüne göre arayüz ikonu.</summary>
        public static Sprite KaynakIkonu(string tur) => tur switch
        {
            "gold" => Arayuz("ikon_altin"),
            "grain" => Arayuz("ikon_tahil"),
            "iron" => Arayuz("ikon_demir"),
            "kut" => Arayuz("ikon_kut"),
            "gok_tasi" => Arayuz("ikon_gok_tasi"),
            _ => Arayuz("ikon_altin"),
        };
    }
}
