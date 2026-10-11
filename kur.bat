@echo off
rem ##########################################################################
rem  KLANLAR VADISI - Yerel Kurulum (Windows)
rem
rem  Tek komutla sunucuyu ayaga kaldirir. Proje klasorunde cift tiklayin
rem  ya da komut isteminde:
rem
rem      kur.bat
rem
rem  Yaptiklari: arac denetimi, composer install, .env + uygulama
rem  anahtari, veritabani, gocler, katalog/dunya tohumu, ornek
rem  dunya, sunucu + Reverb. Iki kez calistirilabilir: var olani bozmaz,
rem  eksik olani tamamlar.
rem
rem  Secenekler:  kur.bat --yardim
rem
rem  NOT: Bu dosyadaki yazilar Turkce ama sapkasiz/noktasizdir (i, s, g...).
rem  Sebep: komut istemi (cmd.exe) kod sayfasina gore okur; ASCII kalinca
rem  her Windows'ta dogru gorunur. Turkce harfli ayrintili kilavuz:
rem  docs\LOKAL-KURULUM.md
rem ##########################################################################

rem PHP'nin Turkce ciktisi (artisan iletileri) dogru gorunsun diye UTF-8.
chcp 65001 >nul 2>&1
setlocal enabledelayedexpansion

rem Betik nerede duruyorsa proje koku orasidir; nereden cagrildigi onemsiz.
cd /d "%~dp0"

rem --- Ayarlar (ortam degiskeniyle de verilebilir) ---------------------------
if not defined KV_VT           set "KV_VT=sqlite"
if not defined KV_HOST         set "KV_HOST=127.0.0.1"
if not defined KV_PORT         set "KV_PORT=8000"
if not defined KV_OYUNCU       set "KV_OYUNCU=24"
if not defined KV_BOY          set "KV_BOY=5"
if not defined KV_DEMO         set "KV_DEMO=1"
if not defined KV_REVERB       set "KV_REVERB=1"
if not defined KV_BASLAT       set "KV_BASLAT=1"
if not defined KV_DB_AD        set "KV_DB_AD=klanlar_vadisi"
if not defined KV_DB_KULLANICI set "KV_DB_KULLANICI=root"
if not defined KV_DB_SIFRE     set "KV_DB_SIFRE="
if not defined KV_DB_SUNUCU    set "KV_DB_SUNUCU=127.0.0.1"
if not defined KV_DB_PORT      set "KV_DB_PORT=3306"

set "GECICI=%TEMP%\klanlar-vadisi"
if not exist "%GECICI%" mkdir "%GECICI%" >nul 2>&1

rem --- Secenekleri oku ------------------------------------------------------
rem Dikkat: cmd'de "if kosul komut1 & komut2" yazilirsa komut2 kosuldan
rem bagimsiz calisir. Bu yuzden her sey tek satirlik parantez icinde.
:AYRISTIR
if "%~1"=="" goto AYRISTIR_BITTI
set "ARG=%~1"
if /i "!ARG!"=="--sqlite"         ( set "KV_VT=sqlite"  & shift & goto AYRISTIR )
if /i "!ARG!"=="/sqlite"          ( set "KV_VT=sqlite"  & shift & goto AYRISTIR )
if /i "!ARG!"=="--mysql"          ( set "KV_VT=mysql"   & shift & goto AYRISTIR )
if /i "!ARG!"=="/mysql"           ( set "KV_VT=mysql"   & shift & goto AYRISTIR )
if /i "!ARG!"=="--demo-yok"       ( set "KV_DEMO=0"     & shift & goto AYRISTIR )
if /i "!ARG!"=="--reverb-yok"     ( set "KV_REVERB=0"   & shift & goto AYRISTIR )
if /i "!ARG!"=="--sadece-kurulum" ( set "KV_BASLAT=0"   & shift & goto AYRISTIR )
if /i "!ARG!"=="--yardim" goto YARDIM
if /i "!ARG!"=="/?"       goto YARDIM
if /i "!ARG!"=="-h"       goto YARDIM
if /i "!ARG:~0,7!"=="--port="   ( set "KV_PORT=!ARG:~7!"   & shift & goto AYRISTIR )
if /i "!ARG:~0,7!"=="--host="   ( set "KV_HOST=!ARG:~7!"   & shift & goto AYRISTIR )
if /i "!ARG:~0,9!"=="--oyuncu=" ( set "KV_OYUNCU=!ARG:~9!" & shift & goto AYRISTIR )
if /i "!ARG:~0,6!"=="--boy="    ( set "KV_BOY=!ARG:~6!"    & shift & goto AYRISTIR )
echo.
echo  [X] Bilinmeyen secenek: !ARG!
echo      Secenekleri gormek icin: kur.bat --yardim
echo.
exit /b 1
:AYRISTIR_BITTI

