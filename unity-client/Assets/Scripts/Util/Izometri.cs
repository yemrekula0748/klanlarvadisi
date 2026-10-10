using UnityEngine;

namespace KlanlarVadisi.Util
{
    /// <summary>
    /// İzometrik ızgara matematiği.
    ///
    /// Sprite'lar 2:1 izometrik olarak üretildi (128x64 piksel/kare).
    /// Unity'de 1 birim = 1 ızgara karesi olacak şekilde ölçeklenir.
    /// </summary>
    public static class Izometri
    {
        /// <summary>Bir karenin ekran genişliği (piksel).</summary>
        public const float KareGenislik = 128f;

        /// <summary>Bir karenin ekran yüksekliği (piksel).</summary>
        public const float KareYukseklik = 64f;

        /// <summary>Sprite'ların piksel/birim oranı.</summary>
        public const float PikselBirim = 128f;

        private static readonly float HalfW = (KareGenislik / PikselBirim) * 0.5f;
        private static readonly float HalfH = (KareYukseklik / PikselBirim) * 0.5f;

        /// <summary>Izgara koordinatını dünya (Unity) konumuna çevirir.</summary>
        public static Vector3 IzgaradanDunyaya(float gx, float gy, float yukseklik = 0f)
        {
            return new Vector3(
                (gx - gy) * HalfW,
                -(gx + gy) * HalfH + yukseklik,
                0f);
        }

        /// <summary>Dünya konumunu ızgara koordinatına çevirir (kesirli).</summary>
        public static Vector2 DunyadanIzgaraya(Vector3 dunya)
        {
            var a = dunya.x / HalfW;
            var b = -dunya.y / HalfH;

            return new Vector2((a + b) * 0.5f, (b - a) * 0.5f);
        }

        /// <summary>Dünya konumunu en yakın ızgara karesine yuvarlar.</summary>
        public static Vector2Int DunyadanKareye(Vector3 dunya)
        {
            var g = DunyadanIzgaraya(dunya);

            return new Vector2Int(Mathf.FloorToInt(g.x), Mathf.FloorToInt(g.y));
        }

        /// <summary>
        /// Çizim sırası: ızgarada arkadaki bina önce çizilir. Unity'de
        /// sortingOrder tamsayı olduğundan ızgara toplamı ölçeklenir.
        /// </summary>
        public static int SiralamaDegeri(float gx, float gy, int katman = 0)
        {
            return Mathf.RoundToInt((gx + gy) * 16f) + katman;
        }

        /// <summary>Bir binanın ayak izinin merkezi.</summary>
        public static Vector3 BinaMerkezi(int x, int y, int w, int h, float yukseklik = 0f)
        {
            return IzgaradanDunyaya(x + w * 0.5f, y + h * 0.5f, yukseklik);
        }
    }
}
