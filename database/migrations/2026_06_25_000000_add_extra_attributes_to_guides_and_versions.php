<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds a nullable `extra_attributes` JSON column to `guides` and
 * `guide_versions` so consumers can attach arbitrary metadata (e.g. the
 * permissions required to see/run a guide). Additive and nullable, so existing
 * rows and the already-published create migration are untouched.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('guides', function (Blueprint $table): void {
            $table->json('extra_attributes')->nullable()->after('active_version_id');
        });

        Schema::table('guide_versions', function (Blueprint $table): void {
            $table->json('extra_attributes')->nullable()->after('published_by');
        });
    }

    public function down(): void
    {
        Schema::table('guides', function (Blueprint $table): void {
            $table->dropColumn('extra_attributes');
        });

        Schema::table('guide_versions', function (Blueprint $table): void {
            $table->dropColumn('extra_attributes');
        });
    }
};
