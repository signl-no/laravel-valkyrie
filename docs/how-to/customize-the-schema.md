# Customize the schema

Use this guide to rename Valkyrie's tables or change their columns.

## Rename tables

You don't need to publish the migration for this. Publish the config and edit the table names:

```bash
php artisan vendor:publish --tag=laravel-valkyrie-config
```

```php
// config/valkyrie.php
'tables' => [
    'abilities' => 'acl_abilities',
    'roles' => 'acl_roles',
    'assigned_roles' => 'acl_role_user',
    'permissions' => 'acl_permissions',
],
```

Set these before you run the migration for the first time. The migration and every query read the names from config, so renaming tables that already exist means writing your own rename migration.

## Change columns

Publish the migration, then stop the package from loading its own copy:

```bash
php artisan vendor:publish --tag=laravel-valkyrie-migrations
```

```php
// config/valkyrie.php
'migrations' => false,
```

Edit the published file in `database/migrations/`. Common changes:

- adding a `tenant_id` column (see [Set up multi-tenancy](set-up-multi-tenancy.md));
- swapping the `json` columns (`abilities.options`, `permissions.except`) for `text` on databases without JSON support;
- changing the morph columns to `uuidMorphs`/`nullableUuidMorphs` or `ulidMorphs`/`nullableUlidMorphs` when your actors or scopes use UUID or ULID keys.

Keep the column names that Valkyrie queries: `name`, `entity_type`, `entity_id`, `only_owned`, `scope_type`, `scope_id`, `forbidden`, `except`, `ability_id` and `role_id`.

## Existing installs

The published file keeps the package migration's filename, so Laravel's migration table already lists it as run. In an app where the package migration has already run, publishing it doesn't run anything again. To change the schema of a live install, write a new migration that alters the tables.

## Verify

```bash
php artisan migrate:status
```

The `create_valkyrie_tables` migration should appear once.

## Related

- [Database schema reference](../reference/database-schema.md)
- [Configuration reference](../reference/configuration.md)
