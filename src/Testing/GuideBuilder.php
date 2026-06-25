<?php

declare(strict_types=1);

namespace ByJesper\DecisionSupport\Testing;

use ByJesper\DecisionSupport\Conditions\Condition;
use ByJesper\DecisionSupport\Definition\EdgeDefinition;
use ByJesper\DecisionSupport\Definition\GuideDefinition;
use ByJesper\DecisionSupport\Definition\NodeDefinition;
use ByJesper\DecisionSupport\NodeTypes\DecisionNode;
use ByJesper\DecisionSupport\NodeTypes\FactNode;
use ByJesper\DecisionSupport\NodeTypes\OutcomeNode;
use ByJesper\DecisionSupport\NodeTypes\QuestionNode;
use ByJesper\DecisionSupport\Profiles\FreeformProfile;

/**
 * Assembles a {@see GuideDefinition} fluently, without the editor or the
 * database. The entry node defaults to the first node added.
 */
final class GuideBuilder
{
    private int $version = 1;

    private string $profile = FreeformProfile::KEY;

    private ?string $entryNode = null;

    /** @var list<NodeDefinition> */
    private array $nodes = [];

    /** @var list<EdgeDefinition> */
    private array $edges = [];

    public function __construct(private readonly string $guideKey) {}

    public static function make(string $guideKey): self
    {
        return new self($guideKey);
    }

    public function version(int $version): self
    {
        $this->version = $version;

        return $this;
    }

    public function profile(string $profile): self
    {
        $this->profile = $profile;

        return $this;
    }

    public function entry(string $nodeKey): self
    {
        $this->entryNode = $nodeKey;

        return $this;
    }

    /**
     * @param  'boolean'|'select'|'date'|'text'|'number'  $inputType
     * @param  list<array{value: string, label: string, label_i18n?: array<string, string>}>  $options
     * @param  array<string, mixed>  $i18n  e.g. `['prompt_i18n' => ['da' => '…']]`, merged into config
     */
    public function question(string $key, string $prompt, string $fact, string $inputType = 'boolean', array $options = [], array $i18n = []): self
    {
        return $this->node(new NodeDefinition($key, QuestionNode::KEY, [
            'prompt' => $prompt,
            'fact' => $fact,
            'inputType' => $inputType,
            'options' => $options,
            ...$i18n,
        ]));
    }

    public function fact(string $key, string $fact): self
    {
        return $this->node(new NodeDefinition($key, FactNode::KEY, ['fact' => $fact]));
    }

    public function decision(string $key, ?string $fact = null): self
    {
        return $this->node(new NodeDefinition($key, DecisionNode::KEY, $fact === null ? [] : ['fact' => $fact]));
    }

    /**
     * @param  list<string>  $warnings
     * @param  array<string, mixed>  $i18n  e.g. `['verdict_i18n' => ['da' => '…']]`, merged into config
     */
    public function outcome(string $key, string $verdict, ?string $text = null, array $warnings = [], array $i18n = []): self
    {
        return $this->node(new NodeDefinition($key, OutcomeNode::KEY, [
            'verdict' => $verdict,
            'text' => $text,
            'warnings' => $warnings,
            ...$i18n,
        ]));
    }

    public function node(NodeDefinition $node): self
    {
        $this->nodes[] = $node;
        $this->entryNode ??= $node->key;

        return $this;
    }

    public function edge(string $from, string $to, string $port = 'out', ?Condition $condition = null): self
    {
        $this->edges[] = new EdgeDefinition($from, $port, $to, $condition);

        return $this;
    }

    public function build(): GuideDefinition
    {
        return new GuideDefinition(
            guideKey: $this->guideKey,
            version: $this->version,
            profile: $this->profile,
            entryNode: $this->entryNode ?? '',
            nodes: $this->nodes,
            edges: $this->edges,
        );
    }
}
