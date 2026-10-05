<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legal_template_versions', function (Blueprint $table) {
            $table->id();
            $table->string('type');
            $table->unsignedInteger('version');
            $table->longText('content');
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->unique(['type', 'version']);
        });

        Schema::table('legal_text_versions', function (Blueprint $table) {
            $table->foreignId('legal_template_version_id')->after('legal_text_id')->constrained()->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('legal_text_versions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('legal_template_version_id');
        });

        Schema::dropIfExists('legal_template_versions');
    }
};
