%name Parser
%declare_class {class Parser}
%token_prefix {TOKEN_}

%include {
declare(strict_types=1);
namespace fpoirotte\DotGraph;

class Token {
    public function __construct($token, $value, $pos, $line, $col) {
        $this->metadata = [
            'tokenName' => $token,
            'value'     => $value,
            'position'  => $pos,
            'line'      => $line,
            'column'    => $col,
        ];
    }

    public function __get($attr)
    {
        return isset($this->metadata[$attr]) ? $this->metadata[$attr] : null;
    }

    public function __toString()
    {
        $md = $this->metadata;
        return "${md['tokenName']} at byte ${md['position']} (line ${md['line']}), column ${md['column']})";
    }
}
}

%include_class {
    protected   $result = [];
    protected   $attributes = [
        self::TOKEN_GRAPH   => [],
        self::TOKEN_NODE    => [],
        self::TOKEN_EDGE    => [],
    ];

    private function __construct() {
    }

    static public function parse($input) {
        $parser = new static();
//        $parser::PrintTrace();
        $lexer  = $parser->tokenize($input);
        foreach ($lexer as $token => $value) {
            try {
                $parser->doParse($token, $value);
            } catch (\Exception $e) {
                $lexer->throw($e);
            }
        }
        $parser->doParse(0, null);
        return $parser->result;
    }

    protected function tokenize($src) {
        $line = $col = $colStart = 1;

        $keywords = [
            'strict'    => self::TOKEN_STRICT,
            'digraph'   => self::TOKEN_DIGRAPH,
            'graph'     => self::TOKEN_GRAPH,
            'node'      => self::TOKEN_NODE,
            'edge'      => self::TOKEN_EDGE,
            'subgraph'  => self::TOKEN_SUBGRAPH,
        ];
        $maxKwLen = max(array_map('strlen', array_keys($keywords)));
        $kw_patt = "/^(?:" . implode('|', array_keys($keywords)) .")\\b/i";

        $edgeops = [
            '--'    => self::TOKEN_UNDIRECTED_EDGE,
            '->'    => self::TOKEN_DIRECTED_EDGE,
        ];

        $chars = [
            '{' => self::TOKEN_CURLY_OPEN,
            '}' => self::TOKEN_CURLY_CLOSE,
            '=' => self::TOKEN_EQUAL,
            '[' => self::TOKEN_BRACKET_OPEN,
            ']' => self::TOKEN_BRACKET_CLOSE,
            ',' => self::TOKEN_COMMA,
            ';' => self::TOKEN_SEMICOLON,
            ':' => self::TOKEN_COLON,
            '+' => self::TOKEN_PLUS,
        ];

        for ($pos = 0, $len = strlen($src); $pos < $len; /**/) {
            if (preg_match($kw_patt, substr($src, $pos, $maxKwLen + 1), $matches)) {
                $token = $keywords[strtolower($matches[0])];
                yield $token => new Token(self::$yyTokenName[$token], null, $pos, $line, $col);
                $match = strlen($matches[0]);
                $pos += $match;
                $col += $match;
                continue;
            }

            $two = substr($src, $pos, 2);
            if (isset($edgeops[$two])) {
                $token = $edgeops[$two];
                yield $token => new Token(self::$yyTokenName[$token], null, $pos, $line, $col);
                $pos += 2;
                $col += 2;
                continue;
            }

            if ($src[$pos] === '#' && $col === 0) {
                $pos += strcspn($src, "\r\n", $pos);
                $pos += (substr($src, $pos, 2) === "\r\n") ? 2 : 1;
                $line++;
                $col  = $colStart;
                continue;
            }

            if ($two == '//') {
                $pos += strcspn($src, "\r\n", $pos);
                $pos += (substr($src, $pos, 2) === "\r\n") ? 2 : 1;
                $line++;
                $col  = $colStart;
                continue;
            }

            if ($two == '/*') { /**/
                $len2 = strpos($src, "*/", $pos);
                if ($len2 === false) {
                    throw new \Exception('');
                }
                $len2   += 2;
                $comment = explode("\n", strtr(substr($src, $pos, $len2), array("\r\n" => "\n", "\r" => "\n")));
                $line   += count($comment) - 1;
                $pos    += $len2;
                $col     = $colStart + strlen(array_pop($comment));
                continue;
            }

            if (preg_match("/^-?(?:\\.[0-9]+|[0-9]+(?:\\.[0-9]*)?)/", substr($src, $pos), $matches)) {
                $token = self::TOKEN_NUMERAL;
                yield $token => new Token(self::$yyTokenName[$token], $matches[0], $pos, $line, $col);
                $match = strlen($matches[0]);
                $pos += $match;
                $col += $match;
                continue;
            }

            if (isset($chars[$src[$pos]])) {
                $token = $chars[$src[$pos]];
                yield $token => new Token(self::$yyTokenName[$token], null, $pos, $line, $col);
                $pos++;
                $col++;
                continue;
            }

            if (strpos(" \t", $src[$pos]) !== false) {
                $pos++;
                $col++;
                continue;
            }

            if (strpos("\r\n", $src[$pos]) !== false) {
                $pos += (substr($src, $pos, 2) === "\r\n") ? 2 : 1;
                $line++;
                $col = $colStart;
                continue;
            }

            $chr = ord($src[$pos]);
            if ($chr === '_' || ($chr > 0x40 && $chr <= 0x5A) || ($chr > 0x60 && $chr <= 0x7A) || ($chr >= 0x80)) {
                $token = self::TOKEN_IDENTIFIER;
                if (preg_match("/[A-Za-z0-9_\\x80-\\xFF]+/", $src, $matches, 0, $pos) !== 1) {
                    throw new \Exception('Runtime error');
                }

                yield $token => new Token(self::$yyTokenName[$token], $matches[0], $pos, $line, $col);
                $match = strlen($matches[0]);
                $pos += $match;
                $col += $match;
                continue;
            }

            if ($src[$pos] === '"') {
                $token = self::TOKEN_DQUOTED_STRING;
                $tokenObj = new Token(self::$yyTokenName[$token], null, $pos, $line, $col);
                $pos++;
                $col++;
                $buf = '';
                while ($pos < $len) {
                    $len2 = strcspn($src, "\\\"\r\n", $pos);
                    if ($pos + $len2 >= $len) {
                        throw new \Exception('Unterminated quoted string');
                    }

                    $buf .= (string) substr($src, $pos, $len2);
                    $pos += $len2;
                    $col += $len2;

                    switch ($src[$pos]) {
                        case "\r":
                            if (substr($src, $pos, 2) === "\r\n") {
                                $pos++;
                            }
                        case "\n":
                            $buf .= "\n";
                            $line++;
                            $col = $colStart;
                            break;

                        case '"':
                            $pos++;
                            $col++;
                            break 2;

                        case '\\':
                            if ($pos + 1 >= $len) {
                                throw new \Exception('Unterminated quoted string');
                            }
                            $col++;
                            switch ($src[++$pos]) {
                                case "\r":
                                case "\n":
                                    $pos += (substr($src, $pos, 2) === "\r\n") ? 2 : 1;
                                    $line++;
                                    $col = $colStart;
                                    break;

                                case '"':
                                    $buf .= '"';
                                    $pos++;
                                    $col++;
                                    break;

                                default:
                                    $buf .= "\\" . $src[$pos++];
                                    $col++;
                                    break;
                            }
                            break;
                    }
                }

                $tokenObj->value = $buf;
                yield $token => $tokenObj;
                continue;
            }

            if ($src[$pos] === '<') {
                $token = self::TOKEN_HTML_STRING;
                $tokenObj = new Token(self::$yyTokenName[$token], null, $pos, $line, $col);
                $pos++;
                $col++;
                $depth = 1;
                while (true) {
                    $len2 = strcspn($src, "<>\r\n", $pos);
                    if ($pos + $len2 >= $len) {
                        throw new \Exception('Unterminated HTML string');
                    }

                    $pos += $len2;
                    $col += $len2 + 1;
                    switch ($src[$pos]) {
                        case '<':
                            $depth++;
                            break;

                        case '>':
                            $depth--;
                            break;

                        case "\r":
                            if (substr($src, $pos, 2) === "\r\n") {
                                $pos++;
                            }
                        case "\n":
                            $line++;
                            $col = $colStart;
                            break;
                    }
                    $pos++;

                    if (!$depth) {
                        break;
                    }
                }

                $tokenObj->value = (string) substr($src, $tokenObj->position + 1, $pos - $tokenObj->position - 2);
                if (class_exists('\\DOMDocument')) {
                    $dom    = new \DOMDocument();
                    $luie   = libxml_use_internal_errors(true);
                    try {
                        if ($dom->loadXML($tokenObj->value, LIBXML_NONET) !== true) {
                            throw new \Exception("Invalid XML in $tokenObj");
                        }
                        $tokenObj->value = $dom;
                    } finally {
                        libxml_use_internal_errors($luie);
                    }

                    if ($tokenObj->value === false) {
                        throw new \Exception("Invalid XML in $tokenObj");
                    }
                }

                yield $token => $tokenObj;
                continue;
            }

            throw new \Exception("Invalid input at byte $pos (line $line, column $col)");
        }
    }
}

