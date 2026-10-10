using System.Collections;
using System.Collections.Generic;
using KlanlarVadisi.Battle;
using KlanlarVadisi.Net;
using KlanlarVadisi.UI;
using KlanlarVadisi.Util;
using KlanlarVadisi.Village;
using KlanlarVadisi.World;
using UnityEngine;
using UnityEngine.EventSystems;
using UnityEngine.UI;

namespace KlanlarVadisi.Core
{
    /// <summary>
    /// Oyunun giriş noktası ve tek sahne yöneticisi.
    ///
    /// Sahnede elle kurulmuş hiçbir nesne yoktur: kamera, arayüz, oba ve
    /// harita burada koddan üretilir. Böylece proje .unity/.prefab
    /// dosyalarına bağımlı kalmaz, sürüm takibinde okunur durur.
    ///
    /// Kipler:
    ///   Giris   -> e-posta/şifre veya konuk girişi
    ///   Oba     -> kendi obası; bina kur/yükselt/taşı
    ///   Harita  -> dünya haritası; rakip bul
    ///   Savas   -> asker indir, sonra sunucunun tekrarını izle
    /// </summary>
    public class OyunBaslatici : MonoBehaviour
    {
        public enum Kip { Giris, Oba, Harita, Savas }

        [Header("Sunucu")]
        public string SunucuAdresi = "http://localhost:8000";

        public Kip AktifKip { get; private set; } = Kip.Giris;

        private Camera _kamera;
        private KameraSurucu _kameraSurucu;
        private Canvas _tuval;
        private UstBar _ustBar;

        private ObaGorunumu _oba;
        private DunyaHaritasi _harita;
        private SavasTekrari _tekrar;
        private SaldiriKurgusu _saldiri;

        private Katalog _katalog;
        private ObaDurumu _durum;
        private GameObject _altBar;
        private GameObject _girisEkrani;

        private Coroutine _tazeleme;

        /* ------------------------------------------------------------------ */
        /* Kurulum                                                             */
        /* ------------------------------------------------------------------ */

        private void Start()
        {
            Application.targetFrameRate = 60;
            Screen.sleepTimeout = SleepTimeout.NeverSleep;

            KameraKur();
            ApiKur();
            ArayuzKur();

            if (ApiClient.I.GirisYapildi)
                StartCoroutine(OyunaGir());
            else
                GirisEkraniniGoster();
        }

        private void KameraKur()
        {
            var go = new GameObject("AnaKamera");
            _kamera = go.AddComponent<Camera>();
            _kamera.orthographic = true;
            _kamera.orthographicSize = 7f;
            _kamera.backgroundColor = new Color(0.078f, 0.094f, 0.110f);
            _kamera.clearFlags = CameraClearFlags.SolidColor;
            _kamera.transform.position = new Vector3(0f, 0f, -10f);
            go.tag = "MainCamera";

            _kameraSurucu = go.AddComponent<KameraSurucu>();
        }

        private void ApiKur()
        {
            if (ApiClient.I != null) return;

            var go = new GameObject("ApiClient");
            var api = go.AddComponent<ApiClient>();
            api.BaseUrl = SunucuAdresi;
        }

        private void ArayuzKur()
        {
            if (FindFirstObjectByType<EventSystem>() == null)
            {
                var es = new GameObject("EventSystem");
                es.AddComponent<EventSystem>();
                es.AddComponent<StandaloneInputModule>();
            }

            _tuval = ArayuzFabrikasi.TuvalKur();
            Bildirim.Olustur(_tuval.transform);
        }

        /* ------------------------------------------------------------------ */
        /* Giriş ekranı                                                        */
        /* ------------------------------------------------------------------ */

