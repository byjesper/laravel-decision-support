# Laravel Decision Support

> Read-only, DB-backed decision-support guide engine for Laravel — graph-first,
> with a resumable evaluator, structured & expression conditions, publish-time
> validation, and Mermaid rendering.

A **decision-support guide** is a directed graph that asks questions, resolves
domain facts, branches on conditions, and ends in an outcome (a verdict plus
reasoning and warnings). This package is the **engine** that evaluates such
guides. It is deliberately **read-only**: it advises, it does not act — no status
mutations, no jobs, no writes to your data. Side effects belong in your
application, wired through events.

It is built **graph-first** with a **resumable interpreter**, so a run can pause
to ask the user something and resume later from a serialized state — perfect for
a Livewire wizard, an API, or a fully headless/offline evaluation.

- **Framework-only.** Depends on `illuminate/contracts`, `illuminate/support`,
  and `symfony/expression-language`. No UI is assumed.
- **Two storage shapes.** Drafts are normalized rows (editable, auditable);
  published versions are an immutable JSON snapshot the runtime reads.
- **Seams, not coupling.** Audit, authorization, translations, and help are host
  concerns — the engine exposes events and contracts instead of depending on any
  of them.

> Looking for the in-app tree editor and runner UI? That ships separately as
> `byjesper/laravel-decision-support-filament` (built on this engine).

## Requirements

- PHP 8.4+
- Laravel 13 (`illuminate/*` `^13.0`)

## Installation

```bash
composer require byjesper/laravel-decision-support
```

The service provider is auto-discovered. Publish the config and/or migrations if
you need them:

```bash
php artisan vendor:publish --tag=decision-support-config
php artisan vendor:publish --tag=decision-support-migrations
php artisan migrate
```

> The package also loads its migrations automatically, so for app-internal use
> you can just run `php artisan migrate` without publishing them.

## Concepts at a glance

| Concept | What it is |
| --- | --- |
| **Node type** | A kind of node the engine can evaluate. Built-ins: `question`, `fact`, `decision`, `outcome`. Custom types are registered by hosts. |
| **Fact provider** | The developer-owned boundary. Declares the *vocabulary* of facts a guide may branch on, and resolves them at run time. One per guide. |
| **Condition** | An edge guard: *structured* (`fact` + operator + value) by default, or an *expression* (`symfony/expression-language`) as an advanced escape hatch. |
| **Guide definition** | The immutable, runtime-facing snapshot of a guide (nodes + edges + entry). The runner only ever reads this. |
| **Run state** | A serializable value object capturing where a run is: current node, status, answers/facts, reached path, pending interaction, or final outcome. |
| **Profile** | A publish-time shape constraint: `phased` (questions → facts → decisions → outcomes) or `freeform`. |

## Quick start (headless)

Everything below works without a database or UI — ideal for tests and
code-authoring consumers.

### 1. Implement a fact provider

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
        ]);
    }

    public function resolve(string $fact, GuideContext $context): FactValue|PendingInteraction
    {
        return new FactValue($this->lookupTenure($context));
        // ...or `new PendingInteraction($interaction)` to suspend for host input.
    }
}
```

Register it (one provider per guide key) in a service provider's `boot()`:

```php
use ByJesper\DecisionSupport\DecisionSupportManager;

app(DecisionSupportManager::class)
    ->registerProvider('employment-eligibility', EmploymentFactProvider::class);
```

### 2. Author a guide

`GuideBuilder` assembles a definition fluently. The entry node defaults to the
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
    ->outcome('senior', 'Eligible (senior)', 'You qualify under the senior track.')
    ->outcome('junior', 'Eligible (junior)')
    ->outcome('no', 'Not eligible')
    ->edge('q_employed', 'f_tenure', 'true')
    ->edge('q_employed', 'no', 'false')
    ->edge('f_tenure', 'd_tenure')
    ->edge('d_tenure', 'senior', 'out', Condition::structured('tenure_years', Operator::GreaterThanOrEqual, 5))
    ->edge('d_tenure', 'junior', 'out', Condition::always())
    ->build();
```

### 3. Run it

```php
use ByJesper\DecisionSupport\Runtime\GuideRunner;

$runner = app(GuideRunner::class);

$state = $runner->start($definition);            // suspends at q_employed
$state->isSuspended();                           // true
$state->pendingInteraction?->prompt;             // 'Are you employed?'

$state = $runner->advance($definition, $state, true);   // answer the question

$state->isCompleted();                           // true
$state->outcome?->verdict;                        // 'Eligible (senior)'
$state->outcome?->warnings;                       // string[]
$state->path;                                     // ['q_employed', 'f_tenure', 'd_tenure', 'senior']
```

`start()` and `advance()` drive the run forward through automatic nodes
(`fact`, `decision`, `outcome`) and only hand control back when they need input
(a **suspension**) or finish (an **outcome**).

### Persisting a run across requests

`RunState` is a plain serializable value object — store it anywhere:

```php
session(['run' => $state->toArray()]);
// ...next request...
$state = RunState::fromArray(session('run'));
$state = $runner->advance($definition, $state, $userInput);
```

## Conditions

Edges are guarded by conditions. The default is **structured**; expressions are
opt-in and sandboxed to the fact vocabulary.

```php
use ByJesper\DecisionSupport\Conditions\Condition;
use ByJesper\DecisionSupport\Enums\Operator;

Condition::structured('tenure_years', Operator::GreaterThanOrEqual, 5);
Condition::expression('tenure_years >= 5 and contract_type == "permanent"');
Condition::always();              // default / else branch
Condition::unknown('tenure_years'); // matches only when the fact is unresolved
```

Operators: `=`, `!=`, `>`, `>=`, `<`, `<=`, `in`, `not_in`, `is_true`, `is_false`.

