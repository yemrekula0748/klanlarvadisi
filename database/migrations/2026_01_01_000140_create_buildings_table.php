<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('buildings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('village_id')->constrained()->cascadeOnDelete();
            $table->foreignId('building_type_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('level')->default(1);
            $table->unsignedTinyInteger('x');
            $table->unsignedTinyInteger('y');
            $table->boolean('is_constructing')->default(false);
            $table->unsignedSmallInteger('target_level')->nullable();
            $table->timestamp('construction_ends_at')->nullable();
            $table->unsignedBigInteger('stored_amount')->default(0); // kaynak binalarında biriken
            $table->timestamp('collected_at')->nullable();
            $table->timestamps();

            $table->index(['village_id', 'building_type_id']);
            $table->index('construction_ends_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('buildings');
    }
};
