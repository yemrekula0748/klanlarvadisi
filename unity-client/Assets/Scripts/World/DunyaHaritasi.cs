using System.Collections;
using System.Collections.Generic;
using KlanlarVadisi.Core;
using KlanlarVadisi.Net;
using KlanlarVadisi.Util;
using UnityEngine;

namespace KlanlarVadisi.World
{
    /// <summary>
    /// 1600x1600'lük dünya haritası (2.56 milyon kare).
    ///
    /// Arazi sunucudan İNDİRİLMEZ: <see cref="WorldTerrain"/> ile aynı
    /// tohumdan üretilir. Yalnızca obalar, kamplar ve damarlar API'den gelir.
    ///
    /// Başarım için harita 32x32'lik parçalara bölünür; her parça TEK bir
    /// mesh'e (birleştirilmiş dörtgenler) dönüştürülür. Böylece milyonlarca
    /// kare yerine ekranda sadece birkaç düzine çizim çağrısı olur.
    /// Görüş alanı dışına çıkan parçalar havuza geri verilir.
    /// </summary>
    public class DunyaHaritasi : MonoBehaviour
    {
        public const int ParcaBoyu = 32;

        [Header("Görünüm")]
        public int GorusYaricapi = 2;          // kaç parça yarıçapında yüklenir
        public float SusYogunlugu = 0.14f;

        private WorldTerrain _arazi;
        private Camera _kamera;
        private Material _malzeme;

        private readonly Dictionary<Vector2Int, Parca> _parcalar = new();
        private readonly Queue<Parca> _havuz = new();
        private readonly Dictionary<Vector2Int, DunyaKaresi> _nesneler = new();
        private readonly HashSet<Vector2Int> _istenenParcalar = new();
        private Transform _isaretKok;

        public System.Action<DunyaKaresi> KareSecildi;

        /// <summary>Bir harita parçası: tek mesh + süsler.</summary>
        private class Parca
        {
            public GameObject Nesne;
            public MeshFilter Filtre;
            public MeshRenderer Cizici;
            public Transform SusKok;
            public Vector2Int Koordinat;
        }

        public void Kur(Camera kamera, int tohum, int genislik, int yukseklik)
        {
            _kamera = kamera;
            _arazi = new WorldTerrain(tohum, genislik, yukseklik);

            // Tüm arazi karelerini tek atlas yerine tek renkli mesh ile çiziyoruz:
            // vertex renkleri biyom rengini taşır, bu en hızlı yoldur.
            var shader = Shader.Find("Sprites/Default");
            _malzeme = new Material(shader);

            _isaretKok = new GameObject("Isaretler").transform;
            _isaretKok.SetParent(transform, false);
        }

        public WorldTerrain Arazi => _arazi;

        /// <summary>Sunucudan gelen dolu kareleri kaydeder.</summary>
        public void NesneleriEkle(DunyaParcasi parca)
        {
            foreach (var k in parca.tiles)
                _nesneler[new Vector2Int(k.x, k.y)] = k;

            IsaretleriTazele();
        }

        private void Update()
        {
            if (_arazi == null || _kamera == null) return;

            GorunenParcalariGuncelle();
        }

        /* ------------------------------------------------------------------ */
        /* Parça yönetimi                                                      */
        /* ------------------------------------------------------------------ */

        private void GorunenParcalariGuncelle()
        {
            var merkez = Izometri.DunyadanIzgaraya(_kamera.transform.position);
            var cx = Mathf.FloorToInt(merkez.x / ParcaBoyu);
            var cy = Mathf.FloorToInt(merkez.y / ParcaBoyu);

            // Zoom arttıkça daha çok parça gerekir
            var yaricap = Mathf.Clamp(
                GorusYaricapi + Mathf.RoundToInt(_kamera.orthographicSize / 12f),
                1, 6);

            var gerekli = new HashSet<Vector2Int>();

            for (var dy = -yaricap; dy <= yaricap; dy++)
            for (var dx = -yaricap; dx <= yaricap; dx++)
            {
                var koord = new Vector2Int(cx + dx, cy + dy);
                if (koord.x < 0 || koord.y < 0) continue;
                if (koord.x * ParcaBoyu >= _arazi.Width || koord.y * ParcaBoyu >= _arazi.Height) continue;

                gerekli.Add(koord);

                if (!_parcalar.ContainsKey(koord))
                    ParcaYukle(koord);
            }

            // Görüş dışındakileri havuza ver
            var kaldir = new List<Vector2Int>();
            foreach (var kv in _parcalar)
                if (!gerekli.Contains(kv.Key)) kaldir.Add(kv.Key);

            foreach (var k in kaldir)
            {
                var p = _parcalar[k];
                p.Nesne.SetActive(false);
                _havuz.Enqueue(p);
                _parcalar.Remove(k);
            }
        }

