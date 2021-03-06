<?php

declare(strict_types=1);

namespace Lkrms\Facade;

use Lkrms\Concept\Facade;
use Lkrms\Store\CacheStore;

/**
 * A facade for CacheStore
 *
 * @uses CacheStore
 *
 * @method static CacheStore load(string $filename = ":memory:", bool $autoFlush = true)
 * @method static void open(string $filename = ":memory:", bool $autoFlush = true)
 * @method static ?string getFilename()
 * @method static bool isOpen()
 * @method static void close()
 * @method static void set(string $key, mixed $value, int $expiry = 0)
 * @method static mixed get(string $key, int $maxAge = null)
 * @method static mixed maybeGet(string $key, callable $callback, int $expiry = 0)
 * @method static void delete(string $key)
 * @method static void flush()
 * @method static void flushExpired()
 */
final class Cache extends Facade
{
    protected static function getServiceName(): string
    {
        return CacheStore::class;
    }
}
