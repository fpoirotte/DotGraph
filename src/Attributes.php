<?php
declare(strict_types=1);
namespace fpoirotte\DotGraph;

class Attributes implements \ArrayAccess, \IteratorAggregate, \Countable
{
    protected $attributes;

    public function __construct(array $attributes = [])
    {
        $this->attributes = [];
        $this->merge($attributes);
    }

    public function merge($attributes): void
    {
        $cls = __CLASS__;
        if (is_object($attributes) && $attributes instanceof $cls) {
            $attributes = $attributes->asArray();
        }
        if (!is_array($attributes)) {
            throw new \InvalidArgumentException("\$attributes must be an array or instance of $cls");
        }
        foreach ($attributes as $key => $value) {
            $this[$key] = $value;
        }
    }

    public function asArray(): array
    {
        return $this->attributes;
    }

    public function count(): int
    {
        return count($this->attributes);
    }

    public function offsetGet($offset)
    {
        return $this->attributes[$offset] ?? null;
    }

    public function offsetSet($offset, $value)
    {
        if (!is_string($offset) || !strlen($offset)) {
            $offset = var_export($offset, true);
            throw new \InvalidArgumentException("Invalid attribute name $offset: should be a non-empty string");
        }

        if (!is_object($value) || !($value instanceof Attribute)) {
            $value = new Attribute($offset, $value);
        }

        $this->attributes[$offset] = $value;
    }

    public function offsetExists($offset)
    {
        return isset($this->attributes[$offset]);
    }

    public function offsetUnset($offset)
    {
        unset($this->attributes[$offset]);
    }

    public function getIterator(): \Traversable
    {
        yield from $this->attributes;
    }

    public function asDot(int $depth = 0): string
    {
        $res = [];
        foreach ($this->attributes as $attr) {
            $res[] = $attr->asDot($depth);
        }

        return implode('', $res);
    }
}
