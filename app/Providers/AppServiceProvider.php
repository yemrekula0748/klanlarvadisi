<?php

namespace App\Providers;

use Illuminate\Foundation\DevCommands;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->yerelGelistirmeSureclerini();
    }

    /**
     * `php artisan dev` ile açılacak süreçler.
     *
     * Laravel'in varsayılanları arasında `npm run dev` (Vite) da var; bu
     * projenin istemcisi Unity olduğu için derlenecek bir web varlığı yok
     * ve o süreç boşuna hata verirdi. Varsayılanları kapatıp oyunun
     * gerçekten ihtiyaç duyduğu üçlüyü kaydediyoruz. Reverb kendi
     * sürecini zaten kaydeder (paket tarafından geldiği için silinmez).
     *
     * Böylece yerelde tek komut yeterli olur:
     *     php artisan dev
     */
    private function yerelGelistirmeSureclerini(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        DevCommands::withoutDefaultCommands();

        DevCommands::artisan('serve', 'sunucu');
        DevCommands::artisan('queue:listen --tries=1 --timeout=0', 'kuyruk');
    }
}
