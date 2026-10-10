using System;
using System.Collections.Generic;

namespace KlanlarVadisi.Net
{
    /// <summary>
    /// Sunucu API'sinin döndürdüğü veri yapıları.
    ///
    /// Alan adları sunucudaki JSON anahtarlarıyla birebir aynıdır; Unity'nin
    /// JsonUtility'si bu şekilde doğrudan eşler. Türkçe alan adları
    /// bilinçlidir — sunucu ve istemci aynı sözlüğü konuşur.
    /// </summary>

    [Serializable] public class GirisYaniti
    {
        public string token;
        public OyuncuOzet player;
        public bool guest;
    }

    [Serializable] public class OyuncuOzet
    {
        public int id;
        public string name;
        public string avatar;
        public int level;
        public int trophies;
        public Lig league;
        public long gok_tasi;
        public ObaOzet village;
        public BoyOzet clan;
    }

    [Serializable] public class Lig
    {
        public string key;
        public string name;
        public int min;
        public string color;
    }

    [Serializable] public class ObaOzet
    {
        public int id;
        public string name;
        public int otag_level;
        public DunyaKonum world;
    }

    [Serializable] public class DunyaKonum { public int x; public int y; }

    [Serializable] public class BoyOzet
    {
        public int id;
        public string name;
        public string tag;
        public int level;
        public string role;
    }

    /* ----------------------------- Oba durumu ----------------------------- */

    [Serializable] public class ObaDurumu
    {
        public OyuncuDurumu player;
        public KoyDurumu village;
        public List<BinaDurumu> buildings = new();
        public OrduDurumu army;
        public KuyrukDurumu queues;
        public List<BoyAskeri> clan_troops = new();
        public List<OyunOlayi> events = new();
    }

    [Serializable] public class OyuncuDurumu
    {
        public int id;
        public string name;
        public string avatar;
        public int level;
        public long xp;
        public int trophies;
        public int best_trophies;
        public Lig league;
        public long gok_tasi;
        public string shield_until;
        public string guard_until;
        public BoyOzet clan;
        public OyuncuIstatistik stats;
    }

    [Serializable] public class OyuncuIstatistik
    {
        public int attack_wins;
        public int defense_wins;
        public int war_stars;
        public int donated;
        public int received;
    }

    [Serializable] public class KoyDurumu
    {
        public int id;
        public string name;
        public int otag_level;
        public string biome;
        public DunyaKonum world;
        public Kaynaklar resources;
        public Kaynaklar production;
        public Kaynaklar capacity;
        public UstaDurumu builders;
        public int grid;
        public string server_time;
    }

    [Serializable] public class Kaynaklar
    {
        public long gold;
        public long grain;
        public long iron;
        public long kut;

        public long Al(string tur) => tur switch
        {
            "gold" => gold, "grain" => grain, "iron" => iron, "kut" => kut, _ => 0,
        };
    }

    [Serializable] public class UstaDurumu { public int busy; public int total; public int free; }

    [Serializable] public class BinaDurumu
    {
        public int id;
        public string key;
        public string category;
        public int level;
        public int x;
        public int y;
        public int w;
        public int h;
        public bool constructing;
        public int target_level;
        public string ends_at;
        public int remaining;
    }

    [Serializable] public class OrduDurumu
    {
        public OrduKullanimi usage;
        public List<AskerDurumu> troops = new();
        public List<TilsimDurumu> spells = new();
        public List<KahramanDurumu> heroes = new();
    }

    [Serializable] public class OrduKullanimi { public int ready; public int queued; public int capacity; }

    [Serializable] public class AskerDurumu
    {
        public string key;
        public int level;
        public int ready;
        public int housing;
        public bool upgrading;
        public string upgrade_ends;
    }

    [Serializable] public class TilsimDurumu
    {
        public string key;
        public int level;
        public int ready;
        public int housing;
        public bool upgrading;
        public string upgrade_ends;
    }

    [Serializable] public class KahramanDurumu
    {
        public string key;
        public string name;
        public int level;
        public bool unlocked;
        public bool available;
        public string regen_until;
        public bool upgrading;
        public string upgrade_ends;
    }

