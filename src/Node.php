<?php
declare(strict_types=1);
namespace fpoirotte\DotGraph;

class Node extends AbstractAccessors {
    protected $_graph;
    protected $_name;
    protected $_attributes;

    static protected $getter_attributes = array(
        'graph'         => '_graph',
        'name'          => '_name',
        'attributes'    => '_attributes',
    );

    public function __construct(AbstractGraph $graph, string $name, array $attributes = [])
    {
        $this->_graph       = $graph;
        $this->_name        = $name;
        $this->_attributes  = new Attributes($attributes);
    }

    public function asDot(int $depth = 0): string
    {
        $indent = str_repeat(' ', $depth << 2);
        $name   = strtr($this->_name, array('"' => '\\"'));
        if (!count($this->_attributes) || $this->_graph instanceof SubGraph) {
            return "$indent\"$name\";\n";
        }

        $attrs = $this->_attributes->asDot($depth + 1);
        return "$indent\"$name\" [\n$attrs$indent];\n";
    }
}
