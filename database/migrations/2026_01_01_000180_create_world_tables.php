<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Dünya haritası yalnızca "dolu" kareleri saklar; arazi istemci ve
        // sunucuda aynı tohumdan deterministik olarak üretilir.
        Schema::create('world_tiles', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('x');
            $table->unsignedInteger('y');
            $table->string('type', 24); // oba, kaynak_damari, ucube_kampi, harabe, kutsal_alan, boy_kalesi
            $table->foreignId('player_id')->nullable()->index();
            $table->foreignId('village_id')->nullable()->index();
            $table->foreignId('clan_id')->nullable()->index();
            $table->foreignId('region_id')->nullable()->index();
            $table->string('label', 48)->nullable();
            $table->unsignedSmallInteger('power')->default(0); // NPC kamp gücü
            $table->json('data')->nullable();
            $table->timestamp('respawn_at')->nullable();
            $table->timestamps();

            $table->unique(['x', 'y']);
            $table->index(['type', 'x', 'y']);
        });

        Schema::create('world_regions', function (Blueprint $table) {
            $table->id();
            $table->string('key', 40)->unique();
            $table->string('name', 48);
            $table->text('lore');
            $table->string('biome', 24);
            $table->unsignedInteger('center_x');
            $table->unsignedInteger('center_y');
            $table->unsignedInteger('radius');
            $table->string('color', 9);
            $table->foreignId('controlling_clan_id')->nullable();
            $table->json('bonus')->nullable(); // {gold: 1.1} gibi bölge bonusları
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('world_regions');
        Schema::dropIfExists('world_tiles');
    }
};