        private void GirisEkraniniGoster()
        {
            AktifKip = Kip.Giris;

            var perde = ArayuzFabrikasi.Kutu(_tuval.transform, "GirisEkrani", Vector2.zero,
                new Vector2(0.5f, 0.5f), Vector2.zero, new Color(0.078f, 0.094f, 0.110f, 1f));
            perde.anchorMin = Vector2.zero;
            perde.anchorMax = Vector2.one;
            perde.offsetMin = perde.offsetMax = Vector2.zero;
            _girisEkrani = perde.gameObject;

            var baslik = ArayuzFabrikasi.Yazi(perde, "KLANLAR VADİSİ",
                new Vector2(700f, 70f), new Vector2(0.5f, 0.5f), new Vector2(0f, 220f),
                54, TextAnchor.MiddleCenter, ArayuzFabrikasi.Altin);
            baslik.fontStyle = FontStyle.Bold;

            ArayuzFabrikasi.Yazi(perde, "Bozkırın sözünü söyleyen boy ol",
                new Vector2(700f, 32f), new Vector2(0.5f, 0.5f), new Vector2(0f, 172f),
                20, TextAnchor.MiddleCenter, new Color(0.72f, 0.68f, 0.6f));

            ArayuzFabrikasi.Resim(perde, SpriteKutuphanesi.Bina("otag"),
                new Vector2(260f, 260f), new Vector2(0.5f, 0.5f), new Vector2(0f, 30f));

            var panel = ArayuzFabrikasi.Panel(perde, "form", new Vector2(420f, 300f),
                new Vector2(0.5f, 0.5f), new Vector2(0f, -170f));

            var eposta = ArayuzFabrikasi.Giris(panel, "E-posta",
                new Vector2(360f, 50f), new Vector2(0.5f, 1f), new Vector2(0f, -30f));
            var sifre = ArayuzFabrikasi.Giris(panel, "Şifre",
                new Vector2(360f, 50f), new Vector2(0.5f, 1f), new Vector2(0f, -90f), sifre: true);
            var ad = ArayuzFabrikasi.Giris(panel, "Oyuncu adı (yeni hesap için)",
                new Vector2(360f, 50f), new Vector2(0.5f, 1f), new Vector2(0f, -150f));

            ArayuzFabrikasi.Dugme(panel, "Giriş Yap", new Vector2(170f, 50f),
                new Vector2(0.5f, 0f), new Vector2(-95f, 60f), () =>
                {
                    StartCoroutine(ApiClient.I.Giris(eposta.text, sifre.text,
                        _ => StartCoroutine(OyunaGir()),
                        h => Bildirim.Goster(h.message, true)));
                }, yesil: true);

            ArayuzFabrikasi.Dugme(panel, "Kayıt Ol", new Vector2(170f, 50f),
                new Vector2(0.5f, 0f), new Vector2(95f, 60f), () =>
                {
                    StartCoroutine(ApiClient.I.Kayit(eposta.text, sifre.text, ad.text,
                        _ => StartCoroutine(OyunaGir()),
                        h => Bildirim.Goster(h.message, true)));
                });

            ArayuzFabrikasi.Dugme(panel, "Konuk Olarak Dene", new Vector2(300f, 46f),
                new Vector2(0.5f, 0f), new Vector2(0f, 8f), () =>
                {
                    StartCoroutine(ApiClient.I.KonukGiris(
                        _ => StartCoroutine(OyunaGir()),
                        h => Bildirim.Goster(h.message, true)));
                });
        }

        /* ------------------------------------------------------------------ */
        /* Oyuna giriş                                                         */
        /* ------------------------------------------------------------------ */

        private IEnumerator OyunaGir()
        {
            if (_girisEkrani != null) Destroy(_girisEkrani);

            Bildirim.Goster("Katalog yükleniyor...");

            var hata = false;
            yield return ApiClient.I.KatalogGetir(
                k => _katalog = k,
                h => { hata = true; Bildirim.Goster(h.message, true); });

            if (hata || _katalog == null)
            {
                Bildirim.Goster("Sunucuya bağlanılamadı. Adres doğru mu?", true);
                GirisEkraniniGoster();
                yield break;
            }

            yield return ApiClient.I.ObaGetir(
                d => _durum = d,
                h => { hata = true; Bildirim.Goster(h.message, true); });

            if (hata || _durum == null) yield break;

            ObaKur();
            AltBarKur();
            ObayaGec();

            _ustBar = UstBar.Olustur(_tuval.transform);
            _ustBar.Guncelle(_durum);

            Bildirim.Goster($"Hoş geldin, {_durum.player.name}!");

            _tazeleme = StartCoroutine(DuzenliTazele());
        }

