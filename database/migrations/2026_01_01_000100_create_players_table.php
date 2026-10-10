<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('players', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('name', 32)->unique();
            $table->string('avatar', 48)->default('kurt');
            $table->unsignedSmallInteger('level')->default(1);
            $table->unsignedBigInteger('xp')->default(0);
            $table->integer('trophies')->default(0);
            $table->integer('best_trophies')->default(0);
            $table->unsignedBigInteger('gok_tasi')->default(0);

            $table->foreignId('clan_id')->nullable()->index();
            $table->string('clan_role', 16)->nullable(); // lider, vezir, alp, er
            $table->timestamp('clan_joined_at')->nullable();
            $table->unsignedInteger('donated')->default(0);
            $table->unsignedInteger('received')->default(0);

            $table->unsignedInteger('attack_wins')->default(0);
            $table->unsignedInteger('attack_count')->default(0);
            $table->unsignedInteger('defense_wins')->default(0);
            $table->unsignedInteger('defense_count')->default(0);
            $table->unsignedBigInteger('total_loot')->default(0);
            $table->unsignedInteger('war_stars')->default(0);

            $table->timestamp('shield_until')->nullable();
            $table->timestamp('guard_until')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->boolean('is_banned')->default(false);
            $table->timestamps();

            $table->index('trophies');
            $table->index(['clan_id', 'trophies']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('players');
    }
};
