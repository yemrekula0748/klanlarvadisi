using System;
using System.Collections;
using System.Collections.Generic;
using System.Text;
using KlanlarVadisi.Util;
using UnityEngine;
using UnityEngine.Networking;

namespace KlanlarVadisi.Net
{
    /// <summary>
    /// Laravel oyun sunucusuyla konuşan tek nokta.
    ///
    /// Bütün oyun kararları sunucuda verilir; istemci yalnızca istek atar ve
    /// gelen durumu gösterir. Bu yüzden burada hiçbir oyun kuralı yoktur.
    ///
    /// Kullanım:
    ///   yield return Api.I.Giris("posta", "sifre", (y) => { ... }, (h) => { ... });
    /// </summary>
    public class ApiClient : MonoBehaviour
    {
        public static ApiClient I { get; private set; }

        [Tooltip("Sunucu adresi. Editörde yerel sunucu, derlemede gerçek adres.")]
        public string BaseUrl = "http://localhost:8000";

        public string Token { get; private set; }
        public bool GirisYapildi => !string.IsNullOrEmpty(Token);

        private const string TokenKey = "klanlar_token";
        private const int ZamanAsimi = 20;

        private void Awake()
        {
            if (I != null && I != this) { Destroy(gameObject); return; }
            I = this;
            DontDestroyOnLoad(gameObject);

            Token = PlayerPrefs.GetString(TokenKey, "");

            // Derleme ayarlarından adres geçersiz kılınabilir.
            var fromArgs = AdresArgumanlardan();
            if (!string.IsNullOrEmpty(fromArgs)) BaseUrl = fromArgs;
        }

        private static string AdresArgumanlardan()
        {
            foreach (var a in Environment.GetCommandLineArgs())
                if (a.StartsWith("--sunucu=")) return a.Substring("--sunucu=".Length);
            return null;
        }

        public void BelirteciKaydet(string token)
        {
            Token = token;
            PlayerPrefs.SetString(TokenKey, token ?? "");
            PlayerPrefs.Save();
        }

        public void CikisYap()
        {
            BelirteciKaydet("");
        }

        /* ------------------------------------------------------------------ */
        /* Düşük seviye istek                                                  */
        /* ------------------------------------------------------------------ */

        private IEnumerator Istek(
            string method, string path, string jsonBody,
            Action<string> basarili, Action<ApiHatasi> hata)
        {
            var url = $"{BaseUrl.TrimEnd('/')}/api/v1/{path.TrimStart('/')}";

            using var req = new UnityWebRequest(url, method);
            req.timeout = ZamanAsimi;
            req.downloadHandler = new DownloadHandlerBuffer();

            if (!string.IsNullOrEmpty(jsonBody))
            {
                req.uploadHandler = new UploadHandlerRaw(Encoding.UTF8.GetBytes(jsonBody));
                req.SetRequestHeader("Content-Type", "application/json");
            }

            req.SetRequestHeader("Accept", "application/json");
            if (GirisYapildi) req.SetRequestHeader("Authorization", "Bearer " + Token);

            yield return req.SendWebRequest();

            var text = req.downloadHandler?.text ?? "";

            if (req.result == UnityWebRequest.Result.Success)
            {
                basarili?.Invoke(text);
                yield break;
            }

            // Sunucu hata gövdesi gönderdiyse onu kullan
            var apiHata = new ApiHatasi
            {
                message = "Sunucuya ulaşılamadı.",
                code = "baglanti_hatasi",
            };

            if (!string.IsNullOrEmpty(text))
            {
                try
                {
                    var d = MiniJson.AsDict(MiniJson.Deserialize(text));
                    if (d != null)
                    {
                        apiHata.message = MiniJson.Str(d, "message", apiHata.message);
                        apiHata.code = MiniJson.Str(d, "code", "sunucu_hatasi");

                        // Doğrulama hataları: ilk iletiyi göster
                        if (d.TryGetValue("errors", out var errs))
                        {
                            var ed = MiniJson.AsDict(errs);
                            if (ed != null)
                                foreach (var kv in ed)
                                {
                                    var list = MiniJson.AsList(kv.Value);
                                    if (list is { Count: > 0 })
                                    {
                                        apiHata.message = list[0].ToString();
                                        apiHata.code = "dogrulama";
                                        break;
                                    }
                                }
                        }
                    }
                }
                catch { /* gövde JSON değilse varsayılan ileti kalır */ }
            }

            if (req.responseCode == 401)
            {
                apiHata.code = "yetkisiz";
                apiHata.message = "Oturumun düştü, tekrar giriş yap.";
                BelirteciKaydet("");
            }

            Debug.LogWarning($"[API] {method} {path} -> {req.responseCode} {apiHata.code}: {apiHata.message}");
            hata?.Invoke(apiHata);
        }