        private void ObaKur()
        {
            var go = new GameObject("Oba");
            _oba = go.AddComponent<ObaGorunumu>();
            _oba.BinaSecildi += BinaSecildi;
            _oba.DurumuUygula(_durum);

            var savasGo = new GameObject("Savas");
            _tekrar = savasGo.AddComponent<SavasTekrari>();
            savasGo.SetActive(false);

            var saldiriGo = new GameObject("SaldiriKurgusu");
            _saldiri = saldiriGo.AddComponent<SaldiriKurgusu>();
            saldiriGo.SetActive(false);
        }

        /// <summary>Durumu belirli aralıklarla sunucudan tazeler.</summary>
        private IEnumerator DuzenliTazele()
        {
            while (true)
            {
                yield return new WaitForSeconds(20f);

                if (AktifKip != Kip.Oba) continue;

                yield return ApiClient.I.ObaGetir(d =>
                {
                    _durum = d;
                    _oba.DurumuUygula(d);
                    _ustBar.Guncelle(d);
                }, _ => { });
            }
        }

        private void DurumuUygula(ObaDurumu d)
        {
            if (d == null) return;

            _durum = d;
            _oba.DurumuUygula(d);
            _ustBar.Guncelle(d);
        }

        /* ------------------------------------------------------------------ */
        /* Alt menü                                                            */
        /* ------------------------------------------------------------------ */

        private void AltBarKur()
        {
            var kok = ArayuzFabrikasi.Kutu(_tuval.transform, "AltBar",
                new Vector2(0f, 86f), new Vector2(0.5f, 0f), Vector2.zero,
                new Color(0.1f, 0.09f, 0.08f, 0.9f));

            kok.anchorMin = new Vector2(0f, 0f);
            kok.anchorMax = new Vector2(1f, 0f);
            kok.pivot = new Vector2(0.5f, 0f);
            kok.offsetMin = Vector2.zero;
            kok.offsetMax = new Vector2(0f, 86f);
            _altBar = kok.gameObject;

            var dugmeler = new (string ad, string ikon, System.Action tik)[]
            {
                ("Yapı",    "ikon_usta",  DukkaniAc),
                ("Ordu",    "ikon_ordu",  OrduyuAc),
                ("Saldır",  "ikon_savas", RakipAra),
                ("Harita",  "ikon_dunya", HaritayaGec),
                ("Boy",     "ikon_boy",   BoyuAc),
            };

            var genislik = 132f;
            var baslangic = -(dugmeler.Length - 1) * genislik * 0.5f;

            for (var i = 0; i < dugmeler.Length; i++)
            {
                var d = dugmeler[i];
                var x = baslangic + i * genislik;

                var btn = ArayuzFabrikasi.Dugme(kok, "", new Vector2(120f, 70f),
                    new Vector2(0.5f, 0.5f), new Vector2(x, 0f), d.tik);

                ArayuzFabrikasi.Resim(btn.transform, SpriteKutuphanesi.Arayuz(d.ikon),
                    new Vector2(38f, 38f), new Vector2(0.5f, 1f), new Vector2(0f, -6f));
                ArayuzFabrikasi.Yazi(btn.transform, d.ad, new Vector2(110f, 22f),
                    new Vector2(0.5f, 0f), new Vector2(0f, 6f), 16, TextAnchor.MiddleCenter);
            }
        }

        /* ------------------------------------------------------------------ */
        /* Kip geçişleri                                                       */
        /* ------------------------------------------------------------------ */

