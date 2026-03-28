# Changelog

All notable changes to `ez-php/orm` are documented here.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).

---

## [v1.2.0] — 2026-03-28

### Changed
- Updated `ez-php/contracts` dependency constraint to `^1.2`

---

## [v1.0.1] — 2026-03-25

### Changed
- Tightened all `ez-php/*` dependency constraints from `"*"` to `"^1.0"` for predictable resolution

---

## [v1.0.0] — 2026-03-24

### Added
- `Model` — Active Record base class; `find()`, `all()`, `create()`, `save()`, `update()`, `delete()`, and `fresh()`
- `QueryBuilder` — fluent SQL builder with `where()`, `orWhere()`, `orderBy()`, `limit()`, `offset()`, `join()`, `get()`, and `first()`
- `SchemaBuilder` — programmatic DDL: `create()`, `alter()`, `drop()`, column type helpers, indexes, and foreign keys
- Relations — `hasOne()`, `hasMany()`, `belongsTo()`, `belongsToMany()` with eager and lazy loading
- Soft deletes — `SoftDelete` trait adds `deleted_at`, `softDelete()`, `restore()`, and `withTrashed()` scoping
- Model events — `creating`, `created`, `updating`, `updated`, `deleting`, `deleted` fired through the event bus
- `OrmServiceProvider` — binds the query builder factory and wires model events to the event dispatcher
- `OrmException` for constraint violations, missing records, and configuration errors
