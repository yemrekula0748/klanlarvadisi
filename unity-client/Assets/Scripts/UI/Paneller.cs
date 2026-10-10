using System;
using System.Collections.Generic;
using KlanlarVadisi.Net;
using KlanlarVadisi.Util;
using UnityEngine;
using UnityEngine.UI;

namespace KlanlarVadisi.UI
{
    /// <summary>
    /// Açılır paneller için ortak taban: başlık, kapat düğmesi, gövde.
    /// </summary>
    public class Panel : MonoBehaviour
    {
        public RectTransform Govde { get; private set; }
        protected RectTransform Kok;

        public static T Olustur<T>(Transform tuval, string baslik, Vector2 boyut)
            where T : Panel
        {
            // Karartma perdesi
            var perde = ArayuzFabrikasi.Kutu(tuval, "perde", Vector2.zero,
                new Vector2(0.5f, 0.5f), Vector2.zero, new Color(0f, 0f, 0f, 0.6f));
            perde.anchorMin = Vector2.zero;
            perde.anchorMax = Vector2.one;
            perde.offsetMin = perde.offsetMax = Vector2.zero;

            var kok = ArayuzFabrikasi.Panel(perde, "panel", boyut,
                new Vector2(0.5f, 0.5f), Vector2.zero);

            var p = perde.gameObject.AddComponent<T>();
            p.Kok = kok;

            var b = ArayuzFabrikasi.Yazi(kok, baslik, new Vector2(boyut.x - 120f, 40f),
                new Vector2(0.5f, 1f), new Vector2(0f, -34f), 26, TextAnchor.MiddleCenter,
                ArayuzFabrikasi.Altin);
            b.fontStyle = FontStyle.Bold;

            ArayuzFabrikasi.Dugme(kok, "X", new Vector2(44f, 44f), new Vector2(1f, 1f),
                new Vector2(-16f, -16f), () => Destroy(perde.gameObject), yaziBoyutu: 22);

            p.Govde = ArayuzFabrikasi.Kutu(kok, "govde",
                new Vector2(boyut.x - 40f, boyut.y - 100f), new Vector2(0.5f, 0.5f),
                new Vector2(0f, -16f), new Color(0f, 0f, 0f, 0f));

            return p;
        }

        public void Kapat() => Destroy(gameObject);
    }

    /// <summary>Bina kurma dükkânı: kategoriye göre kurulabilir yapılar.</summary>
    public class DukkanPaneli : Panel
    {
        public static DukkanPaneli Ac(Transform tuval, Katalog katalog, ObaDurumu durum,
            Action<string> binaSecildi)
        {
            var p = Olustur<DukkanPaneli>(tuval, "Yapı Kur", new Vector2(760f, 560f));
            var liste = ArayuzFabrikasi.Liste(p.Govde, new Vector2(700f, 420f),
                new Vector2(0.5f, 0.5f), new Vector2(0f, -10f));

            // Otağ seviyesine göre kaç tane daha kurulabilir?
            var sayim = new Dictionary<string, int>();
            foreach (var b in durum.buildings)
                sayim[b.key] = (sayim.TryGetValue(b.key, out var mevcutAdet) ? mevcutAdet : 0) + 1;

            var otagSeviyesi = durum.village.otag_level;

            foreach (var tip in katalog.buildings)
            {
                if (tip.key == "otag") continue;

                var seviye1 = tip.levels.Count > 0 ? tip.levels[0] : null;
                if (seviye1 == null || seviye1.otag > otagSeviyesi) continue;

                var mevcut = sayim.TryGetValue(tip.key, out var adet) ? adet : 0;
                var maliyet = MaliyetMetni(seviye1.cost);

                ArayuzFabrikasi.ListeSatiri(liste,
                    SpriteKutuphanesi.Bina(tip.key),
                    $"{tip.name}  ({mevcut} adet)",
                    $"{tip.description}\n{maliyet}",
                    "Kur",
                    () => { binaSecildi?.Invoke(tip.key); p.Kapat(); },
                    88f);
            }

            return p;
        }