if /i "%KV_VT%"=="sqlite" goto VT_SECILDI
if /i "%KV_VT%"=="mysql"  goto VT_SECILDI
echo.
echo  [X] Veritabani 'sqlite' ya da 'mysql' olmali, verilen: %KV_VT%
echo.
exit /b 1
:VT_SECILDI

rem ##########################################################################
rem  1) Gerekli araclar
rem ##########################################################################

echo.
echo == Gerekli araclar denetleniyor ==

where php >nul 2>&1
if errorlevel 1 goto YOK_PHP

php -r "exit(version_compare(PHP_VERSION, '8.3.0', 'lt') ? 1 : 0);"
if errorlevel 1 goto ESKI_PHP

for /f "delims=" %%v in ('php -r "echo PHP_VERSION;"') do set "PHP_SURUM=%%v"
echo    [+] PHP !PHP_SURUM!

rem --- PHP eklentileri: zorunlu olanlar ---
php -m > "%GECICI%\eklentiler.txt" 2>nul
set "EKSIK="
for %%e in (ctype curl dom fileinfo filter mbstring openssl session tokenizer xml) do call :EKLENTI_DENETLE %%e
if /i "%KV_VT%"=="sqlite" call :EKLENTI_DENETLE pdo_sqlite
if /i "%KV_VT%"=="sqlite" call :EKLENTI_DENETLE sqlite3
if /i "%KV_VT%"=="mysql"  call :EKLENTI_DENETLE pdo_mysql
if defined EKSIK goto YOK_EKLENTI
echo    [+] PHP eklentileri tam

rem --- Composer ---
set "COMPOSER_KOMUTU="
where composer >nul 2>&1
if not errorlevel 1 set "COMPOSER_KOMUTU=composer"
if not defined COMPOSER_KOMUTU if exist "composer.phar" set "COMPOSER_KOMUTU=php composer.phar"
if not defined COMPOSER_KOMUTU goto YOK_COMPOSER
echo    [+] Composer var (!COMPOSER_KOMUTU!)

rem --- Node: istege bagli ---
rem Node yalnizca gorselleri yeniden uretmek (tools\art) icin gerekir.
rem Hazir gorseller depoda oldugu icin sunucu ve oyun Node olmadan calisir.
where node >nul 2>&1
if errorlevel 1 goto NODE_YOK
for /f "delims=" %%v in ('node -v') do set "NODE_SURUM=%%v"
echo    [+] Node !NODE_SURUM!
goto NODE_BITTI
:NODE_YOK
echo    [!] Node bulunamadi. Oyun yine calisir - hazir gorseller depoda.
echo        Gorselleri kendiniz uretmek isterseniz:
echo            winget install --id OpenJS.NodeJS.LTS
:NODE_BITTI

rem ##########################################################################
rem  2) PHP paketleri
rem ##########################################################################

echo.
echo == PHP paketleri ==

if exist "vendor\autoload.php" goto VENDOR_HAZIR
echo    composer install calisiyor - ilk seferde birkac dakika surebilir...
%COMPOSER_KOMUTU% install --no-interaction --prefer-dist
if errorlevel 1 goto COMPOSER_HATASI
echo    [+] PHP paketleri kuruldu
goto VENDOR_BITTI
:VENDOR_HAZIR
echo    [+] vendor\ hazir (atlandi)
:VENDOR_BITTI

