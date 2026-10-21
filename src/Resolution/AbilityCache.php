<?php

declare(strict_types=1);

namespace Signl\Valkyrie\Resolution;

use Closure;
use Illuminate\Container\Attributes\Config;
use Illuminate\Container\Attributes\Singleton;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Database\DatabaseManager;
use Signl\Valkyrie\Contracts\CachePartition;

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
        private readonly DatabaseManager $db,
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
        $this->increment($this->partitionVersionKey($this->partition->key()));
    }

    /**
     * Invalidate the cached entries of one actor only.
     */
    public function bumpActor(string $actorType, int|string $actorKey): void
    {
        $this->increment($this->actorVersionKey($this->partition->key(), $actorType, $actorKey));
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
        $partition = $this->partition->key();
        $key = implode(':', [$partition, $actorType, $actorKey, $scopeType ?? '-', $scopeKey ?? '-']);

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
        return "valkyrie:version:{$partition}:{$actorType}:{$actorKey}";
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

        $connection = $this->db->connection();

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
            $this->store()->forever($key, 1);
        }
    }

    private function partitionVersionKey(string $partition): string
    {
        return "valkyrie:version:{$partition}";
    }

    private function store(): Repository
    {
        return $this->cache->store($this->storeName);
    }

    private function version(string $versionKey): int
    {
        return $this->versions[$versionKey] ??= (int) $this->store()->get($versionKey, 0);
    }
}
