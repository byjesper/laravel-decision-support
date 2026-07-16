<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('guides', function (Blueprint $table): void {
            // `active_version_id` is resolved via Guide::activeVersion() on
            // effectively every guide load, but the create migration left it a
            // bare column with no index. Add one. (No FK: it would create a
            // circular reference with guide_versions.guide_id, and SQLite —
            // used by the test suite — cannot ALTER-ADD a foreign key anyway.
            // Referential integrity is de-facto maintained by GuidePublisher
            // being the sole writer of the column.)
            $table->index('active_version_id');
        });
    }

    public function down(): void
    {
        Schema::table('guides', function (Blueprint $table): void {
            $table->dropIndex(['active_version_id']);
        });
    }
};
