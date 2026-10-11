#!/usr/bin/env bash
###############################################################################
# KLANLAR VADİSİ — Yerel Kurulum (macOS / Linux)
#
# Tek komutla sunucuyu ayağa kaldırır:
#
#     ./kur.sh
#
# Yaptıkları: araç denetimi -> composer install -> .env + uygulama anahtarı ->
# veritabanı -> göçler -> katalog/dünya tohumu -> örnek dünya -> sunucu + Reverb.
#
# İki kez çalıştırılabilir: var olanı bozmaz, eksik olanı tamamlar.
#
# Seçenekler için:  ./kur.sh --yardim
###############################################################################

set -euo pipefail

# Betik nerede duruyorsa proje kökü orasıdır; nereden çağrıldığı önemli değil.
KOK="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
cd "$KOK"

###############################################################################
# Ekrana yazma
###############################################################################

if [ -t 1 ] && [ -z "${NO_COLOR:-}" ]; then
    K_KALIN=$'\033[1m'; K_KIRMIZI=$'\033[31m'; K_YESIL=$'\033[32m'
    K_SARI=$'\033[33m';  K_MAVI=$'\033[36m';   K_BITIS=$'\033[0m'
else
    K_KALIN=''; K_KIRMIZI=''; K_YESIL=''; K_SARI=''; K_MAVI=''; K_BITIS=''
fi

baslik()  { printf '\n%s== %s ==%s\n' "$K_KALIN$K_MAVI" "$1" "$K_BITIS"; }
bilgi()   { printf '   %s\n' "$1"; }
tamam()   { printf '   %s✓%s %s\n' "$K_YESIL" "$K_BITIS" "$1"; }
uyari()   { printf '   %s!%s %s\n' "$K_SARI" "$K_BITIS" "$1"; }
hata()    { printf '\n%s✗ %s%s\n' "$K_KIRMIZI$K_KALIN" "$1" "$K_BITIS" >&2; }

# Ölümcül hata: ne yapılacağını söyleyip çıkar.
dur() {
    hata "$1"
    shift
    for satir in "$@"; do
        printf '  %s\n' "$satir" >&2
    done
    printf '\n' >&2
    exit 1
}

###############################################################################
# Ayarlar — ortam değişkeni ya da bayrakla değiştirilir
###############################################################################

VT="${KV_VT:-sqlite}"              # sqlite | mysql
VT_SECILDI=0                       # --sqlite/--mysql açıkça verildi mi?
SUNUCU_HOST="${KV_HOST:-127.0.0.1}"
SUNUCU_PORT="${KV_PORT:-8000}"
OYUNCU_SAYISI="${KV_OYUNCU:-24}"
BOY_SAYISI="${KV_BOY:-5}"
DEMO="${KV_DEMO:-1}"               # 1 = örnek dünya kur
REVERB="${KV_REVERB:-1}"           # 1 = Reverb'i de başlat
BASLAT="${KV_BASLAT:-1}"           # 0 = yalnızca kurulum yap, sunucuyu başlatma

# MySQL seçilirse kullanılacak bilgiler
DB_AD="${KV_DB_AD:-klanlar_vadisi}"
DB_KULLANICI="${KV_DB_KULLANICI:-root}"
DB_SIFRE="${KV_DB_SIFRE:-}"
DB_SUNUCU="${KV_DB_SUNUCU:-127.0.0.1}"
DB_PORT="${KV_DB_PORT:-3306}"

