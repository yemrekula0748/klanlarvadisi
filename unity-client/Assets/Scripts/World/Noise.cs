using System;

namespace KlanlarVadisi.World
{
    /// <summary>
    /// Deterministik gürültü (value noise + fBm).
    ///
    /// ÖNEMLİ: Bu sınıf, sunucudaki <c>app/Support/Noise.php</c> ile BİREBİR
    /// aynı sonucu üretmek zorundadır. Aynı tohum, aynı arazi demektir;
    /// böylece 1600x1600'lük dünya haritasının tek bir karesi bile ağdan
    /// indirilmez. Burada bir satır değişirse sunucuda da değişmelidir.
    /// </summary>
    public sealed class Noise
    {
        private readonly int _seed;

        public Noise(int seed) { _seed = seed; }

        /// <summary>2B tamsayı karma — [0,1) aralığında deterministik değer.</summary>
        public float Hash(int x, int y, int salt = 0)
        {
            // PHP tarafındaki taşma davranışını birebir yakalamak için
            // tüm ara sonuçlar 31 bitle maskelenir.
            long h = _seed ^ ((long)x * 374761393L) ^ ((long)y * 668265263L) ^ ((long)salt * 2147483647L);
            h &= 0x7FFFFFFF;
            h = ((h ^ (h >> 13)) * 1274126177L);
            h &= 0x7FFFFFFF;
            h ^= h >> 16;

            return (h & 0x7FFFFFFF) / 2147483647f;
        }

        private static float Smooth(float t) => t * t * (3f - 2f * t);

        /// <summary>Bilinear interpolasyonlu değer gürültüsü.</summary>
        public float Value(float x, float y, int salt = 0)
        {
            var x0 = (int)Math.Floor(x);
            var y0 = (int)Math.Floor(y);
            var fx = Smooth(x - x0);
            var fy = Smooth(y - y0);

            var v00 = Hash(x0, y0, salt);
            var v10 = Hash(x0 + 1, y0, salt);
            var v01 = Hash(x0, y0 + 1, salt);
            var v11 = Hash(x0 + 1, y0 + 1, salt);

            var top = v00 + (v10 - v00) * fx;
            var bottom = v01 + (v11 - v01) * fx;

            return top + (bottom - top) * fy;
        }

        /// <summary>Katmanlı (fBm) gürültü — [0,1].</summary>
        public float Fbm(float x, float y, int octaves = 5,
            float lacunarity = 2f, float gain = 0.5f, int salt = 0)
        {
            var sum = 0f;
            var amp = 1f;
            var norm = 0f;
            var freq = 1f;

            for (var i = 0; i < octaves; i++)
            {
                sum += amp * Value(x * freq, y * freq, salt + i * 101);
                norm += amp;
                amp *= gain;
                freq *= lacunarity;
            }

            return norm > 0f ? sum / norm : 0f;
        }

        /// <summary>Sırt/damar deseni (nehir ve dağ sıraları) — [0,1].</summary>
        public float Ridge(float x, float y, int octaves = 4, int salt = 7)
        {
            var v = Fbm(x, y, octaves, 2f, 0.5f, salt);
            return 1f - Math.Abs(v * 2f - 1f);
        }
    }
}
