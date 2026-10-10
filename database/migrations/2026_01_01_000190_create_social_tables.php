<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_messages', function (Blueprint $table) {
            $table->id();
            $table->string('scope', 12)->default('genel'); // genel, boy, savas
            $table->foreignId('clan_id')->nullable()->index();
            $table->foreignId('player_id')->nullable()->constrained()->nullOnDelete();
            $table->string('kind', 16)->default('mesaj'); // mesaj, sistem, bagis
            $table->string('body', 400);
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['scope', 'id']);
            $table->index(['clan_id', 'id']);
        });

        Schema::create('game_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('player_id')->constrained()->cascadeOnDelete();
            $table->string('type', 32); // saldiri_alindi, bina_bitti, boy_daveti ...
            $table->string('title', 80);
            $table->string('body', 300)->default('');
            $table->json('payload')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['player_id', 'read_at']);
        });

        Schema::create('donations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('from_player_id')->constrained('players')->cascadeOnDelete();
            $table->foreignId('to_player_id')->constrained('players')->cascadeOnDelete();
            $table->foreignId('troop_type_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('quantity');
            $table->unsignedSmallInteger('level');
            $table->timestamps();
        });

        // Boy Otağı'nda bekleyen bağış askerleri (savunmada da kullanılır)
        Schema::create('clan_troop_stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('village_id')->constrained()->cascadeOnDelete();
            $table->foreignId('troop_type_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('level')->default(1);
            $table->unsignedInteger('quantity')->default(0);
            $table->timestamps();

            $table->unique(['village_id', 'troop_type_id', 'level']);
        });

        Schema::create('troop_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('player_id')->constrained()->cascadeOnDelete();
            $table->string('message', 200)->default('');
            $table->unsignedInteger('fulfilled')->default(0);
            $table->unsignedInteger('capacity')->default(10);
            $table->timestamp('expires_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['troop_requests', 'clan_troop_stocks', 'donations', 'game_events', 'chat_messages'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