yardim() {
    cat <<'YARDIM'
Klanlar Vadisi — yerel kurulum betiği

Kullanım:
  ./kur.sh [seçenekler]

Seçenekler:
  --sqlite              Veritabanı olarak SQLite kullan (varsayılan; kurulum gerekmez)
  --mysql               Veritabanı olarak MySQL kullan (sunucusu çalışır olmalı)
  --port=8000           Sunucu portu
  --host=127.0.0.1      Sunucunun dinleyeceği adres
  --oyuncu=24           Örnek dünyadaki oyuncu sayısı
  --boy=5               Örnek dünyadaki boy sayısı
  --demo-yok            Örnek oyuncuları/boyları oluşturma (boş dünya)
  --reverb-yok          Reverb (gerçek zamanlı) sunucusunu başlatma
  --sadece-kurulum      Kurulumu yap, sunucuları başlatma
  --yardim              Bu yazı

Ortam değişkenleriyle de aynısı yapılabilir:
  KV_VT=mysql KV_DB_SIFRE=gizli ./kur.sh
  KV_PORT=9000 ./kur.sh

MySQL bilgileri (yalnızca --mysql ile):
  KV_DB_AD (klanlar_vadisi), KV_DB_KULLANICI (root), KV_DB_SIFRE,
  KV_DB_SUNUCU (127.0.0.1), KV_DB_PORT (3306)
YARDIM
}

for arg in "$@"; do
    case "$arg" in
        --sqlite)          VT=sqlite; VT_SECILDI=1 ;;
        --mysql)           VT=mysql;  VT_SECILDI=1 ;;
        --port=*)          SUNUCU_PORT="${arg#*=}" ;;
        --host=*)          SUNUCU_HOST="${arg#*=}" ;;
        --oyuncu=*)        OYUNCU_SAYISI="${arg#*=}" ;;
        --boy=*)           BOY_SAYISI="${arg#*=}" ;;
        --demo-yok)        DEMO=0 ;;
        --reverb-yok)      REVERB=0 ;;
        --sadece-kurulum)  BASLAT=0 ;;
        --yardim|-y|-h|--help) yardim; exit 0 ;;
        *) dur "Bilinmeyen seçenek: $arg" "Seçenekleri görmek için: ./kur.sh --yardim" ;;
    esac
done

case "$VT" in
    sqlite|mysql) ;;
    *) dur "Veritabanı 'sqlite' ya da 'mysql' olmalı, verilen: $VT" ;;
esac

###############################################################################
# 1) Gerekli araçlar
###############################################################################

baslik "Gerekli araçlar denetleniyor"

if [ "$(uname -s)" = "Darwin" ]; then
    KUR_PHP='brew install php'
    KUR_COMPOSER='brew install composer'
    KUR_NODE='brew install node'
    KUR_NOT='(Homebrew yoksa: https://brew.sh)'
else
    KUR_PHP='sudo apt install php8.3-cli php8.3-sqlite3 php8.3-mbstring php8.3-bcmath php8.3-gd php8.3-zip php8.3-curl php8.3-xml'
    KUR_COMPOSER='sudo apt install composer'
    KUR_NODE='sudo apt install nodejs npm   # ya da: https://nodejs.org'
    KUR_NOT='(apt yoksa dağıtımınızın paket yöneticisini kullanın)'
fi

# --- PHP ---
if ! command -v php >/dev/null 2>&1; then
    dur "PHP bulunamadı." \
        "Sunucu PHP 8.3 veya üstü ile çalışır. Kurmak için:" \
        "" \
        "    $KUR_PHP" \
        "  $KUR_NOT"
fi

PHP_SURUM="$(php -r 'echo PHP_VERSION;')"
if ! php -r 'exit(version_compare(PHP_VERSION, "8.3.0", ">=") ? 0 : 1);'; then
    dur "PHP sürümü çok eski: $PHP_SURUM (en az 8.3 gerekli)." \
        "Yükseltmek için:" \
        "" \
        "    $KUR_PHP"
fi
tamam "PHP $PHP_SURUM"

# --- PHP eklentileri ---
# Zorunlu olanlar: bunlar olmadan Laravel açılmaz.
GEREKLI_EKLENTILER="ctype curl dom fileinfo filter mbstring openssl session tokenizer xml"
if [ "$VT" = "sqlite" ]; then
    GEREKLI_EKLENTILER="$GEREKLI_EKLENTILER pdo_sqlite sqlite3"
else
    GEREKLI_EKLENTILER="$GEREKLI_EKLENTILER pdo_mysql"
fi

# İsteğe bağlı: oyun bunlar olmadan da çalışır, yalnızca yardımcı araçlar kullanır.
EK_EKLENTILER="gd zip"