    [Serializable] public class KuyrukDurumu
    {
        public List<EgitimKalemi> training = new();
        public List<EgitimKalemi> brewing = new();
    }

    [Serializable] public class EgitimKalemi
    {
        public int id;
        public string key;
        public int quantity;
        public int each;
        public string starts_at;
        public string ends_at;
    }

    [Serializable] public class BoyAskeri { public string key; public int level; public int quantity; }

    [Serializable] public class OyunOlayi
    {
        public int id;
        public string type;
        public string title;
        public string body;
        public string at;
    }

    /* ------------------------------- Katalog ------------------------------ */

    [Serializable] public class Katalog
    {
        public string version;
        public IzgaraAyari grid;
        public List<BinaTipi> buildings = new();
        public List<AskerTipi> troops = new();
        public List<KahramanTipi> heroes = new();
        public List<TilsimTipi> spells = new();
        public DunyaAyari world;
    }

    [Serializable] public class IzgaraAyari { public int size; public int margin; }
    [Serializable] public class DunyaAyari { public int seed; public int width; public int height; }

    [Serializable] public class BinaTipi
    {
        public string key;
        public string name;
        public string category;
        public string produces;
        public string stores;
        public string description;
        public string lore;
        public string sprite;
        public int max_level;
        public int w;
        public int h;
        public List<BinaSeviyesi> levels = new();
    }

    [Serializable] public class BinaSeviyesi
    {
        public int level;
        public Kaynaklar cost;
        public int seconds;
        public int hp;
        public int production;
        public long storage;
        public int dps;
        public float range;
        public int capacity;
        public int otag;
        public int xp;
    }

    [Serializable] public class AskerTipi
    {
        public string key;
        public string name;
        public string category;
        public string description;
        public string lore;
        public int housing;
        public int train_seconds;
        public Kaynaklar cost;
        public int max_level;
        public string preferred;
        public float move_speed;
        public float range;
        public float attack_speed;
        public bool is_air;
        public int unlock_kisla;
        public List<AskerSeviyesi> levels = new();
    }

    [Serializable] public class AskerSeviyesi
    {
        public int level;
        public int hp;
        public int dps;
        public int seconds;
        public int demirci;
    }

    [Serializable] public class KahramanTipi
    {
        public string key;
        public string name;
        public string title;
        public string lore;
        public int max_level;
        public int unlock_otag;
        public int regen_minutes;
    }

    [Serializable] public class TilsimTipi
    {
        public string key;
        public string name;
        public string category;
        public string description;
        public string lore;
        public int housing;
        public int brew_seconds;
        public int max_level;
        public float radius;
        public int unlock_buyu;
    }

    /* -------------------------------- Dünya ------------------------------- */

    [Serializable] public class DunyaBilgisi
    {
        public int seed;
        public int width;
        public int height;
        public int chunk;
        public List<BolgeBilgisi> regions = new();
    }

    [Serializable] public class BolgeBilgisi
    {
        public string key;
        public string name;
        public string lore;
        public string biome;
        public DunyaKonum center;
        public int radius;
        public string color;
    }

    [Serializable] public class DunyaParcasi
    {
        public int x;
        public int y;
        public int w;
        public int h;
        public List<DunyaKaresi> tiles = new();
    }

    [Serializable] public class DunyaKaresi
    {
        public int x;
        public int y;
        public string type;
        public string label;
        public int power;
        public ObaOzet village;
        public KareOyuncusu player;
    }

    [Serializable] public class KareOyuncusu
    {
        public int id;
        public string name;
        public int trophies;
        public bool @protected;
        public BoyOzet clan;
    }

    /* -------------------------------- Savaş ------------------------------- */

    [Serializable] public class KesifYaniti
    {
        public KoyDurumu village;
        public OyuncuOzet player;
        public Kaynaklar loot;
        public List<BinaDurumu> buildings = new();
    }

    [Serializable] public class RakipYaniti { public KesifYaniti target; }

    [Serializable] public class SaldiriIstegi
    {
        public List<AskerIndirme> deployments = new();
        public List<TilsimAtma> spells = new();
        public List<string> heroes = new();
        public string type = "coklu";
    }

