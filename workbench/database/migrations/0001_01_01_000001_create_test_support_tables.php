<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function down(): void
    {
        Schema::dropIfExists('reports');
        Schema::dropIfExists('posts');
        Schema::dropIfExists('offices');
    }

    public function up(): void
    {
        Schema::create('offices', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('posts', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->foreignId('user_id')->nullable();
            $table->foreignId('author_id')->nullable();
            $table->timestamps();
        });

        Schema::create('reports', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->foreignId('office_id')->nullable();
            $table->foreignId('user_id')->nullable();
            $table->timestamps();
        });
    }
};