        public static string MaliyetMetni(Kaynaklar k)
        {
            var parcalar = new List<string>();
            if (k.gold > 0) parcalar.Add($"{ArayuzFabrikasi.Sayi(k.gold)} altın");
            if (k.grain > 0) parcalar.Add($"{ArayuzFabrikasi.Sayi(k.grain)} tahıl");
            if (k.iron > 0) parcalar.Add($"{ArayuzFabrikasi.Sayi(k.iron)} demir");
            if (k.kut > 0) parcalar.Add($"{ArayuzFabrikasi.Sayi(k.kut)} kut");

            return parcalar.Count > 0 ? string.Join(" · ", parcalar) : "bedava";
        }
    }

    /// <summary>Seçili binanın bilgi ve işlem penceresi.</summary>
    public class BinaPaneli : Panel
    {
        public static BinaPaneli Ac(Transform tuval, BinaDurumu bina, BinaTipi tip,
            ObaDurumu durum, Action yukselt, Action tasi, Action bitir, Action iptal)
        {
            var p = Olustur<BinaPaneli>(tuval, tip?.name ?? bina.key, new Vector2(560f, 440f));

            ArayuzFabrikasi.Resim(p.Govde, SpriteKutuphanesi.Bina(bina.key),
                new Vector2(150f, 150f), new Vector2(0f, 1f), new Vector2(18f, -10f));

            ArayuzFabrikasi.Yazi(p.Govde, $"Seviye {bina.level}", new Vector2(300f, 28f),
                new Vector2(0f, 1f), new Vector2(180f, -16f), 22, TextAnchor.MiddleLeft,
                ArayuzFabrikasi.Altin);

            if (tip != null)
            {
                var aciklama = ArayuzFabrikasi.Yazi(p.Govde, tip.description,
                    new Vector2(320f, 60f), new Vector2(0f, 1f), new Vector2(180f, -50f),
                    17, TextAnchor.UpperLeft);
                aciklama.horizontalOverflow = HorizontalWrapMode.Wrap;

                var efsane = ArayuzFabrikasi.Yazi(p.Govde, tip.lore,
                    new Vector2(490f, 96f), new Vector2(0f, 1f), new Vector2(18f, -180f),
                    15, TextAnchor.UpperLeft, new Color(0.68f, 0.64f, 0.58f));
                efsane.horizontalOverflow = HorizontalWrapMode.Wrap;
                efsane.fontStyle = FontStyle.Italic;
            }

            if (bina.constructing)
            {
                ArayuzFabrikasi.Yazi(p.Govde,
                    $"İnşa ediliyor — {ArayuzFabrikasi.Sure(bina.remaining)} kaldı",
                    new Vector2(480f, 30f), new Vector2(0.5f, 0f), new Vector2(0f, 76f),
                    19, TextAnchor.MiddleCenter, new Color(0.95f, 0.78f, 0.35f));

                ArayuzFabrikasi.Dugme(p.Govde, "Gök Taşı ile Bitir", new Vector2(230f, 52f),
                    new Vector2(0.5f, 0f), new Vector2(-120f, 16f), () => { bitir?.Invoke(); p.Kapat(); });
                ArayuzFabrikasi.Dugme(p.Govde, "İptal", new Vector2(150f, 52f),
                    new Vector2(0.5f, 0f), new Vector2(120f, 16f), () => { iptal?.Invoke(); p.Kapat(); });
            }
            else
            {
                var sonraki = tip?.levels?.Find(l => l.level == bina.level + 1);

                if (sonraki != null)
                {
                    var uygun = sonraki.otag <= durum.village.otag_level;
                    var bilgi = uygun
                        ? $"Seviye {sonraki.level}: {DukkanPaneli.MaliyetMetni(sonraki.cost)}  ·  {ArayuzFabrikasi.Sure(sonraki.seconds)}"
                        : $"Seviye {sonraki.level} için Otağ {sonraki.otag} gerekir";

                    ArayuzFabrikasi.Yazi(p.Govde, bilgi, new Vector2(500f, 28f),
                        new Vector2(0.5f, 0f), new Vector2(0f, 78f), 17, TextAnchor.MiddleCenter,
                        uygun ? ArayuzFabrikasi.Metin : new Color(0.85f, 0.5f, 0.45f));

                    if (uygun)
                        ArayuzFabrikasi.Dugme(p.Govde, "Yükselt", new Vector2(200f, 52f),
                            new Vector2(0.5f, 0f), new Vector2(-110f, 16f),
                            () => { yukselt?.Invoke(); p.Kapat(); }, yesil: true);
                }
                else
                {
                    ArayuzFabrikasi.Yazi(p.Govde, "Azami seviyede", new Vector2(400f, 28f),
                        new Vector2(0.5f, 0f), new Vector2(0f, 78f), 18, TextAnchor.MiddleCenter,
                        ArayuzFabrikasi.Altin);
                }

                ArayuzFabrikasi.Dugme(p.Govde, "Taşı", new Vector2(160f, 52f),
                    new Vector2(0.5f, 0f), new Vector2(110f, 16f),
                    () => { tasi?.Invoke(); p.Kapat(); });
            }

            return p;
        }
    }

