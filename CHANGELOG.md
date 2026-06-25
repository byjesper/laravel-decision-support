# Changelog

All notable changes to `byjesper/laravel-decision-support` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

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

[Unreleased]: https://github.com/byjesper/laravel-decision-support/compare/v0.2.0...HEAD
[0.2.0]: https://github.com/byjesper/laravel-decision-support/compare/v0.1.0...v0.2.0
[0.1.0]: https://github.com/byjesper/laravel-decision-support/releases/tag/v0.1.0
