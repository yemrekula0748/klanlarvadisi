# Klanlar Vadisi — Kendi Bilgisayarınızda Çalıştırma

Bu kılavuz, oyunu masaüstünüzde sıfırdan çalıştırmanız için yazıldı.
Sunucu kendi bilgisayarınızda çalışır, Unity istemcisi ona bağlanır.
İnternete açmanız ya da bir hesap açmanız gerekmez.

**Kısa yol** — dört adım:

```bash
# 0) Dosyaları bilgisayarınıza alın
git clone https://github.com/yemrekula0748/klanlarvadisi.git ~/Desktop/klanlarvadisi
cd ~/Desktop/klanlarvadisi
git checkout claude/turkish-mythology-game-8zna5c

# 1) Kurulumu çalıştırın (macOS/Linux)
chmod +x kur.sh && ./kur.sh
#    Windows'ta:  kur.bat

# 2) Unity'de unity-client klasörünü açın, Assets/Scenes/Oyun.unity
#    sahnesini açıp Play'e basın.
```

Git kullanmak istemiyorsanız: GitHub'da depo sayfasında **Code → Download ZIP**,
arşivi masaüstüne açın ve `cd` ile o klasöre girin. ZIP'ten çıkan `kur.sh`
çalıştırma izni taşımayabilir; `chmod +x kur.sh` ya da doğrudan `bash kur.sh`.

