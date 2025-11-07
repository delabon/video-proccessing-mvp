<?php

use App\Models\Video;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('video_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Video::class)->index()->constrained()->cascadeOnDelete()->cascadeOnUpdate();
            $table->string('name');
            $table->string('resolution'); // Enum
            $table->string('type', 50);
            $table->string('disk', 50);
            $table->string('path');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('video_variants');
    }
};