        private void ObayaGec()
        {
            AktifKip = Kip.Oba;

            _oba.gameObject.SetActive(true);
            if (_harita != null) _harita.gameObject.SetActive(false);
            if (_tekrar != null) _tekrar.gameObject.SetActive(false);
            if (_saldiri != null) _saldiri.gameObject.SetActive(false);
            if (_altBar != null) _altBar.SetActive(true);

            var orta = _oba.IzgaraBoyu * 0.5f;
            _kameraSurucu.SinirlariAyarla(2f, _oba.IzgaraBoyu - 2f, 2f, _oba.IzgaraBoyu - 2f);
            _kameraSurucu.EnAz = 3f;
            _kameraSurucu.EnCok = 16f;
            _kameraSurucu.Odakla(orta, orta, 9f);
        }

        private void HaritayaGec()
        {
            if (_harita == null) HaritaKur();

            AktifKip = Kip.Harita;

            _oba.gameObject.SetActive(false);
            _harita.gameObject.SetActive(true);

            var v = _durum.village.world;
            _kameraSurucu.SinirlariAyarla(0f, OyunSabitleri.DunyaGenisligi,
                0f, OyunSabitleri.DunyaYuksekligi);
            _kameraSurucu.EnAz = 4f;
            _kameraSurucu.EnCok = 40f;
            _kameraSurucu.Odakla(v.x, v.y, 14f);

            Bildirim.Goster("Dünya haritası — obana dönmek için Harita'ya yeniden bas");
        }

        private void HaritaKur()
        {
            var go = new GameObject("DunyaHaritasi");
            _harita = go.AddComponent<DunyaHaritasi>();
            _harita.Kur(_kamera,
                _katalog.world?.seed ?? OyunSabitleri.DunyaTohumu,
                _katalog.world?.width ?? OyunSabitleri.DunyaGenisligi,
                _katalog.world?.height ?? OyunSabitleri.DunyaYuksekligi);

            _harita.KareSecildi += KareSecildi;
        }

        private void KareSecildi(DunyaKaresi kare)
        {
            if (kare.type != "oba" || kare.village == null)
            {
                Bildirim.Goster($"{kare.label}");
                return;
            }

            if (kare.player != null && kare.player.id == _durum.player.id)
            {
                ObayaGec();
                return;
            }

            StartCoroutine(ApiClient.I.Kesif(kare.village.id,
                k => HedefPaneli.Ac(_tuval.transform, k,
                    () => SaldiriyaBasla(k),
                    RakipAra),
                h => Bildirim.Goster(h.message, true)));
        }

        /* ------------------------------------------------------------------ */
        /* Eylemler                                                            */
        /* ------------------------------------------------------------------ */

        private void DukkaniAc()
        {
            if (AktifKip != Kip.Oba) ObayaGec();

            DukkanPaneli.Ac(_tuval.transform, _katalog, _durum, anahtar =>
            {
                Bildirim.Goster("Kurmak istediğin yere dokun");
                _oba.IzgarayiGoster(true);
                StartCoroutine(BinaYerlestir(anahtar));
            });
        }

        /// <summary>Oyuncunun ızgaraya dokunmasını bekler ve binayı kurar.</summary>
        private IEnumerator BinaYerlestir(string anahtar)
        {
            var tip = _katalog.buildings.Find(b => b.key == anahtar);
            var onizleme = new GameObject("onizleme");
            var sr = onizleme.AddComponent<SpriteRenderer>();
            sr.sprite = SpriteKutuphanesi.Bina(anahtar);
            sr.color = new Color(1f, 1f, 1f, 0.7f);
            sr.sortingOrder = 9500;

            while (true)
            {
                if (Input.GetKeyDown(KeyCode.Escape)) break;

                var fare = _kamera.ScreenToWorldPoint(Input.mousePosition);
                var kare = Izometri.DunyadanKareye(fare);
                kare.x = Mathf.Clamp(kare.x, 0, _oba.IzgaraBoyu - (tip?.w ?? 1));
                kare.y = Mathf.Clamp(kare.y, 0, _oba.IzgaraBoyu - (tip?.h ?? 1));

                onizleme.transform.position = Izometri.IzgaradanDunyaya(kare.x, kare.y);

                if (Input.GetMouseButtonUp(0) && !_kameraSurucu.Surukluyor
                    && !EventSystem.current.IsPointerOverGameObject())
                {
                    var bitti = false;
                    yield return ApiClient.I.BinaKur(anahtar, kare.x, kare.y,
                        d => { DurumuUygula(d); Bildirim.Goster($"{tip?.name ?? anahtar} kuruldu."); bitti = true; },
                        h => { Bildirim.Goster(h.message, true); bitti = true; });

                    if (bitti) break;
                }

                yield return null;
            }

            Destroy(onizleme);
            _oba.IzgarayiGoster(false);
        }

