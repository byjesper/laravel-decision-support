<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('guide_edges', function (Blueprint $table): void {
            // Optional human/localized edge label for the diagram. `label` is the
            // base string; `label_i18n` is a per-locale map. Null/absent => the
            // renderer derives a label from the port/condition (the prior behaviour).
            $table->string('label')->nullable()->after('from_port');
            $table->json('label_i18n')->nullable()->after('label');
        });
    }

    public function down(): void
    {
        Schema::table('guide_edges', function (Blueprint $table): void {
            $table->dropColumn(['label', 'label_i18n']);
        });
    }
};