MEVCUT_EKLENTILER="$(php -m)"
eklenti_var() {
    printf '%s\n' "$MEVCUT_EKLENTILER" | grep -qix "$1"
}

EKSIK_EKLENTI=""
for eklenti in $GEREKLI_EKLENTILER; do
    eklenti_var "$eklenti" || EKSIK_EKLENTI="$EKSIK_EKLENTI $eklenti"
done

if [ -n "$EKSIK_EKLENTI" ]; then
    dur "PHP eklentileri eksik:$EKSIK_EKLENTI" \
        "Bunlar olmadan sunucu açılmaz. Kurmak için:" \
        "" \
        "    $KUR_PHP" \
        "" \
        "  macOS'ta brew ile kurulan PHP bunların hepsini içerir." \
        "  Linux'ta eksik olan her eklenti için php8.3-<eklenti> paketini kurun."
fi
tamam "PHP eklentileri tam"

EKSIK_EK=""
for eklenti in $EK_EKLENTILER; do
    eklenti_var "$eklenti" || EKSIK_EK="$EKSIK_EK $eklenti"
done
if [ -n "$EKSIK_EK" ]; then
    uyari "İsteğe bağlı eklenti yok:$EKSIK_EK"
    bilgi "  Oyun çalışır; bunlar yalnızca görsel/arşiv araçları için gerekir."
fi

# --- Composer ---
COMPOSER_KOMUTU=""
if command -v composer >/dev/null 2>&1; then
    COMPOSER_KOMUTU="composer"
elif [ -f "composer.phar" ]; then
    COMPOSER_KOMUTU="php composer.phar"
else
    dur "Composer bulunamadı." \
        "PHP paketlerini indirmek için Composer 2.x gerekli. Kurmak için:" \
        "" \
        "    $KUR_COMPOSER" \
        "" \
        "  ya da tek dosya olarak proje klasörüne indirin:" \
        "    php -r \"copy('https://getcomposer.org/installer','ci.php');\" && php ci.php && rm ci.php"
fi
tamam "Composer var ($COMPOSER_KOMUTU)"

# --- Node (isteğe bağlı) ---
# Node yalnızca görselleri yeniden üretmek için gerekir (tools/art).
# Hazır görseller depoda olduğu için sunucu ve oyun Node olmadan da çalışır.
NODE_DURUM="yok"
if command -v node >/dev/null 2>&1; then
    NODE_SURUM="$(node -v | tr -d 'v')"
    NODE_ANA="${NODE_SURUM%%.*}"
    if [ "${NODE_ANA:-0}" -ge 20 ] 2>/dev/null; then
        NODE_DURUM="tamam"
        tamam "Node $NODE_SURUM"
    else
        NODE_DURUM="eski"
        uyari "Node $NODE_SURUM eski (20+ önerilir). Oyun yine çalışır; yalnızca"
        bilgi "  görselleri yeniden üretmek (tools/art) için gerekir: $KUR_NODE"
    fi
else
    uyari "Node bulunamadı. Oyun yine çalışır — hazır görseller depoda."
    bilgi "  Görselleri kendiniz üretmek isterseniz: $KUR_NODE"
fi

###############################################################################
# .env okuma / yazma yardımcıları (PHP ile — sed'in platform farkları yok)
###############################################################################

