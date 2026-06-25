---
name: decision-support-development
description: Build and run decision-support guides with byjesper/laravel-decision-support — author guides in code, implement and register a FactProvider, evaluate runs with GuideRunner, validate/publish drafts, and test guides headless. Use when creating guide flows, fact providers, custom node types, or conditions, or when debugging suspended/unknown runs.
---

# Decision Support Development

A guide is a directed graph evaluated by a **resumable, read-only** interpreter.
It asks questions, resolves facts through a host-owned provider, branches on
conditions, and ends in an outcome. The engine performs **no side effects** —
react to its events instead.

## When to use this skill

Use this when you are: authoring a guide (in code or for the DB editor),
implementing a `FactProvider`, adding a custom `NodeType`, writing edge
conditions, validating/publishing a draft, or diagnosing why a run suspends or
reaches an `unknown` outcome.

## Key model

- **Read-only.** Nodes evaluate and advise; they perform no side effects. Do host
  work (audit, notifications, persistence) in listeners for the package events
  (`GuideRunStarted`, `GuidePublished`, `GuideDrafted`, `NodeChanged`).
- **Draft vs. snapshot.** Drafts are normalized `guide_nodes`/`guide_edges` rows;
  publishing freezes them into an immutable JSON snapshot in
  `guide_versions.definition`. The runtime only ever reads a `GuideDefinition`
  (`$version->toDefinition()`).
- **Profiles.** A guide declares `phased` (questions → facts → decisions →
  outcomes, no backward edges) or `freeform`; the profile is enforced at publish.
- **Node types are pluggable.** The four built-ins are `question`, `fact`,
  `decision`, `outcome`; register custom ones on `DecisionSupportManager` rather
  than special-casing host code.

## 1. Implement a FactProvider (the dev ↔ domain boundary)

Declare the facts a guide may branch on, and resolve them at run time. Return a
`FactValue` when known, or a `PendingInteraction` to suspend for host input.

```php
use ByJesper\DecisionSupport\Contracts\FactProvider;
use ByJesper\DecisionSupport\Enums\FactType;
use ByJesper\DecisionSupport\Facts\{FactDefinition, FactValue, FactVocabulary, PendingInteraction};
use ByJesper\DecisionSupport\Runtime\GuideContext;

final class EmploymentFactProvider implements FactProvider
{
    public function vocabulary(): FactVocabulary
    {
        return new FactVocabulary([
            new FactDefinition('tenure_years', FactType::Number),
            new FactDefinition('contract_type', FactType::Enum, ['permanent', 'fixed_term']),
        ]);
    }

    public function resolve(string $fact, GuideContext $context): FactValue|PendingInteraction
    {
        return match ($fact) {
            'tenure_years'  => new FactValue($this->tenureFor($context)),
            'contract_type' => new FactValue($this->contractFor($context)),
        };
    }
}
```

Register one provider per guide key in your service provider's `boot()`:

```php
app(\ByJesper\DecisionSupport\DecisionSupportManager::class)
    ->registerProvider('employment-eligibility', EmploymentFactProvider::class);
```

## 2. Author a guide in code

Use `GuideBuilder` to assemble a `GuideDefinition` without the database editor —
ideal for seeders and code-authoring consumers. The entry node defaults to the
first node added.

```php
use ByJesper\DecisionSupport\Conditions\Condition;
use ByJesper\DecisionSupport\Enums\Operator;
use ByJesper\DecisionSupport\Testing\GuideBuilder;

$definition = GuideBuilder::make('employment-eligibility')
    ->profile('phased')
    ->question('q_employed', 'Are you employed?', 'employed', 'boolean')
    ->fact('f_tenure', 'tenure_years')
    ->decision('d_tenure')
    ->outcome('senior', 'Eligible (senior)')
    ->outcome('junior', 'Eligible (junior)')
    ->outcome('no', 'Not eligible')
    ->edge('q_employed', 'f_tenure', 'true')
    ->edge('q_employed', 'no', 'false')
    ->edge('f_tenure', 'd_tenure')
    ->edge('d_tenure', 'senior', 'out', Condition::structured('tenure_years', Operator::GreaterThanOrEqual, 5))
    ->edge('d_tenure', 'junior', 'out', Condition::always())
    ->build();
```

