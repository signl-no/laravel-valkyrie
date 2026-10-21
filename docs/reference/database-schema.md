# Database schema

The four tables are created by `2026_09_24_101538_create_valkyrie_tables.php`. Their names come from `valkyrie.tables.*`, and the defaults are shown below.

Morph columns are created with Laravel's `morphs()` and `nullableMorphs()`, so the ID columns follow your app's default morph key type. That type is bigint unless you call `Schema::morphUsingUuids()` or `Schema::morphUsingUlids()`.

## `valkyrie_abilities`

| Column | Type | Notes |
|---|---|---|
| `id` | bigint | Primary key |
| `name` | string(100) | An ability name, or `*` |
| `title` | string, nullable | |
| `entity_type` | string, nullable | A morph class, `*`, or `null` |
| `entity_id` | bigint, nullable | Set for an ability on one record |
| `only_owned` | boolean | Default `false` |
| `options` | json, nullable | |
| `created_at`, `updated_at` | timestamp | |

Unique index: `name, entity_type, entity_id, only_owned`

## `valkyrie_roles`

| Column | Type | Notes |
|---|---|---|
| `id` | bigint | Primary key |
| `name` | string(100) | |
| `title` | string, nullable | |
| `scope_type`, `scope_id` | nullable morph | Set for a scoped definition |
| `created_at`, `updated_at` | timestamp | |

Unique index: `name, scope_type, scope_id`

## `valkyrie_assigned_roles`

| Column | Type | Notes |
|---|---|---|
| `id` | bigint | Primary key |
| `role_id` | foreign key → roles | Cascades on delete |
| `entity_type`, `entity_id` | morph | The actor |
| `scope_type`, `scope_id` | nullable morph | |

Unique index: `role_id, entity_type, entity_id, scope_type, scope_id`

## `valkyrie_permissions`

| Column | Type | Notes |
|---|---|---|
| `id` | bigint | Primary key |
| `ability_id` | foreign key → abilities | Cascades on delete |
| `entity_type`, `entity_id` | morph | An actor, or a role using the `role` morph alias |
| `forbidden` | boolean | Default `false` |
| `except` | json, nullable | Names removed from a wildcard ability |
| `scope_type`, `scope_id` | nullable morph | |

Unique index: `ability_id, entity_type, entity_id, forbidden, scope_type, scope_id`

## Deletion behavior

| Deleted row | Effect |
|---|---|
| Ability | Its permission rows are deleted by a foreign key cascade. |
| Role | Its assignments are deleted by a foreign key cascade. Its permission rows are deleted by the `Role` model's `deleted` event, because permissions point at roles through a morph rather than a foreign key. |
| Actor (a model using `HasRolesAndAbilities`) | Its permission rows and role assignments are deleted, and its cache is invalidated. For soft-deleting models, this happens on `forceDelete()` only. |
| Scope model using `IsAccessScope` | Permission rows, role assignments and role definitions scoped to it are deleted, and the whole cache is invalidated. For soft-deleting models, this happens on `forceDelete()` only. |
| Scope model without `IsAccessScope` | Nothing. Valkyrie doesn't track the deletion, so scoped rows stay behind. |
