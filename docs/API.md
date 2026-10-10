# Klanlar Vadisi — API Başvurusu

Taban adres: `/api/v1`
Kimlik doğrulama: `Authorization: Bearer <token>` (Laravel Sanctum)

Bütün yanıtlar JSON'dur. Hata yanıtları şu biçimdedir:

```json
{ "message": "Yeterli Altın yok.", "code": "kaynak_yetersiz" }
```

İstemci `code` alanına göre davranır; `message` doğrudan gösterilebilir
(sunucu Türkçe üretir).

## Hata kodları

| Kod | Anlamı |
|-----|--------|
| `kaynak_yetersiz` | Kaynak yetmiyor |
| `otag_seviyesi` | Otağ seviyesi düşük |
| `adet_siniri` | Bu Otağ seviyesinde daha fazla kurulamaz |
| `usta_yok` | Boş usta yok |
| `kare_dolu` | Hedef karede başka bina var |
| `izgara_disi` | Oba sınırının dışı |
| `ordu_dolu` | Ordugâh kapasitesi aşıldı |
| `ordu_kapasitesi` | Gönderilen ordu kapasiteyi aşıyor |
| `kisla_seviyesi` | Kışla seviyesi yetersiz |
| `demirci_seviyesi` | Demirci seviyesi yetersiz |
| `asker_yetersiz` | Elde o kadar asker yok |
| `kalkan_var` | Hedef kalkan altında |
| `rakip_yok` | Uygun rakip bulunamadı |
| `boy_dolu` / `kupa_yetersiz` / `boy_kapali` | Boya katılma engelleri |
| `savas_gunu_degil` | Boy savaşı henüz başlamadı |
| `saldiri_hakki_bitti` | Savaş saldırı hakkı kalmadı |
| `izin_yok` | Yetki yok (403) |
| `bulunamadi` | Kayıt yok (404) |

---

## Giriş

### `POST /kayit`
```json
{ "email": "...", "password": "...", "player_name": "BilgeKağan" }
```
→ `201 { "token": "...", "player": {...} }`

Oyuncu profilini, obasını ve başlangıç binalarını birlikte oluşturur.
Oyuncuya üç günlük yeni oyuncu koruması verilir.

### `POST /giris`
```json
{ "email": "...", "password": "..." }
```

### `POST /konuk`
```json
{ "device": "cihaz-kimligi" }
```
Hesap açmadan oynama. Aynı cihaz kimliği hep aynı oyuncuyu döndürür.

### `GET /ben` · `POST /cikis` · `PATCH /profil`

---

## Katalog

### `GET /katalog`
Bütün bina, asker, kahraman ve tılsım tanımları + seviye tabloları.
ETag ve `Cache-Control: max-age=600` döner — istemci bir kez çekip saklar.

---

## Oba

### `GET /oba`
Oyuncunun tam durumu: kaynaklar, üretim hızı, depo kapasitesi, binalar,
ordu, kuyruklar, bildirimler. **İstemcinin ana isteği budur.**

Bu uç çağrıldığında sunucu tembel çözümlemeyi yapar: biriken kaynaklar
işlenir, süresi dolan inşaat ve eğitimler tamamlanır. Bu yüzden oyuncu
başına arka plan işi gerekmez.

### `POST /oba/bina`
```json
{ "key": "ates_kulesi", "x": 10, "y": 12 }
```

### `POST /oba/bina/{id}/yukselt`
### `POST /oba/bina/{id}/iptal`
Harcanan kaynağın yarısı iade edilir; yeni kurulan bina tamamen kalkar.

### `POST /oba/bina/{id}/bitir`
Gök Taşı ile anında tamamla. Maliyet kalan süreye göre hesaplanır.

### `POST /oba/bina/{id}/tasi` — `{ "x": 8, "y": 20 }`
### `DELETE /oba/bina/{id}` — yalnızca duvar ve tuzaklar

### `POST /oba/duzen`
```json
{ "buildings": [ { "id": 12, "x": 8, "y": 20 } ] }
```
Toplu taşıma. Tek bir çakışma varsa **hiçbiri** uygulanmaz.

### `POST /oba/duzen/otomatik`
Otağ seviyesine uygun hazır düzen kurar (surlu, bölmeli).

### `GET /oba/{village}/kesif`
Başka bir obanın düzenini ve yağmalanabilir kaynağını gösterir.

---

## Ordu

### `POST /ordu/egit` — `{ "troop": "ceri", "quantity": 10 }`
### `DELETE /ordu/kuyruk/{id}` — kaynağın tamamı iade edilir
### `POST /ordu/tilsim` — `{ "spell": "yildirim", "quantity": 2 }`
### `POST /ordu/gelistir` — `{ "troop": "ceri" }` (Demirci)
### `POST /ordu/kahraman/uyandir` · `POST /ordu/kahraman/gelistir`

