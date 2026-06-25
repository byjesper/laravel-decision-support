<?php

declare(strict_types=1);

use ByJesper\DecisionSupport\Contracts\NodeType;
use ByJesper\DecisionSupport\NodeTypes\DecisionNode;
use ByJesper\DecisionSupport\NodeTypes\FactNode;
use ByJesper\DecisionSupport\NodeTypes\OutcomeNode;
use ByJesper\DecisionSupport\NodeTypes\QuestionNode;

it('exposes help text for every built-in config field', function (): void {
    /** @var list<NodeType> $nodeTypes */
    $nodeTypes = [new QuestionNode, new FactNode, new DecisionNode, new OutcomeNode];

    foreach ($nodeTypes as $nodeType) {
        $schema = $nodeType->configSchema();

        expect($schema)->not->toBeEmpty();

        foreach ($schema as $spec) {
            expect($spec)->toBeArray()
                ->and($spec)->toHaveKey('help')
                ->and($spec['help'])->toBeString()->not->toBe('');
        }
    }
});