rem ##########################################################################
rem  3) .env ve uygulama anahtari
rem ##########################################################################

echo.
echo == Yapilandirma (.env) ==

if exist ".env" goto ENV_VAR
if not exist ".env.example" goto YOK_ORNEK_ENV
copy /y ".env.example" ".env" >nul
echo    [+] .env, .env.example'dan olusturuldu
goto ENV_BITTI
:ENV_VAR
echo    [+] .env var (korundu)
:ENV_BITTI

findstr /b /r "APP_KEY=." ".env" >nul 2>&1
if not errorlevel 1 goto ANAHTAR_VAR
php artisan key:generate --force --quiet
if errorlevel 1 goto ANAHTAR_HATASI
echo    [+] Uygulama anahtari uretildi (APP_KEY)
goto ANAHTAR_BITTI
:ANAHTAR_VAR
echo    [+] Uygulama anahtari var (korundu)
:ANAHTAR_BITTI

rem ##########################################################################
rem  4) Veritabani
rem ##########################################################################

echo.
echo == Veritabani: %KV_VT% ==

if /i "%KV_VT%"=="mysql" goto VT_MYSQL

rem --- SQLite: sunucu kurmak gerekmez, tek dosya yeter ---
call :ENV_YAZ DB_CONNECTION "sqlite"
call :ENV_YAZ DB_DATABASE "database/database.sqlite"
if exist "database\database.sqlite" goto SQLITE_VAR
type nul > "database\database.sqlite"
echo    [+] database\database.sqlite olusturuldu
goto VT_BITTI
:SQLITE_VAR
echo    [+] database\database.sqlite var (korundu)
goto VT_BITTI

:VT_MYSQL
php -r "try { new PDO('mysql:host='.$argv[1].';port='.$argv[2], $argv[3], $argv[4], [PDO::ATTR_TIMEOUT => 5]); } catch (Throwable $e) { fwrite(STDERR, $e->getMessage().PHP_EOL); exit(1); }" "%KV_DB_SUNUCU%" "%KV_DB_PORT%" "%KV_DB_KULLANICI%" "%KV_DB_SIFRE%"
if errorlevel 1 goto MYSQL_YOK
php -r "$pdo = new PDO('mysql:host='.$argv[1].';port='.$argv[2], $argv[3], $argv[4]); $ad = preg_replace('/[^A-Za-z0-9_]/', '', $argv[5]); $pdo->exec('CREATE DATABASE IF NOT EXISTS `'.$ad.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');" "%KV_DB_SUNUCU%" "%KV_DB_PORT%" "%KV_DB_KULLANICI%" "%KV_DB_SIFRE%" "%KV_DB_AD%"
if errorlevel 1 goto MYSQL_YOK
call :ENV_YAZ DB_CONNECTION "mysql"
call :ENV_YAZ DB_HOST "%KV_DB_SUNUCU%"
call :ENV_YAZ DB_PORT "%KV_DB_PORT%"
call :ENV_YAZ DB_DATABASE "%KV_DB_AD%"
call :ENV_YAZ DB_USERNAME "%KV_DB_KULLANICI%"
call :ENV_YAZ DB_PASSWORD "%KV_DB_SIFRE%"
echo    [+] MySQL hazir: %KV_DB_AD% @ %KV_DB_SUNUCU%:%KV_DB_PORT%
:VT_BITTI

rem Laravel'in yazmasi gereken klasorler (arsivden bos gelebilir)
if not exist "storage\framework\cache\data" mkdir "storage\framework\cache\data" >nul 2>&1
if not exist "storage\framework\sessions"   mkdir "storage\framework\sessions" >nul 2>&1
if not exist "storage\framework\views"      mkdir "storage\framework\views" >nul 2>&1
if not exist "storage\logs"                 mkdir "storage\logs" >nul 2>&1
if not exist "bootstrap\cache"              mkdir "bootstrap\cache" >nul 2>&1
php artisan config:clear --quiet

