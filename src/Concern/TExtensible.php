<?php

declare(strict_types=1);

namespace Lkrms\Concern;

use Lkrms\Support\ClosureBuilder;

/**
 * Implements IExtensible to store arbitrary property values
 *
 * @see \Lkrms\Contract\IExtensible
 */
trait TExtensible
{
    /**
     * Normalised property names => values
     *
     * @var array<string,mixed>
     */
    protected $MetaProperties = [];

    /**
     * Normalised property names => last names passed to setMetaProperty
     *
     * @var array<string,string>
     */
    protected $MetaPropertyNames = [];

    /**
     * Property names => normalised property names
     *
     * @var array<string,string>
     */
    private $MetaPropertyMap = [];

    public function __clone()
    {
        // Don't clone undeclared properties
        $this->MetaProperties    = [];
        $this->MetaPropertyNames = [];
        $this->MetaPropertyMap   = [];
    }

    private function normaliseMetaProperty(string $name): string
    {
        if (is_null($normalised = $this->MetaPropertyMap[$name] ?? null))
        {
            $normalised = ClosureBuilder::get(static::class)->maybeNormaliseProperty($name);
            $this->MetaPropertyMap[$name] = $normalised;
        }

        return $normalised;
    }

    public function setMetaProperty(string $name, $value): void
    {
        $normalised = $this->normaliseMetaProperty($name);
        $this->MetaProperties[$normalised]    = $value;
        $this->MetaPropertyNames[$normalised] = $name;
    }

    public function getMetaProperty(string $name)
    {
        return $this->MetaProperties[$this->normaliseMetaProperty($name)] ?? null;
    }

    public function isMetaPropertySet(string $name): bool
    {
        return isset($this->MetaProperties[$this->normaliseMetaProperty($name)]);
    }

    public function unsetMetaProperty(string $name): void
    {
        unset($this->MetaProperties[$this->normaliseMetaProperty($name)]);
    }

    public function getMetaProperties(): array
    {
        $names = array_map(
            function ($name) { return $this->MetaPropertyNames[$name]; },
            array_keys($this->MetaProperties)
        );

        return array_combine($names, $this->MetaProperties);
    }
}