        private IEnumerator Get<T>(string path, Action<T> ok, Action<ApiHatasi> err)
            => Istek("GET", path, null,
                t => ok?.Invoke(JsonUtility.FromJson<T>(t)), err);

        private IEnumerator Post<T>(string path, string body, Action<T> ok, Action<ApiHatasi> err)
            => Istek("POST", path, body ?? "{}",
                t => ok?.Invoke(JsonUtility.FromJson<T>(t)), err);

        /// <summary>Ham gövde döndüren istek (tekrar verisi gibi dinamik JSON için).</summary>
        public IEnumerator Ham(string method, string path, string body,
            Action<string> ok, Action<ApiHatasi> err)
            => Istek(method, path, body, ok, err);

        /// <summary>
        /// Yayın (broadcasting) kanal yetkilendirmesi.
        ///
        /// Bu uç `/api/v1` önekinin dışındadır (`/broadcasting/auth`), bu
        /// yüzden tam adresle ayrı bir istek yapılır.
        /// </summary>
        public IEnumerator YayinYetkisi(string socketId, string kanal,
            Action<string> ok, Action<ApiHatasi> err)
        {
            var url = $"{BaseUrl.TrimEnd('/')}/broadcasting/auth";
            var govde = MiniJson.Serialize(new Dictionary<string, object>
            {
                ["socket_id"] = socketId,
                ["channel_name"] = kanal,
            });

            using var req = new UnityWebRequest(url, "POST");
            req.timeout = ZamanAsimi;
            req.downloadHandler = new DownloadHandlerBuffer();
            req.uploadHandler = new UploadHandlerRaw(Encoding.UTF8.GetBytes(govde));
            req.SetRequestHeader("Content-Type", "application/json");
            req.SetRequestHeader("Accept", "application/json");
            if (GirisYapildi) req.SetRequestHeader("Authorization", "Bearer " + Token);

            yield return req.SendWebRequest();

            if (req.result == UnityWebRequest.Result.Success)
            {
                ok?.Invoke(req.downloadHandler.text);
                yield break;
            }

            err?.Invoke(new ApiHatasi
            {
                message = $"Kanal yetkisi alınamadı ({req.responseCode}).",
                code = "yayin_yetkisi",
            });
        }

        /* ------------------------------------------------------------------ */
        /* Giriş                                                               */
        /* ------------------------------------------------------------------ */

        public IEnumerator Kayit(string eposta, string sifre, string oyuncuAdi,
            Action<GirisYaniti> ok, Action<ApiHatasi> err)
        {
            var body = MiniJson.Serialize(new Dictionary<string, object>
            {
                ["email"] = eposta,
                ["password"] = sifre,
                ["player_name"] = oyuncuAdi,
                ["device"] = SystemInfo.deviceModel,
            });

            return Post<GirisYaniti>("kayit", body, y =>
            {
                BelirteciKaydet(y.token);
                ok?.Invoke(y);
            }, err);
        }

        public IEnumerator Giris(string eposta, string sifre,
            Action<GirisYaniti> ok, Action<ApiHatasi> err)
        {
            var body = MiniJson.Serialize(new Dictionary<string, object>
            {
                ["email"] = eposta,
                ["password"] = sifre,
                ["device"] = SystemInfo.deviceModel,
            });

            return Post<GirisYaniti>("giris", body, y =>
            {
                BelirteciKaydet(y.token);
                ok?.Invoke(y);
            }, err);
        }

        /// <summary>Hesap açmadan, cihaz kimliğiyle oyna.</summary>
        public IEnumerator KonukGiris(Action<GirisYaniti> ok, Action<ApiHatasi> err)
        {
            var cihaz = SystemInfo.deviceUniqueIdentifier;
            if (string.IsNullOrEmpty(cihaz) || cihaz.Length < 8)
                cihaz = SystemInfo.deviceName + "-" + Application.persistentDataPath.GetHashCode();

            var body = MiniJson.Serialize(new Dictionary<string, object> { ["device"] = cihaz });

            return Post<GirisYaniti>("konuk", body, y =>
            {
                BelirteciKaydet(y.token);
                ok?.Invoke(y);
            }, err);
        }

        public IEnumerator Ben(Action<GirisYaniti> ok, Action<ApiHatasi> err)
            => Get("ben", ok, err);

        /* ------------------------------------------------------------------ */
        /* Katalog ve oba                                                      */
        /* ------------------------------------------------------------------ */

        public IEnumerator KatalogGetir(Action<Katalog> ok, Action<ApiHatasi> err)
            => Get("katalog", ok, err);

        public IEnumerator ObaGetir(Action<ObaDurumu> ok, Action<ApiHatasi> err)
            => Get("oba", ok, err);