---

## Savaş

### `POST /savas/rakip-ara`
Kupaya yakın, kalkansız, kendi boyundan olmayan bir oba döndürür.

### `POST /savas/saldir/{village}`
```json
{
  "deployments": [ { "troop": "ceri", "x": 20.5, "y": 3.0, "t": 0.0 } ],
  "spells":      [ { "spell": "yildirim", "x": 22, "y": 22, "t": 25.0 } ],
  "heroes":      [ "oguz_kagan" ]
}
```

`x`,`y` ızgara koordinatı (0-44); `t` saldırı başından itibaren saniye.

Yanıt savaşın **tamamını** içerir: yıldız, yıkım yüzdesi, ganimet, kupa
değişimi ve `replay` (olay zaman çizgisi). İstemci hiçbir hesap yapmaz,
yalnızca oynatır.

#### Tekrar olayları

| Olay | Anlamı |
|------|--------|
| `dep` | Asker indi |
| `spawn` | Savunan asker doğdu (Boy Otağı, Kurt İni) |
| `mv` | Yeni yol — `p` dizisi ızgara noktaları |
| `atk` / `atku` | Asker binaya / askere vurmaya başladı |
| `def` | Savunma yapısı askere ateş açtı |
| `bdie` / `udie` | Bina yıkıldı / asker öldü |
| `hp` | Can güncellemesi (saniyede bir, toplu sözlük) |
| `trap` | Tuzak patladı |
| `spell` | Tılsım atıldı |
| `abil` | Kahraman yeteneği tetiklendi |

Olaylar yalnızca **durum değiştiğinde** yayılır (`atk` her vuruşta değil,
hedef değiştiğinde). İstemci vuruş animasyonunu birimin `as` (attack speed)
değeriyle kendisi döngüye alır. Bu, 230 askerlik bir savaşın kaydını
~110 KB'de tutar.

### `GET /savas/gecmis` · `GET /savas/{id}` · `POST /savas/{id}/intikam`

Tekrar kayıtlarına yalnızca savaşın iki tarafı erişebilir.

---

## Dünya

### `GET /dunya/bilgi`
Tohum, harita boyutu ve 16 mitolojik bölge. İstemci araziyi bu tohumla
kendisi üretir — arazi verisi hiç indirilmez.

### `GET /dunya/bolge?x=&y=&w=&h=`
Yalnızca **dolu** kareler: obalar, mitolojik kamplar, kaynak damarları,
harabeler, kutsal alanlar. En çok 64×64.

### `GET /dunya/kare?x=&y=`
Tek karenin arazisi + bölgesi + üzerindeki nesne.

---

## Boy

### `GET /boy?q=&min=&type=` · `GET /boy/{id}` · `POST /boy`
Boy kurmak 40.000 altındır.

### `POST /boy/{id}/katil` · `POST /boy/ayril`
Açık boya doğrudan katılınır; davetli boya başvuru gider. Boy başı
ayrılırsa liderlik en kıdemli üyeye geçer; son üye ayrılırsa boy silinir.

### `GET /boy/basvurular` · `POST /boy/basvuru/{id}` — `{ "accept": true }`
### `POST /boy/cikar/{member}` · `POST /boy/rutbe/{member}` · `POST /boy/baslik/{member}`
### `GET /boy/sohbet?after=` · `POST /boy/sohbet`
### `POST /boy/asker-iste` · `GET /boy/asker-istekleri` · `POST /boy/bagis/{id}`

Bağışlanan askerler alıcının Boy Otağı'na girer ve **savunmada** çıkar.

---

## Boy savaşı

### `GET /boy-savasi/guncel` · `POST /boy-savasi/ilan`
### `GET /boy-savasi/{war}/hedefler` · `GET /boy-savasi/{war}/kesif/{defender}`
### `POST /boy-savasi/{war}/saldir/{defender}` · `GET /boy-savasi/gecmis`

Savaş saldırılarında kupa değişmez; yalnızca **yeni** yıldızlar sayılır
(aynı hedefe ikinci saldırıda yalnızca fark eklenir). Savunma düzenleri
hazırlık günü dondurulur.

---

## Sıralama

### `GET /siralama/oyuncular` · `GET /siralama/boylar`
60 saniye önbelleklenir.

---

## Gerçek zamanlı (Reverb)

Unity, Pusher protokolüyle Reverb'e bağlanır:

| Kanal | Olaylar |
|-------|---------|
| `private-oyuncu.{id}` | `oba.saldirildi` |
| `private-boy.{id}` | `boy.sohbet`, `savas.guncelleme` |

Yetkilendirme `/broadcasting/auth` üzerinden Sanctum belirteciyle yapılır.
Reverb çalışmıyorsa oyun yine oynanır; istemci 20 saniyede bir durum çeker.
