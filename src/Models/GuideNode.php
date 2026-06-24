<?php

declare(strict_types=1);

namespace ByJesper\DecisionSupport\Models;

use ByJesper\DecisionSupport\Definition\NodeDefinition;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $guide_version_id
 * @property string $type
 * @property string $key
 * @property array<string, mixed> $config
 * @property string|null $label
 * @property int|null $position
 */
final class GuideNode extends Model
{
    protected $guarded = [];

    /** @return array<string, string> */
    #[\Override]
    protected function casts(): array
    {
        return ['config' => 'array'];
    }

    /** @return BelongsTo<GuideVersion, $this> */
    public function version(): BelongsTo
    {
        return $this->belongsTo(GuideVersion::class, 'guide_version_id');
    }

    public function toDefinition(): NodeDefinition
    {
        return new NodeDefinition(
            key: $this->key,
            type: $this->type,
            config: $this->config,
            label: $this->label,
            position: $this->position,
        );
    }
}
