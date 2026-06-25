<?php

declare(strict_types=1);

namespace ByJesper\DecisionSupport\NodeTypes;

use ByJesper\DecisionSupport\Contracts\NodeType;
use ByJesper\DecisionSupport\Definition\NodeDefinition;
use ByJesper\DecisionSupport\Runtime\EvaluationContext;
use ByJesper\DecisionSupport\Runtime\Interaction;
use ByJesper\DecisionSupport\Runtime\LocaleResolver;
use ByJesper\DecisionSupport\Runtime\NodeResult;
use ByJesper\DecisionSupport\Support\PortSet;
use ByJesper\DecisionSupport\Validation\ValidationResult;

/**
 * Asks the user a question and stores the answer as both an answer and a fact.
 * Suspends to collect input the first time it is reached; on resume it routes
 * through a port derived from the answer (true/false for booleans, the chosen
 * value for selects, otherwise a single `out` port for free text/date/number).
 */
final class QuestionNode implements NodeType
{
    public const string KEY = 'question';

    /** @var list<string> */
    private const array INPUT_TYPES = ['boolean', 'select', 'date', 'text', 'number'];

    #[\Override]
    public function key(): string
    {
        return self::KEY;
    }

    #[\Override]
    public function configSchema(): array
    {
        return [
            'prompt' => ['type' => 'string', 'required' => true, 'help' => 'The question shown to the person running the guide.'],
            'fact' => ['type' => 'string', 'required' => true, 'help' => 'The fact name the answer is stored under, and that edge conditions reference.'],
            'inputType' => ['type' => 'enum', 'values' => self::INPUT_TYPES, 'required' => true, 'help' => 'How the answer is collected. boolean routes true/false; select routes by chosen value; date/text/number route through a single "out" port.'],
            'options' => ['type' => 'list', 'required' => false, 'help' => 'For a select question: the choices, one "value:label" per line.'],
        ];
    }

    #[\Override]
    public function ports(NodeDefinition $node): PortSet
    {
        return match ($this->inputType($node)) {
            'boolean' => PortSet::of('true', 'false'),
            'select' => PortSet::of(...array_map(
                static fn (array $o): string => $o['value'],
                $this->options($node),
            )),
            default => PortSet::of('out'),
        };
    }

    #[\Override]
    public function validate(NodeDefinition $node): ValidationResult
    {
        $result = ValidationResult::valid();

        if (! is_string($node->config('prompt')) || $node->config('prompt') === '') {
            $result = $result->merge(ValidationResult::error('question.prompt_required', 'Question node requires a prompt.', $node->key));
        }

        if (! is_string($node->config('fact')) || $node->config('fact') === '') {
            $result = $result->merge(ValidationResult::error('question.fact_required', 'Question node requires a fact name to store the answer.', $node->key));
        }

        if (! in_array($this->inputType($node), self::INPUT_TYPES, true)) {
            $result = $result->merge(ValidationResult::error('question.input_type_invalid', 'Question node has an invalid input type.', $node->key));
        }

        if ($this->inputType($node) === 'select' && $this->options($node) === []) {
            $result = $result->merge(ValidationResult::error('question.options_required', 'A select question requires at least one option.', $node->key));
        }

        return $result;
    }

    #[\Override]
    public function evaluate(NodeDefinition $node, EvaluationContext $context): NodeResult
    {
        if (! $context->hasInput()) {
            return NodeResult::suspend($this->interaction($node, $context));
        }

        $fact = $this->factName($node);
        $type = $this->inputType($node);
        $value = $this->coerce($type, $context->input);
        $port = $this->portFor($type, $value);

        return NodeResult::advance($port, [
            'answers' => [$fact => $value],
            'facts' => [$fact => $value],
        ]);
    }

    private function interaction(NodeDefinition $node, EvaluationContext $context): Interaction
    {
        $resolver = $context->localeResolver();
        $basePrompt = is_string($node->config('prompt')) ? $node->config('prompt') : $node->key;

        return new Interaction(
            nodeKey: $node->key,
            kind: 'question',
            prompt: $resolver->localizedString($this->promptI18n($node), $basePrompt),
            inputType: $this->inputType($node),
            options: $this->localizedOptions($node, $resolver),
        );
    }

    /** @return array<string, mixed> */
    private function promptI18n(NodeDefinition $node): array
    {
        $map = $node->config('prompt_i18n');

        return is_array($map) ? $map : [];
    }

    /**
     * Options with each label resolved through the locale chain (`label_i18n`).
     *
     * @return list<array{value: string, label: string}>
     */
    private function localizedOptions(NodeDefinition $node, LocaleResolver $resolver): array
    {
        $options = $node->config('options');
        if (! is_array($options)) {
            return [];
        }

        $normalized = [];
        foreach ($options as $option) {
            if (is_array($option) && isset($option['value']) && is_scalar($option['value'])) {
                $value = (string) $option['value'];
                $base = isset($option['label']) && is_scalar($option['label']) ? (string) $option['label'] : $value;
                $i18n = isset($option['label_i18n']) && is_array($option['label_i18n']) ? $option['label_i18n'] : [];
                $normalized[] = ['value' => $value, 'label' => $resolver->localizedString($i18n, $base)];
            }
        }

        return $normalized;
    }

    private function coerce(string $type, mixed $input): mixed
    {
        return match ($type) {
            'boolean' => filter_var($input, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? (bool) $input,
            'number' => is_numeric($input) ? $input + 0 : $input,
            default => is_scalar($input) ? (string) $input : $input,
        };
    }

    private function portFor(string $type, mixed $value): string
    {
        return match ($type) {
            'boolean' => $value === true ? 'true' : 'false',
            'select' => is_scalar($value) ? (string) $value : 'out',
            default => 'out',
        };
    }

    private function factName(NodeDefinition $node): string
    {
        $fact = $node->config('fact');

        return is_string($fact) && $fact !== '' ? $fact : $node->key;
    }

    /** @return 'boolean'|'select'|'date'|'text'|'number' */
    private function inputType(NodeDefinition $node): string
    {
        $type = $node->config('inputType');

        /** @var 'boolean'|'select'|'date'|'text'|'number' */
        return is_string($type) && in_array($type, self::INPUT_TYPES, true) ? $type : 'text';
    }

    /** @return list<array{value: string, label: string}> */
    private function options(NodeDefinition $node): array
    {
        $options = $node->config('options');
        if (! is_array($options)) {
            return [];
        }

        $normalized = [];
        foreach ($options as $option) {
            if (is_array($option) && isset($option['value']) && is_scalar($option['value'])) {
                $value = (string) $option['value'];
                $label = isset($option['label']) && is_scalar($option['label']) ? (string) $option['label'] : $value;
                $normalized[] = ['value' => $value, 'label' => $label];
            }
        }

        return $normalized;
    }
}
