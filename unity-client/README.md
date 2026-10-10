# Klanlar Vadisi — Unity İstemcisi

Unity **6000.0 LTS** (2022.3 LTS de çalışır) ile açın:
Unity Hub → **Add** → bu klasörü seçin → `Assets/Scenes/Oyun.unity`.

Sunucu `http://localhost:8000` üzerinde çalışıyor olmalıdır
(bkz. kök dizindeki README).

## Sahnede ne var?

Neredeyse hiçbir şey: tek bir boş nesne ve üzerinde `OyunBaslatici`.
Kamera, arayüz, oba ve harita oyun başlarken koddan kurulur. Böylece
ikili .prefab dosyaları sürüm takibini kirletmez ve iki kişi aynı anda
arayüze dokunduğunda birleştirme çakışması çıkmaz.

## Sunucu adresini değiştirmek

- **Editörde:** sahnedeki `Oyun` nesnesinin `Sunucu Adresi` alanı
- **Derlemede:** `KlanlarVadisi.exe --sunucu=https://oyun.example.com`

## Klasörler

| Klasör | İçerik |
|--------|--------|
| `Scripts/Core` | Giriş noktası, kamera, sunucudan aktarılan sabitler |
| `Scripts/Net` | API istemcisi, veri modelleri, savaş tekrarı çözümleyici |
| `Scripts/World` | Deterministik arazi üretimi, dünya haritası |
| `Scripts/Village` | Oba ve bina görünümleri |
| `Scripts/Battle` | Savaş tekrarı oynatıcı, saldırı kurgusu, efektler |
| `Scripts/UI` | Kod içi arayüz fabrikası ve paneller |
| `Resources/Art` | 106 sprite (`tools/art` ile üretilir) |

## Üretilen dosyalar — elle düzenlemeyin

- `Scripts/Core/OyunSabitleri.cs`
- `Scripts/World/BolgeTanimlari.cs`

Bunlar sunucudaki `config/game.php` ve `config/regions.php` dosyalarından
`php artisan game:export-unity` ile üretilir. Arazi üretiminin sunucuyla
birebir aynı kalması buna bağlıdır.

## Önemli: `Noise.cs` ve `WorldTerrain.cs`

Bu iki dosya sunucudaki `app/Support/Noise.php` ve
`app/Services/WorldService.php` ile **birebir aynı sonucu üretmek
zorundadır**. Birinde bir satır değişirse diğerinde de değişmelidir;
aksi hâlde istemci ile sunucu farklı arazi görür.

## Derleme hedefleri

Proje mobil öncelikli ayarlanmıştır (yatay, 60 fps, IL2CPP uyumlu).
Windows/macOS/Linux, Android ve iOS derlemeleri ek ayar gerektirmez.
WebGL için sunucuda CORS'u `GAME_ALLOWED_ORIGINS` ile açmanız gerekir.
