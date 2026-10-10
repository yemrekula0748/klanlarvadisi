using KlanlarVadisi.Util;
using UnityEngine;

namespace KlanlarVadisi.Core
{
    /// <summary>
    /// Harita ve oba için kamera: sürükle, yakınlaştır, sınırla.
    ///
    /// Hem fare (tekerlek + sol tuş sürükleme) hem dokunmatik (tek parmak
    /// kaydırma, iki parmak sıkıştırma) destekler.
    /// </summary>
    public class KameraSurucu : MonoBehaviour
    {
        [Header("Yakınlaştırma")]
        public float EnAz = 2.5f;
        public float EnCok = 28f;
        public float TekerlekHizi = 3.5f;
        public float Yumusakligi = 12f;

        [Header("Sınırlar (ızgara karesi)")]
        public float SinirEnAzX = -5f;
        public float SinirEnCokX = 50f;
        public float SinirEnAzY = -5f;
        public float SinirEnCokY = 50f;

        public bool Kilitli { get; set; }

        private Camera _kamera;
        private Vector3 _hedefKonum;
        private float _hedefBoyut;
        private Vector3 _surukleBaslangic;
        private bool _surukluyor;

        private void Awake()
        {
            _kamera = GetComponent<Camera>();
            _hedefKonum = transform.position;
            _hedefBoyut = _kamera.orthographicSize;
        }

        public void Odakla(float gx, float gy, float boyut = -1f)
        {
            var p = Izometri.IzgaradanDunyaya(gx, gy);
            _hedefKonum = new Vector3(p.x, p.y, transform.position.z);
            if (boyut > 0f) _hedefBoyut = Mathf.Clamp(boyut, EnAz, EnCok);
        }

        public void SinirlariAyarla(float enAzX, float enCokX, float enAzY, float enCokY)
        {
            SinirEnAzX = enAzX; SinirEnCokX = enCokX;
            SinirEnAzY = enAzY; SinirEnCokY = enCokY;
        }

        private void Update()
        {
            if (!Kilitli)
            {
                Yakinlastir();
                Surukle();
            }

            // Yumuşak takip
            transform.position = Vector3.Lerp(transform.position, _hedefKonum,
                Time.unscaledDeltaTime * Yumusakligi);
            _kamera.orthographicSize = Mathf.Lerp(_kamera.orthographicSize, _hedefBoyut,
                Time.unscaledDeltaTime * Yumusakligi);
        }

        private void Yakinlastir()
        {
            var tekerlek = Input.mouseScrollDelta.y;
            if (Mathf.Abs(tekerlek) > 0.01f)
                _hedefBoyut = Mathf.Clamp(_hedefBoyut - tekerlek * TekerlekHizi, EnAz, EnCok);

            // İki parmakla sıkıştırma
            if (Input.touchCount == 2)
            {
                var t0 = Input.GetTouch(0);
                var t1 = Input.GetTouch(1);

                var oncekiMesafe = ((t0.position - t0.deltaPosition) - (t1.position - t1.deltaPosition)).magnitude;
                var mesafe = (t0.position - t1.position).magnitude;
                var fark = oncekiMesafe - mesafe;

                _hedefBoyut = Mathf.Clamp(_hedefBoyut + fark * 0.02f, EnAz, EnCok);
            }
        }

        private void Surukle()
        {
            // Dokunmatik
            if (Input.touchCount == 1)
            {
                var t = Input.GetTouch(0);

                if (t.phase == TouchPhase.Moved)
                {
                    var delta = _kamera.ScreenToWorldPoint(Vector3.zero)
                        - _kamera.ScreenToWorldPoint(new Vector3(t.deltaPosition.x, t.deltaPosition.y, 0f));
                    KonumEkle(delta);
                }

                return;
            }

            // Fare
            if (Input.GetMouseButtonDown(0))
            {
                _surukleBaslangic = _kamera.ScreenToWorldPoint(Input.mousePosition);
                _surukluyor = true;
            }

            if (Input.GetMouseButtonUp(0)) _surukluyor = false;

            if (_surukluyor && Input.GetMouseButton(0))
            {
                var simdi = _kamera.ScreenToWorldPoint(Input.mousePosition);
                var delta = _surukleBaslangic - simdi;

                // Küçük hareketler tıklama sayılsın diye eşik var
                if (delta.sqrMagnitude > 0.0004f) KonumEkle(delta);
            }
        }

        private void KonumEkle(Vector3 delta)
        {
            _hedefKonum += new Vector3(delta.x, delta.y, 0f);
            SinirlaraSikistir();
        }

        private void SinirlaraSikistir()
        {
            var g = Izometri.DunyadanIzgaraya(_hedefKonum);
            g.x = Mathf.Clamp(g.x, SinirEnAzX, SinirEnCokX);
            g.y = Mathf.Clamp(g.y, SinirEnAzY, SinirEnCokY);

            var p = Izometri.IzgaradanDunyaya(g.x, g.y);
            _hedefKonum = new Vector3(p.x, p.y, _hedefKonum.z);
        }

        /// <summary>Oyuncu bu karede ekranı sürükledi mi? (tıklama ayırt etmek için)</summary>
        public bool Surukluyor => _surukluyor;
    }
}
