using System;
using System.Collections.Generic;
using UnityEngine;

namespace KlanlarVadisi.World
{
    public enum Biyom
    {
        Deniz, Kiyi, Bozkir, Cayir, Orman, Tayga, Dag, Kar, Col, Bataklik, Nehir,
    }

    public struct AraziOrnegi
    {
        public Biyom Biyom;
        public float Yukseklik;
        public float Sicaklik;
        public float Nem;
        public bool Gecilebilir;
    }

    /// <summary>
    /// Dünya arazisinin istemci tarafı üretimi.
    ///
    /// Sunucudaki <c>App\Services\WorldService::terrainAt()</c> ile birebir
    /// aynı sonucu verir. Oyuncu haritada gezerken hiçbir arazi verisi
    /// indirilmez; yalnızca obalar, kamplar ve damarlar sunucudan gelir.
    /// </summary>
    public sealed class WorldTerrain
    {
        public readonly int Width;
        public readonly int Height;

        private readonly Noise _noise;
        private readonly List<Sekillendirici> _shapers = new();

        /// <summary>Adı olan bölgelerin araziyi büken etkisi.</summary>
        private struct Sekillendirici
        {
            public float Cx, Cy, R, E, T, M, S;
        }

        public WorldTerrain(int seed, int width, int height)
        {
            _noise = new Noise(seed);
            Width = width;
            Height = height;
            VarsayilanBolgeleriYukle();
        }

        /// <summary>
        /// Bölge tanımları sunucudaki <c>config/regions.php</c> ile aynıdır.
        /// `php artisan game:export-unity` komutu bu dosyayı yeniden üretir.
        /// </summary>
        private void VarsayilanBolgeleriYukle()
        {
            foreach (var r in BolgeTanimlari.Hepsi)
            {
                _shapers.Add(new Sekillendirici
                {
                    Cx = r.AtX * Width,
                    Cy = r.AtY * Height,
                    R = r.Radius * Mathf.Min(Width, Height),
                    E = r.E, T = r.T, M = r.M, S = r.Strength,
                });
            }
        }

        private static float Stretch(float v, float k) => Mathf.Clamp01((v - 0.5f) * k + 0.5f);

        private static float SmoothStep(float t)
        {
            t = Mathf.Clamp01(t);
            return t * t * (3f - 2f * t);
        }

        public AraziOrnegi Ornekle(int x, int y)
        {
            // Kenar maskesi: haritanın içi kara, kenarları Kök Deniz
            var nx = (x / (float)Width) * 2f - 1f;
            var ny = (y / (float)Height) * 2f - 1f;
            var edge = Mathf.Max(Mathf.Abs(nx), Mathf.Abs(ny));
            var mask = SmoothStep((1f - edge) / 0.26f);

            var continent = Stretch(_noise.Fbm(x / 260f, y / 260f, 5, 2f, 0.5f, 11), 1.9f);
            var elevation = continent * mask;

            var temperature = Stretch(
                1f - (y / (float)Height) + (_noise.Fbm(x / 170f, y / 170f, 3, 2f, 0.5f, 23) - 0.5f) * 0.30f,
                1.15f) - elevation * 0.28f;
            temperature = Mathf.Clamp01(temperature);

            var moisture = Stretch(
                _noise.Fbm(x / 150f, y / 150f, 4, 2f, 0.5f, 37) * 0.78f
                + _noise.Fbm(x / 430f, y / 430f, 2, 2f, 0.5f, 71) * 0.22f,
                2.3f);

            // Bölgeler araziyi kendi karakterine büker
            foreach (var s in _shapers)
            {
                var dx = x - s.Cx;
                var dy = y - s.Cy;
                var dist = Mathf.Sqrt(dx * dx + dy * dy);
                if (dist >= s.R) continue;

                var w = SmoothStep(1f - dist / s.R) * s.S;
                elevation += (s.E - elevation) * w;
                temperature += (s.T - temperature) * w;
                moisture += (s.M - moisture) * w;
            }

            elevation = Mathf.Clamp01(elevation * mask + elevation * (1f - mask) * mask);
            temperature = Mathf.Clamp01(temperature);
            moisture = Mathf.Clamp01(moisture);

            var isRiver = _noise.Ridge(x / 280f, y / 280f, 4, 53) > 0.9715f
                && elevation > 0.20f && elevation < 0.70f;

            var biyom = Siniflandir(elevation, temperature, moisture, isRiver);

            return new AraziOrnegi
            {
                Biyom = biyom,
                Yukseklik = elevation,
                Sicaklik = temperature,
                Nem = moisture,
                Gecilebilir = Gecilebilir(biyom),
            };
        }

        private static Biyom Siniflandir(float e, float t, float m, bool river)
        {
            if (e < 0.17f) return Biyom.Deniz;
            if (e < 0.21f) return Biyom.Kiyi;
            if (e > 0.87f) return Biyom.Kar;
            if (e > 0.70f) return Biyom.Dag;
            if (river) return Biyom.Nehir;
            if (t < 0.22f) return m > 0.52f ? Biyom.Tayga : Biyom.Bozkir;
            if (t > 0.68f && m < 0.40f) return Biyom.Col;
            if (m > 0.64f) return e < 0.29f ? Biyom.Bataklik : Biyom.Orman;
            if (m > 0.46f) return Biyom.Cayir;
            return Biyom.Bozkir;
        }

        public static bool Gecilebilir(Biyom b) => b switch
        {
            Biyom.Deniz => false,
            Biyom.Dag => false,
            Biyom.Kar => false,
            Biyom.Nehir => false,
            _ => true,
        };

        public static string SpriteAdi(Biyom b) => b switch
        {
            Biyom.Deniz => "deniz",
            Biyom.Kiyi => "kiyi",
            Biyom.Bozkir => "bozkir",
            Biyom.Cayir => "cayir",
            Biyom.Orman => "orman",
            Biyom.Tayga => "tayga",
            Biyom.Dag => "dag",
            Biyom.Kar => "kar",
            Biyom.Col => "col",
            Biyom.Bataklik => "bataklik",
            Biyom.Nehir => "nehir",
            _ => "bozkir",
        };

        /// <summary>Biyoma göre haritaya serpilecek süs sprite'ı (yoksa null).</summary>
        public static string SusAdi(Biyom b, float rastgele) => b switch
        {
            Biyom.Orman => rastgele < 0.55f ? "sus_cam" : "sus_agac",
            Biyom.Tayga => "sus_cam",
            Biyom.Kar => rastgele < 0.5f ? "sus_kar_cam" : null,
            Biyom.Dag => "sus_kaya",
            Biyom.Col => rastgele < 0.35f ? "sus_kaktus" : null,
            Biyom.Cayir => rastgele < 0.18f ? "sus_agac" : null,
            Biyom.Bozkir => rastgele < 0.08f ? "sus_kaya" : null,
            _ => null,
        };
    }
}
