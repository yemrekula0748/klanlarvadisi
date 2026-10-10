<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('building_types', function (Blueprint $table) {
            $table->id();
            $table->string('key', 48)->unique();
            $table->string('name', 48);
            $table->string('category', 16); // otag, kaynak, depo, ordu, savunma, duvar, tuzak, ozel
            $table->string('produces', 12)->nullable(); // gold, grain, iron, kut
            $table->string('stores', 12)->nullable();
            $table->text('description');
            $table->text('lore');
            $table->string('sprite', 64);
            $table->unsignedSmallInteger('max_level')->default(1);
            $table->unsignedTinyInteger('grid_w')->default(3);
            $table->unsignedTinyInteger('grid_h')->default(3);
            $table->json('count_per_otag_level'); // [otagSeviyesi => izinliAdet]
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('building_levels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('building_type_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('level');
            $table->unsignedBigInteger('cost_gold')->default(0);
            $table->unsignedBigInteger('cost_grain')->default(0);
            $table->unsignedBigInteger('cost_iron')->default(0);
            $table->unsignedBigInteger('cost_kut')->default(0);
            $table->unsignedInteger('build_seconds')->default(0);
            $table->unsignedInteger('hitpoints')->default(100);
            $table->unsignedInteger('production_per_hour')->default(0);
            $table->unsignedBigInteger('storage_capacity')->default(0);
            $table->unsignedInteger('damage_per_second')->default(0);
            $table->decimal('attack_range', 5, 2)->default(0);
            $table->unsignedInteger('troop_capacity')->default(0);
            $table->unsignedSmallInteger('requires_otag_level')->default(1);
            $table->unsignedInteger('xp_reward')->default(0);
            $table->json('extra')->nullable();
            $table->timestamps();

            $table->unique(['building_type_id', 'level']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('building_levels');
        Schema::dropIfExists('building_types');
    }
};
