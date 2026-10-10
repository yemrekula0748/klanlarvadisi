<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('battles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attacker_player_id')->constrained('players')->cascadeOnDelete();
            $table->foreignId('defender_player_id')->nullable()->constrained('players')->nullOnDelete();
            $table->foreignId('defender_village_id')->nullable()->constrained('villages')->nullOnDelete();
            $table->foreignId('clan_war_id')->nullable()->index();
            $table->string('type', 16)->default('coklu'); // coklu, savas, intikam, npc
            $table->unsignedTinyInteger('stars')->default(0);
            $table->decimal('destruction_percent', 5, 2)->default(0);
            $table->unsignedInteger('duration_seconds')->default(0);
            $table->unsignedBigInteger('loot_gold')->default(0);
            $table->unsignedBigInteger('loot_grain')->default(0);
            $table->unsignedBigInteger('loot_iron')->default(0);
            $table->unsignedBigInteger('loot_kut')->default(0);
            $table->integer('trophies_attacker')->default(0);
            $table->integer('trophies_defender')->default(0);
            $table->unsignedBigInteger('seed');
            $table->json('army')->nullable();     // saldırı ordusu anlık görüntüsü
            $table->json('layout')->nullable();   // savunma düzeni anlık görüntüsü
            $table->json('replay')->nullable();   // olay zaman çizgisi (tekrar oynatma)
            $table->timestamps();

            $table->index(['attacker_player_id', 'created_at']);
            $table->index(['defender_player_id', 'created_at']);
        });

        Schema::create('clan_wars', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clan_a_id')->constrained('clans')->cascadeOnDelete();
            $table->foreignId('clan_b_id')->constrained('clans')->cascadeOnDelete();
            $table->unsignedSmallInteger('size')->default(10);
            $table->string('state', 16)->default('hazirlik'); // hazirlik, savas, bitti
            $table->timestamp('prep_ends_at');
            $table->timestamp('battle_ends_at');
            $table->unsignedInteger('stars_a')->default(0);
            $table->unsignedInteger('stars_b')->default(0);
            $table->decimal('destruction_a', 6, 2)->default(0);
            $table->decimal('destruction_b', 6, 2)->default(0);
            $table->foreignId('winner_clan_id')->nullable();
            $table->timestamps();

            $table->index(['state', 'battle_ends_at']);
        });

        Schema::create('clan_war_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clan_war_id')->constrained()->cascadeOnDelete();
            $table->foreignId('clan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('player_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('map_position');
            $table->unsignedTinyInteger('attacks_used')->default(0);
            $table->unsignedTinyInteger('stars_earned')->default(0);
            $table->unsignedTinyInteger('best_stars_against')->default(0);
            $table->decimal('best_destruction_against', 5, 2)->default(0);
            $table->json('layout')->nullable();
            $table->timestamps();

            $table->unique(['clan_war_id', 'player_id']);
            $table->index(['clan_war_id', 'clan_id', 'map_position']);
        });

        Schema::create('clan_war_attacks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clan_war_id')->constrained()->cascadeOnDelete();
            $table->foreignId('battle_id')->constrained()->cascadeOnDelete();
            $table->foreignId('attacker_player_id')->constrained('players')->cascadeOnDelete();
            $table->foreignId('defender_player_id')->constrained('players')->cascadeOnDelete();
            $table->unsignedTinyInteger('stars');
            $table->unsignedTinyInteger('new_stars');
            $table->decimal('destruction_percent', 5, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['clan_war_attacks', 'clan_war_members', 'clan_wars', 'battles'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