rem ##########################################################################
rem  5) Gocler, katalog ve ornek dunya
rem ##########################################################################

echo.
echo == Tablolar ve oyun verisi ==

php artisan migrate --force --quiet
if errorlevel 1 goto GOC_HATASI
echo    [+] Gocler calisti (tablolar hazir)

rem CatalogSeeder: binalar/askerler/kahramanlar/tilsimlar + seviye tablolari.
rem WorldSeeder:   kutsal alanlar, mitolojik kamplar, kaynak damarlari, harabeler.
rem Ikisi de tekrar calistirilabilir: var olani gunceller, cogaltmaz.
echo    Katalog ve dunya tohumlaniyor (ilk seferde ~1 dakika)...
php artisan db:seed --force --quiet
if errorlevel 1 goto TOHUM_HATASI
echo    [+] Katalog + dunya nesneleri hazir

if "%KV_DEMO%"=="0" goto DEMO_YOK
echo    Ornek dunya kuruluyor: %KV_OYUNCU% oyuncu, %KV_BOY% boy...
php artisan game:demo --players=%KV_OYUNCU% --clans=%KV_BOY%
if errorlevel 1 goto DEMO_HATASI
goto DEMO_BITTI
:DEMO_YOK
echo    [!] Ornek dunya atlandi. Oyuna konuk girisiyle baslayabilirsiniz.
:DEMO_BITTI

rem ##########################################################################
rem  6) Ozet icin gereken degerler
rem ##########################################################################

set "API_ADRESI=http://%KV_HOST%:%KV_PORT%"
set "UNITY_ADRESI=%API_ADRESI%"
if "%KV_HOST%"=="127.0.0.1" set "UNITY_ADRESI=http://localhost:%KV_PORT%"
if "%KV_HOST%"=="0.0.0.0"   set "UNITY_ADRESI=http://localhost:%KV_PORT%"

rem Reverb bilgileri .env'den okunur: Unity'deki alanlar bunlarla ayni olmali.
set "REVERB_ANAHTARI=klanlar-vadisi-anahtari"
set "REVERB_PORTU=8080"
for /f "usebackq tokens=1,* delims==" %%a in (`findstr /b /c:"REVERB_APP_KEY=" ".env"`) do set "REVERB_ANAHTARI=%%b"
for /f "usebackq tokens=1,* delims==" %%a in (`findstr /b /c:"REVERB_PORT=" ".env"`) do set "REVERB_PORTU=%%b"
set REVERB_ANAHTARI=%REVERB_ANAHTARI:"=%
set REVERB_PORTU=%REVERB_PORTU:"=%

rem Ornek giris: e-postayi veritabanindan, sifreyi DemoSeeder'dan oku.
set "DEMO_EPOSTA="
set "DEMO_SIFRE="
if "%KV_DEMO%"=="0" goto OZET_DEGERLERI_BITTI
php artisan tinker --execute="echo App\Models\User::where('email','like','%%@klanlarvadisi.test')->orderBy('id')->value('email');" > "%GECICI%\eposta.txt" 2>nul
if exist "%GECICI%\eposta.txt" set /p DEMO_EPOSTA=<"%GECICI%\eposta.txt"
php -r "$s = file_get_contents('database/seeders/DemoSeeder.php'); $q = chr(39); preg_match('~'.$q.'password'.$q.'\s*=>\s*'.$q.'([^'.$q.']+)'.$q.'~', $s, $m); echo $m[1] ?? '';" > "%GECICI%\sifre.txt" 2>nul
if exist "%GECICI%\sifre.txt" set /p DEMO_SIFRE=<"%GECICI%\sifre.txt"
:OZET_DEGERLERI_BITTI
del "%GECICI%\eklentiler.txt" >nul 2>&1
del "%GECICI%\eposta.txt" >nul 2>&1
del "%GECICI%\sifre.txt" >nul 2>&1

