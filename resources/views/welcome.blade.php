<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'Klanlar Vadisi') }} — sunucu</title>
    <style>
        :root {
            --kece:    #efe6d2;
            --zemin:   #16130f;
            --zemin2:  #1f1a14;
            --cizgi:   #342c22;
            --metin:   #e8dfcb;
            --soluk:   #a2947c;
            --kizil:   #c0392b;
            --altin:   #d9a84a;
            --yesil:   #6f9a4e;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            background: var(--zemin);
            color: var(--metin);
            font: 16px/1.65 system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
            background-image:
                radial-gradient(1100px 500px at 50% -15%, #2a2118 0%, transparent 70%);
        }
        .sarmal { max-width: 960px; margin: 0 auto; padding: 48px 20px 72px; }
        header { border-bottom: 1px solid var(--cizgi); padding-bottom: 28px; margin-bottom: 36px; }
        h1 { margin: 0 0 6px; font-size: 2rem; letter-spacing: .4px; }
        h1 span { color: var(--altin); }
        .alt { color: var(--soluk); margin: 0; }
        h2 { font-size: 1.05rem; text-transform: uppercase; letter-spacing: 1.2px;
             color: var(--soluk); margin: 40px 0 14px; font-weight: 600; }
        .durum {
            display: inline-flex; align-items: center; gap: 8px; margin-top: 16px;
            padding: 6px 14px; border-radius: 999px; font-size: .88rem;
            border: 1px solid var(--cizgi); background: var(--zemin2);
        }
        .nokta { width: 9px; height: 9px; border-radius: 50%; }
        .acik  { background: var(--yesil); box-shadow: 0 0 10px var(--yesil); }
        .kapali { background: var(--kizil); box-shadow: 0 0 10px var(--kizil); }
        .kutular { display: grid; gap: 12px; grid-template-columns: repeat(auto-fit, minmax(118px, 1fr)); }
        .kutu {
            background: var(--zemin2); border: 1px solid var(--cizgi);
            border-radius: 10px; padding: 14px 16px;
        }
        .kutu b { display: block; font-size: 1.5rem; color: var(--altin); font-weight: 600; }
        .kutu span { color: var(--soluk); font-size: .83rem; }
        pre {
            background: #100d0a; border: 1px solid var(--cizgi); border-radius: 10px;
            padding: 14px 16px; overflow-x: auto; font-size: .87rem; color: #d7cdb6;
        }
        code { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; }
        table { width: 100%; border-collapse: collapse; font-size: .92rem; }
        td, th { text-align: left; padding: 9px 10px; border-bottom: 1px solid var(--cizgi); }
        th { color: var(--soluk); font-weight: 600; }
        td code { color: var(--altin); }
        .onizleme { display: grid; gap: 18px; }
        .onizleme figure { margin: 0; }
        .onizleme img {
            width: 100%; height: auto; display: block;
            border: 1px solid var(--cizgi); border-radius: 10px; background: #0d0b09;
        }
        .onizleme figcaption { color: var(--soluk); font-size: .85rem; padding-top: 7px; }
        ol { padding-left: 22px; }
        ol li { margin-bottom: 7px; }
        a { color: var(--altin); }
        footer { margin-top: 52px; padding-top: 22px; border-top: 1px solid var(--cizgi);
                 color: var(--soluk); font-size: .85rem; }
        .uyari {
            background: #2a1c12; border: 1px solid #5a3a1e; border-radius: 10px;
            padding: 14px 16px; margin: 18px 0;
        }
    </style>
</head>
<body>
<div class="sarmal">

    <header>
        <h1>Klanlar <span>Vadisi</span></h1>
        <p class="alt">Türk mitolojisinden beslenen çok oyunculu strateji oyunu — sunucu tarafı.</p>
        <div class="durum">
            <span class="nokta {{ $hazir ? 'acik' : 'kapali' }}"></span>
            {{ $hazir ? 'Sunucu ayakta, katalog yüklü.' : 'Sunucu ayakta ama veritabanı hazır değil.' }}
        </div>
    </header>

    @unless ($hazir)
        <div class="uyari">
            <strong>Veritabanı henüz kurulmamış.</strong> Şunu çalıştır:
            <pre><code>php artisan migrate --seed
php artisan game:demo</code></pre>
        </div>
    @endunless

    @if ($sayilar)
        <h2>Dünyada ne var</h2>
        <div class="kutular">
            <div class="kutu"><b>{{ $sayilar['bina'] }}</b><span>bina türü</span></div>
            <div class="kutu"><b>{{ $sayilar['asker'] }}</b><span>asker türü</span></div>
            <div class="kutu"><b>{{ $sayilar['alp'] }}</b><span>alp</span></div>
            <div class="kutu"><b>{{ $sayilar['buyu'] }}</b><span>büyü</span></div>
            <div class="kutu"><b>{{ $sayilar['oyuncu'] }}</b><span>oyuncu</span></div>
            <div class="kutu"><b>{{ $sayilar['oba'] }}</b><span>oba</span></div>
            <div class="kutu"><b>{{ $sayilar['boy'] }}</b><span>boy</span></div>
        </div>
    @endif

    @if ($onizlemeler->isNotEmpty())
        <h2>Oyun nasıl görünüyor</h2>
        <p class="alt" style="margin-bottom:18px">
            Aşağıdaki görseller oyunun kendi sprite'larından ve sunucunun kendi
            arazi/yerleşim kodundan üretildi — Unity kurmadan da fikir verir.
        </p>
        <div class="onizleme">
            @foreach ($onizlemeler as $ad => $baslik)
                <figure>
                    <img src="{{ route('onizleme', $ad) }}" alt="{{ $baslik }}" loading="lazy">
                    <figcaption>{{ $baslik }}</figcaption>
                </figure>
            @endforeach
        </div>
    @endif

    <h2>Unity istemcisini bağla</h2>
    <ol>
        <li>Unity Hub'a <code>unity-client</code> klasörünü proje olarak ekle
            (Unity sürümü <code>6000.0.32f1</code>).</li>
        <li><code>Assets/Scenes/Oyun.unity</code> sahnesini aç.</li>
        <li>Sahnedeki <code>OyunBaslatici</code> nesnesinde
            <strong>Sunucu Adresi</strong> = <code>{{ url('/') }}</code>,
            <strong>Reverb Portu</strong> = <code>{{ $reverbPort ?: 8080 }}</code>,
            <strong>Reverb Anahtarı</strong> = <code>{{ $reverbAnahtar ?: 'klanlar-vadisi-anahtari' }}</code>.</li>
        <li>Play'e bas. Konuk girişiyle tek tuşta oyuna girilir.</li>
    </ol>

    @if ($demoEposta)
        <h2>Hazır giriş</h2>
        <table>
            <tr><th>E-posta</th><td><code>{{ $demoEposta }}</code></td></tr>
            <tr><th>Şifre</th><td><code>{{ $demoSifre }}</code></td></tr>
        </table>
    @endif

    <h2>Unity olmadan denemek</h2>
    <pre><code># konuk girişi (belirteç döner)
curl -X POST {{ url('/api/v1/konuk') }} \
  -H 'Content-Type: application/json' \
  -d '{"device":"deneme"}'

# kendi oban (belirteci yukarıdan al)
curl {{ url('/api/v1/oba') }} -H "Authorization: Bearer BELIRTEC"</code></pre>
    <p class="alt">Uçların tamamı: <code>docs/API.md</code></p>

    <footer>
        Tek komutla her şeyi başlatmak için <code>php artisan dev</code>
        (sunucu + Reverb + kuyruk). Kurulum adımları:
        <code>docs/LOKAL-KURULUM.md</code>.
    </footer>

</div>
</body>
</html>
