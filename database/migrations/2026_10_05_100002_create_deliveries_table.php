<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->foreignId('legal_text_version_id')->constrained()->cascadeOnDelete();
            $table->string('status')->index();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();

            $table->unique(['shop_id', 'legal_text_version_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deliveries');
    }
};