    /// <summary>Ordu paneli: asker eğit, kuyruğu gör, tılsım hazırla.</summary>
    public class OrduPaneli : Panel
    {
        public static OrduPaneli Ac(Transform tuval, Katalog katalog, ObaDurumu durum,
            Action<string, int> egit)
        {
            var p = Olustur<OrduPaneli>(tuval, "Ordu", new Vector2(820f, 600f));

            var kullanim = durum.army.usage;
            ArayuzFabrikasi.Yazi(p.Govde,
                $"Ordugâh: {kullanim.ready + kullanim.queued} / {kullanim.capacity} alan",
                new Vector2(500f, 28f), new Vector2(0.5f, 1f), new Vector2(0f, -6f),
                19, TextAnchor.MiddleCenter, ArayuzFabrikasi.Altin);

            var liste = ArayuzFabrikasi.Liste(p.Govde, new Vector2(760f, 440f),
                new Vector2(0.5f, 0.5f), new Vector2(0f, -24f));

            // Elde ne kadar asker var?
            var elde = new Dictionary<string, AskerDurumu>();
            foreach (var a in durum.army.troops) elde[a.key] = a;

            // Kışla seviyesi: hangi askerler açık?
            var kislaSeviyesi = 0;
            var karaKislaSeviyesi = 0;
            foreach (var b in durum.buildings)
            {
                if (b.constructing) continue;
                if (b.key == "kisla") kislaSeviyesi = Mathf.Max(kislaSeviyesi, b.level);
                if (b.key == "kara_kisla") karaKislaSeviyesi = Mathf.Max(karaKislaSeviyesi, b.level);
            }

            foreach (var tip in katalog.troops)
            {
                var gerekli = tip.category == "karanlik" ? karaKislaSeviyesi : kislaSeviyesi;
                if (tip.unlock_kisla > gerekli) continue;

                var sahip = elde.TryGetValue(tip.key, out var sahipOlunan) ? sahipOlunan : null;
                var adet = sahip?.ready ?? 0;
                var seviye = sahip?.level ?? 1;

                ArayuzFabrikasi.ListeSatiri(liste,
                    SpriteKutuphanesi.Asker(tip.key),
                    $"{tip.name}  sv{seviye}  ×{adet}",
                    $"{tip.housing} alan · {ArayuzFabrikasi.Sure(tip.train_seconds)} · {DukkanPaneli.MaliyetMetni(tip.cost)}",
                    "Eğit ×5",
                    () => egit?.Invoke(tip.key, 5),
                    84f);
            }

            return p;
        }
    }

