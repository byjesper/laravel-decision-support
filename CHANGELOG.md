# Changelog

All notable changes to `byjesper/laravel-decision-support` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- **Free-form guides may now contain cycles** (e.g. loop back and re-ask a
  question). A profile opts in by implementing the new marker interface
  `ByJesper\DecisionSupport\Contracts\SupportsCycles`; the shipped `FreeformProfile`
  now does. For such profiles the publish-time acyclic check is skipped and, at
  runtime, the revisit guard is replaced by the step budget as the sole
  termination rail. Exactly one rail is active per profile: acyclic profiles use
  the revisit guard (steps bounded by node count), cycle-supporting profiles use
  `max_steps`. `GuideRunner` gains an optional, nullable `GuideProfileRegistry`
  constructor argument; a null registry or an unknown/plain profile behaves
  exactly as before (acyclic). Re-entering an already-answered question re-asks
  it — the run re-suspends and the new answer overwrites the stored one. (#22)
- **Ordering operators (`>`, `>=`, `<`, `<=`) can compare dates.** Operands are
  normalized before comparison: numerics and numeric strings map to floats, and
  unambiguous ISO-8601 date/datetime strings (`Y-m-d`, optionally with a time
  component) map to a timestamp. Arbitrary non-ISO strings remain non-comparable
  (the comparison stays `false`), so this is deliberately narrow. (#18)
- **`GuideDrafted` is now dispatched** — from a `GuideVersion` `created` model
  observer, so it fires for any writer (the Filament editor, a host's custom
  editor, seeders, artisan commands) whenever a *draft* version is created. The
  README events table now states the dispatch mechanism per event. (#21)
- Additive migration adding an index on `guides.active_version_id` (resolved on
  effectively every guide load). No foreign key — it would create a circular
  reference with `guide_versions.guide_id`, and SQLite cannot `ALTER`-add one. (#15)

### Changed

- **Equality and membership now share one loose comparison.** `=`, `!=`, `in`,
  and `not_in` all route a fact the same way; previously `in`/`not_in` were
  strict while `=`/`!=` were loose, so `"5" = 5` matched but `"5" in [5]` did not.
  Booleans no longer coerce through raw string casting: a bool matches only
  another bool or its canonical string forms (`"true"/"1"`, `"false"/"0"`). (#17)
- Internal performance: `GuideDefinition` indexes edges by origin (and
  destination) node once in the constructor, so `edgesFrom()`/`edgesTo()` are
  O(1) lookups instead of O(E) scans. Per-node edge order is preserved
  (`selectTarget()` is first-matching-condition-wins). (#20)

### Fixed

- **Unparseable boolean answers no longer route through `true`.** A boolean
  question fed unrecognized input (`"maybe"`, `"no thanks"`, …) now re-suspends
  and re-asks, reusing the 0.4.0 required-answer machinery, instead of casting
  the junk to `true`. Recognized answers (`"yes"/"no"/"1"/"0"/true/false`) route
  as before. (#19)
- Removed a scaffolding-leftover integration test that only asserted the suite
  wires up. (#16)
- A question node's `inputType` is now validated against the *raw* configured
  value at publish, so an explicitly-invalid type is rejected instead of being
  silently normalized to `text`; a missing value still defaults to `text`.

### Behaviour changes to note when upgrading

- Routing may change for guides that (inadvertently) depended on strict `in`/
  `not_in` failing across types, or on the old bool coercion where `false = ""`
  and `true = "1"` both matched (#17).
- Date-ordering conditions that always fell through to the default branch will
  start routing meaningfully (#18).
- A boolean question that previously mis-routed junk input to `true` now
  re-suspends (#19).
- Cyclic free-form guides go from rejected-at-publish to publishable; a host test
  asserting a cyclic freeform guide fails validation must be updated. Separately,
  acyclic guides with a path deeper than `max_steps` nodes now complete instead of
  being falsely terminated `unknown` at the budget (#22).
- Hosts creating draft versions (including in seeders/tests with `Event::fake`)
  now receive `GuideDrafted` (#21).

## [0.4.0] - 2026-06-26

### Added

- **Mandatory questions.** A question node accepts a `required` config flag; when
  set, a free (text/date/number) question must be answered with a non-blank value
  before the run can advance — the interpreter re-suspends on a null or
  whitespace-only answer instead of routing an empty value through `out`. The
  flag is surfaced on `Interaction` (new `required` property, serialized on the
  run state) so any host UI can disable its submit control accordingly. Ignored
  for boolean/select, which are always answered by the choice itself.
  `GuideBuilder::question()` gains a `$required` parameter. Backward compatible
  (defaults to `false`).

## [0.3.0] - 2026-06-25

### Added

- The **Mermaid diagram now renders node text in the run's language.**
  `MermaidRenderer::render()` resolves each node's display text through the same
  locale chain as the runner (locale → fallback → base): an explicit `label`
  (with optional `label_i18n`) wins for every node type, otherwise a question
  falls back to its `prompt`/`prompt_i18n`, an outcome to its `verdict`/
  `verdict_i18n`, and a fact/decision to its `fact` name, then the key. The
  locale can be passed explicitly via the new optional `$locale`/`$fallbackLocale`
  parameters (for the pre-start diagram, which has no run state) or is derived
  from the highlighted `RunState` when omitted.
- `GuideBuilder::fact()` and `decision()` accept an optional `$label` and
  `$labelI18n` map, so authored fact/decision nodes can show a friendly,
  localized label in the diagram instead of their raw key.
- **Custom / localized edge labels.** `EdgeDefinition` gains an optional `label`
  and `labelI18n` map; when set, the diagram shows that (locale-resolved) text on
  the branch instead of the derived condition/port text (e.g. a humanised
  "Long tenure" rather than `tenure >= 5`). Persisted via new nullable
  `label`/`label_i18n` columns on `guide_edges` (migration included) and exposed
  on `GuideBuilder::edge()`.
- **Structured params on `ValidationError`** (`$params`), exposing the values
  interpolated into each error `message` (node key, port, edge, fact, …) so a
  consumer can re-render the issue in another language by `code` + params. The
  English `message` is unchanged. Resolves #10.

### Notes

- Backward compatible: with no locale the renderer emits the base strings (the
  previous behaviour); nodes/edges without a `label`/`label_i18n` render exactly
  as before; and `ValidationError::$params` defaults to an empty array so existing
  consumers are unaffected.

## [0.2.0] - 2026-06-25

### Added

- Multi-language guide **content**. Outcome `verdict`/`text`/`warnings`, question
  `prompt`, and select-option `label`s accept optional `*_i18n` sibling maps keyed
  by locale (`verdict_i18n`, `text_i18n`, `warnings_i18n`, `prompt_i18n`, per-option
  `label_i18n`). `GuideRunner::start()` takes an optional `$locale` and
  `$fallbackLocale`, carried on `GuideContext` (serialized, so it survives
  suspend/resume); a new `LocaleResolver` resolves `locale → fallback → base`.
  `EvaluationContext` exposes `locale()`, `fallbackLocale()`, and `localeResolver()`;
  `GuideBuilder::question()`/`outcome()` take an `$i18n` array. No locale ⇒ base
  strings, fully backward compatible.
- Optional `help` text on each node type's `configSchema()` field, rendered as
  hint text by the Filament editor. Additive — existing readers ignore it and the
  engine does not interpret the schema.
- `extra_attributes` — a nullable JSON column (cast to `array`) on both `guides`
  and `guide_versions` for arbitrary consumer metadata (e.g.
  `['permissions' => [...]]` for host-side gating). Added via a new additive
  migration so existing installs upgrade without touching the published create
  migration.

### Changed

- `GuidePublisher::publish()` now seeds the guide's `extra_attributes` from the
  version that becomes active, so a host policy can gate on
  `$guide->extra_attributes` without joining to a version. The engine stores and
  copies these attributes but enforces nothing.

## [0.1.0] - 2026-06-24

### Added

- Read-only, resumable decision-support engine: `GuideRunner` (`start`/`advance`)
  driving a serializable `RunState` through `question`/`fact`/`decision`/`outcome`
  node types, with safety rails (missing-fact → `unknown` branch, cycle guard,
  step budget) that never throw on bad guide data.
- Structured and expression conditions (`symfony/expression-language`) behind a
  `ConditionEvaluator` chain; `Operator` enum.
- `FactProvider` contract with a `FactVocabulary`; per-guide registration via
  `DecisionSupportManager`. Node-type, fact-provider, and profile registries.
- `PublishValidator` (graph integrity, termination, fact references, per-node
  config, profile rules) and `GuidePublisher` that snapshots a draft into an
  immutable `guide_versions.definition`.
- `PhasedProfile` and `FreeformProfile`.
- Eloquent models + migration for `guides`, `guide_versions`, `guide_nodes`,
  `guide_edges`.
- `MermaidRenderer` (pure definition → `flowchart`, with reached-path highlight).
- Host-seam events: `GuideRunStarted`, `GuidePublished`, `GuideDrafted`,
  `NodeChanged`.
- Testing helpers: `GuideBuilder`, `FakeFactProvider`, `InteractsWithGuides`,
  `GuideTester`.
- Laravel Boost `decision-support-development` skill (auto-discovered on
  `boost:install` / `boost:update --discover`).

[Unreleased]: https://github.com/byjesper/laravel-decision-support/compare/v0.4.0...HEAD
[0.4.0]: https://github.com/byjesper/laravel-decision-support/compare/v0.3.0...v0.4.0
[0.3.0]: https://github.com/byjesper/laravel-decision-support/compare/v0.2.0...v0.3.0
[0.2.0]: https://github.com/byjesper/laravel-decision-support/compare/v0.1.0...v0.2.0
[0.1.0]: https://github.com/byjesper/laravel-decision-support/releases/tag/v0.1.0