if "%KV_BASLAT%"=="1" goto SUNUCULARI_BASLAT
call :OZET
echo    Sunucuyu baslatmak icin:  php artisan serve --port=%KV_PORT%
echo    Gercek zamanli icin:      php artisan reverb:start
echo.
exit /b 0

rem ##########################################################################
rem  7) Sunucular - kapanista ikisi de temizlenir
rem ##########################################################################

:SUNUCULARI_BASLAT
echo.
echo == Sunucular baslatiliyor ==

rem Onceki calistirmadan kalmis Reverb varsa once onu kapat; boylece betik
rem iki kez calistirildiginda surecler ust uste binmez.
set "ESKI_PID="
if exist "%GECICI%\reverb.pid" set /p ESKI_PID=<"%GECICI%\reverb.pid"
if defined ESKI_PID taskkill /F /FI "PID eq %ESKI_PID%" /FI "IMAGENAME eq php.exe" >nul 2>&1
del "%GECICI%\reverb.pid" >nul 2>&1

php -r "$s = @stream_socket_client('tcp://'.$argv[1].':'.$argv[2], $e, $m, 0.5); if ($s) { fclose($s); exit(0); } exit(1);" "%KV_HOST%" "%KV_PORT%"
if not errorlevel 1 goto PORT_DOLU

set "REVERB_PID="
if "%KV_REVERB%"=="0" goto REVERB_ATLANDI
php -r "$s = @stream_socket_client('tcp://127.0.0.1:'.$argv[1], $e, $m, 0.5); if ($s) { fclose($s); exit(0); } exit(1);" "%REVERB_PORTU%"
if not errorlevel 1 goto REVERB_PORT_DOLU
powershell -NoProfile -ExecutionPolicy Bypass -Command "(Start-Process -FilePath 'php' -ArgumentList 'artisan','reverb:start','--host=0.0.0.0','--port=%REVERB_PORTU%' -WindowStyle Hidden -RedirectStandardOutput 'storage\logs\kur-reverb.log' -RedirectStandardError 'storage\logs\kur-reverb-hata.log' -PassThru).Id" > "%GECICI%\reverb.pid" 2>nul
if exist "%GECICI%\reverb.pid" set /p REVERB_PID=<"%GECICI%\reverb.pid"
if defined REVERB_PID echo    [+] Reverb calisiyor (port %REVERB_PORTU%, gunluk: storage\logs\kur-reverb.log)
if not defined REVERB_PID echo    [!] Reverb baslatilamadi. Oyun yoklamayla calismaya devam eder.
goto REVERB_BITTI
:REVERB_PORT_DOLU
echo    [!] %REVERB_PORTU% portu dolu - Reverb baslatilmadi.
echo        Oyun calisir: canli baglanti yoksa istemci yoklamaya duser.
goto REVERB_BITTI
:REVERB_ATLANDI
echo    [!] Reverb atlandi. Oyun yoklamayla calismaya devam eder.
:REVERB_BITTI

call :OZET

echo   Sunucu calisiyor. Durdurmak icin Ctrl+C.
echo   (Ctrl+C sonrasi "Toplu is islemini sonlandir (Y/N)?" diye sorarsa N
echo    verin; betik Reverb'i kendisi kapatir. Y verirseniz Reverb arkada
echo    kalir - zarari yok, kur.bat bir sonraki calismada onu kapatir.)
echo.

php artisan serve --host=%KV_HOST% --port=%KV_PORT%

echo.
echo Sunucular kapatiliyor...
if defined REVERB_PID taskkill /F /T /FI "PID eq %REVERB_PID%" /FI "IMAGENAME eq php.exe" >nul 2>&1
del "%GECICI%\reverb.pid" >nul 2>&1
echo Gorusmek uzere.
exit /b 0

rem ##########################################################################
rem  Alt yordamlar
rem ##########################################################################

:EKLENTI_DENETLE
findstr /i /x /c:"%~1" "%GECICI%\eklentiler.txt" >nul 2>&1
if errorlevel 1 set "EKSIK=!EKSIK! %~1"
exit /b 0

