<?php

namespace App\Console\Commands;

use Database\Seeders\DemoSeeder;
use Illuminate\Console\Command;

class DemoCommand extends Command
{
    protected $signature = 'game:demo
        {--players=40 : Oluşturulacak oyuncu sayısı}
        {--clans=6 : Oluşturulacak boy sayısı}';

    protected $description = 'Unity istemcisini denemek için örnek oyuncular, obalar ve boylar oluşturur';

    public function handle(DemoSeeder $seeder): int
    {
        $players = (int) $this->option('players');
        $clans = (int) $this->option('clans');

        $this->info("Örnek dünya kuruluyor: {$players} oyuncu, {$clans} boy...");

        $seeder->setCommand($this)->run($players, $clans);

        $this->newLine();
        $this->table(
            ['Varlık', 'Adet'],
            [
                ['Oyuncu', \App\Models\Player::count()],
                ['Oba', \App\Models\Village::count()],
                ['Bina', \App\Models\Building::count()],
                ['Boy', \App\Models\Clan::count()],
                ['Dünya nesnesi', \App\Models\WorldTile::count()],
            ]
        );

        $this->info('Hazır. Örnek giriş: alpagut@klanlarvadisi.test / sifre1234');

        return self::SUCCESS;
    }
}