//////////////////////////////////////////////////////
//////////////////////////////////////////////////////

start ::= root_graph(A) push_scope stmt_list pop_scope. {
    $this->result = A;
}

push_scope ::= CURLY_OPEN.  {
    $this->attributes[self::TOKEN_GRAPH][]  = [];
    $this->attributes[self::TOKEN_NODE][]   = [];
    $this->attributes[self::TOKEN_EDGE][]   = [];
}

pop_scope ::= CURLY_CLOSE.  {
    array_pop($this->attributes[self::TOKEN_GRAPH]);
    array_pop($this->attributes[self::TOKEN_NODE]);
    array_pop($this->attributes[self::TOKEN_EDGE]);
    array_pop($this->graphs);
}

root_graph(A) ::= strict(B) graph_type(C) graph_id(D). {
    $graphtypes = [
        self::TOKEN_UNDIRECTED_EDGE => [MultiGraph::class, Graph::class],
        self::TOKEN_DIRECTED_EDGE   => [MultiDiGraph::class, DiGraph::class],
    ];
    $graphcls = $graphtypes[C][(int) B];
    $this->graphs[] = A = new $graphcls(D);
}

strict(A) ::= STRICT.   {  A = true; }
strict(A) ::= .         {  A = false; }

graph_type(A) ::= GRAPH.      { A = self::TOKEN_UNDIRECTED_EDGE; }
graph_type(A) ::= DIGRAPH.    { A = self::TOKEN_DIRECTED_EDGE; }

