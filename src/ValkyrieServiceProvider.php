<?php

declare(strict_types=1);

namespace Signl\Valkyrie;

use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use Illuminate\View\Compilers\BladeCompiler;
use Signl\Valkyrie\Concerns\HasRolesAndAbilities;
use Signl\Valkyrie\Console\Commands\GrantSuperuserCommand;
use Signl\Valkyrie\Contracts\CachePartition;
use Signl\Valkyrie\Http\Middleware\EnsureHasAbility;
use Signl\Valkyrie\Http\Middleware\EnsureHasRole;
use Signl\Valkyrie\Models\Ability;
use Signl\Valkyrie\Models\Role;
use Signl\Valkyrie\Resolution\AbilityCache;
use Signl\Valkyrie\Support\Models;

final class ValkyrieServiceProvider extends ServiceProvider
{
    /** @var array<class-string, bool> */
    private array $actorClasses = [];

    public function boot(Gate $gate, Dispatcher $events): void
    {
        if (config()->boolean('valkyrie.migrations', true)) {
            $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        }

        Relation::morphMap([
            config()->string('valkyrie.morphs.role') => Models::role(),
            config()->string('valkyrie.morphs.ability') => Models::ability(),
        ]);

        $this->registerGateBridge($gate);
        $this->registerCacheInvalidation();
        $this->registerMiddleware();
        $this->registerBladeDirectives();
        $this->registerMemoFlushing($events);

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/valkyrie.php' => config_path('valkyrie.php'),
            ], ['laravel-valkyrie', 'laravel-valkyrie-config']);

            // The filename is preserved on purpose: the migrator dedupes by name, so a
            // published copy never reruns in apps where the package migration already ran.
            $this->publishes([
                __DIR__.'/../database/migrations/2026_09_24_101538_create_valkyrie_tables.php' => database_path('migrations/2026_09_24_101538_create_valkyrie_tables.php'),
            ], ['laravel-valkyrie', 'laravel-valkyrie-migrations']);

            $this->commands([GrantSuperuserCommand::class]);
        }
    }

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/valkyrie.php', 'valkyrie');

        $this->app->singleton(CachePartition::class, function (Application $app): CachePartition {
            /** @var class-string<CachePartition> $partition */
            $partition = config()->string('valkyrie.cache.partition');

            return $app->make($partition);
        });
    }

    private function isActor(Model $user): bool
    {
        return $this->actorClasses[$user::class] ??= collect(class_uses_recursive($user))->contains(HasRolesAndAbilities::class);
    }

    private function registerBladeDirectives(): void
    {
        $this->callAfterResolving('blade.compiler', function (BladeCompiler $blade): void {
            $blade->if('role', function (?string $role = null): bool {
                if ($role === null) {
                    return false;
                }

                $user = $this->app->make(AuthFactory::class)->guard()->user();

                return $user instanceof Model
                    && $this->app->make(ValkyrieManager::class)->holdsRole($user, $role);
            });
        });
    }

    /**
     * Safety net for writes that bypass the facade (admin UI, tinker, factories).
     * Definition edits that cannot change resolution skip the bump.
     */
    private function registerCacheInvalidation(): void
    {
        $refresh = function (): void {
            $this->app->make(ValkyrieManager::class)->refresh();
        };

        $definitionChanged = function (Model $model, array $relevant) use ($refresh): void {
            if (collect($relevant)->contains(fn (string $attribute): bool => $model->wasChanged($attribute))) {
                $refresh();
            }
        };

        $abilityModel = Models::ability();
        $abilityModel::created(fn () => $refresh());
        $abilityModel::updated(fn (Ability $model) => $definitionChanged($model, ['name', 'entity_type', 'entity_id', 'only_owned']));
        $abilityModel::deleted(fn () => $refresh());

        $roleModel = Models::role();
        $roleModel::created(fn () => $refresh());
        $roleModel::updated(fn (Role $model) => $definitionChanged($model, ['name', 'scope_type', 'scope_id']));
        $roleModel::deleted(fn () => $refresh());

        $bumpEntity = function (mixed $entityType, mixed $entityKey) use ($refresh): void {
            if ($entityType === Relation::getMorphAlias(Models::role()) || ! is_string($entityType) || ! (is_int($entityKey) || is_string($entityKey))) {
                $refresh();

                return;
            }

            $this->app->make(AbilityCache::class)->bumpActor($entityType, $entityKey);
        };

        $rowChanged = function (Model $model) use ($bumpEntity): void {
            $bumpEntity($model->getAttribute('entity_type'), $model->getAttribute('entity_id'));

            // A row reassigned in place leaves the previous entity's cache stale too.
            if ($model->wasChanged('entity_type') || $model->wasChanged('entity_id')) {
                $bumpEntity($model->getOriginal('entity_type'), $model->getOriginal('entity_id'));
            }
        };

        foreach ([Models::permission(), Models::assignedRole()] as $model) {
            $model::created($rowChanged);
            $model::updated($rowChanged);
            $model::deleted($rowChanged);
        }
    }

    /**
     * Valkyrie answers every Gate check first; guests and non-actors fall through as null.
     */
    private function registerGateBridge(Gate $gate): void
    {
        $gate->before(function (mixed $user, string $ability, array $arguments): ?bool {
            if (! $user instanceof Model || ! $this->isActor($user)) {
                return null;
            }

            $entity = $arguments[0] ?? null;
            $entity = match (true) {
                $entity instanceof Model => $entity,
                is_string($entity) && is_a($entity, Model::class, true) => $entity,
                default => null,
            };

            return $this->app->make(ValkyrieManager::class)->check($user, $ability, $entity);
        });
    }

    /**
     * Octane and queue workers reuse the singleton, so the memo flushes per request/job.
     */
    private function registerMemoFlushing(Dispatcher $events): void
    {
        $flush = function (): void {
            $this->app->make(AbilityCache::class)->flush();
        };

        $events->listen(JobProcessing::class, $flush);

        if ($this->app->bound('octane')) {
            $events->listen('Laravel\Octane\Events\RequestReceived', $flush);
        }
    }

    private function registerMiddleware(): void
    {
        $this->callAfterResolving('router', function (Router $router): void {
            $router->aliasMiddleware('role', EnsureHasRole::class);
            $router->aliasMiddleware('ability', EnsureHasAbility::class);
        });
    }
}
