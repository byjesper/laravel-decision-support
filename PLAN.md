# Plan: `byjesper/laravel-decision-support` (+ `-filament`)

## Context

HR Tools (hrtools6) issue #92 asks for a DB-backed "Process Guides" engine: a
less-technical maintainer authors/edits branching decision-support guides in-app
(Filament tree editor + live Mermaid preview, draft/publish/validation), while
developers own a code-side **fact/predicate provider** that resolves the named
facts a guide branches on. The MindKey employment guide is its first consumer.

The motivation is bus-factor: the maintainer is leaving ITU, and a **named
non-developer** will maintain the guides afterward — so the GUI editor is
load-bearing. Separately, the maintainer's own platform **Spring** wants the
*engine* (code-authored guides, no GUI). Two consumers with opposite needs land
exactly on a core/companion split, and they share **no** cross-cutting
conventions (hrtools6 = owen-it audit + `Js::make` + `manifest.php` help; Spring
= custom append-only `AuditLog` + render-hooks + `HelpRegistry`), which forces
the package to depend on *neither* and expose seams instead.

No existing package fits: the Filament workflow plugins (Voodflow, Leek, etc.)
and durable/BPMN engines all execute side effects; state-machine packages mutate
an entity's status. This is **read-only decision support** — an unserved niche in
Laravel. `symfony/expression-language` is the one real component to reuse.

**Outcome:** two open-source packages — a framework-only engine and a Filament
editor/runner — built from `~/Development/packages/laravel-package-template`,
consumed by Spring (engine only) and hrtools6 (both). Built graph-first with a
resumable interpreter so the issue's "phased model" is just a validation profile
and the deferred "fully free-form" engine is later just new node types — no
rewrite.

**Out of scope:** the MindKey employment guide itself (Phase 2) — that is host
code in hrtools6 (a `FactProvider` impl + a permission + a seeded guide). This
plan delivers the engine it plugs into, and a sample guide in Spring to dogfood.

## Decisions (confirmed)