graph_id(A) ::= id(B).  { A = B->value; }
graph_id(A) ::= .       { A = ''; }

stmt_list ::= stmt_list stmt SEMICOLON.
stmt_list ::= stmt_list stmt.
stmt_list ::= .

stmt ::= node_stmt.
stmt ::= edge_stmt.
stmt ::= attr_stmt.
stmt ::= id(B) EQUAL id(C).  {
    $this->graphs[count($this->graphs) - 1]->attributes[B->value] = C->value;
}
stmt ::= subgraph. {}

attr_stmt ::= attr_scope(B) attr_list(C). {
    $this->attributes[B][count($this->attributes[B]) - 1] =
        array_merge($this->attributes[B][count($this->attributes[B]) - 1],C);
}

attr_scope(A) ::= GRAPH.  { A = self::TOKEN_GRAPH; }
attr_scope(A) ::= NODE.   { A = self::TOKEN_NODE; }
attr_scope(A) ::= EDGE.   { A = self::TOKEN_EDGE; }

attr_list(A) ::= attr_list(B) attr_list_item(C).    { A = array_merge(B, C); }
attr_list(A) ::=              attr_list_item(C).    { A = C; }

attr_list_item(A) ::= BRACKET_OPEN BRACKET_CLOSE.           { A = []; }
attr_list_item(A) ::= BRACKET_OPEN a_list(B) BRACKET_CLOSE. { A = B; }

a_list(A) ::= a_list(B) a_list_sep id(C) EQUAL id(D).   { A = B; A[C->value] = D->value; }
a_list(A) ::=                      id(C) EQUAL id(D).   { A = [C->value => D->value]; }

a_list_sep ::= SEMICOLON.
a_list_sep ::= COMMA.
a_list_sep ::= .

