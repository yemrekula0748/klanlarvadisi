<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clans', function (Blueprint $table) {
            $table->id();
            $table->string('name', 32)->unique();
            $table->string('tag', 12)->unique();
            $table->string('description', 400)->default('');
            $table->json('badge');                       // {shape, field, symbol, colors}
            $table->string('type', 12)->default('acik'); // acik, davet, kapali
            $table->integer('required_trophies')->default(0);
            $table->string('war_frequency', 16)->default('sik');
            $table->string('language', 8)->default('tr');
            $table->foreignId('leader_player_id')->nullable()->index();
            $table->unsignedSmallInteger('level')->default(1);
            $table->unsignedBigInteger('xp')->default(0);
            $table->unsignedBigInteger('points')->default(0);
            $table->unsignedInteger('member_count')->default(0);
            $table->unsignedInteger('member_limit')->default(50);
            $table->unsignedInteger('war_wins')->default(0);
            $table->unsignedInteger('war_losses')->default(0);
            $table->unsignedInteger('war_streak')->default(0);
            $table->timestamps();

            $table->index('points');
        });

        Schema::create('clan_join_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('player_id')->constrained()->cascadeOnDelete();
            $table->string('message', 200)->default('');
            $table->string('status', 12)->default('bekliyor'); // bekliyor, kabul, red
            $table->timestamps();
            $table->unique(['clan_id', 'player_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clan_join_requests');
        Schema::dropIfExists('clans');
    }
};
