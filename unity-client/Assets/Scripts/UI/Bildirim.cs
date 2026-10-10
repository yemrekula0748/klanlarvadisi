using System.Collections;
using UnityEngine;
using UnityEngine.UI;

namespace KlanlarVadisi.UI
{
    /// <summary>
    /// Ekranın ortasında beliren kısa bildirimler ve hata iletileri.
    /// Sunucudan gelen Türkçe hata iletileri doğrudan burada gösterilir.
    /// </summary>
    public class Bildirim : MonoBehaviour
    {
        public static Bildirim I { get; private set; }

        private RectTransform _kok;
        private Text _metin;
        private Image _arka;
        private Coroutine _calisan;

        public static Bildirim Olustur(Transform tuval)
        {
            var kutu = ArayuzFabrikasi.Kutu(tuval, "Bildirim",
                new Vector2(620f, 64f), new Vector2(0.5f, 1f), new Vector2(0f, -96f),
                new Color(0.1f, 0.09f, 0.08f, 0f));

            var b = kutu.gameObject.AddComponent<Bildirim>();
            b._kok = kutu;
            b._arka = kutu.GetComponent<Image>();
            b._metin = ArayuzFabrikasi.Yazi(kutu, "", new Vector2(600f, 60f),
                new Vector2(0.5f, 0.5f), Vector2.zero, 21, TextAnchor.MiddleCenter);
            b._metin.color = new Color(1f, 1f, 1f, 0f);

            I = b;

            return b;
        }

        public static void Goster(string ileti, bool hata = false)
        {
            if (I == null) return;
            I.GosterIc(ileti, hata);
        }

        private void GosterIc(string ileti, bool hata)
        {
            _metin.text = ileti;

            if (_calisan != null) StopCoroutine(_calisan);
            _calisan = StartCoroutine(Akis(hata));
        }

        private IEnumerator Akis(bool hata)
        {
            var arkaRenk = hata
                ? new Color(0.55f, 0.13f, 0.11f, 0.95f)
                : new Color(0.13f, 0.28f, 0.16f, 0.95f);

            // Belir
            for (var t = 0f; t < 1f; t += Time.unscaledDeltaTime * 6f)
            {
                _arka.color = new Color(arkaRenk.r, arkaRenk.g, arkaRenk.b, arkaRenk.a * t);
                _metin.color = new Color(1f, 1f, 1f, t);
                _kok.anchoredPosition = new Vector2(0f, -96f - (1f - t) * 20f);
                yield return null;
            }

            yield return new WaitForSecondsRealtime(hata ? 3f : 2f);

            // Soldur
            for (var t = 1f; t > 0f; t -= Time.unscaledDeltaTime * 3f)
            {
                _arka.color = new Color(arkaRenk.r, arkaRenk.g, arkaRenk.b, arkaRenk.a * t);
                _metin.color = new Color(1f, 1f, 1f, t);
                yield return null;
            }

            _arka.color = new Color(0f, 0f, 0f, 0f);
            _metin.color = new Color(1f, 1f, 1f, 0f);
        }
    }
}