edge_stmt ::= node_id_or_subgraph(B) edge_rhs(C) stmt_attrs(D). {
    $inheritedNode  = array_merge(...$this->attributes[self::TOKEN_NODE]);
    $inheritedEdge  = array_merge(...$this->attributes[self::TOKEN_EDGE]);
    $inheritedEdge  = array_merge($inheritedEdge, D);
    $compass        = ['n', 'ne', 'e', 'se', 's', 'sw', 'w', 'nw', 'c', '_'];

    $sources = B;
    foreach (C as $destinations) {
        foreach ($sources[0]->nodes as $source) {
            foreach ($destinations[0]->nodes as $destination) {
                if (!$this->graphs[0]->hasNode($source->name)) {
                    $this->graphs[0]->addNode($source->name, $inheritedNode);
                }
                if (!$this->graphs[0]->hasNode($destination->name)) {
                    $this->graphs[0]->addNode($destination->name, $inheritedNode);
                }
                $edge = $this->graphs[0]->addEdge($source->name, $destination->name, $inheritedEdge);

                // Add port/compass information for the edge.
                switch (count($sources[1])) {
                    // nodeID:port:compass
                    case 2:
                        $edge->sourcePort       = $sources[1][0];
                        $edge->sourceCompass    = $sources[1][1];
                        break;

                    // nodeID:port or nodeID:compass
                    case 1:
                        if (in_array($sources[1][0], $compass, true)) {
                            $edge->sourceCompass = $sources[1][0];
                        } else {
                            $edge->sourcePort = $sources[1][0];
                        }
                        break;
                }
                switch (count($destinations[1])) {
                    // nodeID:port:compass
                    case 2:
                        $edge->destinationPort      = $destinations[1][0];
                        $edge->destinationCompass   = $destinations[1][1];
                        break;

                    // nodeID:port or nodeID:compass
                    case 1:
                        if (in_array($destinations[1][0], $compass, true)) {
                            $edge->destinationCompass = $destinations[1][0];
                        } else {
                            $edge->destinationPort = $destinations[1][0];
                        }
                        break;
                }
            }
        }
        $sources = $destinations;
    }
}

stmt_attrs(A) ::= attr_list(B).    { A = B; }
stmt_attrs(A) ::= .                { A = []; }

node_id_or_subgraph(A) ::= node_id(B).  { A = [new SubGraph(''), B[1]]; A->addNode(B[0]); }
node_id_or_subgraph(A) ::= subgraph(B). { A = [B, []]; }

edge_rhs(A) ::= edge_rhs(B) edgeop node_id_or_subgraph(C).   { A = B; A[] = C; }
edge_rhs(A) ::=             edgeop node_id_or_subgraph(C).   { A = [C]; }

edgeop ::= DIRECTED_EDGE.   {
    if ($this->graphs[0]::GRAPH_TYPE !== 'digraph') {
        throw new \Exception('Cannot add directed edge to undirected graph');
    }
}
edgeop ::= UNDIRECTED_EDGE. {
    if ($this->graphs[0]::GRAPH_TYPE !== 'graph') {
        throw new \Exception('Cannot add undirected edge to directed graph');
    }
}

node_stmt ::= node_id(B) stmt_attrs(C). {
    $inherited  = array_merge(...$this->attributes[self::TOKEN_NODE]);
    $inherited  = array_merge($inherited, C);
    $this->graphs[count($this->graphs) - 1]->addNode(B[0], $inherited);
}

// Note: In a node definition context, ports do not seem to make much sense...
//       The specification still allows them, because the same production rule
//       is used both for nodes declarations & edges declarations.
node_id(A) ::= id(B) port(C).   { A = [B->value, C]; }
node_id(A) ::= id(B).           { A = [B->value, []]; }

// The second "id" is really a "compass_pt". However, the specification states
// that "compass_pt" values are not recognized as special tokens by the parser
// and any "id" is actually valid. Also, the first "id" can be either an actual
// node ID, or a "compass_pt".
// This results in an ambiguity and the specification says that the first "id"
// should be interpreted as a compass value in that case.
port(A) ::= COLON id(B) COLON id(C).    { A = [B->value, C->value]; }
port(A) ::= COLON id(B).                { A = [B->value]; }

subgraph(A) ::= subgraph_constructor(B) push_scope stmt_list pop_scope. {
    $this->graphs[count($this->graphs) - 1]->addSubgraph(B);
    A = B;
}

subgraph_constructor(A) ::= subgraph_id(B). {
    A = $this->graphs[0]->getSubgraph(B);
    if (A === null) {
        $inherited  = array_merge(...$this->attributes[self::TOKEN_GRAPH]);
        A = new SubGraph(B, $inherited);
    }
    $this->graphs[] = A;
}

subgraph_id(A) ::= SUBGRAPH id(B).  { A = B->value;}
subgraph_id(A) ::= SUBGRAPH.        { A = ''; }
subgraph_id(A) ::= .                { A = ''; }


id(A) ::= IDENTIFIER(B).        { A = B; }
id(A) ::= NUMERAL(B).           { A = B; }
id(A) ::= dquoted_string(B).    { A = B; }
id(A) ::= HTML_STRING(B).       { A = B; }

dquoted_string(A) ::= dquoted_string(B) PLUS DQUOTED_STRING(C). { A = B; B->value .= C->value; }
dquoted_string(A) ::=                        DQUOTED_STRING(C). { A = C; }
