<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hero_types', function (Blueprint $table) {
            $table->id();
            $table->string('key', 48)->unique();
            $table->string('name', 48);
            $table->string('title', 64);
            $table->text('lore');
            $table->string('sprite', 64);
            $table->unsignedSmallInteger('max_level')->default(30);
            $table->unsignedSmallInteger('unlock_otag_level')->default(7);
            $table->unsignedInteger('regen_minutes')->default(30);
            $table->boolean('is_air')->default(false);
            $table->decimal('move_speed', 5, 2)->default(24);
            $table->decimal('attack_range', 5, 2)->default(0.8);
            $table->decimal('attack_speed', 5, 2)->default(0.8);
            $table->string('preferred_target', 16)->default('yok');
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('hero_levels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hero_type_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('level');
            $table->unsignedInteger('hitpoints');
            $table->unsignedInteger('damage_per_second');
            $table->unsignedBigInteger('upgrade_cost_kut')->default(0);
            $table->unsignedInteger('upgrade_seconds')->default(0);
            $table->string('ability_name', 48)->nullable();
            $table->string('ability_description', 220)->nullable();
            $table->json('ability')->nullable();
            $table->timestamps();

            $table->unique(['hero_type_id', 'level']);
        });

        Schema::create('player_heroes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('player_id')->constrained()->cascadeOnDelete();
            $table->foreignId('hero_type_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('level')->default(1);
            $table->boolean('unlocked')->default(false);
            $table->boolean('is_upgrading')->default(false);
            $table->timestamp('upgrade_ends_at')->nullable();
            $table->timestamp('regen_until')->nullable();
            $table->timestamps();

            $table->unique(['player_id', 'hero_type_id']);
        });

        Schema::create('spell_types', function (Blueprint $table) {
            $table->id();
            $table->string('key', 48)->unique();
            $table->string('name', 48);
            $table->string('category', 12)->default('ak'); // ak (iyi), kara (karanlık)
            $table->text('description');
            $table->text('lore');
            $table->string('sprite', 64);
            $table->unsignedSmallInteger('housing')->default(1);
            $table->unsignedInteger('brew_seconds')->default(180);
            $table->unsignedBigInteger('cost_kut')->default(0);
            $table->unsignedBigInteger('cost_iron')->default(0);
            $table->unsignedSmallInteger('max_level')->default(5);
            $table->decimal('radius', 5, 2)->default(3);
            $table->unsignedSmallInteger('unlock_buyu_level')->default(1);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('spell_levels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('spell_type_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('level');
            $table->json('effect'); // {damage: .., duration: .., heal: ..}
            $table->unsignedBigInteger('upgrade_cost_kut')->default(0);
            $table->unsignedInteger('upgrade_seconds')->default(0);
            $table->timestamps();

            $table->unique(['spell_type_id', 'level']);
        });

        Schema::create('player_spells', function (Blueprint $table) {
            $table->id();
            $table->foreignId('player_id')->constrained()->cascadeOnDelete();
            $table->foreignId('spell_type_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('level')->default(1);
            $table->unsignedInteger('ready')->default(0);
            $table->boolean('is_upgrading')->default(false);
            $table->timestamp('upgrade_ends_at')->nullable();
            $table->timestamps();

            $table->unique(['player_id', 'spell_type_id']);
        });

        Schema::create('brewing_queue', function (Blueprint $table) {
            $table->id();
            $table->foreignId('village_id')->constrained()->cascadeOnDelete();
            $table->foreignId('spell_type_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('quantity');
            $table->unsignedInteger('seconds_each');
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach ([
            'brewing_queue', 'player_spells', 'spell_levels', 'spell_types',
            'player_heroes', 'hero_levels', 'hero_types',
        ] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