        private void BinaSecildi(BinaDurumu bina)
        {
            if (AktifKip != Kip.Oba) return;

            var tip = _katalog.buildings.Find(b => b.key == bina.key);

            BinaPaneli.Ac(_tuval.transform, bina, tip, _durum,
                yukselt: () => StartCoroutine(ApiClient.I.BinaYukselt(bina.id,
                    d => { DurumuUygula(d); Bildirim.Goster("İnşaat başladı."); },
                    h => Bildirim.Goster(h.message, true))),
                tasi: () => StartCoroutine(BinaTasi(bina)),
                bitir: () => StartCoroutine(ApiClient.I.BinaBitir(bina.id,
                    d => { DurumuUygula(d); Bildirim.Goster("İnşaat tamamlandı!"); },
                    h => Bildirim.Goster(h.message, true))),
                iptal: () => StartCoroutine(ApiClient.I.BinaIptal(bina.id,
                    d => { DurumuUygula(d); Bildirim.Goster("İnşaat iptal edildi."); },
                    h => Bildirim.Goster(h.message, true))));
        }

        private IEnumerator BinaTasi(BinaDurumu bina)
        {
            Bildirim.Goster("Yeni yere dokun (Esc ile vazgeç)");
            _oba.IzgarayiGoster(true);

            var gorunum = _oba.BinayiBul(bina.id);

            while (true)
            {
                if (Input.GetKeyDown(KeyCode.Escape))
                {
                    gorunum?.Guncelle(bina);
                    break;
                }

                var fare = _kamera.ScreenToWorldPoint(Input.mousePosition);
                var kare = Izometri.DunyadanKareye(fare);
                kare.x = Mathf.Clamp(kare.x, 0, _oba.IzgaraBoyu - bina.w);
                kare.y = Mathf.Clamp(kare.y, 0, _oba.IzgaraBoyu - bina.h);

                gorunum?.GeciciKonum(kare.x, kare.y, true);

                if (Input.GetMouseButtonUp(0) && !_kameraSurucu.Surukluyor
                    && !EventSystem.current.IsPointerOverGameObject())
                {
                    var kopya = new List<BinaDurumu>
                    {
                        new() { id = bina.id, x = kare.x, y = kare.y },
                    };

                    var bitti = false;
                    yield return ApiClient.I.DuzenKaydet(kopya,
                        d => { DurumuUygula(d); Bildirim.Goster("Taşındı."); bitti = true; },
                        h => { Bildirim.Goster(h.message, true); bitti = true; });

                    if (bitti) break;
                }

                yield return null;
            }

            _oba.IzgarayiGoster(false);
            DurumuUygula(_durum);
        }

        private void OrduyuAc()
        {
            OrduPaneli.Ac(_tuval.transform, _katalog, _durum, (anahtar, adet) =>
            {
                StartCoroutine(ApiClient.I.AskerEgit(anahtar, adet,
                    d => { DurumuUygula(d); Bildirim.Goster($"{adet} asker kuyruğa girdi."); },
                    h => Bildirim.Goster(h.message, true)));
            });
        }

