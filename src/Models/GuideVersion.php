<?php

declare(strict_types=1);

namespace ByJesper\DecisionSupport\Models;

use ByJesper\DecisionSupport\Definition\EdgeDefinition;
use ByJesper\DecisionSupport\Definition\GuideDefinition;
use ByJesper\DecisionSupport\Definition\NodeDefinition;
use ByJesper\DecisionSupport\Enums\VersionStatus;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $guide_id
 * @property int $number
 * @property VersionStatus $status
 * @property array<string, mixed>|null $definition
 * @property Carbon|null $published_at
 * @property int|null $published_by
 * @property-read Guide $guide
 * @property-read Collection<int, GuideNode> $nodes
 * @property-read Collection<int, GuideEdge> $edges
 */
final class GuideVersion extends Model
{
    protected $guarded = [];

    /** @return array<string, string> */
    #[\Override]
    protected function casts(): array
    {
        return [
            'status' => VersionStatus::class,
            'definition' => 'array',
            'published_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Guide, $this> */
    public function guide(): BelongsTo
    {
        return $this->belongsTo(Guide::class);
    }

    /** @return HasMany<GuideNode, $this> */
    public function nodes(): HasMany
    {
        return $this->hasMany(GuideNode::class);
    }

    /** @return HasMany<GuideEdge, $this> */
    public function edges(): HasMany
    {
        return $this->hasMany(GuideEdge::class);
    }

    /**
     * The runtime-facing definition: the immutable published snapshot when one
     * exists, otherwise assembled live from the draft rows (used by the editor
     * preview and the publish validator).
     */
    public function toDefinition(): GuideDefinition
    {
        if ($this->status === VersionStatus::Published && $this->definition !== null) {
            return GuideDefinition::fromArray($this->definition);
        }

        return $this->buildFromRows();
    }

    private function buildFromRows(): GuideDefinition
    {
        $this->loadMissing(['guide', 'nodes', 'edges']);

        $nodes = $this->nodes->all();
        $edges = $this->edges->all();

        /** @var array<int, string> $keyById */
        $keyById = [];
        foreach ($nodes as $node) {
            $keyById[$node->id] = $node->key;
        }

        $incoming = [];
        $edgeDefinitions = [];
        foreach ($edges as $edge) {
            $from = $keyById[$edge->from_node_id] ?? '';
            $to = $keyById[$edge->to_node_id] ?? '';
            $incoming[$to] = true;
            $edgeDefinitions[] = new EdgeDefinition($from, $edge->from_port, $to, $edge->conditionObject());
        }

        $nodeDefinitions = array_map(static fn (GuideNode $n): NodeDefinition => $n->toDefinition(), $nodes);

        $entry = '';
        foreach ($nodeDefinitions as $definition) {
            if (! isset($incoming[$definition->key])) {
                $entry = $definition->key;
                break;
            }
        }

        return new GuideDefinition(
            guideKey: $this->guide->key,
            version: $this->number,
            profile: $this->guide->profile,
            entryNode: $entry,
            nodes: array_values($nodeDefinitions),
            edges: $edgeDefinitions,
        );
    }
}
