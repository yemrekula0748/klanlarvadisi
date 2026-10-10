using System.Collections.Generic;
using KlanlarVadisi.Util;

namespace KlanlarVadisi.Net
{
    /// <summary>
    /// Sunucudan gelen savaş tekrarını (replay) oyun içi nesnelere çevirir.
    ///
    /// Tekrar verisi dinamik alanlar içerir (can sözlükleri, değişken uzunluklu
    /// yol dizileri), bu yüzden JsonUtility yerine <see cref="MiniJson"/>
    /// kullanılır.
    /// </summary>
    public static class TekrarCozumleyici
    {
        public static Tekrar Cozumle(Dictionary<string, object> d)
        {
            if (d == null) return null;

            var t = new Tekrar
            {
                version = MiniJson.Int(d, "version", 1),
                grid = MiniJson.Int(d, "grid", 44),
                tick_rate = MiniJson.Int(d, "tick_rate", 10),
                duration_ticks = MiniJson.Int(d, "duration_ticks"),
            };

            foreach (var o in MiniJson.AsList(Al(d, "buildings")) ?? new List<object>())
            {
                var b = MiniJson.AsDict(o);
                t.buildings.Add(new TekrarBina
                {
                    id = MiniJson.Int(b, "id"),
                    key = MiniJson.Str(b, "key", "otag"),
                    lv = MiniJson.Int(b, "lv", 1),
                    x = MiniJson.Int(b, "x"),
                    y = MiniJson.Int(b, "y"),
                    w = MiniJson.Int(b, "w", 1),
                    h = MiniJson.Int(b, "h", 1),
                    hp = MiniJson.Int(b, "hp", 1),
                    cat = MiniJson.Str(b, "cat", "ozel"),
                    @as = MiniJson.Float(b, "as", 1f),
                    rng = MiniJson.Float(b, "rng"),
                });
            }

            foreach (var o in MiniJson.AsList(Al(d, "units")) ?? new List<object>())
            {
                var u = MiniJson.AsDict(o);
                t.units.Add(new TekrarAsker
                {
                    id = MiniJson.Int(u, "id"),
                    key = MiniJson.Str(u, "key", "ceri"),
                    lv = MiniJson.Int(u, "lv", 1),
                    hp = MiniJson.Int(u, "hp", 1),
                    air = MiniJson.Bool(u, "air"),
                    side = MiniJson.Str(u, "side", "saldiran"),
                    hero = MiniJson.Bool(u, "hero"),
                    @as = MiniJson.Float(u, "as", 1f),
                    spd = MiniJson.Float(u, "spd", 1.6f),
                });
            }

            foreach (var o in MiniJson.AsList(Al(d, "events")) ?? new List<object>())
            {
                var e = MiniJson.AsDict(o);
                if (e == null) continue;

                var olay = new TekrarOlay
                {
                    t = MiniJson.Int(e, "t"),
                    e = MiniJson.Str(e, "e", ""),
                    u = MiniJson.Int(e, "u", -1),
                    v = MiniJson.Int(e, "v", -1),
                    x = MiniJson.Float(e, "x"),
                    y = MiniJson.Float(e, "y"),
                    r = MiniJson.Float(e, "r"),
                    k = MiniJson.Str(e, "k"),
                    lv = MiniJson.Int(e, "lv", 1),
                    side = MiniJson.Str(e, "side", "saldiran"),
                    hp = MiniJson.Int(e, "hp"),
                    air = MiniJson.Bool(e, "air"),
                };

                // 'b' alanı: can olayında sözlük, diğerlerinde bina kimliği
                if (e.TryGetValue("b", out var bRaw))
                {
                    if (bRaw is Dictionary<string, object> bDict)
                    {
                        olay.hpB = new Dictionary<int, int>();
                        foreach (var kv in bDict)
                            if (int.TryParse(kv.Key, out var id))
                                olay.hpB[id] = (int)ToNumber(kv.Value);
                    }
                    else olay.b = (int)ToNumber(bRaw);
                }

                if (olay.e == "hp" && e.TryGetValue("u", out var uRaw) && uRaw is Dictionary<string, object> uDict)
                {
                    olay.hpU = new Dictionary<int, int>();
                    olay.u = -1;
                    foreach (var kv in uDict)
                        if (int.TryParse(kv.Key, out var id))
                            olay.hpU[id] = (int)ToNumber(kv.Value);
                }

                // Yol noktaları
                if (e.TryGetValue("p", out var pRaw))
                {
                    var list = MiniJson.AsList(pRaw);
                    if (list != null)
                    {
                        olay.p = new List<float[]>(list.Count);
                        foreach (var pt in list)
                        {
                            var pair = MiniJson.AsList(pt);
                            if (pair is { Count: >= 2 })
                                olay.p.Add(new[] { (float)ToNumber(pair[0]), (float)ToNumber(pair[1]) });
                        }
                    }
                }

                t.events.Add(olay);
            }

            var res = MiniJson.AsDict(Al(d, "result"));
            if (res != null)
            {
                t.result = new TekrarSonuc
                {
                    stars = MiniJson.Int(res, "stars"),
                    destruction = MiniJson.Float(res, "destruction"),
                    duration = MiniJson.Int(res, "duration"),
                };
            }

            return t;
        }

        private static double ToNumber(object o) => o switch
        {
            double d => d,
            int i => i,
            string s when double.TryParse(s, System.Globalization.NumberStyles.Any,
                System.Globalization.CultureInfo.InvariantCulture, out var r) => r,
            _ => 0d,
        };

        /// <summary>
        /// Sözlükten güvenli okuma. Yerleşik <c>GetValueOrDefault</c> ile
        /// karışmaması için ayrı ad kullanılır.
        /// </summary>
        private static object Al(Dictionary<string, object> d, string key)
            => d != null && d.TryGetValue(key, out var v) ? v : null;
    }
}