        private void ParcaYukle(Vector2Int koord)
        {
            var p = _havuz.Count > 0 ? _havuz.Dequeue() : ParcaYarat();
            p.Koordinat = koord;
            p.Nesne.name = $"parca_{koord.x}_{koord.y}";
            p.Nesne.SetActive(true);

            MeshUret(p);
            SusleriUret(p);

            _parcalar[koord] = p;

            // Bu parçadaki nesneleri sunucudan iste
            if (_istenenParcalar.Add(koord))
                ParcaNesneleriniIste(koord);
        }

        private Parca ParcaYarat()
        {
            var go = new GameObject("parca");
            go.transform.SetParent(transform, false);

            var p = new Parca
            {
                Nesne = go,
                Filtre = go.AddComponent<MeshFilter>(),
                Cizici = go.AddComponent<MeshRenderer>(),
            };

            p.Cizici.sharedMaterial = _malzeme;
            p.Cizici.sortingOrder = -20000;

            p.SusKok = new GameObject("susler").transform;
            p.SusKok.SetParent(go.transform, false);

            return p;
        }

        /// <summary>
        /// Parçanın bütün karelerini tek bir mesh'e birleştirir.
        /// 32x32 = 1024 kare -> 4096 köşe, tek çizim çağrısı.
        /// </summary>
        private void MeshUret(Parca p)
        {
            var kareSayisi = ParcaBoyu * ParcaBoyu;
            var koseler = new Vector3[kareSayisi * 4];
            var renkler = new Color32[kareSayisi * 4];
            var uvler = new Vector2[kareSayisi * 4];
            var ucgenler = new int[kareSayisi * 6];

            var halfW = (Izometri.KareGenislik / Izometri.PikselBirim) * 0.5f;
            var halfH = (Izometri.KareYukseklik / Izometri.PikselBirim) * 0.5f;

            var i = 0;
            for (var ly = 0; ly < ParcaBoyu; ly++)
            for (var lx = 0; lx < ParcaBoyu; lx++)
            {
                var gx = p.Koordinat.x * ParcaBoyu + lx;
                var gy = p.Koordinat.y * ParcaBoyu + ly;

                var ornek = _arazi.Ornekle(gx, gy);
                var renk = BiyomRengi(ornek.Biyom, gx, gy);

                var merkez = Izometri.IzgaradanDunyaya(gx + 0.5f, gy + 0.5f);
                var v = i * 4;

                // Eşkenar dörtgen: üst, sağ, alt, sol
                koseler[v + 0] = merkez + new Vector3(0f, halfH, 0f);
                koseler[v + 1] = merkez + new Vector3(halfW, 0f, 0f);
                koseler[v + 2] = merkez + new Vector3(0f, -halfH, 0f);
                koseler[v + 3] = merkez + new Vector3(-halfW, 0f, 0f);

                for (var k = 0; k < 4; k++)
                {
                    renkler[v + k] = renk;
                    uvler[v + k] = Vector2.zero;
                }

                var t = i * 6;
                ucgenler[t + 0] = v + 0;
                ucgenler[t + 1] = v + 1;
                ucgenler[t + 2] = v + 2;
                ucgenler[t + 3] = v + 0;
                ucgenler[t + 4] = v + 2;
                ucgenler[t + 5] = v + 3;

                i++;
            }

            var mesh = p.Filtre.mesh;
            if (mesh == null) mesh = new Mesh();
            mesh.Clear();
            mesh.indexFormat = UnityEngine.Rendering.IndexFormat.UInt32;
            mesh.vertices = koseler;
            mesh.colors32 = renkler;
            mesh.uv = uvler;
            mesh.triangles = ucgenler;
            mesh.RecalculateBounds();

            p.Filtre.mesh = mesh;
        }