    [Serializable] public class AskerIndirme
    {
        public string troop;
        public float x;
        public float y;
        public float t;
    }

    [Serializable] public class TilsimAtma
    {
        public string spell;
        public float x;
        public float y;
        public float t;
    }

    [Serializable] public class SaldiriYaniti
    {
        public SavasSonucu battle;
        public ObaDurumu village;
    }

    [Serializable] public class SavasSonucu
    {
        public int id;
        public string type;
        public int stars;
        public float destruction;
        public int duration;
        public Kaynaklar loot;
        public KupaDegisimi trophies;
        public string at;
        public Tekrar replay;
    }

    [Serializable] public class KupaDegisimi { public int attacker; public int defender; }

    /* ---------------------------- Savaş tekrarı --------------------------- */

    [Serializable] public class Tekrar
    {
        public int version;
        public long seed;
        public int grid;
        public int tick_rate;
        public int duration_ticks;
        public List<TekrarBina> buildings = new();
        public List<TekrarAsker> units = new();
        public List<TekrarOlay> events = new();
        public TekrarSonuc result;
    }

    [Serializable] public class TekrarBina
    {
        public int id;
        public string key;
        public int lv;
        public int x;
        public int y;
        public int w;
        public int h;
        public int hp;
        public string cat;
        public float @as;
        public float rng;
    }

    [Serializable] public class TekrarAsker
    {
        public int id;
        public string key;
        public int lv;
        public int hp;
        public bool air;
        public string side;
        public bool hero;
        public float @as;
        public float spd;
    }

    /// <summary>
    /// Tek bir tekrar olayı. Unity'nin JsonUtility'si karmaşık/dinamik
    /// alanları (hp sözlükleri, yol dizileri) çözemediği için olaylar
    /// <see cref="MiniJson"/> ile ayrıştırılıp buraya doldurulur.
    /// </summary>
    public class TekrarOlay
    {
        public int t;              // adım (tick)
        public string e;           // olay türü: dep, mv, atk, atku, def, bdie, udie, hp, spell, trap, spawn, abil
        public int u;              // asker kimliği
        public int v;              // hedef asker kimliği (atku)
        public int b;              // bina kimliği
        public float x, y, r;      // konum / yarıçap
        public string k;           // tılsım veya yetenek anahtarı
        public int lv;
        public string side;
        public int hp;
        public bool air;
        public List<float[]> p;    // yol noktaları (mv)
        public Dictionary<int, int> hpB;  // bina canları
        public Dictionary<int, int> hpU;  // asker canları
    }

    [Serializable] public class TekrarSonuc
    {
        public int stars;
        public float destruction;
        public int duration;
    }

    /* --------------------------------- Boy -------------------------------- */

    [Serializable] public class BoyListesi { public List<BoyBilgisi> data = new(); }

    [Serializable] public class BoyBilgisi
    {
        public int id;
        public string name;
        public string tag;
        public string type;
        public int level;
        public long points;
        public int members;
        public int member_limit;
        public int required_trophies;
        public int war_wins;
        public string description;
    }

    [Serializable] public class BoyDetay
    {
        public BoyBilgisi clan;
        public List<BoyUyesi> members = new();
    }

    [Serializable] public class BoyUyesi
    {
        public int id;
        public string name;
        public string avatar;
        public int level;
        public int trophies;
        public string role;
        public int donated;
        public int received;
        public int war_stars;
        public bool online;
    }

    [Serializable] public class SohbetListesi { public List<SohbetMesaji> messages = new(); }

    [Serializable] public class SohbetMesaji
    {
        public int id;
        public string kind;
        public string body;
        public BoyUyesi player;
        public string at;
    }

    /* ------------------------------ Sıralama ------------------------------ */

    [Serializable] public class OyuncuSiralamasi
    {
        public List<SiralamaSatiri> players = new();
        public int my_rank;
    }

    [Serializable] public class SiralamaSatiri
    {
        public int rank;
        public int id;
        public string name;
        public string avatar;
        public int level;
        public int trophies;
        public Lig league;
        public BoyOzet clan;
    }

    [Serializable] public class ApiHatasi
    {
        public string message;
        public string code;
    }
}
