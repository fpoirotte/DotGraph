<?php
declare(strict_types=1);
namespace fpoirotte\DotGraph;

class Edge extends AbstractAccessors {
    protected $_graph;
    protected $_source;
    protected $_sourcePort;
    protected $_sourceCompass;
    protected $_destination;
    protected $_destinationPort;
    protected $_destinationCompass;
    protected $_attributes;

    static protected $getter_attributes = array(
        'graph'                 => '_graph',
        'source'                => '_source',
        'sourcePort'            => '_sourcePort',
        'sourceCompass'         => '_sourceCompass',
        'destination'           => '_destination',
        'destinationPort'       => '_destinationPort',
        'destinationCompass'    => '_destinationCompass',
        'attributes'            => '_attributes',
    );

    public function __construct(AbstractGraph $graph, string $source, string $destination, array $attributes = [])
    {
        $this->_graph               = $graph;
        $this->_source              = $source;
        $this->_sourcePort          = null;
        $this->_sourceCompass       = null;
        $this->_destination         = $destination;
        $this->_destinationPort     = null;
        $this->_destinationCompass  = null;
        $this->_attributes          = new Attributes($attributes);
    }

    public function __set($attr, $value)
    {
        $allowedAttrs = [
            'sourcePort',
            'sourceCompass',
            'destinationPort',
            'destinationCompass',
        ];
        if (!in_array($attr, $allowedAttrs, true) || !is_string($value)) {
            throw new \InvalidArgumentException('Unknown property or invalid value');
        }

        $compass = ['n', 'ne', 'e', 'se', 's', 'sw', 'w', 'nw', 'c', '_'];
        if (strpos($attr, 'Compass') !== false && !in_array($value, $compass, true)) {
            throw new \InvalidArgumentException('Invalid compass value');
        }

        $attr = self::$getter_attributes[$attr];
        $this->$attr = $value;
    }

    static protected function _escapeString(string $s): string
    {
        return '"' . strtr($s, array('"' => '\\"')) . '"';
    }

    public function asDot(int $depth = 0): string
    {
        $indent = str_repeat(' ', $depth << 2);
        $src    = self::_escapeString($this->_source);
        $dst    = self::_escapeString($this->_destination);
        $edgeop = ($this->_graph::GRAPH_TYPE === 'digraph') ? '->' : '--';

        $compass = ['n', 'ne', 'e', 'se', 's', 'sw', 'w', 'nw', 'c', '_'];
        if ($this->_sourcePort !== null) {
            $src .= ':' . self::_escapeString($this->_sourcePort);
        }
        if ($this->_sourceCompass !== null) {
            $src .= ':' . $this->_sourceCompass;
        } elseif (in_array($this->_sourcePort, $compass, true)) {
            // The dot grammar is ambiguous about ports & compass values.
            // A value such as "foo:sw" could be interpreted as either:
            // - port value "sw" with no compass value for node "foo"
            // - compass value "sw" with no port value for node "foo"
            // Dot resolves this by saying compass values have precedence
            // over ports (i.e. the second interpretation is the correct one).
            //
            // To avoid this, we explicitly set the compass value to "_"
            // (default value for compasses) when the given port value
            // would result in an ambiguity.
            $src .= ':_';
        }

        if ($this->_destinationPort !== null) {
            $src .= ':' . self::_escapeString($this->_destinationPort);
        }
        if ($this->_destinationCompass !== null) {
            $src .= ':' . $this->_destinationCompass;
        } elseif (in_array($this->_destinationPort, $compass, true)) {
            // See the comment above about ports & compasses.
            $src .= ':_';
        }

        if (!count($this->_attributes)) {
            return "$indent$src $edgeop $dst;\n";
        }
        $attrs = $this->_attributes->asDot($depth + 1);
        return "$indent$src $edgeop $dst [\n$attrs$indent];\n";
    }
}
