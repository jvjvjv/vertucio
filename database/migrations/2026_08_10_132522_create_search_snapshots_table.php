<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('search_snapshots', function (Blueprint $table) {
            $table->id();
            $table->string('provider');
            $table->string('vertical');
            $table->string('query');
            $table->timestamp('fetched_at');
            $table->unsignedInteger('result_count');
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['provider', 'vertical', 'fetched_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('search_snapshots');
    }
};
