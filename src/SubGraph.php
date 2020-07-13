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
}
