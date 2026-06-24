<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guides', function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('profile')->default('phased');
            $table->unsignedBigInteger('active_version_id')->nullable();
            $table->timestamps();
        });

        Schema::create('guide_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('guide_id')->constrained('guides')->cascadeOnDelete();
            $table->unsignedInteger('number');
            $table->string('status')->default('draft');
            $table->json('definition')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->unsignedBigInteger('published_by')->nullable();
            $table->timestamps();

            $table->unique(['guide_id', 'number']);
        });

        Schema::create('guide_nodes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('guide_version_id')->constrained('guide_versions')->cascadeOnDelete();
            $table->string('type');
            $table->string('key');
            $table->json('config');
            $table->string('label')->nullable();
            $table->integer('position')->nullable();
            $table->timestamps();

            $table->unique(['guide_version_id', 'key']);
        });

        Schema::create('guide_edges', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('guide_version_id')->constrained('guide_versions')->cascadeOnDelete();
            $table->foreignId('from_node_id')->constrained('guide_nodes')->cascadeOnDelete();
            $table->foreignId('to_node_id')->constrained('guide_nodes')->cascadeOnDelete();
            $table->string('from_port')->default('out');
            $table->json('condition')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guide_edges');
        Schema::dropIfExists('guide_nodes');
        Schema::dropIfExists('guide_versions');
        Schema::dropIfExists('guides');
    }
};
