<?php
declare(strict_types=1);
namespace fpoirotte\DotGraph;

class DiGraph extends AbstractGraph implements \ArrayAccess {
    const STRICT = true;
    const GRAPH_TYPE = 'digraph';

    static protected $getter_attributes = array(
        'edges'         => '_edges',
    );

    protected $_edges;

    public function __construct(string $name = '', array $attributes = [])
    {
        parent::__construct($name, $attributes);
        $this->_edges = array();
    }

    public function asDot(int $depth = 0): string
    {
        $name = strtr($this->_name, array('"' => '\\"'));
        $res = [];
        if (static::STRICT) {
            $res[] = 'strict ';
        }
        $res[] = static::GRAPH_TYPE;
        if ($name !== '') {
            $res[] = " \"$name\"";
        }
        $res[] = " {\n";

        $res[] = parent::asDot($depth + 1);

        foreach ($this->_edges as $edge) {
            $res[] = $edge->asDot(1);
        }

        $res[] = "}\n";
        return implode('', $res);
    }

    public function offsetSet($offset, $value)
    {
        if ($offset === null) {
            if (is_array($value)) {
                // $g[] = ['foo', 'bar']; or $g[] = ['foo', 'bar', [...]];
                $this->addEdge(...$value);
            } else {
                // $g[] = 'foo';
                $this->addNode($value);
            }
        } else if (is_array($offset)) {
            // $g[ ['foo', 'bar'] ] = [...];
            $offset[] = $value;
            $this->addEdge(...$offset);
        } else {
            // $g['foo'] = [...];
            $this->addNode($offset, $value);
        }
    }

    public function offsetGet($offset)
    {
        if (is_array($offset)) {
            return $this->getEdge(...$offset);
        }
        return $this->getNode($offset);
    }

    public function offsetExists($offset)
    {
        if (is_array($offset)) {
            return $this->hasEdge(...$offset);
        }
        return $this->hasNode($offset);
    }

    public function offsetUnset($offset)
    {
        if (is_array($offset)) {
            $this->removeEdge(...$offset);
        } else {
            $this->removeNode($offset);
        }
    }

    public function removeNode(string $name): void
    {
        parent::removeNode($name);
        foreach ($this->_edges as $key => &$edge) {
            if ($edge->source === $name || $edge->destination === $name) {
                $edge = null;
            }
        }
        $this->_edges = array_filter($this->_edges);
    }

    public function addEdge(string $a, string $b, array $attributes = []): void
    {
        $this->addNode($a);
        $this->addNode($b);

        $edge = $this->getEdge($a, $b);
        if ($edge !== null && static::STRICT) {
            $edge->attributes->merge($attributes);
            return;
        }

        $nodes = [$a, $b];
        if (static::GRAPH_TYPE == 'graph') {
            sort($nodes, \SORT_STRING);
        }
        $nodes[] = $attributes;
        $this->_edges[] = new Edge($this, ...$nodes);
    }

    public function getEdge(string $a, string $b): ?Edge
    {
        $nodes = [$a, $b];
        if (static::GRAPH_TYPE == 'graph') {
            sort($nodes, \SORT_STRING);
        }

        foreach ($this->_edges as $edge) {
            if ($edge->source === $nodes[0] && $edge->destination === $nodes[1]) {
                return $edge;
            }
        }
        return null;
    }

    public function hasEdge(string $a, string $b): bool
    {
        return $this->getEdge($a, $b) !== null;
    }

    public function removeEdge(string $a, string $b): void
    {
        $edge = $this->getEdge($a, $b);
        if ($edge !== null) {
            $key = array_search($edge, $this->_edges, true);
            if ($key === false) {
                throw new \RuntimeException('Could not locate edge');
            }
            unset($this->_edges[$key]);
            // Reindex to avoid sparse arrays
            $this->_edges = array_values($this->_edges);
        }
    }

    public function predecessors(string $node): array
    {
        $res = [];
        foreach ($this->_edges as $edge) {
            if ($edge->destination == $node) {
                $res[$edge->source] = 1;
            }
        }
        return array_keys($res);
    }

    public function successors(string $node): array
    {
        $res = [];
        foreach ($this->_edges as $edge) {
            if ($edge->source == $node) {
                $res[$edge->destination] = 1;
            }
        }
        return array_keys($res);
    }

    public function neighbors(string $node): array
    {
        $res = [];
        foreach ($this->_edges as $edge) {
            if ($edge->destination == $node) {
                $res[$edge->source] = 1;
            } elseif ($edge->source == $node) {
                $res[$edge->destination] = 1;
            }
        }
        return array_keys($res);
    }
}
