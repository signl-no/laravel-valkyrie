<?php

declare(strict_types=1);

namespace Signl\Valkyrie\Support;

use Illuminate\Database\Eloquent\Model;

/**
 * Translates accepted entity forms (instance, class string, '*' or null) into morph values.
 */
final class Morph
{
    /**
     * @param  Model|class-string<Model>|string|null  $entity
     */
    public static function key(Model|string|null $entity): int|string|null
    {
        return $entity instanceof Model ? $entity->getKey() : null;
    }

    /**
     * @param  Model|class-string<Model>|string|null  $entity
     */
    public static function type(Model|string|null $entity): ?string
    {
        return match (true) {
            $entity instanceof Model => $entity->getMorphClass(),
            $entity === self::Everything => self::Everything,
            is_string($entity) && is_a($entity, Model::class, true) => (new $entity)->getMorphClass(),
            default => null,
        };
    }

    public const string Everything = '*';
}
