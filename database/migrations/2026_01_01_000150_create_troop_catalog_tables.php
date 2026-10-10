<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('troop_types', function (Blueprint $table) {
            $table->id();
            $table->string('key', 48)->unique();
            $table->string('name', 48);
            $table->string('category', 16); // yaya, atli, kusatma, hava, buyu, karanlik
            $table->text('description');
            $table->text('lore');
            $table->string('sprite', 64);
            $table->unsignedSmallInteger('housing')->default(1);
            $table->unsignedInteger('train_seconds')->default(20);
            $table->unsignedBigInteger('train_cost_gold')->default(0);
            $table->unsignedBigInteger('train_cost_grain')->default(0);
            $table->unsignedBigInteger('train_cost_iron')->default(0);
            $table->unsignedBigInteger('train_cost_kut')->default(0);
            $table->unsignedSmallInteger('max_level')->default(1);
            $table->string('preferred_target', 16)->default('yok'); // yok, savunma, duvar, kaynak, otag
            $table->decimal('target_damage_multiplier', 4, 1)->default(1);
            $table->string('damage_type', 12)->default('tek');      // tek, alan, zincir
            $table->decimal('move_speed', 5, 2)->default(16);       // hücre/saniye * 10
            $table->decimal('attack_range', 5, 2)->default(0.6);
            $table->decimal('attack_speed', 5, 2)->default(1);      // saniye/vuruş
            $table->decimal('splash_radius', 5, 2)->default(0);
            $table->boolean('is_air')->default(false);
            $table->boolean('targets_air')->default(false);
            $table->unsignedSmallInteger('unlock_kisla_level')->default(1);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('troop_levels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('troop_type_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('level');
            $table->unsignedInteger('hitpoints');
            $table->unsignedInteger('damage_per_second');
            $table->unsignedBigInteger('upgrade_cost_iron')->default(0);
            $table->unsignedBigInteger('upgrade_cost_kut')->default(0);
            $table->unsignedInteger('upgrade_seconds')->default(0);
            $table->unsignedSmallInteger('requires_demirci_level')->default(1);
            $table->json('extra')->nullable();
            $table->timestamps();

            $table->unique(['troop_type_id', 'level']);
        });

        Schema::create('player_troops', function (Blueprint $table) {
            $table->id();
            $table->foreignId('player_id')->constrained()->cascadeOnDelete();
            $table->foreignId('troop_type_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('level')->default(1);
            $table->unsignedInteger('ready')->default(0);
            $table->boolean('is_upgrading')->default(false);
            $table->timestamp('upgrade_ends_at')->nullable();
            $table->timestamps();

            $table->unique(['player_id', 'troop_type_id']);
        });

        Schema::create('training_queue', function (Blueprint $table) {
            $table->id();
            $table->foreignId('village_id')->constrained()->cascadeOnDelete();
            $table->foreignId('troop_type_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('quantity');
            $table->unsignedInteger('seconds_each');
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->index(['village_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('training_queue');
        Schema::dropIfExists('player_troops');
        Schema::dropIfExists('troop_levels');
        Schema::dropIfExists('troop_types');
    }
};
