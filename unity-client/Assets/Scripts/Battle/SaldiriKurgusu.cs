using System.Collections.Generic;
using KlanlarVadisi.Core;
using KlanlarVadisi.Net;
using KlanlarVadisi.UI;
using KlanlarVadisi.Util;
using UnityEngine;
using UnityEngine.EventSystems;
using UnityEngine.UI;

namespace KlanlarVadisi.Battle
{
    /// <summary>
    /// Saldırı kurgu ekranı: rakibin obası gösterilir, oyuncu askerlerini
    /// seçip haritaya indirir.
    ///
    /// ÖNEMLİ: Burada hiçbir savaş hesabı yapılmaz. Oyuncunun indirdiği
    /// askerlerin yeri ve zamanı toplanır, sunucuya gönderilir; sonucu ve
    /// tekrarı sunucu üretir. İstemci yalnızca niyeti iletir.
    /// </summary>
    public class SaldiriKurgusu : MonoBehaviour
    {
        private Camera _kamera;
        private KameraSurucu _kameraSurucu;
        private Canvas _tuval;
        private GameObject _arayuz;
        private Transform _binaKok;

        private KesifYaniti _hedef;
        private ObaDurumu _benim;
        private Katalog _katalog;

        private readonly List<AskerIndirme> _indirmeler = new();
        private readonly Dictionary<string, int> _kalan = new();
        private readonly Dictionary<string, Text> _sayaclar = new();
        private readonly List<string> _kahramanlar = new();

        private string _seciliAsker;
        private float _sure;
        private bool _basladi;
        private Text _sureMetni;
        private System.Action<List<AskerIndirme>, List<string>> _bitir;
        private System.Action _vazgec;

        public void Baslat(Canvas tuval, Camera kamera, KameraSurucu kameraSurucu,
            KesifYaniti hedef, ObaDurumu benim, Katalog katalog,
            System.Action<List<AskerIndirme>, List<string>> bitir, System.Action vazgec)
        {
            _tuval = tuval;
            _kamera = kamera;
            _kameraSurucu = kameraSurucu;
            _hedef = hedef;
            _benim = benim;
            _katalog = katalog;
            _bitir = bitir;
            _vazgec = vazgec;

            _indirmeler.Clear();
            _kalan.Clear();
            _kahramanlar.Clear();
            _sure = 0f;
            _basladi = false;

            foreach (var a in benim.army.troops)
                if (a.ready > 0) _kalan[a.key] = a.ready;

            foreach (var h in benim.army.heroes)
                if (h.unlocked && h.available) _kahramanlar.Add(h.key);

            HedefObayiCiz();
            ArayuzuKur();

            var orta = (katalog.grid?.size ?? 44) * 0.5f;
            _kameraSurucu.SinirlariAyarla(0f, katalog.grid?.size ?? 44, 0f, katalog.grid?.size ?? 44);
            _kameraSurucu.Odakla(orta, orta, 11f);
        }

        private void HedefObayiCiz()
        {
            if (_binaKok != null) Destroy(_binaKok.gameObject);

            _binaKok = new GameObject("HedefOba").transform;
            _binaKok.SetParent(transform, false);

            // Zemin
            var izgara = _katalog.grid?.size ?? 44;
            var zeminSprite = SpriteKutuphanesi.Arazi("bozkir");

            for (var y = 0; y < izgara; y++)
            for (var x = 0; x < izgara; x++)
            {
                var go = new GameObject("z");
                go.transform.SetParent(_binaKok, false);
                go.transform.position = Izometri.IzgaradanDunyaya(x + 0.5f, y + 0.5f);

                var sr = go.AddComponent<SpriteRenderer>();
                sr.sprite = zeminSprite;
                sr.sortingOrder = -10000 + Izometri.SiralamaDegeri(x, y);

                // Kenar şeridi (asker indirme alanı) belirgin olsun
                var kenar = _katalog.grid?.margin ?? 3;
                var disarida = x < kenar || y < kenar || x >= izgara - kenar || y >= izgara - kenar;
                sr.color = disarida ? new Color(0.82f, 0.86f, 0.7f) : Color.white;
            }

            foreach (var b in _hedef.buildings)
            {
                var go = new GameObject($"b{b.id}");
                go.transform.SetParent(_binaKok, false);
                go.transform.position = Izometri.IzgaradanDunyaya(b.x, b.y);

                var sr = go.AddComponent<SpriteRenderer>();
                sr.sprite = SpriteKutuphanesi.Bina(b.key);
                sr.sortingOrder = Izometri.SiralamaDegeri(b.x + b.w * 0.5f, b.y + b.h * 0.5f);
            }
        }

