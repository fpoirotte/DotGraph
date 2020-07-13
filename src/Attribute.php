<?php
declare(strict_types=1);
namespace fpoirotte\DotGraph;

class Attribute
{
    protected $name;
    protected $value;

    public function __construct(string $name, $value)
    {
        $this->name = $name;
        $this->setValue($value);
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getValue()
    {
        return $this->value;
    }

    public function setValue($value): void
    {
        if (is_string($value)) {
            $this->value = $value;
            return;
        }

        if (is_object($value)) {
            if ($value instanceof \DOMNode || $value instanceof \SimpleXMLElement) {
                $this->value = $value;
                return;
            }
        }

        throw new \InvalidArgumentException('A string or XML object was expected');
    }

    public function asDot(int $depth = 0): string
    {
        $indent = str_repeat(' ', $depth << 2);
        $name   = $this->name;
        if (is_string($this->value)) {
            $value = strtr($this->value, ['"' => '\\"']);
            return "$indent$name=\"$value\";\n";
        }

        if ($this->value instanceof \SimpleXMLElement) {
            $value = $this->value->asXML();
        } elseif ($this->value instanceof \DOMNode) {
            $root = $this->value->ownerDocument ?? $this->value;
            $value = $root->saveXML($this->value->documentElement, LIBXML_NOXMLDECL);
        }

        return "$indent$name=<$value>;\n";
    }
}