- **Names:** core `byjesper/laravel-decision-support` (`ByJesper\DecisionSupport\`);
  companion `byjesper/laravel-decision-support-filament`
  (`ByJesper\DecisionSupportFilament\`). Namespaces follow the template's
  "strip `laravel-`" rule (`laravel-skeleton` → `ByJesper\Skeleton`).
- **Conditions:** structured builder is primary (non-dev: fact + operator +
  value; boolean→2 ports, enum→N ports); `symfony/expression-language` is an
  opt-in advanced escape hatch per node.
- **Engine scope (v0.1):** graph-first **resumable** interpreter now; phased
  model shipped as a `GuideProfile`. Free-form = register more node types later.

## Package conventions (inherited from the template)

Copy `laravel-package-template/` per its `SETUP.md` and `sed` the placeholders
(`skeleton`→`decision-support`, `Skeleton`→`DecisionSupport`). Inherited as-is:
plain `Illuminate\Support\ServiceProvider` (no spatie/package-tools); Pest 4 +
Testbench 11; PHPStan/Larastan **level 8**, zero baseline additions; **100% type
coverage**; Pint + Rector; `declare(strict_types=1)` + full types + `#[\Override]`
everywhere; `->group('integration')` for DB-bound tests; immutable SemVer tags
from `main`; `composer test` is the full gate. Keep core deps minimal:
`illuminate/contracts`+`illuminate/support` `^13.0`, add **`symfony/expression-language`**.
Consumer-facing guidance goes in `resources/boost/`, not `.ai/guidelines/`.

---

## Package 1 — `byjesper/laravel-decision-support` (engine, framework-only)

### Data model (`database/migrations/*_create_decision_support_tables.php`)

- `guides` — id, key (unique), name, description, profile, active_version_id.
- `guide_versions` — id, guide_id, number, status (`draft|published|archived`),
  published_at/by, **`definition` (json snapshot)**.
- `guide_nodes` — id, version_id, type, key, config (json), label, position.
- `guide_edges` — id, version_id, from_node_id, **from_port**, to_node_id,
  condition (json).

Rationale: **draft = normalized rows** (Filament edits/audits/validates per row);
**published = immutable JSON snapshot** in `guide_versions.definition` that the
runtime always reads. Decouples authoring churn from runtime stability and gives
versioning for free as the node schema evolves.

### Models (`src/Models/`) + `src/Enums/VersionStatus.php`

`Guide`, `GuideVersion`, `GuideNode`, `GuideEdge` — typed Eloquent, json casts.
Models stay audit-agnostic (see Seams).

### Extension contracts (`src/Contracts/`)

```php
interface NodeType {                              // the free-form primitive
    public function key(): string;                // 'question'|'fact'|'decision'|'outcome'|custom
    public function configSchema(): array;        // drives the Filament form
    public function ports(NodeDefinition $n): PortSet;
    public function validate(NodeDefinition $n, GuideContext $c): ValidationResult;
    public function evaluate(NodeDefinition $n, RunState $s): NodeResult; // Advance|Suspend|Terminate
}
interface FactProvider {                          // the dev↔non-dev boundary
    public function vocabulary(): FactVocabulary; // [name,type(bool|enum|date…),outcomes] → editor choices
    public function resolve(string $fact, GuideContext $c): FactValue|PendingInteraction;
}
interface GuideProfile { public function rules(): array; }   // publish-time shape constraints
interface ConditionEvaluator { public function matches(Condition $c, GuideContext $ctx): bool; }
```

### Registries (`src/Registry/`) + entry point

`NodeTypeRegistry`, `FactProviderRegistry`, `GuideProfileRegistry` (singletons,
array+order like Spring's `HelpRegistry`/`WorkflowGuardRegistry`). A
`DecisionSupportManager` facade-style API: `registerProvider($guideKey, $cls)`,
`registerNodeType(...)`, `registerProfile(...)`. Hosts register in their provider
`boot()` (Spring's module→core registry pattern).

### Built-in node types (`src/NodeTypes/`)

`QuestionNode` (select/date/boolean/text → Suspend for input), `FactNode`
(invoke provider; Suspend on `PendingInteraction` e.g. MindKey "search & pick"),
`DecisionNode` (branch on a fact via conditions), `OutcomeNode` (verdict + text +
warnings → Terminate). These four = the issue's phased model; custom types are
registered by hosts.

### Runtime — resumable interpreter (`src/Runtime/`)

- `GuideContext` — answers + resolved facts.
- `RunState` — serializable readonly value object (visited path, context,
  current node, status); plain `toArray()/fromArray()` (no spatie dep) so it
  survives Livewire/session round-trips.
- `NodeResult` — `Advance($port,$patch) | Suspend(Interaction) | Terminate(outcome)`.
- `GuideRunner::start(GuideVersion,$answers=[]): RunState` and
  `advance(RunState,$input=null): RunState`. **Safety rails:** max-step budget,
  visited-set cycle guard, missing-fact → defined `unknown` branch (never throws).
  Records reached path for Mermaid highlight.

### Conditions (`src/Conditions/`)

`Condition` value object + `StructuredConditionEvaluator` (default) and
`ExpressionConditionEvaluator` (`symfony/expression-language`, variables
whitelisted to the fact vocabulary). Chosen per edge by a `type` discriminator.

### Profiles (`src/Profiles/`)

`PhasedProfile` (questions→facts→decisions→outcomes ordering; default, = issue
Phase 1) and `FreeformProfile` (permissive). Guide picks one; enforced at publish.

### Validation pipeline (`src/Validation/`)

`PublishValidator` runs rules, rejecting loudly: graph integrity (no dangling
edges/orphans, every path reaches an outcome), fact references (every condition →
a vocabulary fact, type-compatible; expression vars whitelisted), termination
(cycle detection), profile rules, host-registered rules. Success → snapshot draft
rows into `guide_versions.definition`, mark published, point `active_version_id`.

### Mermaid (`src/Mermaid/`)

`MermaidRenderer` = pure `GuideVersion|draft → string` (single source of truth for
editor preview *and* runner), with per-node-type `NodeRenderer` and a
`pathHighlight(RunState)` overlay. Framework-free.

### Seams (no host coupling) — `src/Events/`

- **Audit:** emit `GuidePublished`/`GuideDrafted`/`NodeChanged`/`GuideRunStarted`
  events; ship **no** owen-it dep. hrtools6 wires owen-it, Spring wires its
  `AuditLogService`.
- **Authorization:** define nothing; Filament package ships permissive policy
  hooks hosts override (hrtools6 `hr employees employ guide`, Spring
  `guide:*:tenant`). No permission strings baked in.
- **Translations:** ship publishable `resources/lang/{en,da}` for *engine UI*
  only; guide **content** is DB data (non-devs translate by editing).
- **Help:** out of package; expose editor/runner slugs for hosts to register.

### Testing helpers (`src/Testing/`) — folded into core, not a 3rd package

`FakeFactProvider`, `GuideBuilder` (assemble a tree without the editor),
`InteractsWithGuides` Pest assertions (`assertReachesOutcome`,
`assertSuspendsForQuestion`). Satisfies the issue's online/offline requirement and
lets Spring/hrtools6 test their guides. In core's main autoload (Laravel-style),
under `ByJesper\DecisionSupport\Testing\`.

### Core critical files

`src/DecisionSupportServiceProvider.php` (merge config, load+publish migrations,
register registries+built-in node types+profiles, publish lang), `src/Contracts/*`,
`src/Runtime/GuideRunner.php`, `src/Validation/PublishValidator.php`,
`src/Mermaid/MermaidRenderer.php`, `config/decision-support.php`.

---

## Package 2 — `byjesper/laravel-decision-support-filament` (editor + runner)

Depends on core + `filament/filament ^5.0` (Spring/hrtools6 are both Filament v5).
Scaffold from the same template; add a `DecisionSupportPlugin` (Filament plugin)
registered in the host panel by string (Spring's optional-plugin pattern).

- `src/Resources/GuideResource.php` (+ List/Create/Edit pages) — guide CRUD,
  versions as a relation manager.
- `src/Pages/GuideTreeEditor.php` — custom Filament page: node list with a
  per-node form driven by `NodeType::configSchema()`; edge/condition builder fed
  by the provider's `vocabulary()` (structured by default, expression as advanced);
  **live Mermaid preview** re-rendered on Livewire update via an Alpine hook;
  **Publish** action surfacing `PublishValidator` failures inline.
- `src/Pages/GuideRunner.php` — renders `Suspend` interactions (question/lookup),
  drives `GuideRunner::advance()`, shows result card (verdict + reasoning +
  warnings) and always-visible Mermaid with **reached-path highlight**.
- `resources/js/decision-support.js` + `package.json` (mermaid dep) + build:
  bundle `mermaid`, import in `resources/js/filament/…`, register via `Js::make`,
  re-run `mermaid.run()` on Livewire updates (mirrors hrtools6's
  `import-run-lifecycle.js`). Asset bundled by the package so hosts don't manage
  the npm dep.
- Permissive resource/page policies hosts override with their own Gate/permission.

### Filament critical files

`src/DecisionSupportFilamentServiceProvider.php`, `src/DecisionSupportPlugin.php`,
`src/Resources/GuideResource.php`, `src/Pages/GuideTreeEditor.php`,
`src/Pages/GuideRunner.php`, `resources/js/decision-support.js`.

---

## Build milestones (sequence, not runtime phases)

1. Scaffold core from template; rename placeholders; add `symfony/expression-language`; green `composer test`.
2. Migrations + models + enum + registries + `DecisionSupportManager`.
3. Built-in node types + conditions (structured + expression) + `GuideRunner`/`RunState` (resumable) + safety rails.
4. `MermaidRenderer` (+ path highlight).
5. `PublishValidator` pipeline + profiles + events.
6. `src/Testing/` helpers + full core Pest suite → tag **core v0.1.0**.
7. Scaffold filament package; `GuideResource` + `GuideTreeEditor` + bundled mermaid asset + publish gate.
8. `GuideRunner` page + path highlight + permissive policies.
9. Filament Livewire/Pest suite → tag **filament v0.1.0**.
10. Dogfood in Spring: add a path repo, code-author a sample guide (seeder, per Spring's `WorkflowSeeder` pattern) + a `FakeFactProvider`, feature-test it end-to-end. hrtools6 later builds the MindKey employment `FactProvider` + permission + seeded guide.

## Reuse (don't rebuild)

- `~/Development/packages/laravel-package-template/{composer.json,SETUP.md,tests/TestCase.php,.github/workflows/ci.yml,phpstan.neon.dist,rector.php}` — copy & rename.
- `symfony/expression-language` — the expression condition path.
- Spring as consumer reference: path repo (`repositories: [{type:path,url:packages/*,symlink:true}]`), registry registration (`packages/spring-core/src/Help/HelpRegistry.php`, `StaffServiceProvider`), code-authored domain data (`packages/staff/database/seeders/WorkflowSeeder.php`), Pest conventions (`packages/spring-core/tests/TestCase.php`).

## Verification

**Per package:** `composer test` green (guideline check → lint → PHPStan L8 →
100% type coverage → unit → parallel → integration).

**Core (Pest):** evaluator Advance/Suspend/Terminate; **resume across a
suspension** (question mid-tree); missing-fact → `unknown` branch (no throw);
cycle guard + step-budget; structured *and* expression condition eval; each
`PublishValidator` rule's reject case (unknown fact / dangling branch / missing
outcome / cycle); `PhasedProfile` enforcement vs `FreeformProfile`; Mermaid output
+ path highlight string.

**Filament (Livewire/Pest):** `GuideResource` authorization; tree-editor
create/edit a guide; live-preview Mermaid container present; **publish gate**
rejects an invalid tree; runner flow question→Suspend→advance→verdict; runner
Mermaid container present.

**End-to-end (in Spring):** seed a sample guide, register a `FakeFactProvider`,
run it through `GuideRunner` offline asserting it reaches the expected outcome —
proving the engine works headless for a code-authoring consumer before the GUI
exists.