        private void ArayuzuKur()
        {
            if (_arayuz != null) Destroy(_arayuz);

            var kok = ArayuzFabrikasi.Kutu(_tuval.transform, "SaldiriArayuzu",
                new Vector2(0f, 120f), new Vector2(0.5f, 0f), Vector2.zero,
                new Color(0.1f, 0.09f, 0.08f, 0.9f));
            kok.anchorMin = new Vector2(0f, 0f);
            kok.anchorMax = new Vector2(1f, 0f);
            kok.pivot = new Vector2(0.5f, 0f);
            kok.offsetMin = Vector2.zero;
            kok.offsetMax = new Vector2(0f, 120f);
            _arayuz = kok.gameObject;

            // Asker seçim kutuları
            var x = 20f;
            foreach (var kv in _kalan)
            {
                var anahtar = kv.Key;
                var tip = _katalog.troops.Find(t => t.key == anahtar);

                var btn = ArayuzFabrikasi.Dugme(kok, "", new Vector2(92f, 92f),
                    new Vector2(0f, 0.5f), new Vector2(x, 0f), () => _seciliAsker = anahtar);

                ArayuzFabrikasi.Resim(btn.transform, SpriteKutuphanesi.Asker(anahtar),
                    new Vector2(58f, 58f), new Vector2(0.5f, 1f), new Vector2(0f, -4f));

                _sayaclar[anahtar] = ArayuzFabrikasi.Yazi(btn.transform, kv.Value.ToString(),
                    new Vector2(80f, 24f), new Vector2(0.5f, 0f), new Vector2(0f, 6f),
                    19, TextAnchor.MiddleCenter, ArayuzFabrikasi.Altin);

                x += 100f;

                if (_seciliAsker == null) _seciliAsker = anahtar;
            }

            // Süre
            _sureMetni = ArayuzFabrikasi.Yazi(kok, "Askerini indir",
                new Vector2(260f, 30f), new Vector2(1f, 1f), new Vector2(-150f, -6f),
                20, TextAnchor.MiddleCenter, ArayuzFabrikasi.Altin);

            ArayuzFabrikasi.Dugme(kok, "Bitir", new Vector2(150f, 56f),
                new Vector2(1f, 0.5f), new Vector2(-20f, -14f), Gonder, yesil: true);

            ArayuzFabrikasi.Dugme(kok, "Vazgeç", new Vector2(120f, 48f),
                new Vector2(1f, 1f), new Vector2(-180f, -62f), () =>
                {
                    Temizle();
                    _vazgec?.Invoke();
                }, yaziBoyutu: 17);
        }

        private void Update()
        {
            if (_basladi)
            {
                _sure += Time.deltaTime;
                var kalan = Mathf.Max(0, OyunSabitleri.SavasAzamiSure - Mathf.FloorToInt(_sure));
                if (_sureMetni != null) _sureMetni.text = $"Süre: {kalan} sn";
            }

            if (!Input.GetMouseButtonUp(0)) return;
            if (_kameraSurucu.Surukluyor) return;
            if (EventSystem.current != null && EventSystem.current.IsPointerOverGameObject()) return;

            AskerIndir();
        }

        private void AskerIndir()
        {
            if (string.IsNullOrEmpty(_seciliAsker)) return;
            if (!_kalan.TryGetValue(_seciliAsker, out var kalanAdet) || kalanAdet <= 0)
            {
                Bildirim.Goster("Bu askerden kalmadı.", true);
                return;
            }

            var dunya = _kamera.ScreenToWorldPoint(Input.mousePosition);
            var g = Izometri.DunyadanIzgaraya(dunya);

            var izgara = _katalog.grid?.size ?? 44;
            var kenar = _katalog.grid?.margin ?? 3;

            g.x = Mathf.Clamp(g.x, 0.5f, izgara - 0.5f);
            g.y = Mathf.Clamp(g.y, 0.5f, izgara - 0.5f);

            // Asker yalnızca kenar şeridine inebilir (obanın içine değil)
            var iceride = g.x > kenar && g.y > kenar && g.x < izgara - kenar && g.y < izgara - kenar;
            if (iceride)
            {
                Bildirim.Goster("Asker yalnızca obanın dışına inebilir.", true);
                return;
            }

            if (!_basladi) _basladi = true;

            _indirmeler.Add(new AskerIndirme
            {
                troop = _seciliAsker,
                x = g.x,
                y = g.y,
                t = _sure,
            });

            _kalan[_seciliAsker] = kalanAdet - 1;
            if (_sayaclar.TryGetValue(_seciliAsker, out var sayac))
                sayac.text = _kalan[_seciliAsker].ToString();

            // Görsel geri bildirim
            var go = new GameObject("inen");
            go.transform.SetParent(transform, false);
            go.transform.position = Izometri.IzgaradanDunyaya(g.x, g.y, 0.12f);

            var sr = go.AddComponent<SpriteRenderer>();
            sr.sprite = SpriteKutuphanesi.Asker(_seciliAsker);
            sr.sortingOrder = Izometri.SiralamaDegeri(g.x, g.y) + 1000;

            Efekt.InisTozu(go.transform.position);
        }

        private void Gonder()
        {
            if (_indirmeler.Count == 0)
            {
                Bildirim.Goster("Önce asker indir.", true);
                return;
            }

            var indirmeler = new List<AskerIndirme>(_indirmeler);
            var kahramanlar = new List<string>(_kahramanlar);

            Temizle();
            _bitir?.Invoke(indirmeler, kahramanlar);
        }

        private void Temizle()
        {
            if (_arayuz != null) Destroy(_arayuz);
            if (_binaKok != null) Destroy(_binaKok.gameObject);

            foreach (Transform c in transform) Destroy(c.gameObject);

            _indirmeler.Clear();
            _sayaclar.Clear();
            _basladi = false;
        }
    }
}
