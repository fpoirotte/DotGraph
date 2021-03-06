<?php
declare(strict_types=1);
namespace fpoirotte\DotGraph;

class DiGraph extends AbstractGraph {
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
            // Turn $g[] = [...]; into $g[...] = [];
            $offset = $value;
            $value  = [];
        }

        if (is_iterable($offset)) {
            foreach ($offset as $src => $dst) {
                if (is_int($src)) {
                    // $g[ ['foo', 'bar'] ] = [...];
                    $this->addNode($dst, $value);
                } else {
                    // $g[ ['foo' => 'bar'] ] = [...];
                    $this->addEdge($src, $dst, $value);
                }
            }
        } else {
            // $g['foo'] = [...];
            $this->addNode($offset, $value);
        }
    }

    public function offsetGet($offset)
    {
        if (!is_iterable($offset)) {
            $offset = [$offset];
        }

        foreach ($offset as $src => $dst) {
            $res = is_int($src) ? $this->getNode($dst) : $this->getEdge($src, $dst);
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
        foreach ($offset as $src => $dst) {
            $exists = is_int($src) ? $this->hasNode($dst) : $this->hasEdge($src, $dst);
            if (!$exists) {
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

        foreach ($offset as $src => $dst) {
            if (is_int($src)) {
                $this->removeNode($dst);
            } else {
                $this->removeEdge($src, $dst);
            }
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

    public function addEdge(string $a, string $b, array $attributes = []): Edge
    {
        $this->addNode($a);
        $this->addNode($b);

        $edge = $this->getEdge($a, $b);
        if ($edge !== null && static::STRICT) {
            $edge->attributes->merge($attributes);
            return $edge;
        }

        $edge = new Edge($this, $a, $b, $attributes);
        $this->_edges[] = $edge;
        return $edge;
    }

    public function getEdge(string $a, string $b): ?Edge
    {
        $nodes = [$a, $b];
        foreach ($this->_edges as $edge) {
            if ($edge->source === $nodes[0] && $edge->destination === $nodes[1]) {
                return $edge;
            }
            if ($edge->source === $nodes[1] && $edge->destination === $nodes[0]) {
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

    public function iterEdges(?string $a, ?string $b): \Traversable
    {
        if ($a === null && $b === null) {
            yield from $this->_edges;
        }

        if (static::GRAPH_TYPE == 'graph') {
            foreach ($this->_edges as $edge) {
                $nodes = [null, $edge->source, $edge->destination];

                $aKey = array_search($a, $nodes, true);
                if ($aKey === false) {
                    continue;
                }

                // Prevents matching of ['a', 'b'] when calling
                // iterEdges('a', 'a').
                unset($nodes[$aKey]);

                if (in_array($b, $nodes, true)) {
                    yield $edge;
                }
            }
            return;
        }

        foreach ($this->_edges as $edge) {
            $srcMatch = ($a === null || $a === $edge->source);
            $dstMatch = ($b === null || $b === $edge->destination);
            if ($srcMatch && $dstMatch) {
                yield $edge;
            }
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
