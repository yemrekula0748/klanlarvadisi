# Klanlar Vadisi

**Türk mitolojisi temalı, Clash of Clans benzeri çok oyunculu strateji oyunu.**
Unity istemcisi + Laravel 13 / MySQL sunucusu.

Obanı kur, Otağını yükselt, Çeri ve Alplarını yetiştir, Bozkurt sürülerini
salıp komşu obaları yağmala; boyuna katıl, Boy Savaşlarına gir. 1600×1600'lük
dünya haritasında Ötüken'den Kızıl Çöl'e, Ergenekon'dan Tanrı Dağı'na uzanan
on altı mitolojik bölgeden birine yerleş.

---

## İçindekiler

- [Mimari](#mimari)
- [Hızlı başlangıç](#hızlı-başlangıç)
- [Sunucu kurulumu](#sunucu-kurulumu)
- [Unity istemcisi](#unity-istemcisi)
- [Oyun tasarımı](#oyun-tasarımı)
- [Görseller](#görseller)
- [Denge ayarı](#denge-ayarı)
- [Test](#test)
- [Üretime alma](#üretime-alma)

---

## Mimari

```
┌──────────────────────┐         HTTPS / JSON          ┌───────────────────────┐
│   Unity İstemcisi    │  ─────────────────────────▶   │   Laravel 13 Sunucu   │
│                      │  ◀─────────────────────────   │                       │
│  • Oba görünümü      │         WebSocket             │  • Oyun kuralları     │
│  • Dünya haritası    │  ◀─────────────────────────   │  • Savaş simülasyonu  │
│  • Savaş tekrarı     │        (Reverb)               │  • MySQL              │
└──────────────────────┘                               └───────────────────────┘
```

**Temel ilke: istemci hiçbir oyun kararı vermez.** Kaynak üretimi, inşaat
süresi, asker gücü ve savaş sonucu tamamen sunucuda hesaplanır. İstemci
yalnızca niyeti iletir ("şu askeri şuraya indir") ve sunucunun ürettiği
sonucu gösterir. Bu yüzden hile yapılamaz: oyuncu istemciyi değiştirse bile
sunucu kendi hesabını yapar.

### Bu mimarinin iki güzel sonucu

**1. Savaş tekrarı (replay).** Sunucu savaşı adım adım simüle eder ve
sıkıştırılmış bir olay zaman çizgisi döndürür. Saldıran da savunan da aynı
savaşı birebir aynı izler; kayıt 100-250 KB'dir.

**2. Dünya haritası tek bir kare veri indirmeden gezilir.** 1600×1600 = 2.56
milyon karelik arazi, sunucu ve istemcide **aynı tohumdan deterministik
olarak üretilir** (`app/Support/Noise.php` ↔ `World/Noise.cs`). API'den
yalnızca "dolu" kareler gelir: obalar, mitolojik kamplar, kaynak damarları.

---

## Hızlı başlangıç

```bash
# 1) Sunucu
composer install
cp .env.example .env
php artisan key:generate

# MySQL veritabanını oluştur, sonra:
php artisan migrate --seed          # katalog + dünya
php artisan game:demo --players=40  # örnek oyuncular ve boylar
php artisan serve                   # http://localhost:8000

# 2) Unity
# unity-client/ klasörünü Unity Hub'a ekle (Unity 6000.0 LTS)
# Assets/Scenes/Oyun.unity sahnesini aç ve Play'e bas
```

Örnek giriş: `alpagut@klanlarvadisi.test` / `sifre1234`
veya giriş ekranında **Konuk Olarak Dene**.

---

## Sunucu kurulumu

### Gereksinimler

| Bileşen | Sürüm |
|---------|-------|
| PHP     | 8.3+ (`pdo_mysql`, `bcmath`, `intl`, `gd`, `zip`) |
| MySQL   | 8.0+ |
| Composer| 2.x |
| Node.js | 20+ (yalnızca görsel üretimi için) |

### Adımlar

```bash
composer install
cp .env.example .env
php artisan key:generate
```

`.env` içinde veritabanını ayarlayın:

```dotenv
DB_CONNECTION=mysql
DB_DATABASE=klanlar_vadisi
DB_USERNAME=root
DB_PASSWORD=

# Dünya tohumu: ÜRETİMDE DEĞİŞTİRMEYİN.
# Değişirse mevcut obaların altındaki arazi değişir.
GAME_WORLD_SEED=20261071
GAME_WORLD_WIDTH=1600
GAME_WORLD_HEIGHT=1600
```

```bash
php artisan migrate --seed
```

`--seed` iki şey yapar:
- **CatalogSeeder** — 32 bina, 18 asker, 4 kahraman, 8 tılsım ve bunların
  seviye tablolarını formüllerden üretir (toplam ~700 satır).
- **WorldSeeder** — dünyaya 16 kutsal alan, 900 mitolojik kamp, 700 kaynak
  damarı ve 400 harabe yerleştirir.

### Zamanlanmış görevler

Kaynak üretimi ve inşaat tamamlanması **tembel** hesaplanır (okuma anında),
bu yüzden arka plan işçisi gerekmez. Yalnızca boy savaşı geçişleri için tek
bir cron yeterlidir:

```cron
* * * * * cd /yol/klanlarvadisi && php artisan schedule:run >> /dev/null 2>&1
```

### Gerçek zamanlı (isteğe bağlı)

Boy sohbeti ve saldırı bildirimleri için Laravel Reverb:

```bash
php artisan reverb:start
```

---

## Unity istemcisi

### Açmak

1. Unity Hub → **Add** → `unity-client/` klasörünü seçin
2. Unity **6000.0 LTS** (veya 2022.3 LTS) ile açın
3. `Assets/Scenes/Oyun.unity` sahnesini açın
4. Play

Sunucu adresi sahnedeki **Oyun** nesnesinin `SunucuAdresi` alanındadır
(varsayılan `http://localhost:8000`). Derlemede komut satırından da
verilebilir: `KlanlarVadisi.exe --sunucu=https://oyun.example.com`

### Proje düzeni

```
unity-client/Assets/
  Resources/Art/          106 sprite (Buildings, Troops, Terrain, UI)
  Scenes/Oyun.unity       Tek sahne — her şey koddan kurulur
  Scripts/
    Core/     OyunBaslatici (giriş noktası), KameraSurucu, OyunSabitleri*
    Net/      ApiClient, ApiModels, TekrarCozumleyici
    World/    Noise, WorldTerrain, DunyaHaritasi, BolgeTanimlari*
    Village/  ObaGorunumu, BinaGorunumu
    Battle/   SavasTekrari, SaldiriKurgusu, Efekt, CanCubugu
    UI/       ArayuzFabrikasi, UstBar, Paneller, Bildirim
    Util/     Izometri, SpriteKutuphanesi, MiniJson
```

`*` ile işaretli dosyalar `php artisan game:export-unity` ile üretilir —
elle düzenlemeyin.

### Neden prefab yok?

Arayüz ve sahne nesneleri **koddan** kurulur (`ArayuzFabrikasi`,
`OyunBaslatici`). Böylece:

- proje sürüm takibinde okunabilir kalır (ikili .prefab farkı olmaz),
- iki kişi aynı anda arayüze dokunduğunda birleştirme çakışması çıkmaz,
- arayüz sunucudan gelen veriyle birlikte değişir.

Tek istisna `Oyun.unity`: içinde sadece bir boş nesne ve `OyunBaslatici`
betiği vardır.

---

## Oyun tasarımı

### Kaynaklar

| Kaynak | Üreten yapı | Kullanım |
|--------|-------------|----------|
| **Altın** | Altın Madeni | Savunma yapıları, surlar |
| **Tahıl** | Tarla | Asker eğitimi, ordu yapıları |
| **Demir** | Demir Ocağı | Asker geliştirme (Demirci) |
| **Kut** | Kam Çadırı | Tılsımlar, kahramanlar |
| **Gök Taşı** | — (premium) | Süre hızlandırma |

### Yapılar (32)

**Merkez:** Otağ (kağan çadırı, 15 seviye)
**Kaynak:** Altın Madeni, Tarla, Demir Ocağı, Kam Çadırı
**Depo:** Hazine, Ambar, Demir Deposu, Kut Taşı
**Ordu:** Kışla, Kara Kışla, Ordugâh, Demirci, Büyü Atölyesi
**Özel:** Kurgan (kahraman uyandırma), Boy Otağı, Gözcü Kulesi, Umay Tapınağı
**Savunma:** Okçu Kulesi, Ateş Kulesi, Mancınık, Yıldırım Kulesi, Gök Okçusu,
Tılsım Taşı, Kartal Gözü, Kurt İni
**Duvar/Tuzak:** Surlar, Kapan, Sivri Kazık, Hava Kapanı, Devrilen Taş,
Albız Tuzağı

### Askerler (18)

**Kışla:** Çeri, Okçu Kız, Gürzcü (duvar kırıcı, ×40 hasar), Börü, Tepegöz,
Alp, Kurt Binici, Baksı (şifacı), Konrul, Mancınık Arabası, Zümrüdüanka
**Kara Kışla:** Toğrul, Karakoncolos, Albastı, Şahmeran, Erlik Çömezi, Yelbegen

### Kahramanlar (4)

| Kahraman | Unvan | Yetenek |
|----------|-------|---------|
| **Oğuz Kağan** | Altı Oğullu Kağan | Bozkurt Çağrısı |
| **Asena** | Gök Kurdun Anası | Gök Uluması |
| **Korkut Ata** | Kopuzun Kamı | Kopuz Tılsımı |
| **Tomris Hatun** | Saka Kağanı | Kan Yemini |

Kahramanlar Kurgan'da uyandırılır, Kut ile yükseltilir ve canı %50'nin
altına inince yeteneği kendiliğinden tetiklenir.

### Tılsımlar (8)

Yıldırım, Şifa, Gazap, Karakış, Yarma (ak) · Çağrı, Zehir, Gölge (kara)

### Savaş

- 44×44 ızgara, saniyede 10 adım, en çok 180 saniye
- Yer askerleri A\* yerine **hedef başına paylaşılan Dijkstra uzaklık alanı**
  ile yol bulur; duvarlar yüksek maliyetlidir, bu yüzden açık varsa dolaşır,
  yoksa kırar
- 1 yıldız: %50 yıkım · 2 yıldız: Otağ düşer · 3 yıldız: %100
- Ganimet, Otağ seviyesi farkına ve yıkım oranına göre ölçeklenir
- Yıldız yiyen oyuncu 12-16 saat kalkan alır

### Boylar (klanlar)

Yirmi dört Oğuz boyunun adını taşıyan klanlar; asker bağışı, boy sohbeti,
rütbeler (Boy Başı, Vezir, Alp, Er) ve **Boy Savaşları** (23 saat hazırlık +
24 saat savaş, üye başına 2 saldırı).

### Dünya haritası

1600×1600 kare. On altı mitolojik bölge araziyi kendi karakterine büker:

| Bölge | Arazi |
|-------|-------|
| Ötüken | Çayır — dünyanın göbeği |
| Altay Dağları, Ergenekon, Demirkapı | Dağ |
| Tanrı Dağı, Ak Dağ | Karlı zirve |
| Kızıl Çöl | Çöl |
| Karanlık Orman | Orman |
| Yada Gölü, Erlik Yurdu | Bataklık |
| Uçsuz Bozkır, Kurgan Ovası | Bozkır |
| Umay Bahçesi, Yer-Su Vadisi | Çayır |
| Temir Tau | Tayga |
| Kök Deniz | Deniz |

---

## Görseller

106 sprite'ın tamamı `tools/art/` altındaki üreteçle yapılır: SVG olarak
çizilir, `sharp` ile PNG'ye dönüştürülür.

```bash
npm install
node tools/art/build.mjs             # hepsi
node tools/art/build.mjs buildings   # yalnızca binalar
ART_SCALE=4 node tools/art/build.mjs # 4x çözünürlük
```

Çıktı: `unity-client/Assets/Resources/Art/` (PNG) ve `tools/art/out/svg/`
(kaynak). Yeni bir bina eklemek için `tools/art/buildings.mjs` içine bir
fonksiyon yazıp `ALL` listesine eklemek yeterlidir.

Görsel dil: 2:1 izometrik projeksiyon, tek ışık yönü (sol üst), Türk-Altay
paleti (keçe beyazı, kilim kızılı, bozkır yeşili, altın) ve Göktürk
damgaları.

---

## Denge ayarı

Saldırı/savunma dengesi **ölçülerek** ayarlanır, tahminle değil:

```bash
php artisan game:balance
php artisan game:balance --levels=9,11,13 --runs=5 --detail
```

Araç her Otağ seviyesi için dört ordu tipini (Yaya, Hava, Kara, Kuşatma)
birkaç taktikle simüle eder; asker seviyeleri Demirci seviyesiyle sınırlanır
— yani gerçek oyuncu ilerlemesi modellenir.

```
+---------+------+---------+---------+------+----------------+--------+-------+---------------+
| Seviye  | Bina | Savunma | Demirci | Alan | En İyi Ordu    | Yıldız | Yıkım | Değerlendirme |
+---------+------+---------+---------+------+----------------+--------+-------+---------------+
| Otağ 3  |   76 |       4 | sv1     |   70 | Yaya Ordusu    |   2.67 | %83.3 | DENGELİ       |
| Otağ 5  |  137 |      12 | sv2     |  135 | Yaya Ordusu    |   1.67 | %74.8 | DENGELİ       |
| Otağ 7  |  173 |      20 | sv4     |  165 | Kuşatma Ordusu |      2 | %72.7 | DENGELİ       |
| Otağ 11 |  245 |      34 | sv8     |  260 | Hava Ordusu    |   2.33 | %88   | DENGELİ       |
+---------+------+---------+---------+------+----------------+--------+-------+---------------+
```

Dengeyi değiştirmek için iki yol var:

1. **Küresel çarpanlar** — `config/game.php` → `tuning`. Tek sayı değiştirip
   `php artisan db:seed --class=CatalogSeeder` çalıştırın.
2. **Tek tek değerler** — `database/seeders/data/{buildings,troops}.php`

Her iki durumda da `game:balance` ile ölçün.

---

## Test

```bash
php artisan test                    # 72 test, 3769 doğrulama
php artisan test --filter=BattleTest
```

Kapsam: savaş simülasyonunun determinizmi, duvar kırıcı çarpanı, hava
savunması ayrımı, kaynak üretimi ve depo sınırları, inşaat kuyruğu ve usta
sayısı, boy/rütbe yetkileri, dünya üretiminin tekrarlanabilirliği ve katalog
bütünlüğü.

Testler bellek içi SQLite ile çalışır; üretim MySQL'dir. CI her iki veritabanını
da dener (`.github/workflows/tests.yml`).

C# tarafı için yapısal denetim:

```bash
node tools/unity/check-cs.mjs
```

---

## Üretime alma

```bash
composer install --no-dev --optimize-autoloader
php artisan config:cache && php artisan route:cache
php artisan migrate --force
```

- `APP_DEBUG=false`, güçlü `APP_KEY`
- `GAME_ALLOWED_ORIGINS` ile CORS'u kendi alan adınıza daraltın
- `GAME_WORLD_SEED` sabit kalmalı
- Savaş tekrarları 30 gün sonra otomatik silinir (yalnızca `replay` alanı;
  sonuç ve ganimet kalır)

### Ölçekleme notları

Kaynak üretimi ve inşaat/eğitim tamamlanması okuma anında hesaplandığı için
oyuncu başına arka plan işi yoktur. Ağır işlem tek noktada toplanmıştır:
savaş çözümü (230 askerlik savaş ~700 ms). Yoğun sunucuda saldırı ucunu
kuyruğa almak veya ayrı bir işçiye taşımak yeterlidir.

---

## Komutlar

| Komut | İş |
|-------|-----|
| `php artisan game:demo` | Örnek oyuncular, obalar ve boylar |
| `php artisan game:balance` | Saldırı/savunma dengesini ölç |
| `php artisan game:export-unity` | Sunucu sabitlerini C#'a aktar |
| `node tools/art/build.mjs` | Görselleri üret |
| `node tools/unity/gen-meta.mjs` | Unity .meta dosyalarını üret |
| `node tools/unity/check-cs.mjs` | C# yapısal denetimi |

---

## Lisans

MIT.
