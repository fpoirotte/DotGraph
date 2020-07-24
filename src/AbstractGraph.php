<?php
declare(strict_types=1);
namespace fpoirotte\DotGraph;

abstract class AbstractGraph extends AbstractAccessors implements \Countable, \ArrayAccess
{
    protected $_name;
    protected $_nodes;
    protected $_parent;
    protected $_subgraphs;
    protected $_attributes;

    static protected $getter_attributes = array(
        'name'          => '_name',
        'nodes'         => '_nodes',
        'parent'        => '_parent',
        'subgraphs'     => '_subgraphs',
        'attributes'    => '_attributes',
    );

    public function __construct(string $name, $attributes = [])
    {
        $this->_name        = $name;
        $this->_nodes       = [];
        $this->_parent      = null;
        $this->_subgraphs   = array();
        $this->_attributes  = new Attributes($attributes);
    }

    public function count()
    {
        return count($this->_nodes);
    }

    public function iterGraphsHierarhy(): \Traversable
    {
        yield $this;
        foreach ($this->_subgraphs as $subgraph) {
            yield from $subgraph->iterGraphsHierarhy();
        }
    }

    public function addSubgraph(SubGraph $subgraph): SubGraph
    {
        // Detect potential duplicates.
        $duplicate = false;
        for ($root = $this; $root->_parent !== null; $root = $root->_parent) {
            // Do nothing.
        }
        foreach ($root->iterGraphsHierarhy() as $graph) {
            if ($graph === $subgraph) {
                $duplicate = true;
                break;
            }
        }

        if ($subgraph->_parent !== null && !$duplicate) {
            $subgraph->_parent->removeSubgraph($subgraph);
        }

        // Import subgraph nodes as necessary
        foreach ($subgraph->iterGraphsHierarhy() as $graph) {
            foreach ($graph->nodes as $node) {
                $root->addNode($node->name, $node->attributes->asArray());
            }
        }

        if ($duplicate) {
            return $subgraph;
        }

        $subgraph->_parent  = $this;
        $this->_subgraphs[] = $subgraph;
        return $subgraph;
    }

    public function removeSubgraph(SubGraph $subgraph): void
    {
        $key = array_search($subgraph, $this->_subgraphs, true);
        if ($key !== null) {
            unset($this->_subgraphs[$key]);
            $this->_subgraphs = array_values($this->_subgraphs);
            $subgraph->_parent = null;
        }
    }

    public function getSubgraph(string $name): ?SubGraph
    {
        if ($name === '') {
            return null;
        }

        foreach ($this->iterGraphsHierarhy() as $graph) {
            if ($graph->_name === $name) {
                return $graph;
            }
        }
        return null;
    }

    public function addNode(string $name, array $attributes = []): Node
    {
        for ($root = $this; $root->_parent !== null; $root = $root->_parent) {
            // Do nothing.
        }

        $node = $this->getNode($name);
        if ($node !== null) {
            $node->attributes->merge($attributes);
        } else {
            $node = new Node($this, $name, $attributes);
            $this->_nodes[$name] = $node;
        }

        if ($root == $this) {
            return $node;
        }

        return $root->addNode($name, $attributes);
    }

    public function getNode(string $name): ?Node
    {
        return $this->_nodes[$name] ?? null;
    }

    public function hasNode(string $name): bool
    {
        return isset($this->_nodes[$name]);
    }

    public function removeNode(string $name): void
    {
        unset($this->_nodes[$name]);
    }

    public function asDot(int $depth = 0): string
    {
        $res = [];
        foreach ($this->_attributes as $v) {
            $res[] = $v->asDot($depth);
        }

        foreach ($this->_nodes as $node) {
            $res[] = $node->asDot($depth);
        }

        foreach ($this->_subgraphs as $subgraph) {
            $res[] = $subgraph->asDot($depth);
        }

        return implode('', $res);
    }

    public function __toString(): string
    {
        return $this->asDot();
    }
}