env_oku() {
    php -r '
        $yol = $argv[1];
        $anahtar = $argv[2];
        if (! is_file($yol)) { exit(0); }
        foreach (file($yol, FILE_IGNORE_NEW_LINES) as $satir) {
            if (! preg_match("/^\s*".preg_quote($anahtar, "/")."\s*=(.*)$/", $satir, $e)) {
                continue;
            }
            $deger = trim($e[1]);
            if (strlen($deger) > 1 && $deger[0] === $deger[strlen($deger) - 1]
                && ($deger[0] === "\"" || $deger[0] === "'"'"'")) {
                $deger = substr($deger, 1, -1);
            }
            echo $deger;
            exit(0);
        }
    ' ".env" "$1"
}

env_yaz() {
    php -r '
        $yol = $argv[1];
        $anahtar = $argv[2];
        $deger = $argv[3];
        $icerik = is_file($yol) ? file_get_contents($yol) : "";
        $kalip = "/^\s*".preg_quote($anahtar, "/")."\s*=.*$/m";
        $satir = $anahtar."=".$deger;
        if (preg_match($kalip, $icerik)) {
            $icerik = preg_replace_callback($kalip, fn () => $satir, $icerik, 1);
        } else {
            $icerik = rtrim($icerik, "\n")."\n".$satir."\n";
        }
        file_put_contents($yol, $icerik);
    ' ".env" "$1" "$2"
}

# Port dinleniyor mu? (lsof/ss gerekmez, PHP yeter)
port_dolu() {
    php -r '
        $s = @stream_socket_client("tcp://".$argv[1].":".$argv[2], $e, $m, 0.5);
        if ($s) { fclose($s); exit(0); }
        exit(1);
    ' "$1" "$2"
}

# Portları en baştan denetle. Sonradan bakmak, dolu port yüzünden
# duracak bir çalıştırmanın önce göç/tohumlama yapmasına yol açıyordu.
if [ "$BASLAT" = "1" ]; then
    if port_dolu "$SUNUCU_HOST" "$SUNUCU_PORT"; then
        dur "$SUNUCU_PORT portu kullanılıyor." \
            "Hiçbir şeye dokunmadan çıkıldı. Başka bir port seçin:" \
            "" \
            "    ./kur.sh --port=8001" \
            "" \
            "  ya da o portu tutan süreci bulun:" \
            "    lsof -nP -iTCP:$SUNUCU_PORT -sTCP:LISTEN"
    fi
fi

###############################################################################
# 2) PHP paketleri
###############################################################################

baslik "PHP paketleri"

if [ -f "vendor/autoload.php" ]; then
    tamam "vendor/ hazır (atlandı)"
else
    bilgi "composer install çalışıyor — ilk seferde birkaç dakika sürebilir..."
    if ! $COMPOSER_KOMUTU install --no-interaction --prefer-dist; then
        dur "composer install başarısız oldu." \
            "Sık nedenler:" \
            "  • İnternet bağlantısı yok ya da vekil sunucu engelliyor." \
            "  • PHP eklentisi eksik — yukarıdaki çıktıda 'requires ext-...' arayın." \
            "" \
            "  Yeniden denemek için:  $COMPOSER_KOMUTU install -v"
    fi
    tamam "PHP paketleri kuruldu"
fi

###############################################################################
# 3) .env ve uygulama anahtarı
###############################################################################

baslik "Yapılandırma (.env)"

if [ -f ".env" ]; then
    tamam ".env var (korundu)"

    # Bayrak verilmediyse .env'deki seçimi sürdür; aksi halde bir kez
    # --mysql ile kuran kullanıcı, sonraki sade çalıştırmada sessizce
    # SQLite'a döndürülüyor ve obasını kaybetmiş gibi oluyordu.
    if [ "$VT_SECILDI" = "0" ]; then
        MEVCUT_VT="$(env_oku DB_CONNECTION)"
        if [ -n "$MEVCUT_VT" ] && [ "$MEVCUT_VT" != "$VT" ]; then
            VT="$MEVCUT_VT"
            tamam "Veritabanı .env'den alındı: $VT (değiştirmek için --sqlite / --mysql)"
        fi
    fi
else
    [ -f ".env.example" ] || dur ".env.example bulunamadı — arşiv eksik açılmış olabilir."
    cp ".env.example" ".env"
    tamam ".env, .env.example'dan oluşturuldu"
fi

if [ -z "$(env_oku APP_KEY)" ]; then
    php artisan key:generate --force --quiet
    tamam "Uygulama anahtarı üretildi (APP_KEY)"
else
    tamam "Uygulama anahtarı var (korundu)"
fi

###############################################################################
# 4) Veritabanı
###############################################################################

baslik "Veritabanı: $VT"

if [ "$VT" = "sqlite" ]; then
    # SQLite: sunucu kurmak gerekmez, tek dosya yeter.
    SQLITE_YOLU="database/database.sqlite"
    env_yaz DB_CONNECTION "sqlite"
    env_yaz DB_DATABASE   "$SQLITE_YOLU"

    if [ -f "$SQLITE_YOLU" ]; then
        tamam "$SQLITE_YOLU var (korundu)"
    else
        mkdir -p "$(dirname "$SQLITE_YOLU")"
        : > "$SQLITE_YOLU"
        tamam "$SQLITE_YOLU oluşturuldu"
    fi
else
    # MySQL: sunucuya ulaşabiliyor muyuz? Veritabanı yoksa oluştur.
    if ! php -r '
        try {
            new PDO("mysql:host=".$argv[1].";port=".$argv[2], $argv[3], $argv[4],
                [PDO::ATTR_TIMEOUT => 5]);
        } catch (Throwable $e) {
            fwrite(STDERR, $e->getMessage()."\n");
            exit(1);
        }
    ' "$DB_SUNUCU" "$DB_PORT" "$DB_KULLANICI" "$DB_SIFRE"; then
        dur "MySQL sunucusuna bağlanılamadı ($DB_SUNUCU:$DB_PORT, kullanıcı: $DB_KULLANICI)." \
            "Seçenekleriniz:" \
            "  1) MySQL'i başlatın:   macOS: brew services start mysql" \
            "                         Linux: sudo systemctl start mysql" \
            "  2) Şifreyi verin:      KV_DB_SIFRE='sifreniz' ./kur.sh --mysql" \
            "  3) MySQL'den vazgeçin: ./kur.sh            (SQLite ile hiçbir kurulum gerekmez)"
    fi

    php -r '
        $pdo = new PDO("mysql:host=".$argv[1].";port=".$argv[2], $argv[3], $argv[4]);
        $ad = preg_replace("/[^A-Za-z0-9_]/", "", $argv[5]);
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `".$ad."` ".
                   "CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    ' "$DB_SUNUCU" "$DB_PORT" "$DB_KULLANICI" "$DB_SIFRE" "$DB_AD"

    env_yaz DB_CONNECTION "mysql"
    env_yaz DB_HOST       "$DB_SUNUCU"
    env_yaz DB_PORT       "$DB_PORT"
    env_yaz DB_DATABASE   "$DB_AD"
    env_yaz DB_USERNAME   "$DB_KULLANICI"
    env_yaz DB_PASSWORD   "$DB_SIFRE"
    tamam "MySQL hazır: $DB_AD @ $DB_SUNUCU:$DB_PORT"
fi

# Laravel'in yazması gereken klasörler (arşivden boş gelebilir)
mkdir -p storage/framework/{cache/data,sessions,views} storage/logs bootstrap/cache
php artisan config:clear --quiet

###############################################################################
# 5) Göçler, katalog ve örnek dünya
###############################################################################

baslik "Tablolar ve oyun verisi"

php artisan migrate --force --quiet
tamam "Göçler çalıştı (tablolar hazır)"

# CatalogSeeder: binalar/askerler/kahramanlar/tılsımlar + seviye tabloları.
# WorldSeeder:   kutsal alanlar, mitolojik kamplar, kaynak damarları, harabeler.
# İkisi de tekrar çalıştırılabilir: var olanı günceller, çoğaltmaz.
bilgi "Katalog ve dünya tohumlanıyor (ilk seferde ~1 dakika)..."
php artisan db:seed --force --quiet
tamam "Katalog + dünya nesneleri hazır"

if [ "$DEMO" = "1" ]; then
    bilgi "Örnek dünya kuruluyor: $OYUNCU_SAYISI oyuncu, $BOY_SAYISI boy..."
    php artisan game:demo --players="$OYUNCU_SAYISI" --clans="$BOY_SAYISI"
else
    uyari "Örnek dünya atlandı (--demo-yok). Oyuna konuk girişiyle başlayabilirsiniz."
fi

###############################################################################
# 6) Özet bilgiler
###############################################################################

API_ADRESI="http://${SUNUCU_HOST}:${SUNUCU_PORT}"
AG_ADRESI=""
if [ "$SUNUCU_HOST" = "127.0.0.1" ] || [ "$SUNUCU_HOST" = "0.0.0.0" ]; then
    # Unity ve tarayıcı için "localhost" daha okunur ve aynı yere gider.
    UNITY_ADRESI="http://localhost:${SUNUCU_PORT}"
else
    UNITY_ADRESI="$API_ADRESI"
fi

# 0.0.0.0 dinlemek "telefondan da oynayayım" demektir; o cihazda localhost
# yanlış adrestir, bu yüzden bilgisayarın yerel IP'sini de yazıyoruz.
if [ "$SUNUCU_HOST" = "0.0.0.0" ]; then
    YEREL_IP="$( (php -r '
        $s = @stream_socket_client("udp://8.8.8.8:53", $e, $m, 0.3);
        if ($s) { $n = stream_socket_get_name($s, false); fclose($s);
                  echo explode(":", $n)[0]; }
    ' 2>/dev/null) || true )"
    [ -n "$YEREL_IP" ] && AG_ADRESI="http://${YEREL_IP}:${SUNUCU_PORT}"
fi

REVERB_ANAHTARI="$(env_oku REVERB_APP_KEY)"
REVERB_PORTU="$(env_oku REVERB_PORT)"
: "${REVERB_ANAHTARI:=klanlar-vadisi-anahtari}"
: "${REVERB_PORTU:=8080}"

# Demo giriş bilgisi: e-postayı veritabanından, şifreyi DemoSeeder'dan oku.
DEMO_EPOSTA=""
DEMO_SIFRE=""
if [ "$DEMO" = "1" ]; then
    # `set -euo pipefail` altında bu ikamelerin başarısızlığı betiği anında
    # öldürür ve 2>/dev/null sebebi yutardı: kullanıcı "kurulum bitti"yi
    # görüp ardından sessiz bir çıkış 255 alırdı. İkisi de hata yutmaz.
    DEMO_EPOSTA="$( (php artisan tinker --execute="echo App\Models\User::where('email','like','%@klanlarvadisi.test')->orderBy('id')->value('email');" 2>/dev/null || true) | tr -d '\r\n' )"
    DEMO_SIFRE="$( (grep -oE "'password'[[:space:]]*=>[[:space:]]*'[^']+'" database/seeders/DemoSeeder.php || true) | head -1 | sed -E "s/.*'([^']+)'\$/\1/" )"
fi

ozet() {
    CIZGI='──────────────────────────────────────────────────────────────'
    printf '\n%s%s%s\n'   "$K_KALIN$K_YESIL" "$CIZGI" "$K_BITIS"
    printf '%s  KLANLAR VADİSİ — sunucu hazır%s\n' "$K_KALIN$K_YESIL" "$K_BITIS"
    printf '%s%s%s\n'     "$K_KALIN$K_YESIL" "$CIZGI" "$K_BITIS"
    printf '\n'
    printf '  %sTarayıcıda%s          %s\n'           "$K_KALIN" "$K_BITIS" "$UNITY_ADRESI"
    printf '  %sAPI adresi%s          %s/api/v1\n'     "$K_KALIN" "$K_BITIS" "$UNITY_ADRESI"
    printf '  %sDurum ucu%s           %s/api/v1/durum\n' "$K_KALIN" "$K_BITIS" "$UNITY_ADRESI"
    printf '\n'
    printf '  %sUnity ayarları%s  (sahnedeki "Oyun" nesnesi -> OyunBaslatici)\n' "$K_KALIN" "$K_BITIS"
    printf '    Sunucu Adresi     %s\n' "$UNITY_ADRESI"
    [ -n "$AG_ADRESI" ] && \
        printf '                      %s  (aynı ağdaki telefon/tablet için)\n' "$AG_ADRESI"
    printf '    Reverb Portu      %s\n' "$REVERB_PORTU"
    printf '    Reverb Anahtarı   %s\n' "$REVERB_ANAHTARI"
    printf '\n'
    printf '  Unity kurulumu ve adım adım anlatım:  docs/LOKAL-KURULUM.md\n'
    printf '  Unity kurmadan bakmak isterseniz:     %s\n' "$UNITY_ADRESI"
    printf '\n'
    if [ -n "$DEMO_EPOSTA" ] && [ -n "$DEMO_SIFRE" ]; then
        printf '  %sÖrnek giriş%s\n' "$K_KALIN" "$K_BITIS"
        printf '    E-posta           %s\n' "$DEMO_EPOSTA"
        printf '    Şifre             %s\n' "$DEMO_SIFRE"
        printf '    (ya da giriş ekranında "Konuk Olarak Dene")\n'
    else
        printf '  %sGiriş%s               giriş ekranında "Konuk Olarak Dene"\n' "$K_KALIN" "$K_BITIS"
    fi
    printf '\n'
}

if [ "$BASLAT" != "1" ]; then
    ozet
    bilgi "Sunucuyu başlatmak için:  php artisan serve --port=$SUNUCU_PORT"
    bilgi "Gerçek zamanlı için:      php artisan reverb:start"
    printf '\n'
    exit 0
fi

###############################################################################
# 7) Sunucular — kapanışta ikisi de temizlenir
###############################################################################

# Başlatılan süreçlerin pid'leri — boşlukla ayrılmış liste tutuyoruz ki
# macOS'un eski bash 3.2'sinde de (set -u ile boş dizi sorunu) çalışsın.
SURECLER=""

temizle() {
    trap - EXIT INT TERM
    if [ -n "$SURECLER" ]; then
        printf '\n%sSunucular kapatılıyor...%s\n' "$K_SARI" "$K_BITIS"
        for pid in $SURECLER; do
            kill "$pid" 2>/dev/null || true
        done
        for pid in $SURECLER; do
            wait "$pid" 2>/dev/null || true
        done
    fi
    printf 'Görüşmek üzere.\n'
}
trap temizle EXIT INT TERM

baslik "Sunucular başlatılıyor"

# (Baştaki denetimden bu yana başka bir süreç portu kapmış olabilir.)
if port_dolu "$SUNUCU_HOST" "$SUNUCU_PORT"; then
    dur "$SUNUCU_PORT portu bu arada kullanılmaya başladı." \
        "Başka bir port seçin:" \
        "" \
        "    ./kur.sh --port=8001" \
        "" \
        "  ya da o portu tutan süreci bulun:" \
        "    lsof -nP -iTCP:$SUNUCU_PORT -sTCP:LISTEN"
fi

REVERB_GUNLUGU="storage/logs/kur-reverb.log"
if [ "$REVERB" = "1" ]; then
    if port_dolu "127.0.0.1" "$REVERB_PORTU"; then
        uyari "$REVERB_PORTU portu dolu — Reverb başlatılmadı."
        bilgi "  Oyun çalışır: canlı bağlantı yoksa istemci yoklamaya düşer."
    else
        php artisan reverb:start --host=0.0.0.0 --port="$REVERB_PORTU" \
            >>"$REVERB_GUNLUGU" 2>&1 &
        SURECLER="$SURECLER $!"
        tamam "Reverb çalışıyor (port $REVERB_PORTU, günlük: $REVERB_GUNLUGU)"
    fi
else
    uyari "Reverb atlandı (--reverb-yok). Oyun yoklamayla çalışmaya devam eder."
fi

ozet

printf '  %sSunucu çalışıyor. Durdurmak için Ctrl+C.%s\n\n' "$K_KALIN" "$K_BITIS"

# Sunucu arka planda başlatılıp wait ile beklenir; çıktı yine
# terminale akar, böylece trap Ctrl+C'de Reverb'i de kapatabilir.
php artisan serve --host="$SUNUCU_HOST" --port="$SUNUCU_PORT" &
SUNUCU_PID="$!"
SURECLER="$SURECLER $SUNUCU_PID"
wait "$SUNUCU_PID" || true
