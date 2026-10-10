using KlanlarVadisi.Util;
using UnityEngine;

namespace KlanlarVadisi.Battle
{
    /// <summary>
    /// Bina ve asker üstündeki can çubuğu.
    ///
    /// Yalnızca hasar alındığında görünür; can dolduğunda kendiliğinden
    /// kaybolur. Böylece savaş sahnesi gereksiz arayüzle dolmaz.
    /// </summary>
    public class CanCubugu : MonoBehaviour
    {
        private SpriteRenderer _zemin;
        private SpriteRenderer _dolu;
        private float _oran = 1f;
        private float _gizleZamani;

        public static CanCubugu Olustur(Transform parent, Vector3 yerel, float olcek = 0.8f)
        {
            var go = new GameObject("can");
            go.transform.SetParent(parent, false);
            go.transform.localPosition = yerel;
            go.transform.localScale = Vector3.one * olcek;

            var cc = go.AddComponent<CanCubugu>();
            cc.Kur();

            return cc;
        }

        private void Kur()
        {
            var zeminGo = new GameObject("zemin");
            zeminGo.transform.SetParent(transform, false);
            _zemin = zeminGo.AddComponent<SpriteRenderer>();
            _zemin.sprite = SpriteKutuphanesi.Arayuz("can_cubugu");
            _zemin.sortingOrder = 9700;
            _zemin.color = new Color(1f, 1f, 1f, 0f);

            var doluGo = new GameObject("dolu");
            doluGo.transform.SetParent(transform, false);
            doluGo.transform.localPosition = Vector3.zero;
            _dolu = doluGo.AddComponent<SpriteRenderer>();
            _dolu.sprite = SpriteKutuphanesi.Arayuz("can_cubugu");
            _dolu.sortingOrder = 9701;
            _dolu.color = new Color(1f, 1f, 1f, 0f);
        }

        /// <summary>Can oranını (0..1) yansıtır ve çubuğu görünür kılar.</summary>
        public void Ayarla(float oran)
        {
            _oran = Mathf.Clamp01(oran);
            _gizleZamani = Time.time + 3f;

            var gorunur = _oran < 0.999f;
            var alfa = gorunur ? 1f : 0f;

            _zemin.color = new Color(0.1f, 0.08f, 0.07f, alfa * 0.85f);

            // Yeşilden sarıya, sarıdan kırmızıya
            var renk = _oran > 0.5f
                ? Color.Lerp(new Color(1f, 0.8f, 0.2f), new Color(0.35f, 0.8f, 0.35f), (_oran - 0.5f) * 2f)
                : Color.Lerp(new Color(0.85f, 0.2f, 0.2f), new Color(1f, 0.8f, 0.2f), _oran * 2f);

            _dolu.color = new Color(renk.r, renk.g, renk.b, alfa);
            _dolu.transform.localScale = new Vector3(_oran, 1f, 1f);
            _dolu.transform.localPosition = new Vector3(-(1f - _oran) * 0.5f * 0.94f, 0f, 0f);
        }

        public void Gizle()
        {
            _zemin.color = new Color(0f, 0f, 0f, 0f);
            _dolu.color = new Color(0f, 0f, 0f, 0f);
        }

        private void Update()
        {
            // Bir süre hasar alınmadıysa çubuk kaybolur
            if (_gizleZamani > 0f && Time.time > _gizleZamani && _oran > 0.999f) Gizle();
        }
    }
}
