<?php

declare(strict_types=1);

namespace Signl\Valkyrie\Resolution;

use Closure;
use Illuminate\Container\Attributes\Config;
use Illuminate\Container\Attributes\Singleton;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Database\Connection;
use Signl\Valkyrie\Contracts\CachePartition;
use Signl\Valkyrie\Support\Models;

/**
 * Two layers: a per-request memo and a versioned cache store. Keys embed a partition and
 * an actor version; bumping either orphans every affected entry without enumerating keys.
 * Store increments run after the surrounding transaction commits.
 *
 * @phpstan-type AbilityRow array{name: string, entity_type: string|null, entity_id: int|string|null, only_owned: bool, forbidden: bool, except: list<string>}
 */
#[Singleton]
final class AbilityCache
{
    /** @var array<string, list<AbilityRow>> */
    private array $memo = [];

    /** @var array<string, int> */
    private array $versions = [];

    private bool $pendingBump = false;

    public function __construct(
        private readonly CacheFactory $cache,
        private readonly CachePartition $partition,
        #[Config('valkyrie.cache.enabled', true)] private readonly bool $enabled,
        #[Config('valkyrie.cache.store')] private readonly ?string $storeName,
        #[Config('valkyrie.cache.ttl', 86400)] private readonly int $ttl,
    ) {}

    /**
     * Invalidate every cached entry in the current partition.
     */
    public function bump(): void
    {
        $this->increment($this->partitionVersionKey($this->namespace()));
    }

    /**
     * Invalidate the cached entries of one actor only.
     */
    public function bumpActor(string $actorType, int|string $actorKey): void
    {
        $this->increment($this->actorVersionKey($this->namespace(), $actorType, $actorKey));
    }

    /**
     * The connection Valkyrie's models run on, which may differ from the default.
     */
    public function connection(): Connection
    {
        $model = Models::permission();

        return (new $model)->getConnection();
    }

    public function enabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Clear the per-request memo; store entries stay valid.
     */
    public function flush(): void
    {
        $this->memo = [];
        $this->versions = [];
        $this->pendingBump = false;
    }

    /**
     * Drop rows read inside a rolled-back transaction. A deferred bump survives while an
     * outer transaction is still open, so its uncommitted rows never reach the store.
     */
    public function rolledBack(int $remainingLevel): void
    {
        $this->memo = [];
        $this->versions = [];

        if ($remainingLevel === 0) {
            $this->pendingBump = false;
        }
    }

    /**
     * @param  Closure(): list<AbilityRow>  $load
     * @return list<AbilityRow>
     */
    public function rows(
        string $actorType,
        int|string $actorKey,
        ?string $scopeType,
        int|string|null $scopeKey,
        Closure $load,
    ): array {
        $partition = $this->namespace();
        $key = implode(':', [$partition, self::part($actorType), self::part($actorKey), self::part($scopeType), self::part($scopeKey)]);

        if (isset($this->memo[$key])) {
            return $this->memo[$key];
        }

        if (! $this->enabled || $this->pendingBump) {
            return $this->memo[$key] = $load();
        }

        $partitionVersion = $this->version($this->partitionVersionKey($partition));
        $actorVersion = $this->version($this->actorVersionKey($partition, $actorType, $actorKey));

        return $this->memo[$key] = $this->store()->remember("valkyrie:{$partitionVersion}.{$actorVersion}:{$key}", $this->ttl, $load);
    }

    private function actorVersionKey(string $partition, string $actorType, int|string $actorKey): string
    {
        return 'valkyrie:version:'.implode(':', [$partition, self::part($actorType), self::part($actorKey)]);
    }

    /**
     * Clear the memo now; increment the store version once the transaction commits.
     */
    private function increment(string $key): void
    {
        $this->memo = [];
        $this->versions = [];

        if (! $this->enabled) {
            return;
        }

        $connection = $this->connection();

        if ($connection->transactionLevel() === 0) {
            $this->incrementNow($key);

            return;
        }

        $this->pendingBump = true;

        $connection->afterCommit(function () use ($key): void {
            $this->incrementNow($key);
            $this->pendingBump = false;
        });
    }

    private function incrementNow(string $key): void
    {
        if ($this->store()->increment($key) === false) {
            $this->seed($key);
            $this->store()->increment($key);
        }
    }

    /**
     * The partition plus the models' database, so tenants on separate databases never
     * share entries even when they share the partition.
     */
    private function namespace(): string
    {
        $connection = $this->connection();

        return implode('|', [self::part($this->partition->key()), self::part($connection->getName()), self::part($connection->getDatabaseName())]);
    }

    /**
     * Encode one key component so no value can contain a delimiter (`:` or `|`) or
     * pass for another; `!` marks null and never results from encoding.
     */
    private static function part(int|string|null $value): string
    {
        return $value === null ? '!' : rawurlencode((string) $value);
    }

    private function partitionVersionKey(string $partition): string
    {
        return "valkyrie:version:{$partition}";
    }

    private function store(): Repository
    {
        return $this->cache->store($this->storeName);
    }

    /**
     * Start a missing version at a random number: a version key that was evicted must
     * never come back at a number that older entries were cached under.
     */
    private function seed(string $versionKey): void
    {
        $this->store()->add($versionKey, random_int(1, PHP_INT_MAX >> 1));
    }

    private function version(string $versionKey): int
    {
        if (isset($this->versions[$versionKey])) {
            return $this->versions[$versionKey];
        }

        $version = $this->store()->get($versionKey);

        if ($version === null) {
            $this->seed($versionKey);
            $version = $this->store()->get($versionKey, 0);
        }

        return $this->versions[$versionKey] = (int) $version;
    }
}
