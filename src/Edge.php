<?php
declare(strict_types=1);
namespace fpoirotte\DotGraph;

class Edge extends AbstractAccessors {
    protected $_graph;
    protected $_source;
    protected $_destination;
    protected $_attributes;

    static protected $getter_attributes = array(
        'graph'         => '_graph',
        'source'        => '_source',
        'destination'   => '_destination',
        'attributes'    => '_attributes',
    );

    public function __construct(AbstractGraph $graph, string $source, string $destination, array $attributes = [])
    {
        $this->_graph       = $graph;
        $this->_source      = $source;
        $this->_destination = $destination;
        $this->_attributes  = new Attributes($attributes);
    }

    public function asDot(int $depth = 0): string
    {
        $indent = str_repeat(' ', $depth << 2);
        $src    = strtr($this->_source, array('"' => '\\"'));
        $dst    = strtr($this->_destination, array('"' => '\\"'));
        $edgeop = ($this->_graph::GRAPH_TYPE === 'digraph') ? '->' : '--';
        if (!count($this->_attributes)) {
            return "$indent\"$src\" $edgeop \"$dst\";\n";
        }

        $attrs = $this->_attributes->asDot($depth + 1);
        return "$indent\"$src\" $edgeop \"$dst\" [\n$attrs$indent];\n";
    }
}
