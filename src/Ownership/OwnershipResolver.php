<?php

declare(strict_types=1);

namespace Signl\Valkyrie\Ownership;

use Closure;
use Illuminate\Container\Attributes\Singleton;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

/**
 * Decides ownership: by default the entity's `user_id` must equal the actor's key.
 * Attribute rules only hold for actors of the auth user model, since a bare key cannot
 * tell user 5 from service account 5; other actor types need a closure rule.
 */
#[Singleton]
final class OwnershipResolver
{
    /** @var array<class-string<Model>, string|Closure(Model, Model): bool> */
    private array $resolvers = [];

    public function owns(Model $actor, Model $entity): bool
    {
        $resolver = $this->resolverFor($entity);

        if ($resolver instanceof Closure) {
            return (bool) $resolver($entity, $actor);
        }

        if (! $this->isUser($actor)) {
            return false;
        }

        $attributes = $entity->getAttributes();

        if (! Arr::exists($attributes, $resolver) || $attributes[$resolver] === null) {
            return false;
        }

        return (string) $attributes[$resolver] === (string) $actor->getKey();
    }

    /**
     * @param  class-string<Model>  $model
     * @param  string|Closure(Model, Model): bool  $attribute
     */
    public function register(string $model, string|Closure $attribute): void
    {
        $this->resolvers[$model] = $attribute;
    }

    private function isUser(Model $actor): bool
    {
        $model = config('auth.providers.users.model');

        return is_string($model) && $actor instanceof $model;
    }

    /**
     * @return string|Closure(Model, Model): bool
     */
    private function resolverFor(Model $entity): string|Closure
    {
        foreach ($this->resolvers as $class => $resolver) {
            if ($entity instanceof $class) {
                return $resolver;
            }
        }

        return self::DefaultAttribute;
    }

    public const string DefaultAttribute = 'user_id';
}
