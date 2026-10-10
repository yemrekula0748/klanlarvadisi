using System;
using System.Collections;
using System.Collections.Generic;
using System.Globalization;
using System.Text;

namespace KlanlarVadisi.Util
{
    /// <summary>
    /// Küçük, bağımlılıksız JSON ayrıştırıcı/yazıcı.
    ///
    /// Unity'nin yerleşik <c>JsonUtility</c>'si sözlükleri, dizi içindeki
    /// karışık türleri ve kök seviyesindeki dizileri çözemez. Savaş tekrarı
    /// (replay) verisi tam olarak bunları içerdiği için burada basit bir
    /// ayrıştırıcı kullanılır. Üçüncü taraf paket gerekmez.
    ///
    /// Döndürülen türler: Dictionary&lt;string,object&gt;, List&lt;object&gt;,
    /// string, double, bool, null.
    /// </summary>
    public static class MiniJson
    {
        public static object Deserialize(string json)
        {
            if (string.IsNullOrEmpty(json)) return null;
            var p = new Parser(json);
            return p.ParseValue();
        }

        public static string Serialize(object obj)
        {
            var sb = new StringBuilder();
            Write(sb, obj);
            return sb.ToString();
        }

        /* ------------------------- Yardımcı erişim ------------------------ */

        public static Dictionary<string, object> AsDict(object o) => o as Dictionary<string, object>;
        public static List<object> AsList(object o) => o as List<object>;

        public static string Str(Dictionary<string, object> d, string key, string fallback = null)
            => d != null && d.TryGetValue(key, out var v) && v != null ? v.ToString() : fallback;

        public static int Int(Dictionary<string, object> d, string key, int fallback = 0)
        {
            if (d == null || !d.TryGetValue(key, out var v) || v == null) return fallback;
            if (v is double dd) return (int)Math.Round(dd);
            return int.TryParse(v.ToString(), NumberStyles.Any, CultureInfo.InvariantCulture, out var r) ? r : fallback;
        }

        public static float Float(Dictionary<string, object> d, string key, float fallback = 0f)
        {
            if (d == null || !d.TryGetValue(key, out var v) || v == null) return fallback;
            if (v is double dd) return (float)dd;
            return float.TryParse(v.ToString(), NumberStyles.Any, CultureInfo.InvariantCulture, out var r) ? r : fallback;
        }

        public static bool Bool(Dictionary<string, object> d, string key, bool fallback = false)
        {
            if (d == null || !d.TryGetValue(key, out var v) || v == null) return fallback;
            if (v is bool b) return b;
            var s = v.ToString().ToLowerInvariant();
            return s == "true" || s == "1";
        }

        /* ----------------------------- Ayrıştırıcı ------------------------ */

        private sealed class Parser
        {
            private readonly string _s;
            private int _i;

            public Parser(string s) { _s = s; _i = 0; }

            private void SkipWhite()
            {
                while (_i < _s.Length && char.IsWhiteSpace(_s[_i])) _i++;
            }

            public object ParseValue()
            {
                SkipWhite();
                if (_i >= _s.Length) return null;

                switch (_s[_i])
                {
                    case '{': return ParseObject();
                    case '[': return ParseArray();
                    case '"': return ParseString();
                    case 't': _i += 4; return true;
                    case 'f': _i += 5; return false;
                    case 'n': _i += 4; return null;
                    default: return ParseNumber();
                }
            }

            private Dictionary<string, object> ParseObject()
            {
                var dict = new Dictionary<string, object>();
                _i++; // '{'

                while (true)
                {
                    SkipWhite();
                    if (_i >= _s.Length) break;
                    if (_s[_i] == '}') { _i++; break; }
                    if (_s[_i] == ',') { _i++; continue; }

                    var key = ParseString();
                    SkipWhite();
                    if (_i < _s.Length && _s[_i] == ':') _i++;
                    dict[key] = ParseValue();
                }

                return dict;
            }

            private List<object> ParseArray()
            {
                var list = new List<object>();
                _i++; // '['

                while (true)
                {
                    SkipWhite();
                    if (_i >= _s.Length) break;
                    if (_s[_i] == ']') { _i++; break; }
                    if (_s[_i] == ',') { _i++; continue; }
                    list.Add(ParseValue());
                }

                return list;
            }

            private string ParseString()
            {
                var sb = new StringBuilder();
                _i++; // '"'

                while (_i < _s.Length)
                {
                    var c = _s[_i++];
                    if (c == '"') break;

                    if (c != '\\') { sb.Append(c); continue; }

                    if (_i >= _s.Length) break;
                    var esc = _s[_i++];
                    switch (esc)
                    {
                        case '"': sb.Append('"'); break;
                        case '\\': sb.Append('\\'); break;
                        case '/': sb.Append('/'); break;
                        case 'b': sb.Append('\b'); break;
                        case 'f': sb.Append('\f'); break;
                        case 'n': sb.Append('\n'); break;
                        case 'r': sb.Append('\r'); break;
                        case 't': sb.Append('\t'); break;
                        case 'u':
                            if (_i + 4 <= _s.Length)
                            {
                                var hex = _s.Substring(_i, 4);
                                _i += 4;
                                sb.Append((char)Convert.ToInt32(hex, 16));
                            }
                            break;
                    }
                }

                return sb.ToString();
            }

            private object ParseNumber()
            {
                var start = _i;
                while (_i < _s.Length && "+-0123456789.eE".IndexOf(_s[_i]) >= 0) _i++;
                var token = _s.Substring(start, _i - start);

                return double.TryParse(token, NumberStyles.Any, CultureInfo.InvariantCulture, out var d)
                    ? d
                    : 0d;
            }
        }

        /* ------------------------------- Yazıcı --------------------------- */

        private static void Write(StringBuilder sb, object value)
        {
            switch (value)
            {
                case null: sb.Append("null"); break;
                case string s: WriteString(sb, s); break;
                case bool b: sb.Append(b ? "true" : "false"); break;
                case IDictionary dict: WriteDict(sb, dict); break;
                case IList list: WriteList(sb, list); break;
                case float fl: sb.Append(fl.ToString("R", CultureInfo.InvariantCulture)); break;
                case double db: sb.Append(db.ToString("R", CultureInfo.InvariantCulture)); break;
                default: sb.Append(Convert.ToString(value, CultureInfo.InvariantCulture)); break;
            }
        }

        private static void WriteDict(StringBuilder sb, IDictionary dict)
        {
            sb.Append('{');
            var first = true;
            foreach (DictionaryEntry e in dict)
            {
                if (!first) sb.Append(',');
                first = false;
                WriteString(sb, e.Key.ToString());
                sb.Append(':');
                Write(sb, e.Value);
            }
            sb.Append('}');
        }

        private static void WriteList(StringBuilder sb, IList list)
        {
            sb.Append('[');
            for (var i = 0; i < list.Count; i++)
            {
                if (i > 0) sb.Append(',');
                Write(sb, list[i]);
            }
            sb.Append(']');
        }

        private static void WriteString(StringBuilder sb, string s)
        {
            sb.Append('"');
            foreach (var c in s)
            {
                switch (c)
                {
                    case '"': sb.Append("\\\""); break;
                    case '\\': sb.Append("\\\\"); break;
                    case '\n': sb.Append("\\n"); break;
                    case '\r': sb.Append("\\r"); break;
                    case '\t': sb.Append("\\t"); break;
                    default:
                        if (c < 32) sb.Append("\\u").Append(((int)c).ToString("x4"));
                        else sb.Append(c);
                        break;
                }
            }
            sb.Append('"');
        }
    }
}
