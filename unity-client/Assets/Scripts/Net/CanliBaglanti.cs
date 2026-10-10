using System;
using System.Collections;
using System.Collections.Concurrent;
using System.Collections.Generic;
using System.Net.WebSockets;
using System.Text;
using System.Threading;
using System.Threading.Tasks;
using KlanlarVadisi.Util;
using UnityEngine;

namespace KlanlarVadisi.Net
{
    /// <summary>
    /// Laravel Reverb'e (Pusher protokolü) WebSocket bağlantısı.
    ///
    /// Boy sohbeti, saldırı bildirimi ve savaş güncellemeleri anında gelir.
    /// Bağlantı kopsa da oyun oynanır: <see cref="OyunBaslatici"/> ayrıca
    /// 20 saniyede bir durum çeker, bu yalnızca onun üstüne gelen bir hızdır.
    ///
    /// Platform notu: <c>ClientWebSocket</c> masaüstü, Android ve iOS'ta
    /// çalışır. WebGL'de .NET soketleri kullanılamaz; orada bu sınıf
    /// kendini devre dışı bırakır ve oyun yoklama (polling) ile sürer.
    /// </summary>
    public class CanliBaglanti : MonoBehaviour
    {
        public static CanliBaglanti I { get; private set; }

        [Header("Reverb")]
        public string Host = "localhost";
        public int Port = 8080;
        public bool Guvenli;              // wss:// için true
        public string AppKey = "klanlar-vadisi-anahtari";

        public bool Bagli { get; private set; }

        /// <summary>kanal adı -> olay adı -> veri (sözlük)</summary>
        public event Action<string, string, Dictionary<string, object>> OlayGeldi;

        private ClientWebSocket _soket;
        private CancellationTokenSource _iptal;
        private readonly ConcurrentQueue<string> _gelenler = new();
        private readonly HashSet<string> _kanallar = new();
        private string _soketId;
        private float _sonDeneme;
        private int _denemeSayisi;
        private bool _istendi;            // Baslat() çağrıldı mı?

#if UNITY_WEBGL && !UNITY_EDITOR
        private const bool Destekleniyor = false;
#else
        private const bool Destekleniyor = true;
#endif

        private void Awake()
        {
            if (I != null && I != this) { Destroy(gameObject); return; }
            I = this;
            DontDestroyOnLoad(gameObject);
        }

        public void Baslat()
        {
            if (!Destekleniyor)
            {
                Debug.Log("[Canlı] Bu platformda WebSocket yok; yoklama kullanılacak.");
                return;
            }

            _istendi = true;

            if (Bagli || _soket != null) return;

            _ = BaglanAsync();
        }

        /// <summary>Kanala katılır. Özel kanallar sunucudan yetki alır.</summary>
        public void KanalaKatil(string kanal)
        {
            if (!_kanallar.Add(kanal)) return;
            if (Bagli) StartCoroutine(KanalYetkisiAl(kanal));
        }

        public void KanaldanAyril(string kanal)
        {
            if (!_kanallar.Remove(kanal)) return;
            if (!Bagli) return;

            Gonder(MiniJson.Serialize(new Dictionary<string, object>
            {
                ["event"] = "pusher:unsubscribe",
                ["data"] = new Dictionary<string, object> { ["channel"] = kanal },
            }));
        }

        /* ------------------------------------------------------------------ */

        private async Task BaglanAsync()
        {
            try
            {
                _iptal = new CancellationTokenSource();
                _soket = new ClientWebSocket();

                var sema = Guvenli ? "wss" : "ws";
                var url = $"{sema}://{Host}:{Port}/app/{AppKey}?protocol=7&client=unity&version=1.0";

                await _soket.ConnectAsync(new Uri(url), _iptal.Token);

                Bagli = true;
                _denemeSayisi = 0;
                Debug.Log("[Canlı] Reverb bağlantısı kuruldu.");

                _ = DinleAsync();
            }
            catch (Exception e)
            {
                Bagli = false;
                _soket = null;
                Debug.LogWarning($"[Canlı] Bağlanılamadı: {e.Message}");
            }
        }

