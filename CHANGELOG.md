# Changelog

All notable changes to `byjesper/laravel-decision-support` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Documentation

- README: note that Laravel Boost only publishes skills from **direct** root
  `composer.json` dependencies, so the `decision-support-development` skill is
  only discovered when the engine is required directly (not transitively). Added
  the `composer require` + `boost:update --discover` recipe.

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

[Unreleased]: https://github.com/byjesper/laravel-decision-support/compare/v0.1.0...HEAD
[0.1.0]: https://github.com/byjesper/laravel-decision-support/releases/tag/v0.1.0