Unity kurmak istemiyorsanız sunucuyu tek başına da deneyebilirsiniz:
[API'yi `curl` ile deneme](#apiyi-curl-ile-deneme) bölümüne geçin.

---

## İçindekiler

- [Sıra: masaüstünde ne yapacağım](#sıra-masaüstünde-ne-yapacağım)
- [1. Araçları kurma](#1-araçları-kurma)
- [2. Kurulum betiği](#2-kurulum-betiği)
- [3. Unity istemcisi](#3-unity-istemcisi)
- [API'yi curl ile deneme](#apiyi-curl-ile-deneme)
- [Sık sorunlar](#sık-sorunlar)

---

## Sıra: masaüstünde ne yapacağım

| # | Adım | Ne kadar sürer |
|---|------|----------------|
| 1 | `git clone` ya da ZIP indirip masaüstüne aç | 1–2 dakika |
| 2 | PHP + Composer kur (bir kez) | 5 dakika |
| 3 | `./kur.sh` ya da `kur.bat` çalıştır | ilk seferde 5–10 dakika |
| 4 | Unity Hub + Unity 6000.0.32f1 kur (bir kez) | 20–40 dakika (indirme) |
| 5 | `unity-client` klasörünü Unity'ye ekle, `Oyun.unity` → Play | 2 dakika |

Adım 3'ün sonunda ekranda **API adresi**, **Unity'ye yazacağınız değerler** ve
**örnek giriş bilgileri** yazar. Adım 4–5 yalnızca oyunu görmek için gerekir;
sunucu adım 3'ten sonra zaten çalışır durumdadır.

Arşivi açarken klasör yolunda **Türkçe karakter ve boşluk** olmaması işinizi
kolaylaştırır (`~/Desktop/klanlarvadisi` ya da `C:\klanlarvadisi` gibi).

---

## 1. Araçları kurma

Gereken en az sürümler:

| Araç | Sürüm | Ne için |
|------|-------|---------|
| PHP | 8.3+ | Sunucu |
| Composer | 2.x | PHP paketlerini indirir |
| Node.js | 20+ | **İsteğe bağlı** — yalnızca görselleri yeniden üretmek için |
| Unity | 6000.0.32f1 | **İsteğe bağlı** — oyunu görmek için |

Veritabanı kurmanız **gerekmez**: kurulum betiği varsayılan olarak SQLite
kullanır, o da tek bir dosyadır. MySQL isterseniz
[aşağıda](#mysql-kullanmak-isteyenler) anlatıldı.

### macOS

[Homebrew](https://brew.sh) yoksa önce onu kurun:

```bash
/bin/bash -c "$(curl -fsSL https://raw.githubusercontent.com/Homebrew/install/HEAD/install.sh)"
```

Sonra:

```bash
brew install php composer
brew install node          # isteğe bağlı
```

`brew install php` güncel PHP'yi (8.3+) gerekli eklentilerle birlikte kurar.
Denetlemek için:

```bash
php -v                     # 8.3.0'dan büyük olmalı
composer --version         # 2.x olmalı
```

### Windows

Komut istemini (ya da PowerShell'i) **Yönetici olarak** açıp:

```bat
winget install --id PHP.PHP.8.3
winget install --id Composer.Composer
winget install --id OpenJS.NodeJS.LTS    :: isteğe bağlı
```

> Paket adları zamanla değişebilir. Bulunamazsa `winget search php` ve
> `winget search composer` ile arayın. Elle kurulum da olur:
> PHP → <https://windows.php.net/download> (zip'i `C:\php` içine açıp
> `C:\php`'yi PATH'e ekleyin), Composer → <https://getcomposer.org/Composer-Setup.exe>

Kurulumdan sonra **yeni** bir komut istemi açın (PATH ancak o zaman tazelenir) ve
denetleyin:

```bat
php -v
composer --version
```

**Windows'a özel bir adım:** Windows için indirilen PHP'de eklentiler kapalı
gelir. `kur.bat` eksik olanı size söyler; açmak için:

1. `php --ini` komutuyla `php.ini` dosyasının yerini bulun.
   Yoksa aynı klasördeki `php.ini-development` dosyasını `php.ini` adıyla kopyalayın.
2. Dosyayı Not Defteri ile açın.
3. Şu satırların başındaki `;` işaretini silin:

   ```ini
   extension=curl
   extension=fileinfo
   extension=mbstring
   extension=openssl
   extension=pdo_sqlite
   extension=sqlite3
   ```

4. Kaydedin, komut istemini kapatıp yeniden açın.

### Linux (Debian / Ubuntu)

```bash
sudo apt install php8.3-cli php8.3-sqlite3 php8.3-mbstring php8.3-curl \
                 php8.3-xml php8.3-zip php8.3-gd composer
sudo apt install nodejs npm        # isteğe bağlı
```

---

## 2. Kurulum betiği

Arşivi açtığınız klasörde:

```bash
./kur.sh            # macOS / Linux
```

```bat
kur.bat             :: Windows (çift tıklayabilirsiniz de)
```

Betik sırayla şunları yapar:

1. **Araçları denetler** — PHP 8.3+, PHP eklentileri, Composer, (varsa) Node.
   Eksik bir şey varsa ne kuracağınızı söyleyip durur; sessizce patlamaz.
2. **`composer install`** — `vendor/` klasörü yoksa PHP paketlerini indirir.
3. **`.env` dosyasını hazırlar** — yoksa `.env.example`'dan kopyalar,
   `php artisan key:generate` ile uygulama anahtarını üretir.
4. **Veritabanını kurar** — varsayılan SQLite: `database/database.sqlite`
   dosyasını oluşturur ve `.env`'i ona göre ayarlar.
5. **Tabloları ve oyun verisini yükler** — `php artisan migrate`, sonra
   katalog (32 bina, 18 asker, 4 kahraman, 8 tılsım) ve dünya nesneleri
   (kutsal alanlar, mitolojik kamplar, kaynak damarları, harabeler), sonra
   `php artisan game:demo` ile örnek oyuncular, obalar ve boylar.
6. **Sunucuları başlatır** — `php artisan serve` ön planda,
   `php artisan reverb:start` arkada. **Ctrl+C** ikisini birden kapatır.

Betik **iki kez çalıştırılabilir**: var olan `.env`'i, anahtarı, veritabanını ve
oyuncuları korur; yalnızca eksik olanı tamamlar. İlk çalıştırma (paket indirme +
örnek dünya) birkaç dakika sürer, sonraki çalıştırmalar saniyeler içinde biter.

### Betiğin sonunda ne görürsünüz

```
──────────────────────────────────────────────────────────────
  KLANLAR VADİSİ — sunucu hazır
──────────────────────────────────────────────────────────────

  API adresi          http://127.0.0.1:8000/api/v1
  Durum ucu           http://127.0.0.1:8000/api/v1/durum

  Unity ayarları  (sahnedeki "Oyun" nesnesi -> OyunBaslatici)
    Sunucu Adresi     http://localhost:8000
    Reverb Portu      8080
    Reverb Anahtarı   klanlar-vadisi-anahtari

  Örnek giriş
    E-posta           alpagut@klanlarvadisi.test
    Şifre             sifre1234
    (ya da giriş ekranında "Konuk Olarak Dene")

  Sunucu çalışıyor. Durdurmak için Ctrl+C.
```

Buradaki **Reverb Anahtarı** `.env` dosyanızdaki `REVERB_APP_KEY` değerinden
okunur; Unity'ye birebir aynısını yazacaksınız.

### Seçenekler

```bash
./kur.sh --yardim
```

| Seçenek | Ne yapar |
|---------|----------|
| `--sqlite` | SQLite kullan (varsayılan, kurulum gerekmez) |
| `--mysql` | MySQL kullan |
| `--port=8001` | Sunucu portunu değiştir |
| `--host=0.0.0.0` | Ağdaki başka cihazlardan (telefon) erişilebilir yap |
| `--oyuncu=40` | Örnek dünyadaki oyuncu sayısı (varsayılan 24) |
| `--boy=6` | Örnek dünyadaki boy sayısı (varsayılan 5) |
| `--demo-yok` | Örnek oyuncu/boy oluşturma (bomboş dünya) |
| `--reverb-yok` | Reverb'i başlatma |
| `--sadece-kurulum` | Kurulumu yap, sunucuyu başlatma |

Aynı ayarlar ortam değişkeniyle de verilebilir:

```bash
KV_PORT=9000 KV_OYUNCU=40 ./kur.sh
```

```bat
set KV_PORT=9000
kur.bat
```

### MySQL kullanmak isteyenler

MySQL 8.0+ sunucusu çalışır durumdaysa:

```bash
./kur.sh --mysql
KV_DB_SIFRE='sifreniz' ./kur.sh --mysql        # şifre gerekiyorsa
```

```bat
set KV_DB_SIFRE=sifreniz
kur.bat --mysql
```

Betik veritabanı yoksa `klanlar_vadisi` adıyla kendisi oluşturur (utf8mb4) ve
`.env`'i ona göre ayarlar. Değiştirilebilen değerler:

| Değişken | Varsayılan |
|----------|-----------|
| `KV_DB_AD` | `klanlar_vadisi` |
| `KV_DB_KULLANICI` | `root` |
| `KV_DB_SIFRE` | *(boş)* |
| `KV_DB_SUNUCU` | `127.0.0.1` |
| `KV_DB_PORT` | `3306` |

Bağlanamazsa betik durur ve ne yapacağınızı söyler — veritabanınıza yarım iş
yazmaz.

> **SQLite'tan MySQL'e geçerken** tablolar sıfırdan kurulur; SQLite'taki
> obalarınız MySQL'e taşınmaz. Tersi de geçerli.

---

## 3. Unity istemcisi

### Unity'yi kurma

1. [Unity Hub](https://unity.com/download) indirip kurun.
2. Hub → **Installs** → **Install Editor** → **Archive** sekmesinden
   **6000.0.32f1** sürümünü seçin. Doğrudan bağlantı:
   `unityhub://6000.0.32f1/b2e806cf271c`
   (Tarayıcıya yazıp Enter'a basarsanız Hub açılır.)
3. Platform modülü eklemeniz gerekmez; editörde Play'e basmak yeter.

Projenin beklediği sürüm `unity-client/ProjectSettings/ProjectVersion.txt`
dosyasında yazılıdır. Daha yeni bir 6000.0.x sürümü de açar, Unity yükseltmeyi
sorar.

### Projeyi açma

1. Unity Hub → **Projects** → **Add** → **Add project from disk**
2. Arşivin içindeki **`unity-client`** klasörünü seçin
   (`klanlarvadisi` kökünü değil — bir alt klasörünü).
3. Projeye tıklayıp açın. İlk açılış paketleri derler, birkaç dakika sürer.
4. **Project** penceresinden `Assets/Scenes/Oyun.unity` sahnesine çift tıklayın.

### Sunucu adresini ayarlama

Sahnede tek bir nesne vardır: **Oyun**. Kamera, arayüz, oba ve harita
koddan kurulur.

1. **Hierarchy**'de **Oyun** nesnesini seçin.
2. **Inspector**'da `OyunBaslatici` bileşenini göreceksiniz:

| Başlık | Alan | Ne yazılacak |
|--------|------|--------------|
| Sunucu | **Sunucu Adresi** | `http://localhost:8000` — `kur.sh` çıktısındaki değer. Port değiştirdiyseniz (`--port=8001`) burayı da değiştirin. Sonuna `/api/v1` **eklemeyin**, istemci kendisi ekler. |
| Gerçek zamanlı (Reverb) | **Reverb Portu** | `8080` — `.env`'deki `REVERB_PORT` ile aynı olmalı. |
| Gerçek zamanlı (Reverb) | **Reverb Anahtarı** | `klanlar-vadisi-anahtari` — **`.env`'deki `REVERB_APP_KEY` ile birebir aynı olmalı.** Farklıysa canlı bağlantı kurulmaz (oyun yine çalışır, yoklamaya düşer). |

Reverb adresi, Sunucu Adresi'nden türetilir: ayrıca host yazmanız gerekmez.

### Oynama

**Play** tuşuna basın. Giriş ekranı açılır:

- **Konuk Olarak Dene** — hesap gerekmez, yeni bir oba kurulur.
- Ya da `kur.sh` çıktısındaki örnek giriş: `alpagut@klanlarvadisi.test` /
  `sifre1234` — kurulu bir oba, ordu ve boy ile başlarsınız.

Alt bardan **Oba / Harita / Ordu / Boy** ekranları arasında geçersiniz.

---

## API'yi curl ile deneme

Unity kurmadan sunucunun çalıştığını görmek için. Aşağıdaki çıktılar bu
kılavuz yazılırken **gerçekten çalıştırılıp** alınmıştır.

> Her istekte `Accept: application/json` başlığını gönderin. Bu başlık
> olmadan kimlik doğrulaması gereken bir uca girerseniz Laravel oturum
> girişine yönlenmeye çalışır ve 401 yerine 500 döner.

### 1) Sunucu ayakta mı?

```bash
curl -s http://localhost:8000/api/v1/durum -H 'Accept: application/json'
```

```json
{"oyun":"Klanlar Vadisi","surum":"1.0.0","sunucu":"2026-10-11T08:25:19+00:00","dunya":{"width":1600,"height":1600}}
```

### 2) Konuk girişi — belirteç al

`device` alanı **zorunlu** ve **en az 8 karakter** olmalıdır. Aynı `device`
değeriyle tekrar girince aynı oyuncuya dönersiniz (yeni bir oba açılmaz).

```bash
curl -s -X POST http://localhost:8000/api/v1/konuk \
  -H 'Content-Type: application/json' -H 'Accept: application/json' \
  -d '{"device":"masaustu-deneme-01"}'
```

```json
{
  "token": "1|XeyMx8LGhuZCI54Yodz0ZwZ4wyUslYOsn9vsAuGje1bcb4c6",
  "player": {
    "id": 25,
    "name": "Konuk404730",
    "avatar": "kurt",
    "level": 1,
    "trophies": 0,
    "league": { "key": "cakmaktas", "name": "Çakmaktaşı Boyu", "min": 0, "color": "#8d8375" },
    "gok_tasi": 500,
    "village": { "id": 25, "name": "Konuk404730 Obası", "otag_level": 1, "world": { "x": 748, "y": 783 } },
    "clan": null
  },
  "guest": true
}
```

Belirteci kabuk değişkenine alın:

```bash
BELIRTEC=$(curl -s -X POST http://localhost:8000/api/v1/konuk \
  -H 'Content-Type: application/json' -H 'Accept: application/json' \
  -d '{"device":"masaustu-deneme-01"}' \
  | php -r 'echo json_decode(stream_get_contents(STDIN), true)["token"];')
```

`device` 8 karakterden kısaysa sunucu Türkçe doğrulama hatası döner:

```bash
curl -s -X POST http://localhost:8000/api/v1/konuk \
  -H 'Content-Type: application/json' -H 'Accept: application/json' \
  -d '{"device":"test"}'
```

```json
{"message":"cihaz en az 8 karakter olmalıdır.","code":"dogrulama",
 "errors":{"device":["cihaz en az 8 karakter olmalıdır."]}}
```

### 3) Kendi bilgim

```bash
curl -s http://localhost:8000/api/v1/ben \
  -H "Authorization: Bearer $BELIRTEC" -H 'Accept: application/json'
```

```json
{"player":{"id":25,"name":"Konuk404730","avatar":"kurt","level":1,"trophies":0,
"league":{"key":"cakmaktas","name":"Çakmaktaşı Boyu","min":0,"color":"#8d8375"},
"gok_tasi":500,"village":{"id":25,"name":"Konuk404730 Obası","otag_level":1,
"world":{"x":748,"y":783}},"clan":null}}
```

### 4) Katalog — bütün oyun dengesi

Belirteç gerekmez; istemci açılışta bir kez çeker. Yanıt yaklaşık **193 KB**:

```bash
curl -s http://localhost:8000/api/v1/katalog -H 'Accept: application/json' \
  -o katalog.json -w 'HTTP %{http_code} / %{size_download} bayt\n'
```

```
HTTP 200 / 197899 bayt
```

İçinde ne var:

```bash
php -r '$d = json_decode(file_get_contents("katalog.json"), true);
printf("bina:%d asker:%d kahraman:%d tılsım:%d lig:%d biyom:%d\n",
count($d["buildings"]), count($d["troops"]), count($d["heroes"]),
count($d["spells"]), count($d["leagues"]), count($d["biomes"]));'
```

```
bina:32 asker:18 kahraman:4 tılsım:8 lig:10 biyom:11
```

İlk bina kaydı:

```json
{
  "key": "otag",
  "name": "Otağ",
  "category": "otag",
  "description": "Obanın kalbi. Kağanın çadırı yıkılırsa oba düşer.",
  "sprite": "buildings/otag",
  "max_level": 15,
  "w": 4, "h": 4
}
```

### 5) Oba durumu

```bash
curl -s http://localhost:8000/api/v1/oba \
  -H "Authorization: Bearer $BELIRTEC" -H 'Accept: application/json'
```

Yanıtın `village` bölümü (kaynaklar okuma anında hesaplanır):

```json
{
  "id": 25,
  "name": "Konuk404730 Obası",
  "otag_level": 1,
  "biome": "cayir",
  "world": { "x": 748, "y": 783 },
  "resources": { "gold": 2001, "grain": 2001, "iron": 0, "kut": 0 },
  "production": { "gold": 400, "grain": 400, "iron": 0, "kut": 0 },
  "capacity": { "gold": 2500, "grain": 2500, "iron": 1500, "kut": 1500 },
  "builders": { "busy": 0, "total": 2, "free": 2 },
  "grid": 44,
  "server_time": "2026-10-11T08:13:47+00:00"
}
```

Yanıtın tamamı şu bölümlerden oluşur: `player`, `village`, `buildings`,
`army`, `queues`, `clan_troops`, `events`.

### 6) Dünya parçası

Obanızın çevresindeki 32×32 kareyi ister:

```bash
curl -s "http://localhost:8000/api/v1/dunya/bolge?x=748&y=783" \
  -H "Authorization: Bearer $BELIRTEC" -H 'Accept: application/json'
```

Dönen parçanın ilk karesi — kendi obanız:

```json
{
  "x": 748, "y": 783,
  "type": "oba",
  "label": "Konuk404730 Obası",
  "village": { "id": 25, "name": "Konuk404730 Obası", "otag_level": 1 },
  "player": { "id": 25, "name": "Konuk404730", "trophies": 0, "protected": true, "clan": null }
}
```

Arazi (çayır, dağ, bozkır…) veritabanında tutulmaz; `GAME_WORLD_SEED`
tohumundan hem sunucuda hem Unity'de aynı biçimde üretilir. Bu uç yalnızca
üstündeki **nesneleri** (obalar, kamplar, damarlar, harabeler) döner.

Bütün uçların dökümü: [`docs/API.md`](API.md)

---

## Sık sorunlar

### `./kur.sh: Permission denied`

ZIP'ten çıkan dosyalar çalıştırma izni taşımaz. İkisinden biri yeter:

```bash
chmod +x kur.sh && ./kur.sh
# ya da
bash kur.sh
```

### “8000 portu kullanılıyor”

Başka bir program (ya da önceki bir `kur.sh`) o portu tutuyor.

```bash
./kur.sh --port=8001                      # en kolayı
lsof -nP -iTCP:8000 -sTCP:LISTEN          # macOS/Linux: kim tutuyor?
```

```bat
netstat -ano | findstr :8000              :: Windows
```

Port'u değiştirirseniz Unity'deki **Sunucu Adresi** alanını da
`http://localhost:8001` yapmayı unutmayın.

### Unity'de “Sunucuya bağlanılamadı. Adres doğru mu?”

Sırayla denetleyin:

1. `kur.sh` penceresi hâlâ açık mı? Kapatırsanız sunucu da durur.
2. Tarayıcıda <http://localhost:8000/api/v1/durum> açılıyor mu?
3. Inspector'daki **Sunucu Adresi** ile betiğin yazdığı adres aynı mı?
   Sonunda `/` ya da `/api/v1` olmamalı.
4. Oyunu telefonda/başka cihazda deniyorsanız sunucuyu
   `./kur.sh --host=0.0.0.0` ile başlatın ve Unity'ye bilgisayarın yerel IP'sini
   yazın (`http://192.168.1.x:8000`).

### CORS hatası (tarayıcıdan denerken)

Unity editöründe CORS sorun olmaz; WebGL derlemesinde ya da tarayıcıdan
JavaScript ile çağırırken olur. İzin verilen kaynaklar `.env` dosyasındaki
`GAME_ALLOWED_ORIGINS` değişkenindedir:

```dotenv
GAME_ALLOWED_ORIGINS=*                          # yerelde en rahatı
GAME_ALLOWED_ORIGINS=http://localhost:3000      # tek bir kaynak
```

Virgülle birden fazla yazabilirsiniz. Değiştirdikten sonra sunucuyu yeniden
başlatın (Ctrl+C → `./kur.sh`).

### 401 “Unauthenticated.”

Belirteç yok, yanlış ya da silinmiş. Konuk girişini tekrarlayıp yeni belirteç
alın. Unity'de bu durumda giriş ekranına dönülür.

```json
{"message":"Unauthenticated."}
```

### 500 “Route [login] not defined.”

`Accept: application/json` başlığını göndermeden kimlik doğrulaması gereken bir
uca girdiniz (örneğin adresi tarayıcıya yazdınız). Başlığı ekleyin:

```bash
curl -s http://localhost:8000/api/v1/ben -H 'Accept: application/json'
```

Unity istemcisi bu başlığı her istekte zaten gönderir.

### 419 hatası

Oyun API'si belirteçle çalışır, çerez/oturum kullanmaz; 419 (CSRF) almanız
için `/api/v1/...` dışında bir adrese POST atmış olmanız gerekir. Adresin
`/api/v1` ile başladığını denetleyin.

### “database/database.sqlite … unable to open database file”

SQLite dosyası silinmiş ya da yazma izni yok. Betiği yeniden çalıştırmak yeter:

```bash
./kur.sh --sadece-kurulum
```

Dosyayı elle de oluşturabilirsiniz:

```bash
touch database/database.sqlite && php artisan migrate --force
```

Veritabanını tamamen sıfırlamak isterseniz (bütün obalar silinir):

```bash
rm database/database.sqlite && ./kur.sh
```

### Reverb kapalıysa ne olur?

Hiçbir şey bozulmaz. Reverb yalnızca **anlık bildirim** taşır: boy sohbeti,
“obana saldırı var”, inşaat bitti gibi. Bağlantı kurulamazsa istemci sessizce
**yoklamaya** (düzenli aralıkla sunucuyu sorma) döner; oyun oynanmaya devam
eder, haberler birkaç saniye gecikir.

`storage/logs/kur-reverb.log` dosyasında şunu görmelisiniz:

```
INFO  Starting server on 0.0.0.0:8080 (localhost).
```

Reverb bağlanmıyorsa en sık nedeni **Reverb Anahtarı**nın `.env`'deki
`REVERB_APP_KEY` ile aynı olmamasıdır.

### Windows: Ctrl+C sonrası “Toplu iş işlemini sonlandır (Y/N)?”

**N** verin; `kur.bat` Reverb'i kendisi kapatır. **Y** verirseniz Reverb arkada
kalır — zararı yok, `kur.bat` bir sonraki çalışmasında onu kapatır.

### Windows: “PHP eklentileri eksik”

Yukarıdaki [Windows bölümündeki](#windows) `php.ini` adımlarını uygulayın.

### macOS: “php: command not found” (brew ile kurduğunuz halde)

Apple Silicon'da brew `/opt/homebrew/bin` kullanır. Kabuğunuz onu görmüyorsa:

```bash
echo 'eval "$(/opt/homebrew/bin/brew shellenv)"' >> ~/.zprofile
exec zsh
```

### `composer install` uzun sürüyor ya da hata veriyor

İlk kurulumda 124 paket indirilir, bağlantıya göre 2–5 dakika sürer. Hata
alırsanız ayrıntılı çıktıyla tekrar deneyin:

```bash
composer install -v
```

Çıktıda `requires ext-...` yazıyorsa o PHP eklentisi eksik demektir.

### Örnek dünya çok büyük / çok küçük

```bash
./kur.sh --oyuncu=40 --boy=6      # daha kalabalık
./kur.sh --demo-yok               # bomboş dünya, yalnız sizin obanız
```

Boy sayısı istediğinizden az çıkabilir: her boya 3–8 üye düşer, oyuncular
bitince boy kurulmaz. Daha çok boy için oyuncu sayısını da artırın.

### Betiği durdurdum, sunucu hâlâ çalışıyor mu?

Ctrl+C hem `php artisan serve`'i hem Reverb'i kapatır. Emin olmak için:

```bash
pgrep -fl "artisan serve"     # çıktı yoksa her şey kapalı
pgrep -fl "reverb:start"
```

---

## Sonra ne yapmalı

- Oyunun kuralları, dengesi ve mimarisi: [`README.md`](../README.md)
- Bütün API uçları: [`docs/API.md`](API.md)
- Testler: `php artisan test`
- Görselleri yeniden üretme (Node gerekir): `npm install && npm run gorseller`
