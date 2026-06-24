<?php

declare(strict_types=1);

namespace ByJesper\DecisionSupport\Models;

use ByJesper\DecisionSupport\Conditions\Condition;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $guide_version_id
 * @property int $from_node_id
 * @property int $to_node_id
 * @property string $from_port
 * @property array<string, mixed>|null $condition
 */
final class GuideEdge extends Model
{
    protected $guarded = [];

    /** @return array<string, string> */
    #[\Override]
    protected function casts(): array
    {
        return ['condition' => 'array'];
    }

    /** @return BelongsTo<GuideVersion, $this> */
    public function version(): BelongsTo
    {
        return $this->belongsTo(GuideVersion::class, 'guide_version_id');
    }

    /** @return BelongsTo<GuideNode, $this> */
    public function fromNode(): BelongsTo
    {
        return $this->belongsTo(GuideNode::class, 'from_node_id');
    }

    /** @return BelongsTo<GuideNode, $this> */
    public function toNode(): BelongsTo
    {
        return $this->belongsTo(GuideNode::class, 'to_node_id');
    }

    public function conditionObject(): ?Condition
    {
        return $this->condition === null ? null : Condition::fromArray($this->condition);
    }
}
