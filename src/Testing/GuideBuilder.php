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
     * @param  bool  $required  mandate a non-blank answer for a free (text/date/number) question
     */
    public function question(string $key, string $prompt, string $fact, string $inputType = 'boolean', array $options = [], array $i18n = [], bool $required = false): self
    {
        return $this->node(new NodeDefinition($key, QuestionNode::KEY, [
            'prompt' => $prompt,
            'fact' => $fact,
            'inputType' => $inputType,
            'options' => $options,
            'required' => $required,
            ...$i18n,
        ]));
    }

    /**
     * @param  array<string, string>  $labelI18n  per-locale display labels (written to `label_i18n`), surfaced in the Mermaid diagram
     */
    public function fact(string $key, string $fact, ?string $label = null, array $labelI18n = []): self
    {
        return $this->node(new NodeDefinition($key, FactNode::KEY, $this->withLabelI18n(['fact' => $fact], $labelI18n), $label));
    }

    /**
     * @param  array<string, string>  $labelI18n  per-locale display labels (written to `label_i18n`), surfaced in the Mermaid diagram
     */
    public function decision(string $key, ?string $fact = null, ?string $label = null, array $labelI18n = []): self
    {
        $config = $fact === null ? [] : ['fact' => $fact];

        return $this->node(new NodeDefinition($key, DecisionNode::KEY, $this->withLabelI18n($config, $labelI18n), $label));
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  array<string, string>  $labelI18n
     * @return array<string, mixed>
     */
    private function withLabelI18n(array $config, array $labelI18n): array
    {
        return $labelI18n === [] ? $config : [...$config, 'label_i18n' => $labelI18n];
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

    /**
     * @param  array<string, string>  $labelI18n  per-locale edge labels for the diagram (override the derived condition/port label)
     */
    public function edge(string $from, string $to, string $port = 'out', ?Condition $condition = null, ?string $label = null, array $labelI18n = []): self
    {
        $this->edges[] = new EdgeDefinition($from, $port, $to, $condition, $label, $labelI18n);

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