    /// <summary>Saldırı öncesi hedef kartı.</summary>
    public class HedefPaneli : Panel
    {
        public static HedefPaneli Ac(Transform tuval, KesifYaniti hedef,
            Action saldir, Action baskaRakip)
        {
            var p = Olustur<HedefPaneli>(tuval, "Rakip Oba", new Vector2(620f, 460f));

            ArayuzFabrikasi.Yazi(p.Govde, hedef.player?.name ?? "Bilinmeyen",
                new Vector2(500f, 34f), new Vector2(0.5f, 1f), new Vector2(0f, -10f),
                24, TextAnchor.MiddleCenter);

            ArayuzFabrikasi.Yazi(p.Govde,
                $"Otağ {hedef.village.otag_level}  ·  {hedef.player?.trophies ?? 0} kupa",
                new Vector2(500f, 28f), new Vector2(0.5f, 1f), new Vector2(0f, -46f),
                18, TextAnchor.MiddleCenter, ArayuzFabrikasi.Altin);

            // Yağmalanabilir kaynaklar
            var kaynaklar = new[] { "gold", "grain", "iron", "kut" };
            var degerler = new[] { hedef.loot.gold, hedef.loot.grain, hedef.loot.iron, hedef.loot.kut };

            for (var i = 0; i < 4; i++)
            {
                var x = -210f + i * 140f;
                ArayuzFabrikasi.Resim(p.Govde, SpriteKutuphanesi.KaynakIkonu(kaynaklar[i]),
                    new Vector2(40f, 40f), new Vector2(0.5f, 0.5f), new Vector2(x, 20f));
                ArayuzFabrikasi.Yazi(p.Govde, ArayuzFabrikasi.Sayi(degerler[i]),
                    new Vector2(120f, 26f), new Vector2(0.5f, 0.5f), new Vector2(x, -16f),
                    18, TextAnchor.MiddleCenter);
            }

            ArayuzFabrikasi.Yazi(p.Govde, "Yağmalanabilir ganimet",
                new Vector2(400f, 24f), new Vector2(0.5f, 0.5f), new Vector2(0f, 56f),
                16, TextAnchor.MiddleCenter, new Color(0.7f, 0.67f, 0.62f));

            ArayuzFabrikasi.Dugme(p.Govde, "Saldır!", new Vector2(220f, 58f),
                new Vector2(0.5f, 0f), new Vector2(-120f, 14f),
                () => { saldir?.Invoke(); p.Kapat(); }, yesil: true, yaziBoyutu: 24);

            ArayuzFabrikasi.Dugme(p.Govde, "Başka Rakip", new Vector2(200f, 58f),
                new Vector2(0.5f, 0f), new Vector2(120f, 14f),
                () => { baskaRakip?.Invoke(); p.Kapat(); });

            return p;
        }
    }

    /// <summary>Savaş sonucu: yıldız, yıkım, ganimet.</summary>
    public class SonucPaneli : Panel
    {
        public static SonucPaneli Ac(Transform tuval, SavasSonucu sonuc, Action kapat)
        {
            var p = Olustur<SonucPaneli>(tuval, sonuc.stars > 0 ? "Zafer!" : "Akın Bitti",
                new Vector2(600f, 460f));

            // Yıldızlar
            for (var i = 0; i < 3; i++)
            {
                var dolu = i < sonuc.stars;
                ArayuzFabrikasi.Resim(p.Govde,
                    SpriteKutuphanesi.Arayuz(dolu ? "ikon_yildiz" : "ikon_yildiz_bos"),
                    new Vector2(dolu ? 96f : 76f, dolu ? 96f : 76f),
                    new Vector2(0.5f, 1f),
                    new Vector2(-120f + i * 120f, i == 1 ? -10f : -24f));
            }

            ArayuzFabrikasi.Yazi(p.Govde, $"%{sonuc.destruction:0.#} yıkım",
                new Vector2(400f, 34f), new Vector2(0.5f, 1f), new Vector2(0f, -126f),
                26, TextAnchor.MiddleCenter, ArayuzFabrikasi.Altin);

            var kupa = sonuc.trophies?.attacker ?? 0;
            ArayuzFabrikasi.Yazi(p.Govde, (kupa >= 0 ? "+" : "") + kupa + " kupa",
                new Vector2(400f, 28f), new Vector2(0.5f, 1f), new Vector2(0f, -164f),
                20, TextAnchor.MiddleCenter,
                kupa >= 0 ? new Color(0.5f, 0.85f, 0.5f) : new Color(0.9f, 0.5f, 0.45f));

            // Ganimet
            var kaynaklar = new[] { "gold", "grain", "iron", "kut" };
            var degerler = new[] { sonuc.loot.gold, sonuc.loot.grain, sonuc.loot.iron, sonuc.loot.kut };

            for (var i = 0; i < 4; i++)
            {
                var x = -195f + i * 130f;
                ArayuzFabrikasi.Resim(p.Govde, SpriteKutuphanesi.KaynakIkonu(kaynaklar[i]),
                    new Vector2(36f, 36f), new Vector2(0.5f, 0f), new Vector2(x, 112f));
                ArayuzFabrikasi.Yazi(p.Govde, ArayuzFabrikasi.Sayi(degerler[i]),
                    new Vector2(120f, 24f), new Vector2(0.5f, 0f), new Vector2(x, 86f),
                    17, TextAnchor.MiddleCenter);
            }

            ArayuzFabrikasi.Dugme(p.Govde, "Obaya Dön", new Vector2(240f, 56f),
                new Vector2(0.5f, 0f), new Vector2(0f, 16f),
                () => { kapat?.Invoke(); p.Kapat(); }, yesil: true, yaziBoyutu: 22);

            return p;
        }
    }