        public IEnumerator BinaKur(string anahtar, int x, int y,
            Action<ObaDurumu> ok, Action<ApiHatasi> err)
        {
            var body = MiniJson.Serialize(new Dictionary<string, object>
            {
                ["key"] = anahtar, ["x"] = x, ["y"] = y,
            });

            return Istek("POST", "oba/bina", body,
                t => ok?.Invoke(JsonUtility.FromJson<ObaDurumu>(AltNesne(t, "village"))), err);
        }

        public IEnumerator BinaYukselt(int binaId, Action<ObaDurumu> ok, Action<ApiHatasi> err)
            => Istek("POST", $"oba/bina/{binaId}/yukselt", "{}",
                t => ok?.Invoke(JsonUtility.FromJson<ObaDurumu>(AltNesne(t, "village"))), err);

        public IEnumerator BinaIptal(int binaId, Action<ObaDurumu> ok, Action<ApiHatasi> err)
            => Istek("POST", $"oba/bina/{binaId}/iptal", "{}",
                t => ok?.Invoke(JsonUtility.FromJson<ObaDurumu>(AltNesne(t, "village"))), err);

        public IEnumerator BinaBitir(int binaId, Action<ObaDurumu> ok, Action<ApiHatasi> err)
            => Istek("POST", $"oba/bina/{binaId}/bitir", "{}",
                t => ok?.Invoke(JsonUtility.FromJson<ObaDurumu>(AltNesne(t, "village"))), err);

        public IEnumerator DuzenKaydet(List<BinaDurumu> binalar,
            Action<ObaDurumu> ok, Action<ApiHatasi> err)
        {
            var liste = new List<object>();
            foreach (var b in binalar)
                liste.Add(new Dictionary<string, object> { ["id"] = b.id, ["x"] = b.x, ["y"] = b.y });

            var body = MiniJson.Serialize(new Dictionary<string, object> { ["buildings"] = liste });

            return Istek("POST", "oba/duzen", body,
                t => ok?.Invoke(JsonUtility.FromJson<ObaDurumu>(AltNesne(t, "village"))), err);
        }

        public IEnumerator HazirDuzen(Action<ObaDurumu> ok, Action<ApiHatasi> err)
            => Istek("POST", "oba/duzen/otomatik", "{}",
                t => ok?.Invoke(JsonUtility.FromJson<ObaDurumu>(AltNesne(t, "village"))), err);

        /* ------------------------------------------------------------------ */
        /* Ordu                                                                */
        /* ------------------------------------------------------------------ */

        public IEnumerator AskerEgit(string anahtar, int adet,
            Action<ObaDurumu> ok, Action<ApiHatasi> err)
        {
            var body = MiniJson.Serialize(new Dictionary<string, object>
            {
                ["troop"] = anahtar, ["quantity"] = adet,
            });
            return Post("ordu/egit", body, ok, err);
        }

        public IEnumerator EgitimIptal(int kalemId, Action<ObaDurumu> ok, Action<ApiHatasi> err)
            => Istek("DELETE", $"ordu/kuyruk/{kalemId}", "{}",
                t => ok?.Invoke(JsonUtility.FromJson<ObaDurumu>(t)), err);

        public IEnumerator TilsimHazirla(string anahtar, int adet,
            Action<ObaDurumu> ok, Action<ApiHatasi> err)
        {
            var body = MiniJson.Serialize(new Dictionary<string, object>
            {
                ["spell"] = anahtar, ["quantity"] = adet,
            });
            return Post("ordu/tilsim", body, ok, err);
        }

        public IEnumerator AskerGelistir(string anahtar, Action<ObaDurumu> ok, Action<ApiHatasi> err)
        {
            var body = MiniJson.Serialize(new Dictionary<string, object> { ["troop"] = anahtar });
            return Post("ordu/gelistir", body, ok, err);
        }

        public IEnumerator KahramanUyandir(string anahtar, Action<ObaDurumu> ok, Action<ApiHatasi> err)
        {
            var body = MiniJson.Serialize(new Dictionary<string, object> { ["hero"] = anahtar });
            return Post("ordu/kahraman/uyandir", body, ok, err);
        }

        /* ------------------------------------------------------------------ */
        /* Savaş                                                               */
        /* ------------------------------------------------------------------ */

        public IEnumerator RakipAra(Action<RakipYaniti> ok, Action<ApiHatasi> err)
            => Post("savas/rakip-ara", "{}", ok, err);

        public IEnumerator Kesif(int obaId, Action<KesifYaniti> ok, Action<ApiHatasi> err)
            => Get($"oba/{obaId}/kesif", ok, err);

