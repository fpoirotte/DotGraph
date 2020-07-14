<?php
declare(strict_types=1);
namespace fpoirotte\DotGraph;

class SubGraph extends AbstractGraph
{
    public function asDot(int $depth = 0): string
    {
        $indent = str_repeat(' ', $depth << 2);
        $res    = ["${indent}subgraph "];
        if ($this->_name !== '') {
            $name = strtr($this->name, ['"' => '\\"']);
            $res[] = "\"$name\" ";
        }
        $res[]  = "{\n";
        $res[]  = parent::asDot($depth + 1);
        $res[]  = "$indent}\n";
        return implode('', $res);
    }

    public function offsetSet($offset, $value)
    {
        if ($offset === null) {
            $offset = $value;
            $value  = [];
        }

        if (is_iterable($offset)) {
            foreach ($offset as $node) {
                $this->addNode($node, $value);
            }
        } else {
            $this->addNode($offset, $value);
        }
    }

    public function offsetGet($offset)
    {
        if (!is_iterable($offset)) {
            $offset = [$offset];
        }

        foreach ($offset as $node) {
            $res = $this->getNode($dst);
            if ($res !== null) {
                return $res;
            }
        }
        return null;
    }

    public function offsetExists($offset)
    {
        if (!is_iterable($offset)) {
            $offset = [$offset];
        }

        $notEmpty = false;
        foreach ($offset as $node) {
            if (!$this->hasNode($node)) {
                return false;
            }
            $notEmpty = true;
        }
        return $notEmpty;
    }

    public function offsetUnset($offset)
    {
        if (!is_iterable($offset)) {
            $offset = [$offset];
        }

        foreach ($offset as $node) {
            $this->removeNode($node);
        }
    }
}