A `decision` node emits a single `out` port and lets its outgoing edges decide
the target: the first matching condition wins, with an `always` edge as the
default. Provide a default or `unknown` branch so unresolved facts always route
somewhere.

## Working with the database

Model a guide as `Guide → GuideVersion → GuideNode`/`GuideEdge`, then validate
and publish a draft. Publishing freezes the draft rows into the immutable
`definition` snapshot and points the guide's `active_version_id` at it.

```php
use ByJesper\DecisionSupport\Publishing\GuidePublisher;

$result = app(GuidePublisher::class)->publish($version);

if ($result->fails()) {
    // Nothing was published. Each error has a code, message, and optional nodeKey.
    foreach ($result->errors as $error) {
        logger()->warning($error->code, ['message' => $error->message, 'node' => $error->nodeKey]);
    }
}

$definition = $version->fresh()->toDefinition();   // the published snapshot
```

### Publish validation

`PublishValidator` rejects a draft *loudly* rather than letting a broken guide
reach the runtime. It checks:

- **Graph integrity** — a resolvable entry, no dangling edges, no orphan
  (unreachable) nodes, every declared port has an outgoing edge.
- **Termination** — the graph is acyclic, and every leaf is an `outcome` (so
  every path reaches a verdict).
- **Fact references** — every structured condition's fact is in the vocabulary;
  expression conditions are linted against it.
- **Per-node config** — e.g. a question needs a prompt; an outcome needs a verdict.
- **Profile rules** — e.g. `phased` forbids edges that move backwards across
  phases.

## Safety rails

The runtime **never throws on bad guide data**:

- A **missing fact** routes to a defined `unknown`/default branch.
- A **cycle** (a node re-entered on the same path) terminates with an `unknown`
  outcome.
- A **step budget** (`config('decision-support.max_steps')`, default `200`) caps
  runaway runs.

An `unknown` outcome (`$state->outcome->unknown === true`) signals a rail fired,
with the reason in its `text`/`warnings`.

## Rendering a diagram

`MermaidRenderer` is a pure function from a definition (plus an optional run
state) to Mermaid `flowchart` source — the same renderer powers an editor
preview and a runner view. Pass a `RunState` to highlight the reached path.

```php
use ByJesper\DecisionSupport\Mermaid\MermaidRenderer;

$mermaid = app(MermaidRenderer::class)->render($definition, $state);
```

## Extending the engine

Register everything on the `DecisionSupportManager` (typically in `boot()`):

```php
$manager = app(\ByJesper\DecisionSupport\DecisionSupportManager::class);

$manager->registerProvider('some-guide', SomeFactProvider::class); // fact provider per guide
$manager->registerNodeType(new MyCustomNode());                     // implements NodeType
$manager->registerProfile(new MyProfile());                         // implements GuideProfile
```

A custom **node type** implements `ByJesper\DecisionSupport\Contracts\NodeType`
and returns `NodeResult::advance()`, `::suspend()`, or `::terminate()` from
`evaluate()` — that is all the engine needs to fold it into the same resumable
loop as the built-ins.

## Events (host seams)

The engine emits events instead of depending on your audit/authorization stack.
Listen to these to wire side effects:

| Event | When |
| --- | --- |
| `GuideRunStarted` | A run begins (carries the initial `RunState`). |
| `GuidePublished` | A version is published. |
| `GuideDrafted` | A draft version is created. |
| `NodeChanged` | A node is edited. |

## Testing your guides

The package ships first-class test helpers (no DB, no editor required):

```php
use ByJesper\DecisionSupport\Testing\{FakeFactProvider, GuideBuilder, InteractsWithGuides};

uses(InteractsWithGuides::class);

it('reaches the senior outcome', function () {
    $guide  = GuideBuilder::make('employment-eligibility')/* ... */->build();
    $runner = $this->decisionRunner('employment-eligibility', FakeFactProvider::make()->with('tenure_years', 6));

    $state = $runner->advance($guide, $runner->start($guide), true);

    $this->assertReachesOutcome($state, 'Eligible (senior)');
});
```

Helpers: `decisionRunner()`, `assertReachesOutcome()`, `assertReachesUnknown()`,
`assertSuspendsForQuestion()`, plus `FakeFactProvider` (`->with()`, `->pending()`,
`->declare()`) and `GuideBuilder`. Outside PHPUnit, `GuideTester` exposes the
same helpers as a standalone object.

## Laravel Boost

This package ships a [Laravel Boost](https://laravel.com/docs/boost) **skill** —
`decision-support-development`
(`resources/boost/skills/decision-support-development/SKILL.md`). When a consuming
app runs `php artisan boost:install` (or `boost:update --discover`), Boost offers
to install it. It is loaded **on-demand** — only when the agent is actually
authoring guides, fact providers, node types, or conditions — so it adds no
upfront context cost to apps that aren't touching this engine.

> **Boost only discovers skills from _direct_ dependencies.** It reads
> `require`/`require-dev` in your application's root `composer.json` and does not
> walk transitive dependencies. If you pull this engine in only transitively (for
> example via `byjesper/laravel-decision-support-filament`), Boost never sees its
> skill. Since you use the engine's API (`FactProvider`, `GuideBuilder`,
> `Condition`, …) directly anyway, require it directly to get the skill:
>
> ```bash
> composer require byjesper/laravel-decision-support
> php artisan boost:update --discover   # select the engine to publish its skill
> ```

## Testing

```bash
composer test
```

This runs the full gate: guideline check, lint (Pint + Rector), static analysis
(Larastan level 8), 100% type coverage, and the unit, parallel, and integration
suites. Database-bound tests are tagged `->group('integration')` and run against
an in-memory SQLite connection.

## License

The MIT License (MIT). See [LICENSE.md](LICENSE.md).