    /// <summary>Boy paneli: boy ara, katıl, üyeleri gör, sohbet et.</summary>
    public class BoyPaneli : Panel
    {
        public static BoyPaneli Ac(Transform tuval, BoyDetay detay, List<BoyBilgisi> arama,
            Action<int> katil, Action ayril, Action<string> sohbetGonder,
            List<SohbetMesaji> mesajlar)
        {
            var baslik = detay?.clan != null ? detay.clan.name : "Boylar";
            var p = Olustur<BoyPaneli>(tuval, baslik, new Vector2(880f, 620f));

            if (detay?.clan != null)
            {
                ArayuzFabrikasi.Yazi(p.Govde,
                    $"Seviye {detay.clan.level} · {detay.clan.members}/{detay.clan.member_limit} üye · {detay.clan.points} puan",
                    new Vector2(600f, 26f), new Vector2(0.5f, 1f), new Vector2(0f, -8f),
                    18, TextAnchor.MiddleCenter, ArayuzFabrikasi.Altin);

                // Üyeler
                var uyeListe = ArayuzFabrikasi.Liste(p.Govde, new Vector2(400f, 400f),
                    new Vector2(0f, 0.5f), new Vector2(10f, -20f));

                foreach (var u in detay.members)
                    ArayuzFabrikasi.ListeSatiri(uyeListe, null,
                        $"{u.name} {(u.online ? "•" : "")}",
                        $"{RutbeAdi(u.role)} · {u.trophies} kupa · {u.donated} bağış",
                        null, null, 58f);

                // Sohbet
                var sohbetListe = ArayuzFabrikasi.Liste(p.Govde, new Vector2(400f, 340f),
                    new Vector2(1f, 0.5f), new Vector2(-10f, 10f));

                if (mesajlar != null)
                    foreach (var m in mesajlar)
                        ArayuzFabrikasi.ListeSatiri(sohbetListe, null,
                            m.player?.name ?? "Sistem", m.body, null, null, 52f);

                var giris = ArayuzFabrikasi.Giris(p.Govde, "Mesaj yaz...",
                    new Vector2(300f, 44f), new Vector2(1f, 0.5f), new Vector2(-110f, -180f));

                ArayuzFabrikasi.Dugme(p.Govde, "Gönder", new Vector2(96f, 44f),
                    new Vector2(1f, 0.5f), new Vector2(-10f, -180f),
                    () =>
                    {
                        if (string.IsNullOrWhiteSpace(giris.text)) return;
                        sohbetGonder?.Invoke(giris.text);
                        giris.text = "";
                    }, yesil: true, yaziBoyutu: 17);

                ArayuzFabrikasi.Dugme(p.Govde, "Boydan Ayrıl", new Vector2(180f, 46f),
                    new Vector2(0f, 0f), new Vector2(10f, 10f),
                    () => { ayril?.Invoke(); p.Kapat(); });
            }
            else
            {
                var liste = ArayuzFabrikasi.Liste(p.Govde, new Vector2(820f, 460f),
                    new Vector2(0.5f, 0.5f), new Vector2(0f, -10f));

                if (arama != null)
                    foreach (var b in arama)
                        ArayuzFabrikasi.ListeSatiri(liste, SpriteKutuphanesi.Arayuz("ikon_boy"),
                            $"{b.name}  [{b.tag}]",
                            $"Seviye {b.level} · {b.members}/{b.member_limit} üye · {b.required_trophies} kupa şartı",
                            "Katıl", () => { katil?.Invoke(b.id); p.Kapat(); }, 76f);
            }

            return p;
        }

        private static string RutbeAdi(string rol) => rol switch
        {
            "lider" => "Boy Başı",
            "vezir" => "Vezir",
            "alp" => "Alp",
            _ => "Er",
        };
    }
}