        private void RakipAra()
        {
            Bildirim.Goster("Rakip aranıyor...");

            StartCoroutine(ApiClient.I.RakipAra(
                y => HedefPaneli.Ac(_tuval.transform, y.target,
                    () => SaldiriyaBasla(y.target), RakipAra),
                h => Bildirim.Goster(h.message, true)));
        }

        private void BoyuAc()
        {
            if (_durum.player.clan != null)
            {
                StartCoroutine(ApiClient.I.BoyDetayGetir(_durum.player.clan.id, detay =>
                {
                    StartCoroutine(ApiClient.I.SohbetGetir(0, sohbet =>
                    {
                        BoyPaneli.Ac(_tuval.transform, detay, null,
                            null,
                            ayril: () => StartCoroutine(ApiClient.I.BoydanAyril(
                                _ => { Bildirim.Goster("Boydan ayrıldın."); Tazele(); },
                                h => Bildirim.Goster(h.message, true))),
                            sohbetGonder: metin => StartCoroutine(ApiClient.I.SohbetGonder(metin,
                                _ => Bildirim.Goster("Gönderildi."),
                                h => Bildirim.Goster(h.message, true))),
                            mesajlar: sohbet.messages);
                    }, _ => { }));
                }, h => Bildirim.Goster(h.message, true)));

                return;
            }

            StartCoroutine(ApiClient.I.BoyAra("", liste =>
            {
                BoyPaneli.Ac(_tuval.transform, null, liste.data,
                    katil: id => StartCoroutine(ApiClient.I.BoyaKatil(id,
                        _ => { Bildirim.Goster("Boya katıldın!"); Tazele(); },
                        h => Bildirim.Goster(h.message, true))),
                    ayril: null, sohbetGonder: null, mesajlar: null);
            }, h => Bildirim.Goster(h.message, true)));
        }

        private void Tazele()
        {
            StartCoroutine(ApiClient.I.ObaGetir(DurumuUygula, _ => { }));
        }

        /* ------------------------------------------------------------------ */
        /* Savaş                                                               */
        /* ------------------------------------------------------------------ */

        private void SaldiriyaBasla(KesifYaniti hedef)
        {
            AktifKip = Kip.Savas;

            _oba.gameObject.SetActive(false);
            if (_harita != null) _harita.gameObject.SetActive(false);
            if (_altBar != null) _altBar.SetActive(false);

            _saldiri.gameObject.SetActive(true);
            _saldiri.Baslat(_tuval, _kamera, _kameraSurucu, hedef, _durum, _katalog,
                (indirmeler, kahramanlar) => StartCoroutine(SaldiriyiGonder(hedef, indirmeler, kahramanlar)),
                ObayaGecVeTemizle);
        }

        private IEnumerator SaldiriyiGonder(KesifYaniti hedef,
            List<AskerIndirme> indirmeler, List<string> kahramanlar)
        {
            Bildirim.Goster("Akın çözümleniyor...");

            var istek = new SaldiriIstegi
            {
                deployments = indirmeler,
                heroes = kahramanlar,
                type = "coklu",
            };

            yield return ApiClient.I.Saldir(hedef.village.id, istek,
                (sonuc, tekrar) =>
                {
                    _saldiri.gameObject.SetActive(false);
                    _tekrar.gameObject.SetActive(true);
                    _tekrar.Yukle(tekrar);
                    _tekrar.Bitti = () => SonucPaneli.Ac(_tuval.transform, sonuc, ObayaGecVeTemizle);
                    _tekrar.Oynat();

                    _kameraSurucu.Odakla(22f, 22f, 10f);
                },
                h =>
                {
                    Bildirim.Goster(h.message, true);
                    ObayaGecVeTemizle();
                });
        }

        private void ObayaGecVeTemizle()
        {
            _tekrar?.Temizle();
            if (_tekrar != null) _tekrar.gameObject.SetActive(false);
            if (_saldiri != null) _saldiri.gameObject.SetActive(false);

            ObayaGec();
            Tazele();
        }
    }
}