        /// <summary>Biyom rengi + kareye özgü hafif ton değişimi.</summary>
        private Color32 BiyomRengi(Biyom b, int x, int y)
        {
            var temel = b switch
            {
                Biyom.Deniz => new Color(0.106f, 0.290f, 0.420f),
                Biyom.Kiyi => new Color(0.788f, 0.702f, 0.510f),
                Biyom.Bozkir => new Color(0.561f, 0.627f, 0.353f),
                Biyom.Cayir => new Color(0.435f, 0.608f, 0.290f),
                Biyom.Orman => new Color(0.184f, 0.365f, 0.227f),
                Biyom.Tayga => new Color(0.231f, 0.420f, 0.322f),
                Biyom.Dag => new Color(0.490f, 0.459f, 0.412f),
                Biyom.Kar => new Color(0.910f, 0.933f, 0.949f),
                Biyom.Col => new Color(0.773f, 0.541f, 0.290f),
                Biyom.Bataklik => new Color(0.333f, 0.376f, 0.227f),
                Biyom.Nehir => new Color(0.176f, 0.435f, 0.576f),
                _ => Color.magenta,
            };

            // Kareye özgü ton: harita düz görünmesin
            var h = Mathf.Abs(Mathf.Sin(x * 12.9898f + y * 78.233f) * 43758.5453f) % 1f;
            var ton = 0.94f + h * 0.12f;

            return new Color32(
                (byte)(Mathf.Clamp01(temel.r * ton) * 255),
                (byte)(Mathf.Clamp01(temel.g * ton) * 255),
                (byte)(Mathf.Clamp01(temel.b * ton) * 255),
                255);
        }

        /// <summary>Ağaç, kaya gibi süsleri seyrek olarak serpiştirir.</summary>
        private void SusleriUret(Parca p)
        {
            foreach (Transform c in p.SusKok) Destroy(c.gameObject);

            for (var ly = 0; ly < ParcaBoyu; ly += 2)
            for (var lx = 0; lx < ParcaBoyu; lx += 2)
            {
                var gx = p.Koordinat.x * ParcaBoyu + lx;
                var gy = p.Koordinat.y * ParcaBoyu + ly;

                var h = Mathf.Abs(Mathf.Sin(gx * 45.164f + gy * 91.741f) * 31337.17f) % 1f;
                if (h > SusYogunlugu) continue;

                var ornek = _arazi.Ornekle(gx, gy);
                var ad = WorldTerrain.SusAdi(ornek.Biyom, h / SusYogunlugu);
                if (ad == null) continue;

                var go = new GameObject("sus");
                go.transform.SetParent(p.SusKok, false);
                go.transform.position = Izometri.IzgaradanDunyaya(gx + 0.5f, gy + 0.5f);
                go.transform.localScale = Vector3.one * 0.5f;

                var sr = go.AddComponent<SpriteRenderer>();
                sr.sprite = SpriteKutuphanesi.Arazi(ad);
                sr.sortingOrder = Izometri.SiralamaDegeri(gx, gy) - 5000;
            }
        }

        /* ------------------------------------------------------------------ */
        /* Sunucudan nesneler                                                  */
        /* ------------------------------------------------------------------ */

        private void ParcaNesneleriniIste(Vector2Int koord)
        {
            if (ApiClient.I == null) return;

            StartCoroutine(ApiClient.I.DunyaParca(
                koord.x * ParcaBoyu, koord.y * ParcaBoyu, ParcaBoyu, ParcaBoyu,
                NesneleriEkle,
                _ => { /* parça alınamadıysa sessiz geç: harita yine çizilir */ }));
        }

        private void IsaretleriTazele()
        {
            foreach (Transform c in _isaretKok) Destroy(c.gameObject);

            foreach (var kv in _nesneler)
            {
                var k = kv.Value;
                var ad = k.type switch
                {
                    "oba" => "isaret_oba",
                    "ucube_kampi" => "isaret_kamp",
                    "kaynak_damari" => "isaret_damar",
                    "harabe" => "isaret_harabe",
                    "kutsal_alan" => "isaret_kutsal",
                    _ => "isaret_harabe",
                };

                var go = new GameObject($"isaret_{k.x}_{k.y}");
                go.transform.SetParent(_isaretKok, false);
                go.transform.position = Izometri.IzgaradanDunyaya(k.x + 0.5f, k.y + 0.5f, 0.18f);
                go.transform.localScale = Vector3.one * 0.55f;

                var sr = go.AddComponent<SpriteRenderer>();
                sr.sprite = SpriteKutuphanesi.Arazi(ad);
                sr.sortingOrder = Izometri.SiralamaDegeri(k.x, k.y) + 100;

                var col = go.AddComponent<BoxCollider2D>();
                col.size = new Vector2(0.5f, 0.5f);

                var tik = go.AddComponent<KareTiklama>();
                tik.Kare = k;
                tik.Harita = this;
            }
        }

        internal void KareTiklandi(DunyaKaresi k) => KareSecildi?.Invoke(k);

        /// <summary>Harita işaretlerine dokunmayı yakalar.</summary>
        private class KareTiklama : MonoBehaviour
        {
            public DunyaKaresi Kare;
            public DunyaHaritasi Harita;

            private void OnMouseUpAsButton() => Harita.KareTiklandi(Kare);
        }
    }
}