        /// <summary>
        /// Saldırıyı sunucuya gönderir. Dönen tekrar (replay) ham JSON olarak
        /// ayrıştırılır; JsonUtility dinamik alanları çözemez.
        /// </summary>
        public IEnumerator Saldir(int obaId, SaldiriIstegi istek,
            Action<SavasSonucu, Tekrar> ok, Action<ApiHatasi> err)
        {
            var depl = new List<object>();
            foreach (var d in istek.deployments)
                depl.Add(new Dictionary<string, object>
                {
                    ["troop"] = d.troop, ["x"] = d.x, ["y"] = d.y, ["t"] = d.t,
                });

            var spells = new List<object>();
            foreach (var s in istek.spells)
                spells.Add(new Dictionary<string, object>
                {
                    ["spell"] = s.spell, ["x"] = s.x, ["y"] = s.y, ["t"] = s.t,
                });

            var body = MiniJson.Serialize(new Dictionary<string, object>
            {
                ["deployments"] = depl,
                ["spells"] = spells,
                ["heroes"] = istek.heroes.ConvertAll(h => (object)h),
                ["type"] = istek.type,
            });

            return Istek("POST", $"savas/saldir/{obaId}", body, t =>
            {
                var kok = MiniJson.AsDict(MiniJson.Deserialize(t));
                var savasDict = kok != null && kok.TryGetValue("battle", out var bv)
                    ? MiniJson.AsDict(bv) : null;
                var tekrarDict = savasDict != null && savasDict.TryGetValue("replay", out var rv)
                    ? MiniJson.AsDict(rv) : null;

                var sonuc = JsonUtility.FromJson<SavasSonucu>(AltNesne(t, "battle"));
                ok?.Invoke(sonuc, TekrarCozumleyici.Cozumle(tekrarDict));
            }, err);
        }

        public IEnumerator SavasGecmisi(Action<string> ok, Action<ApiHatasi> err)
            => Istek("GET", "savas/gecmis", null, ok, err);

        /* ------------------------------------------------------------------ */
        /* Dünya                                                               */
        /* ------------------------------------------------------------------ */

        public IEnumerator DunyaBilgi(Action<DunyaBilgisi> ok, Action<ApiHatasi> err)
            => Get("dunya/bilgi", ok, err);

        public IEnumerator DunyaParca(int x, int y, int w, int h,
            Action<DunyaParcasi> ok, Action<ApiHatasi> err)
            => Get($"dunya/bolge?x={x}&y={y}&w={w}&h={h}", ok, err);

        /* ------------------------------------------------------------------ */
        /* Boy                                                                 */
        /* ------------------------------------------------------------------ */

        public IEnumerator BoyAra(string terim, Action<BoyListesi> ok, Action<ApiHatasi> err)
            => Get($"boy?q={UnityWebRequest.EscapeURL(terim ?? "")}", ok, err);

        public IEnumerator BoyDetayGetir(int boyId, Action<BoyDetay> ok, Action<ApiHatasi> err)
            => Get($"boy/{boyId}", ok, err);

        public IEnumerator BoyaKatil(int boyId, Action<string> ok, Action<ApiHatasi> err)
            => Istek("POST", $"boy/{boyId}/katil", "{}", ok, err);

        public IEnumerator BoydanAyril(Action<string> ok, Action<ApiHatasi> err)
            => Istek("POST", "boy/ayril", "{}", ok, err);

        public IEnumerator BoyKur(string ad, string aciklama, Action<string> ok, Action<ApiHatasi> err)
        {
            var body = MiniJson.Serialize(new Dictionary<string, object>
            {
                ["name"] = ad, ["description"] = aciklama ?? "", ["type"] = "acik",
            });
            return Istek("POST", "boy", body, ok, err);
        }

        public IEnumerator SohbetGetir(int sonId, Action<SohbetListesi> ok, Action<ApiHatasi> err)
            => Get($"boy/sohbet?after={sonId}", ok, err);

        public IEnumerator SohbetGonder(string metin, Action<string> ok, Action<ApiHatasi> err)
        {
            var body = MiniJson.Serialize(new Dictionary<string, object> { ["body"] = metin });
            return Istek("POST", "boy/sohbet", body, ok, err);
        }

        /* ------------------------------------------------------------------ */
        /* Sıralama                                                            */
        /* ------------------------------------------------------------------ */

        public IEnumerator OyuncuSiralama(Action<OyuncuSiralamasi> ok, Action<ApiHatasi> err)
            => Get("siralama/oyuncular", ok, err);

        /* ------------------------------------------------------------------ */

        /// <summary>
        /// JSON gövdesinden tek bir alt nesneyi metin olarak çıkarır.
        /// JsonUtility iç içe nesneleri ayrı ayrı ayrıştırabilmek için gerekir.
        /// </summary>
        private static string AltNesne(string json, string anahtar)
        {
            var d = MiniJson.AsDict(MiniJson.Deserialize(json));
            if (d == null || !d.TryGetValue(anahtar, out var v)) return "{}";
            return MiniJson.Serialize(v);
        }
    }
}