:ENV_YAZ
php -r "$y = '.env'; $i = file_get_contents($y); $k = '/^'.preg_quote($argv[1], '/').'=.*$/m'; $s = $argv[1].'='.$argv[2]; $i = preg_match($k, $i) ? preg_replace_callback($k, fn () => $s, $i, 1) : rtrim($i, PHP_EOL).PHP_EOL.$s.PHP_EOL; file_put_contents($y, $i);" "%~1" "%~2"
exit /b 0

:OZET
echo.
echo --------------------------------------------------------------
echo   KLANLAR VADISI - sunucu hazir
echo --------------------------------------------------------------
echo.
echo   API adresi          %API_ADRESI%/api/v1
echo   Durum ucu           %API_ADRESI%/api/v1/durum
echo.
echo   Unity ayarlari  (sahnedeki "Oyun" nesnesi -^> OyunBaslatici)
echo     Sunucu Adresi     %UNITY_ADRESI%
echo     Reverb Portu      %REVERB_PORTU%
echo     Reverb Anahtari   %REVERB_ANAHTARI%
echo.
if not defined DEMO_EPOSTA goto OZET_KONUK
if not defined DEMO_SIFRE  goto OZET_KONUK
echo   Ornek giris
echo     E-posta           %DEMO_EPOSTA%
echo     Sifre             %DEMO_SIFRE%
echo     (ya da giris ekraninda "Konuk Olarak Dene")
echo.
exit /b 0
:OZET_KONUK
echo   Giris               giris ekraninda "Konuk Olarak Dene"
echo.
exit /b 0

rem ##########################################################################
rem  Hata yollari - ne yapilacagini soyleyip cikar
rem ##########################################################################

:YOK_PHP
echo.
echo  [X] PHP bulunamadi.
echo      Sunucu PHP 8.3 veya ustu ile calisir. Komut istemini YONETICI
echo      olarak acip:
echo.
echo          winget install --id PHP.PHP.8.3
echo.
echo      (Paket adi degismis olabilir: "winget search php" ile bakin.
echo       Elle kurulum: https://windows.php.net/download adresinden zip'i
echo       C:\php klasorune acip C:\php'yi PATH'e ekleyin.)
echo.
echo      Kurduktan sonra YENI bir komut istemi acip kur.bat'i tekrar calistirin.
echo.
pause
exit /b 1

:ESKI_PHP
for /f "delims=" %%v in ('php -r "echo PHP_VERSION;"') do set "PHP_SURUM=%%v"
echo.
echo  [X] PHP surumu cok eski: !PHP_SURUM! - en az 8.3 gerekli.
echo      Yukseltmek icin:
echo.
echo          winget install --id PHP.PHP.8.3
echo.
pause
exit /b 1

:YOK_EKLENTI
echo.
echo  [X] PHP eklentileri eksik:!EKSIK!
echo      Bunlar olmadan sunucu acilmaz. Windows'ta eklentiler php.ini
echo      dosyasindan ACILIR, ayrica indirmek gerekmez:
echo.
echo        1) "php --ini" komutu ile php.ini'nin yerini bulun.
echo           (Dosya yoksa php.ini-development'i php.ini olarak kopyalayin.)
echo        2) Dosyayi not defteri ile acin.
echo        3) Yukarida yazan her eklenti icin satir basindaki ';' isaretini
echo           silin. Ornek:
echo              ;extension=pdo_sqlite   -^>   extension=pdo_sqlite
echo        4) Kaydedin, komut istemini kapatip yeniden acin, kur.bat'i
echo           tekrar calistirin.
echo.
pause
exit /b 1