        private async Task DinleAsync()
        {
            var tampon = new byte[16 * 1024];
            var parca = new StringBuilder();

            try
            {
                while (_soket != null && _soket.State == WebSocketState.Open)
                {
                    var sonuc = await _soket.ReceiveAsync(
                        new ArraySegment<byte>(tampon), _iptal.Token);

                    if (sonuc.MessageType == WebSocketMessageType.Close)
                    {
                        await _soket.CloseAsync(WebSocketCloseStatus.NormalClosure, "", CancellationToken.None);
                        break;
                    }

                    parca.Append(Encoding.UTF8.GetString(tampon, 0, sonuc.Count));

                    if (!sonuc.EndOfMessage) continue;

                    _gelenler.Enqueue(parca.ToString());
                    parca.Clear();
                }
            }
            catch (Exception e)
            {
                Debug.LogWarning($"[Canlı] Dinleme bitti: {e.Message}");
            }
            finally
            {
                Bagli = false;
                _soket = null;
            }
        }

        private void Gonder(string json)
        {
            if (_soket == null || _soket.State != WebSocketState.Open) return;

            var veri = Encoding.UTF8.GetBytes(json);
            _ = _soket.SendAsync(new ArraySegment<byte>(veri),
                WebSocketMessageType.Text, true, _iptal.Token);
        }

        /* ------------------------------------------------------------------ */

        private void Update()
        {
            // Gelen iletiler ana iş parçacığında işlenir
            while (_gelenler.TryDequeue(out var ileti)) IletiyiIsle(ileti);

            // Kopmuşsa artan aralıklarla yeniden dene. `_istendi` olmadan
            // denenmez: giriş yapılmadan kanal da belirteç de yoktur.
            if (_istendi && Destekleniyor && !Bagli && _soket == null
                && Time.unscaledTime - _sonDeneme > Mathf.Min(30f, 3f * (1 + _denemeSayisi)))
            {
                _sonDeneme = Time.unscaledTime;
                _denemeSayisi++;
                _ = BaglanAsync();
            }
        }

        private void IletiyiIsle(string ham)
        {
            var kok = MiniJson.AsDict(MiniJson.Deserialize(ham));
            if (kok == null) return;

            var olay = MiniJson.Str(kok, "event", "");
            var kanal = MiniJson.Str(kok, "channel", "");

            // Pusher'ın `data` alanı iç içe JSON metnidir
            Dictionary<string, object> veri = null;
            if (kok.TryGetValue("data", out var ham2))
            {
                veri = ham2 is string s
                    ? MiniJson.AsDict(MiniJson.Deserialize(s))
                    : MiniJson.AsDict(ham2);
            }

            switch (olay)
            {
                case "pusher:connection_established":
                    _soketId = MiniJson.Str(veri, "socket_id");
                    foreach (var k in _kanallar) StartCoroutine(KanalYetkisiAl(k));
                    break;

                case "pusher:ping":
                    Gonder("{\"event\":\"pusher:pong\",\"data\":{}}");
                    break;

                case "pusher:error":
                    Debug.LogWarning($"[Canlı] Sunucu hatası: {MiniJson.Str(veri, "message")}");
                    break;

                default:
                    if (!olay.StartsWith("pusher"))
                        OlayGeldi?.Invoke(kanal, olay, veri);
                    break;
            }
        }

        /// <summary>
        /// Özel kanal için sunucudan imza alır (Sanctum belirteciyle) ve
        /// abone olur.
        /// </summary>
        private IEnumerator KanalYetkisiAl(string kanal)
        {
            if (string.IsNullOrEmpty(_soketId) || ApiClient.I == null) yield break;

            string imza = null;

            yield return ApiClient.I.YayinYetkisi(_soketId, kanal,
                t =>
                {
                    var d = MiniJson.AsDict(MiniJson.Deserialize(t));
                    imza = MiniJson.Str(d, "auth");
                },
                h => Debug.LogWarning($"[Canlı] Kanal yetkisi alınamadı ({kanal}): {h.message}"));

            if (string.IsNullOrEmpty(imza)) yield break;

            Gonder(MiniJson.Serialize(new Dictionary<string, object>
            {
                ["event"] = "pusher:subscribe",
                ["data"] = new Dictionary<string, object>
                {
                    ["channel"] = kanal,
                    ["auth"] = imza,
                },
            }));
        }

        private void OnDestroy()
        {
            try
            {
                _iptal?.Cancel();
                _soket?.Abort();
            }
            catch { /* kapanışta önemli değil */ }
        }
    }
}
