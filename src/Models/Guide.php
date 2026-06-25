<?php

declare(strict_types=1);

namespace ByJesper\DecisionSupport\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $key
 * @property string $name
 * @property string|null $description
 * @property string $profile
 * @property int|null $active_version_id
 * @property array<string, mixed>|null $extra_attributes
 */
final class Guide extends Model
{
    protected $guarded = [];

    /** @return array<string, string> */
    #[\Override]
    protected function casts(): array
    {
        return [
            'extra_attributes' => 'array',
        ];
    }

    /** @return HasMany<GuideVersion, $this> */
    public function versions(): HasMany
    {
        return $this->hasMany(GuideVersion::class);
    }

    /** @return BelongsTo<GuideVersion, $this> */
    public function activeVersion(): BelongsTo
    {
        return $this->belongsTo(GuideVersion::class, 'active_version_id');
    }
}
