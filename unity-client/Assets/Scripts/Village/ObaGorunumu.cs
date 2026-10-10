using System.Collections.Generic;
using KlanlarVadisi.Net;
using KlanlarVadisi.Util;
using UnityEngine;

namespace KlanlarVadisi.Village
{
    /// <summary>
    /// Oyuncunun obasını izometrik olarak çizer.
    ///
    /// Sunucudan gelen <see cref="ObaDurumu"/> tek doğruluk kaynağıdır;
    /// burada yalnızca görselleştirme yapılır. Her bina bir SpriteRenderer
    /// nesnesidir, sıralama ızgara derinliğine göre verilir.
    /// </summary>
    public class ObaGorunumu : MonoBehaviour
    {
        public Color ZeminRengi = new(0.43f, 0.58f, 0.28f);
        public Color IzgaraRengi = new(1f, 1f, 1f, 0.07f);

        private readonly Dictionary<int, BinaGorunumu> _binalar = new();
        private Transform _zemin;
        private Transform _binaKok;
        private ObaDurumu _durum;

        public ObaDurumu Durum => _durum;
        public int IzgaraBoyu { get; private set; } = 44;

        /// <summary>Bina tıklandığında tetiklenir.</summary>
        public System.Action<BinaDurumu> BinaSecildi;

        private void Awake()
        {
            _zemin = new GameObject("Zemin").transform;
            _zemin.SetParent(transform, false);

            _binaKok = new GameObject("Binalar").transform;
            _binaKok.SetParent(transform, false);
        }

        public void DurumuUygula(ObaDurumu durum)
        {
            _durum = durum;
            IzgaraBoyu = durum?.village?.grid > 0 ? durum.village.grid : 44;

            if (_zemin.childCount == 0) ZeminiCiz();

            var gorulen = new HashSet<int>();

            foreach (var b in durum.buildings)
            {
                gorulen.Add(b.id);

                if (!_binalar.TryGetValue(b.id, out var gorunum))
                {
                    gorunum = BinaGorunumu.Olustur(_binaKok, b);
                    gorunum.Tiklandi += () => BinaSecildi?.Invoke(gorunum.Veri);
                    _binalar[b.id] = gorunum;
                }

                gorunum.Guncelle(b);
            }

            // Artık olmayan binaları kaldır (yıkılan duvar, iptal edilen inşaat)
            var silinecek = new List<int>();
            foreach (var kv in _binalar)
                if (!gorulen.Contains(kv.Key)) silinecek.Add(kv.Key);

            foreach (var id in silinecek)
            {
                if (_binalar[id] != null) Destroy(_binalar[id].gameObject);
                _binalar.Remove(id);
            }
        }

        public BinaGorunumu BinayiBul(int id)
            => _binalar.TryGetValue(id, out var g) ? g : null;

        /// <summary>Izgara zeminini tek seferde üretir.</summary>
        private void ZeminiCiz()
        {
            var zeminSprite = SpriteKutuphanesi.Arazi("cayir");

            for (var y = 0; y < IzgaraBoyu; y++)
            for (var x = 0; x < IzgaraBoyu; x++)
            {
                var go = new GameObject($"kare_{x}_{y}");
                go.transform.SetParent(_zemin, false);
                go.transform.position = Izometri.IzgaradanDunyaya(x + 0.5f, y + 0.5f);

                var sr = go.AddComponent<SpriteRenderer>();
                sr.sprite = zeminSprite;
                sr.sortingOrder = -10000 + Izometri.SiralamaDegeri(x, y);

                // Hafif desen: komşu karelerde ton farkı
                var ton = ((x + y) % 2 == 0) ? 1f : 0.96f;
                sr.color = new Color(ton, ton, ton, 1f);
            }
        }

        /// <summary>Izgara çizgilerini açıp kapatır (düzenleme kipi).</summary>
        public void IzgarayiGoster(bool goster)
        {
            var mevcut = transform.Find("IzgaraCizgileri");

            if (!goster)
            {
                if (mevcut != null) mevcut.gameObject.SetActive(false);
                return;
            }

            if (mevcut != null) { mevcut.gameObject.SetActive(true); return; }

            var kok = new GameObject("IzgaraCizgileri");
            kok.transform.SetParent(transform, false);

            for (var i = 0; i <= IzgaraBoyu; i++)
            {
                CizgiCiz(kok.transform,
                    Izometri.IzgaradanDunyaya(i, 0),
                    Izometri.IzgaradanDunyaya(i, IzgaraBoyu));
                CizgiCiz(kok.transform,
                    Izometri.IzgaradanDunyaya(0, i),
                    Izometri.IzgaradanDunyaya(IzgaraBoyu, i));
            }
        }

        private void CizgiCiz(Transform parent, Vector3 a, Vector3 b)
        {
            var go = new GameObject("cizgi");
            go.transform.SetParent(parent, false);

            var lr = go.AddComponent<LineRenderer>();
            lr.useWorldSpace = true;
            lr.positionCount = 2;
            lr.SetPosition(0, a);
            lr.SetPosition(1, b);
            lr.startWidth = lr.endWidth = 0.012f;
            lr.material = new Material(Shader.Find("Sprites/Default"));
            lr.startColor = lr.endColor = IzgaraRengi;
            lr.sortingOrder = 9000;
        }
    }
}