Node/edge rules:
- `question` ports: boolean → `true`/`false`; select → one port per option value;
  date/text/number → a single `out` port.
- `fact` and `decision` emit a single `out` port; `decision` routing is done by
  the **edge conditions** (first match wins; an `always` edge is the default/else,
  an `unknown('fact')` edge matches when a fact is unresolved).
- `outcome` is terminal.

## 3. Run it (headless or in a UI)

```php
use ByJesper\DecisionSupport\Runtime\GuideRunner;

$runner = app(GuideRunner::class);
$state  = $runner->start($definition);          // suspends at q_employed

if ($state->isSuspended()) {
    // render $state->pendingInteraction (prompt, inputType, options)
    $state = $runner->advance($definition, $state, true);
}

$state->isCompleted();          // true
$state->outcome?->verdict;      // 'Eligible (senior)'
$state->path;                   // reached node keys, for Mermaid highlighting
```

`RunState` is serializable (`$state->toArray()` / `RunState::fromArray()`), so
store it in the session or a Livewire property across a suspension.

## 4. Validate and publish a draft

`PublishValidator` rejects structurally broken guides; `GuidePublisher` validates
then freezes the draft rows into the immutable snapshot the runtime reads.

```php
$result = app(\ByJesper\DecisionSupport\Publishing\GuidePublisher::class)->publish($version);
if ($result->fails()) {
    // surface $result->errors (code, message, nodeKey) inline — nothing was published
}
```

Publishing also points `guides.active_version_id` at the version and **seeds the
guide's `extra_attributes` from it** (see below).

### Extra attributes (consumer metadata)

`Guide` and `GuideVersion` both have a nullable `extra_attributes` JSON column
(cast to `array`) for arbitrary host metadata — typically `['permissions' => [...]]`
for gating. The **guide** copy is authoritative; the **version** copy is an editable
working copy that publishing copies up onto the guide. The engine stores/copies but
never enforces — gate in the host `Guide` policy by reading
`$guide->extra_attributes['permissions']`.

## 5. Test guides

Pull in `InteractsWithGuides` and `FakeFactProvider` for fast, DB-free tests.

```php
use ByJesper\DecisionSupport\Testing\{FakeFactProvider, GuideBuilder, InteractsWithGuides};

uses(InteractsWithGuides::class);

it('reaches the senior outcome', function () {
    $guide  = GuideBuilder::make('employment-eligibility')/* … */->build();
    $runner = $this->decisionRunner('employment-eligibility', FakeFactProvider::make()->with('tenure_years', 6));

    $state = $runner->start($guide);
    $state = $runner->advance($guide, $state, true);

    $this->assertReachesOutcome($state, 'Eligible (senior)');
});
```

Available assertions: `assertReachesOutcome`, `assertReachesUnknown`,
`assertSuspendsForQuestion`.

## Render a diagram

`MermaidRenderer` turns a definition (plus an optional `RunState`) into Mermaid
`flowchart` source — pass the run state to highlight the reached path.

```php
$mermaid = app(\ByJesper\DecisionSupport\Mermaid\MermaidRenderer::class)->render($definition, $state);
```

## Conventions

- Never put host side effects in a node type — listen to package events
  (`GuideRunStarted`, `GuidePublished`, `GuideDrafted`, `NodeChanged`).
- Only branch on facts declared in the provider's vocabulary; publish validation
  enforces this, including expression conditions (linted against the vocabulary).
- Always provide a default (`always`) or `unknown` branch out of a decision so
  unresolved facts route somewhere — the runtime never throws.
