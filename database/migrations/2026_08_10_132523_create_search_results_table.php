<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('search_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('search_snapshot_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('position');
            $table->text('url');
            $table->char('url_hash', 64);
            $table->text('title')->nullable();
            $table->text('description')->nullable();
            $table->string('source')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->text('image_url')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index('url_hash');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('search_results');
    }
};
