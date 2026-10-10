<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('villages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('player_id')->constrained()->cascadeOnDelete();
            $table->string('name', 40);
            $table->unsignedInteger('world_x');
            $table->unsignedInteger('world_y');
            $table->string('biome', 24)->default('bozkir');
            $table->unsignedSmallInteger('otag_level')->default(1);

            $table->unsignedBigInteger('gold')->default(0);
            $table->unsignedBigInteger('grain')->default(0);
            $table->unsignedBigInteger('iron')->default(0);
            $table->unsignedBigInteger('kut')->default(0);
            $table->timestamp('resources_updated_at')->nullable();

            $table->unsignedInteger('builders')->default(2);
            $table->boolean('is_capital')->default(true);
            $table->timestamp('last_attacked_at')->nullable();
            $table->timestamps();

            $table->unique(['world_x', 'world_y']);
            $table->index('player_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('villages');
    }
};
