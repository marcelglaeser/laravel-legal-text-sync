<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legal_texts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->timestamps();

            $table->unique(['user_id', 'type']);
        });

        Schema::create('legal_text_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('legal_text_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->longText('content');
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->unique(['legal_text_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legal_text_versions');
        Schema::dropIfExists('legal_texts');
    }
};
