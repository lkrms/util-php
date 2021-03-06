<?php

declare(strict_types=1);

namespace Lkrms\Contract;

/**
 * Writes properties that have not been declared or are not visible in the
 * current scope
 *
 */
interface IWritable
{
    /**
     * Return a writable property list, or ["*"] for all available properties
     *
     * @return string[]
     */
    public static function getWritable(): array;

    public function __set(string $name, $value): void;

    public function __unset(string $name): void;
}