:YOK_COMPOSER
echo.
echo  [X] Composer bulunamadi.
echo      PHP paketlerini indirmek icin Composer 2.x gerekli:
echo.
echo          winget install --id Composer.Composer
echo.
echo      (ya da https://getcomposer.org/Composer-Setup.exe calistirin)
echo      Kurduktan sonra YENI bir komut istemi acin.
echo.
pause
exit /b 1

:COMPOSER_HATASI
echo.
echo  [X] composer install basarisiz oldu.
echo      Sik nedenler:
echo        - Internet baglantisi yok ya da vekil sunucu engelliyor.
echo        - PHP eklentisi eksik: ciktida "requires ext-..." arayin.
echo.
echo      Ayrintili cikti icin:  %COMPOSER_KOMUTU% install -v
echo.
pause
exit /b 1

:YOK_ORNEK_ENV
echo.
echo  [X] .env.example bulunamadi - arsiv eksik acilmis olabilir.
echo      Zip'i tum dosyalariyla yeniden acin.
echo.
pause
exit /b 1

:ANAHTAR_HATASI
echo.
echo  [X] php artisan key:generate basarisiz oldu.
echo      .env dosyasinin salt okunur olmadigindan emin olun.
echo.
pause
exit /b 1

:MYSQL_YOK
echo.
echo  [X] MySQL sunucusuna baglanilamadi.
echo      Sunucu: %KV_DB_SUNUCU%:%KV_DB_PORT%   Kullanici: %KV_DB_KULLANICI%
echo.
echo      Secenekleriniz:
echo        1) MySQL'i baslatin:   net start MySQL80
echo        2) Sifreyi verin:      set KV_DB_SIFRE=sifreniz
echo                               kur.bat --mysql
echo        3) MySQL'den vazgecin: kur.bat
echo           SQLite ile hicbir veritabani kurulumu gerekmez - onerilen yol.
echo.
pause
exit /b 1

:GOC_HATASI
echo.
echo  [X] php artisan migrate basarisiz oldu.
echo      SQLite kullaniyorsaniz database\database.sqlite dosyasinin
echo      yazilabilir oldugundan emin olun. Ayrintili hata icin:
echo.
echo          php artisan migrate --force
echo.
pause
exit /b 1

:TOHUM_HATASI
echo.
echo  [X] Katalog/dunya tohumlamasi basarisiz oldu. Ayrintili hata icin:
echo.
echo          php artisan db:seed --force
echo.
pause
exit /b 1

:DEMO_HATASI
echo.
echo  [X] Ornek dunya kurulamadi. Ayrintili hata icin:
echo.
echo          php artisan game:demo --players=%KV_OYUNCU% --clans=%KV_BOY%
echo.
echo      Ornek dunya olmadan da oynayabilirsiniz:  kur.bat --demo-yok
echo.
pause
exit /b 1

:PORT_DOLU
echo.
echo  [X] %KV_PORT% portu kullaniliyor.
echo      Baska bir port secin:
echo.
echo          kur.bat --port=8001
echo.
echo      ya da o portu tutan sureci bulun:
echo          netstat -ano ^| findstr :%KV_PORT%
echo.
pause
exit /b 1

:YARDIM
echo Klanlar Vadisi - yerel kurulum betigi
echo.
echo Kullanim:
echo   kur.bat [secenekler]
echo.
echo Secenekler:
echo   --sqlite              Veritabani olarak SQLite kullan (varsayilan)
echo   --mysql               Veritabani olarak MySQL kullan
echo   --port=8000           Sunucu portu
echo   --host=127.0.0.1      Sunucunun dinleyecegi adres
echo   --oyuncu=24           Ornek dunyadaki oyuncu sayisi
echo   --boy=5               Ornek dunyadaki boy sayisi
echo   --demo-yok            Ornek oyuncu/boy olusturma
echo   --reverb-yok          Reverb (gercek zamanli) sunucusunu baslatma
echo   --sadece-kurulum      Kurulumu yap, sunuculari baslatma
echo   --yardim              Bu yazi
echo.
echo Ortam degiskenleriyle de ayni sey yapilabilir:
echo   set KV_PORT=9000
echo   kur.bat
echo.
echo MySQL bilgileri (yalnizca --mysql ile):
echo   KV_DB_AD, KV_DB_KULLANICI, KV_DB_SIFRE, KV_DB_SUNUCU, KV_DB_PORT
echo.
exit /b 0
