<?php
/* Driver template for the PHP_ParserrGenerator parser generator. (PHP port of LEMON)
*/

// code external to the class is included here
#line 5 "src/Parser.y"

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
use ArrayAccess; #line 35 "src/Parser.php"

/**
 * This can be used to store both the string representation of
 * a token, and any useful meta-data associated with the token.
 *
 * meta-data should be stored as an array
 */
class ParseryyToken implements ArrayAccess
{
    public $string = '';
    public $metadata = array();

    function __construct($s, $m = array())
    {
        if ($s instanceof ParseryyToken) {
            $this->string = $s->string;
            $this->metadata = $s->metadata;
        } else {
            $this->string = (string) $s;
            if ($m instanceof ParseryyToken) {
                $this->metadata = $m->metadata;
            } elseif (is_array($m)) {
                $this->metadata = $m;
            }
        }
    }

    function __toString()
    {
        return $this->string;
    }

    function offsetExists($offset)
    {
        return isset($this->metadata[$offset]);
    }

    function offsetGet($offset)
    {
        return $this->metadata[$offset];
    }

    function offsetSet($offset, $value)
    {
        if ($offset === null) {
            if (isset($value[0])) {
                $x = ($value instanceof ParseryyToken) ?
                    $value->metadata : $value;
                $this->metadata = array_merge($this->metadata, $x);
                return;
            }
            $offset = count($this->metadata);
        }
        if ($value === null) {
            return;
        }
        if ($value instanceof ParseryyToken) {
            if ($value->metadata) {
                $this->metadata[$offset] = $value->metadata;
            }
        } elseif ($value) {
            $this->metadata[$offset] = $value;
        }
    }

    function offsetUnset($offset)
    {
        unset($this->metadata[$offset]);
    }
}

/** The following structure represents a single element of the
 * parser's stack.  Information stored includes:
 *
 *   +  The state number for the parser at this level of the stack.
 *
 *   +  The value of the token stored at this level of the stack.
 *      (In other words, the "major" token.)
 *
 *   +  The semantic value stored at this level of the stack.  This is
 *      the information used by the action routines in the grammar.
 *      It is sometimes called the "minor" token.
 */
class ParseryyStackEntry
{
    public $stateno;       /* The state-number */
    public $major;         /* The major token value.  This is the code
                     ** number for the token at this stack level */
    public $minor; /* The user-supplied minor token value.  This
                     ** is the value of the token  */
};

// declare_class is output here
#line 2 "src/Parser.y"
class Parser#line 131 "src/Parser.php"
{
/* First off, code is included which follows the "include_class" declaration
** in the input file. */
#line 33 "src/Parser.y"

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
#line 415 "src/Parser.php"

/* Next is all token values, as class constants
*/
/* 
** These constants (all generated automatically by the parser generator)
** specify the various kinds of tokens (terminals) that the parser
** understands. 
**
** Each symbol here is a terminal symbol in the grammar.
*/
    const TOKEN_CURLY_OPEN                     =  1;
    const TOKEN_CURLY_CLOSE                    =  2;
    const TOKEN_STRICT                         =  3;
    const TOKEN_GRAPH                          =  4;
    const TOKEN_DIGRAPH                        =  5;
    const TOKEN_SEMICOLON                      =  6;
    const TOKEN_EQUAL                          =  7;
    const TOKEN_NODE                           =  8;
    const TOKEN_EDGE                           =  9;
    const TOKEN_BRACKET_OPEN                   = 10;
    const TOKEN_BRACKET_CLOSE                  = 11;
    const TOKEN_COMMA                          = 12;
    const TOKEN_DIRECTED_EDGE                  = 13;
    const TOKEN_UNDIRECTED_EDGE                = 14;
    const TOKEN_COLON                          = 15;
    const TOKEN_SUBGRAPH                       = 16;
    const TOKEN_IDENTIFIER                     = 17;
    const TOKEN_NUMERAL                        = 18;
    const TOKEN_HTML_STRING                    = 19;
    const TOKEN_PLUS                           = 20;
    const TOKEN_DQUOTED_STRING                 = 21;
    const YY_NO_ACTION = 135;
    const YY_ACCEPT_ACTION = 134;
    const YY_ERROR_ACTION = 133;

/* Next are that tables used to determine what action to take based on the
** current state and lookahead token.  These tables are used to implement
** functions that take a state number and lookahead value and return an
** action integer.  
**
** Suppose the action integer is N.  Then the action is determined as
** follows
**
**   0 <= N < self::YYNSTATE                              Shift N.  That is,
**                                                        push the lookahead
**                                                        token onto the stack
**                                                        and goto state N.
**
**   self::YYNSTATE <= N < self::YYNSTATE+self::YYNRULE   Reduce by rule N-YYNSTATE.
**
**   N == self::YYNSTATE+self::YYNRULE                    A syntax error has occurred.
**
**   N == self::YYNSTATE+self::YYNRULE+1                  The parser accepts its
**                                                        input. (and concludes parsing)
**
**   N == self::YYNSTATE+self::YYNRULE+2                  No such action.  Denotes unused
**                                                        slots in the yy_action[] table.
**
** The action table is constructed as a single large static array $yy_action.
** Given state S and lookahead X, the action is computed as
**
**      self::$yy_action[self::$yy_shift_ofst[S] + X ]
**
** If the index value self::$yy_shift_ofst[S]+X is out of range or if the value
** self::$yy_lookahead[self::$yy_shift_ofst[S]+X] is not equal to X or if
** self::$yy_shift_ofst[S] is equal to self::YY_SHIFT_USE_DFLT, it means that
** the action is not in the table and that self::$yy_default[S] should be used instead.  
**
** The formula above is for computing the action when the lookahead is
** a terminal symbol.  If the lookahead is a non-terminal (as occurs after
** a reduce action) then the static $yy_reduce_ofst array is used in place of
** the static $yy_shift_ofst array and self::YY_REDUCE_USE_DFLT is used in place of
** self::YY_SHIFT_USE_DFLT.
**
** The following are the tables generated in this section:
**
**  self::$yy_action        A single table containing all actions.
**  self::$yy_lookahead     A table containing the lookahead for each entry in
**                          yy_action.  Used to detect hash collisions.
**  self::$yy_shift_ofst    For each state, the offset into self::$yy_action for
**                          shifting terminals.
**  self::$yy_reduce_ofst   For each state, the offset into self::$yy_action for
**                          shifting non-terminals after a reduce.
**  self::$yy_default       Default action for each state.
*/
    const YY_SZ_ACTTAB = 142;
static public $yy_action = array(
 /*     0 */    53,   37,   36,   29,   19,   32,   56,   57,   58,   28,
 /*    10 */    17,   11,   60,   61,   52,   16,   63,   62,    8,   76,
 /*    20 */    49,   21,   48,   30,   19,   32,   56,   57,   58,   28,
 /*    30 */    17,    1,   60,   61,   52,   16,   63,   13,    8,   25,
 /*    40 */    73,   21,   48,   30,   54,   51,   74,    3,    9,   71,
 /*    50 */    75,   64,   55,    5,   69,   50,    4,   22,   11,   60,
 /*    60 */    61,   52,   39,   63,  134,   24,   15,   22,   40,   20,
 /*    70 */     6,   38,   39,   67,   21,   48,   30,   27,   43,   34,
 /*    80 */     2,   38,   66,   68,   21,   48,   30,   41,   18,   60,
 /*    90 */    61,   52,    6,   63,   14,   45,   44,   12,   30,   25,
 /*   100 */    73,   30,   10,    6,   31,   47,  111,  111,   72,   42,
 /*   110 */    35,   59,  112,  112,   23,   73,   45,   44,   65,   10,
 /*   120 */    33,   70,   46,   30,    7,   26,  109,   30,  109,   30,
 /*   130 */    30,  109,  109,  109,  109,  109,  109,  109,  109,   30,
 /*   140 */    30,   30,
    );
    static public $yy_lookahead = array(
 /*     0 */    27,    4,    5,   20,   31,   32,   33,   34,   35,   36,
 /*    10 */    37,   16,   17,   18,   19,   42,   21,   21,   45,    3,
 /*    20 */    27,   48,   49,   50,   31,   32,   33,   34,   35,   36,
 /*    30 */    37,   26,   17,   18,   19,   42,   21,    7,   45,   38,
 /*    40 */    39,   48,   49,   50,    2,   44,    4,   46,   15,    6,
 /*    50 */     8,    9,    6,   43,   11,   12,   46,   31,   16,   17,
 /*    60 */    18,   19,   36,   21,   23,   24,    7,   31,   42,   28,
 /*    70 */    10,   45,   36,   39,   48,   49,   50,   25,   42,   31,
 /*    80 */    26,   45,   31,   11,   48,   49,   50,    1,   40,   17,
 /*    90 */    18,   19,   10,   21,    7,   13,   14,   41,   50,   38,
 /*   100 */    39,   50,   15,   10,   31,   44,   13,   14,   31,   30,
 /*   110 */    31,   31,   13,   14,   38,   39,   13,   14,   47,   15,
 /*   120 */    31,   31,   31,   50,   29,   25,   51,   50,   51,   50,
 /*   130 */    50,   51,   51,   51,   51,   51,   51,   51,   51,   50,
 /*   140 */    50,   50,
);
    const YY_SHIFT_USE_DFLT = -18;
    const YY_SHIFT_MAX = 34;
    static public $yy_shift_ofst = array(
 /*     0 */    16,   42,   42,   -5,   -5,   82,   72,   15,   93,   15,
 /*    10 */    15,   15,   15,   15,   15,   15,  103,   60,   43,   87,
 /*    20 */    -3,   86,  104,   60,   86,   60,  -18,  -18,   99,   -4,
 /*    30 */   -17,   30,   46,   33,   59,
);
    const YY_REDUCE_USE_DFLT = -28;
    const YY_REDUCE_MAX = 27;
    static public $yy_reduce_ofst = array(
 /*     0 */    41,  -27,   -7,   26,   36,    1,   48,   79,   61,   51,
 /*    10 */    89,   91,   73,   90,   80,   77,   10,   76,   56,   71,
 /*    20 */    95,  100,   71,   34,   52,   34,   54,    5,
);
    static public $yyExpectedTokens = array(
        /* 0 */ array(3, ),
        /* 1 */ array(2, 4, 8, 9, 16, 17, 18, 19, 21, ),
        /* 2 */ array(2, 4, 8, 9, 16, 17, 18, 19, 21, ),
        /* 3 */ array(16, 17, 18, 19, 21, ),
        /* 4 */ array(16, 17, 18, 19, 21, ),
        /* 5 */ array(10, 13, 14, ),
        /* 6 */ array(11, 17, 18, 19, 21, ),
        /* 7 */ array(17, 18, 19, 21, ),
        /* 8 */ array(10, 13, 14, ),
        /* 9 */ array(17, 18, 19, 21, ),
        /* 10 */ array(17, 18, 19, 21, ),
        /* 11 */ array(17, 18, 19, 21, ),
        /* 12 */ array(17, 18, 19, 21, ),
        /* 13 */ array(17, 18, 19, 21, ),
        /* 14 */ array(17, 18, 19, 21, ),
        /* 15 */ array(17, 18, 19, 21, ),
        /* 16 */ array(13, 14, ),
        /* 17 */ array(10, ),
        /* 18 */ array(6, 11, 12, ),
        /* 19 */ array(7, 15, ),
        /* 20 */ array(4, 5, ),
        /* 21 */ array(1, ),
        /* 22 */ array(15, ),
        /* 23 */ array(10, ),
        /* 24 */ array(1, ),
        /* 25 */ array(10, ),
        /* 26 */ array(),
        /* 27 */ array(),
        /* 28 */ array(13, 14, ),
        /* 29 */ array(21, ),
        /* 30 */ array(20, ),
        /* 31 */ array(7, ),
        /* 32 */ array(6, ),
        /* 33 */ array(15, ),
        /* 34 */ array(7, ),
        /* 35 */ array(),
        /* 36 */ array(),
        /* 37 */ array(),
        /* 38 */ array(),
        /* 39 */ array(),
        /* 40 */ array(),
        /* 41 */ array(),
        /* 42 */ array(),
        /* 43 */ array(),
        /* 44 */ array(),
        /* 45 */ array(),
        /* 46 */ array(),
        /* 47 */ array(),
        /* 48 */ array(),
        /* 49 */ array(),
        /* 50 */ array(),
        /* 51 */ array(),
        /* 52 */ array(),
        /* 53 */ array(),
        /* 54 */ array(),
        /* 55 */ array(),
        /* 56 */ array(),
        /* 57 */ array(),
        /* 58 */ array(),
        /* 59 */ array(),
        /* 60 */ array(),
        /* 61 */ array(),
        /* 62 */ array(),
        /* 63 */ array(),
        /* 64 */ array(),
        /* 65 */ array(),
        /* 66 */ array(),
        /* 67 */ array(),
        /* 68 */ array(),
        /* 69 */ array(),
        /* 70 */ array(),
        /* 71 */ array(),
        /* 72 */ array(),
        /* 73 */ array(),
        /* 74 */ array(),
        /* 75 */ array(),
        /* 76 */ array(),
);
    static public $yy_default = array(
 /*     0 */    82,  126,  126,  126,  126,  110,  133,   86,  110,  133,
 /*    10 */   133,  125,  133,  133,  133,  133,  133,  133,  107,  119,
 /*    20 */   133,  133,  119,   95,  133,  109,   89,   89,   94,  133,
 /*    30 */   129,  133,   88,  121,  133,   85,   84,   83,  111,  112,
 /*    40 */   113,   78,   80,  114,  116,  115,  124,  117,  123,  122,
 /*    50 */   106,  108,  130,   77,   79,   87,   90,   91,   92,   93,
 /*    60 */   127,  128,  131,  132,   98,  118,  120,   99,  101,  102,
 /*    70 */   103,  105,  104,  100,   96,   97,   81,
);
/* The next thing included is series of defines which control
** various aspects of the generated parser.
**    self::YYNOCODE      is a number which corresponds
**                        to no legal terminal or nonterminal number.  This
**                        number is used to fill in empty slots of the hash 
**                        table.
**    self::YYFALLBACK    If defined, this indicates that one or more tokens
**                        have fall-back values which should be used if the
**                        original value of the token will not parse.
**    self::YYSTACKDEPTH  is the maximum depth of the parser's stack.
**    self::YYNSTATE      the combined number of states.
**    self::YYNRULE       the number of rules in the grammar
**    self::YYERRORSYMBOL is the code number of the error symbol.  If not
**                        defined, then do no error processing.
*/
    const YYNOCODE = 52;
    const YYSTACKDEPTH = 100;
    const YYNSTATE = 77;
    const YYNRULE = 56;
    const YYERRORSYMBOL = 22;
    const YYERRSYMDT = 'yy0';
    const YYFALLBACK = 0;
    /** The next table maps tokens into fallback tokens.  If a construct
     * like the following:
     * 
     *      %fallback ID X Y Z.
     *
     * appears in the grammer, then ID becomes a fallback token for X, Y,
     * and Z.  Whenever one of the tokens X, Y, or Z is input to the parser
     * but it does not parse, the type of the token is changed to ID and
     * the parse is retried before an error is thrown.
     */
    static public $yyFallback = array(
    );
    /**
     * Turn parser tracing on by giving a stream to which to write the trace
     * and a prompt to preface each trace message.  Tracing is turned off
     * by making either argument NULL 
     *
     * Inputs:
     * 
     * - A stream resource to which trace output should be written.
     *   If NULL, then tracing is turned off.
     * - A prefix string written at the beginning of every
     *   line of trace output.  If NULL, then tracing is
     *   turned off.
     *
     * Outputs:
     * 
     * - None.
     * @param resource
     * @param string
     */
    static function Trace($TraceFILE, $zTracePrompt)
    {
        if (!$TraceFILE) {
            $zTracePrompt = 0;
        } elseif (!$zTracePrompt) {
            $TraceFILE = 0;
        }
        self::$yyTraceFILE = $TraceFILE;
        self::$yyTracePrompt = $zTracePrompt;
    }

    /**
     * Output debug information to output (php://output stream)
     */
    static function PrintTrace()
    {
        self::$yyTraceFILE = fopen('php://output', 'w');
        self::$yyTracePrompt = '';
    }

    /**
     * @var resource|0
     */
    static public $yyTraceFILE;
    /**
     * String to prepend to debug output
     * @var string|0
     */
    static public $yyTracePrompt;
    /**
     * @var int
     */
    public $yyidx = -1;                    /* Index of top element in stack */
    /**
     * @var int
     */
    public $yyerrcnt;                 /* Shifts left before out of the error */
    /**
     * @var array
     */
    public $yystack = array();  /* The parser's stack */

    /**
     * For tracing shifts, the names of all terminals and nonterminals
     * are required.  The following table supplies these names
     * @var array
     */
    static public $yyTokenName = array( 
  '$',             'CURLY_OPEN',    'CURLY_CLOSE',   'STRICT',      
  'GRAPH',         'DIGRAPH',       'SEMICOLON',     'EQUAL',       
  'NODE',          'EDGE',          'BRACKET_OPEN',  'BRACKET_CLOSE',
  'COMMA',         'DIRECTED_EDGE',  'UNDIRECTED_EDGE',  'COLON',       
  'SUBGRAPH',      'IDENTIFIER',    'NUMERAL',       'HTML_STRING', 
  'PLUS',          'DQUOTED_STRING',  'error',         'start',       
  'root_graph',    'push_scope',    'stmt_list',     'pop_scope',   
  'strict',        'graph_type',    'graph_id',      'id',          
  'stmt',          'node_stmt',     'edge_stmt',     'attr_stmt',   
  'subgraph',      'attr_scope',    'attr_list',     'attr_list_item',
  'a_list',        'a_list_sep',    'node_id_or_subgraph',  'edge_rhs',    
  'stmt_attrs',    'node_id',       'edgeop',        'port',        
  'subgraph_constructor',  'subgraph_id',   'dquoted_string',
    );

    /**
     * For tracing reduce actions, the names of all rules are required.
     * @var array
     */
    static public $yyRuleName = array(
 /*   0 */ "start ::= root_graph push_scope stmt_list pop_scope",
 /*   1 */ "push_scope ::= CURLY_OPEN",
 /*   2 */ "pop_scope ::= CURLY_CLOSE",
 /*   3 */ "root_graph ::= strict graph_type graph_id",
 /*   4 */ "strict ::= STRICT",
 /*   5 */ "strict ::=",
 /*   6 */ "graph_type ::= GRAPH",
 /*   7 */ "graph_type ::= DIGRAPH",
 /*   8 */ "graph_id ::= id",
 /*   9 */ "graph_id ::=",
 /*  10 */ "stmt_list ::= stmt_list stmt SEMICOLON",
 /*  11 */ "stmt_list ::= stmt_list stmt",
 /*  12 */ "stmt_list ::=",
 /*  13 */ "stmt ::= node_stmt",
 /*  14 */ "stmt ::= edge_stmt",
 /*  15 */ "stmt ::= attr_stmt",
 /*  16 */ "stmt ::= id EQUAL id",
 /*  17 */ "stmt ::= subgraph",
 /*  18 */ "attr_stmt ::= attr_scope attr_list",
 /*  19 */ "attr_scope ::= GRAPH",
 /*  20 */ "attr_scope ::= NODE",
 /*  21 */ "attr_scope ::= EDGE",
 /*  22 */ "attr_list ::= attr_list attr_list_item",
 /*  23 */ "attr_list ::= attr_list_item",
 /*  24 */ "attr_list_item ::= BRACKET_OPEN BRACKET_CLOSE",
 /*  25 */ "attr_list_item ::= BRACKET_OPEN a_list BRACKET_CLOSE",
 /*  26 */ "a_list ::= a_list a_list_sep id EQUAL id",
 /*  27 */ "a_list ::= id EQUAL id",
 /*  28 */ "a_list_sep ::= SEMICOLON",
 /*  29 */ "a_list_sep ::= COMMA",
 /*  30 */ "a_list_sep ::=",
 /*  31 */ "edge_stmt ::= node_id_or_subgraph edge_rhs stmt_attrs",
 /*  32 */ "stmt_attrs ::= attr_list",
 /*  33 */ "stmt_attrs ::=",
 /*  34 */ "node_id_or_subgraph ::= node_id",
 /*  35 */ "node_id_or_subgraph ::= subgraph",
 /*  36 */ "edge_rhs ::= edge_rhs edgeop node_id_or_subgraph",
 /*  37 */ "edge_rhs ::= edgeop node_id_or_subgraph",
 /*  38 */ "edgeop ::= DIRECTED_EDGE",
 /*  39 */ "edgeop ::= UNDIRECTED_EDGE",
 /*  40 */ "node_stmt ::= node_id stmt_attrs",
 /*  41 */ "node_id ::= id port",
 /*  42 */ "node_id ::= id",
 /*  43 */ "port ::= COLON id COLON id",
 /*  44 */ "port ::= COLON id",
 /*  45 */ "subgraph ::= subgraph_constructor push_scope stmt_list pop_scope",
 /*  46 */ "subgraph_constructor ::= subgraph_id",
 /*  47 */ "subgraph_id ::= SUBGRAPH id",
 /*  48 */ "subgraph_id ::= SUBGRAPH",
 /*  49 */ "subgraph_id ::=",
 /*  50 */ "id ::= IDENTIFIER",
 /*  51 */ "id ::= NUMERAL",
 /*  52 */ "id ::= dquoted_string",
 /*  53 */ "id ::= HTML_STRING",
 /*  54 */ "dquoted_string ::= dquoted_string PLUS DQUOTED_STRING",
 /*  55 */ "dquoted_string ::= DQUOTED_STRING",
    );

    /**
     * This function returns the symbolic name associated with a token
     * value.
     * @param int
     * @return string
     */
    function tokenName($tokenType)
    {
        if ($tokenType === 0) {
            return 'End of Input';
        }
        if ($tokenType > 0 && $tokenType < count(self::$yyTokenName)) {
            return self::$yyTokenName[$tokenType];
        } else {
            return "Unknown";
        }
    }

    /**
     * The following function deletes the value associated with a
     * symbol.  The symbol can be either a terminal or nonterminal.
     * @param int the symbol code
     * @param mixed the symbol's value
     */
    static function yy_destructor($yymajor, $yypminor)
    {
        switch ($yymajor) {
        /* Here is inserted the actions which take place when a
        ** terminal or non-terminal is destroyed.  This can happen
        ** when the symbol is popped from the stack during a
        ** reduce or during error processing or when a parser is 
        ** being destroyed before it is finished parsing.
        **
        ** Note: during a reduce, the only symbols destroyed are those
        ** which appear on the RHS of the rule, but which are not used
        ** inside the C code.
        */
            default:  break;   /* If no destructor action specified: do nothing */
        }
    }

    /**
     * Pop the parser's stack once.
     *
     * If there is a destructor routine associated with the token which
     * is popped from the stack, then call it.
     *
     * Return the major token number for the symbol popped.
     * @param ParseryyParser
     * @return int
     */
    function yy_pop_parser_stack()
    {
        if (!count($this->yystack)) {
            return;
        }
        $yytos = array_pop($this->yystack);
        if (self::$yyTraceFILE && $this->yyidx >= 0) {
            fwrite(self::$yyTraceFILE,
                self::$yyTracePrompt . 'Popping ' . self::$yyTokenName[$yytos->major] .
                    "\n");
        }
        $yymajor = $yytos->major;
        self::yy_destructor($yymajor, $yytos->minor);
        $this->yyidx--;
        return $yymajor;
    }

    /**
     * Deallocate and destroy a parser.  Destructors are all called for
     * all stack elements before shutting the parser down.
     */
    function __destruct()
    {
        while ($this->yyidx >= 0) {
            $this->yy_pop_parser_stack();
        }
        if (is_resource(self::$yyTraceFILE)) {
            fclose(self::$yyTraceFILE);
        }
    }

    /**
     * Based on the current state and parser stack, get a list of all
     * possible lookahead tokens
     * @param int
     * @return array
     */
    function yy_get_expected_tokens($token)
    {
        $state = $this->yystack[$this->yyidx]->stateno;
        $expected = self::$yyExpectedTokens[$state];
        if (in_array($token, self::$yyExpectedTokens[$state], true)) {
            return $expected;
        }
        $stack = $this->yystack;
        $yyidx = $this->yyidx;
        do {
            $yyact = $this->yy_find_shift_action($token);
            if ($yyact >= self::YYNSTATE && $yyact < self::YYNSTATE + self::YYNRULE) {
                // reduce action
                $done = 0;
                do {
                    if ($done++ == 100) {
                        $this->yyidx = $yyidx;
                        $this->yystack = $stack;
                        // too much recursion prevents proper detection
                        // so give up
                        return array_unique($expected);
                    }
                    $yyruleno = $yyact - self::YYNSTATE;
                    $this->yyidx -= self::$yyRuleInfo[$yyruleno]['rhs'];
                    $nextstate = $this->yy_find_reduce_action(
                        $this->yystack[$this->yyidx]->stateno,
                        self::$yyRuleInfo[$yyruleno]['lhs']);
                    if (isset(self::$yyExpectedTokens[$nextstate])) {
                        $expected += self::$yyExpectedTokens[$nextstate];
                            if (in_array($token,
                                  self::$yyExpectedTokens[$nextstate], true)) {
                            $this->yyidx = $yyidx;
                            $this->yystack = $stack;
                            return array_unique($expected);
                        }
                    }
                    if ($nextstate < self::YYNSTATE) {
                        // we need to shift a non-terminal
                        $this->yyidx++;
                        $x = new ParseryyStackEntry;
                        $x->stateno = $nextstate;
                        $x->major = self::$yyRuleInfo[$yyruleno]['lhs'];
                        $this->yystack[$this->yyidx] = $x;
                        continue 2;
                    } elseif ($nextstate == self::YYNSTATE + self::YYNRULE + 1) {
                        $this->yyidx = $yyidx;
                        $this->yystack = $stack;
                        // the last token was just ignored, we can't accept
                        // by ignoring input, this is in essence ignoring a
                        // syntax error!
                        return array_unique($expected);
                    } elseif ($nextstate === self::YY_NO_ACTION) {
                        $this->yyidx = $yyidx;
                        $this->yystack = $stack;
                        // input accepted, but not shifted (I guess)
                        return $expected;
                    } else {
                        $yyact = $nextstate;
                    }
                } while (true);
            }
            break;
        } while (true);
        return array_unique($expected);
    }

    /**
     * Based on the parser state and current parser stack, determine whether
     * the lookahead token is possible.
     * 
     * The parser will convert the token value to an error token if not.  This
     * catches some unusual edge cases where the parser would fail.
     * @param int
     * @return bool
     */
    function yy_is_expected_token($token)
    {
        if ($token === 0) {
            return true; // 0 is not part of this
        }
        $state = $this->yystack[$this->yyidx]->stateno;
        if (in_array($token, self::$yyExpectedTokens[$state], true)) {
            return true;
        }
        $stack = $this->yystack;
        $yyidx = $this->yyidx;
        do {
            $yyact = $this->yy_find_shift_action($token);
            if ($yyact >= self::YYNSTATE && $yyact < self::YYNSTATE + self::YYNRULE) {
                // reduce action
                $done = 0;
                do {
                    if ($done++ == 100) {
                        $this->yyidx = $yyidx;
                        $this->yystack = $stack;
                        // too much recursion prevents proper detection
                        // so give up
                        return true;
                    }
                    $yyruleno = $yyact - self::YYNSTATE;
                    $this->yyidx -= self::$yyRuleInfo[$yyruleno]['rhs'];
                    $nextstate = $this->yy_find_reduce_action(
                        $this->yystack[$this->yyidx]->stateno,
                        self::$yyRuleInfo[$yyruleno]['lhs']);
                    if (isset(self::$yyExpectedTokens[$nextstate]) &&
                          in_array($token, self::$yyExpectedTokens[$nextstate], true)) {
                        $this->yyidx = $yyidx;
                        $this->yystack = $stack;
                        return true;
                    }
                    if ($nextstate < self::YYNSTATE) {
                        // we need to shift a non-terminal
                        $this->yyidx++;
                        $x = new ParseryyStackEntry;
                        $x->stateno = $nextstate;
                        $x->major = self::$yyRuleInfo[$yyruleno]['lhs'];
                        $this->yystack[$this->yyidx] = $x;
                        continue 2;
                    } elseif ($nextstate == self::YYNSTATE + self::YYNRULE + 1) {
                        $this->yyidx = $yyidx;
                        $this->yystack = $stack;
                        if (!$token) {
                            // end of input: this is valid
                            return true;
                        }
                        // the last token was just ignored, we can't accept
                        // by ignoring input, this is in essence ignoring a
                        // syntax error!
                        return false;
                    } elseif ($nextstate === self::YY_NO_ACTION) {
                        $this->yyidx = $yyidx;
                        $this->yystack = $stack;
                        // input accepted, but not shifted (I guess)
                        return true;
                    } else {
                        $yyact = $nextstate;
                    }
                } while (true);
            }
            break;
        } while (true);
        $this->yyidx = $yyidx;
        $this->yystack = $stack;
        return true;
    }

    /**
     * Find the appropriate action for a parser given the terminal
     * look-ahead token iLookAhead.
     *
     * If the look-ahead token is YYNOCODE, then check to see if the action is
     * independent of the look-ahead.  If it is, return the action, otherwise
     * return YY_NO_ACTION.
     * @param int The look-ahead token
     */
    function yy_find_shift_action($iLookAhead)
    {
        $stateno = $this->yystack[$this->yyidx]->stateno;
     
        /* if ($this->yyidx < 0) return self::YY_NO_ACTION;  */
        if (!isset(self::$yy_shift_ofst[$stateno])) {
            // no shift actions
            return self::$yy_default[$stateno];
        }
        $i = self::$yy_shift_ofst[$stateno];
        if ($i === self::YY_SHIFT_USE_DFLT) {
            return self::$yy_default[$stateno];
        }
        if ($iLookAhead == self::YYNOCODE) {
            return self::YY_NO_ACTION;
        }
        $i += $iLookAhead;
        if ($i < 0 || $i >= self::YY_SZ_ACTTAB ||
              self::$yy_lookahead[$i] != $iLookAhead) {
            if (count(self::$yyFallback) && $iLookAhead < count(self::$yyFallback)
                   && ($iFallback = self::$yyFallback[$iLookAhead]) != 0) {
                if (self::$yyTraceFILE) {
                    fwrite(self::$yyTraceFILE, self::$yyTracePrompt . "FALLBACK " .
                        self::$yyTokenName[$iLookAhead] . " => " .
                        self::$yyTokenName[$iFallback] . "\n");
                }
                return $this->yy_find_shift_action($iFallback);
            }
            return self::$yy_default[$stateno];
        } else {
            return self::$yy_action[$i];
        }
    }

    /**
     * Find the appropriate action for a parser given the non-terminal
     * look-ahead token $iLookAhead.
     *
     * If the look-ahead token is self::YYNOCODE, then check to see if the action is
     * independent of the look-ahead.  If it is, return the action, otherwise
     * return self::YY_NO_ACTION.
     * @param int Current state number
     * @param int The look-ahead token
     */
    function yy_find_reduce_action($stateno, $iLookAhead)
    {
        /* $stateno = $this->yystack[$this->yyidx]->stateno; */

        if (!isset(self::$yy_reduce_ofst[$stateno])) {
            return self::$yy_default[$stateno];
        }
        $i = self::$yy_reduce_ofst[$stateno];
        if ($i == self::YY_REDUCE_USE_DFLT) {
            return self::$yy_default[$stateno];
        }
        if ($iLookAhead == self::YYNOCODE) {
            return self::YY_NO_ACTION;
        }
        $i += $iLookAhead;
        if ($i < 0 || $i >= self::YY_SZ_ACTTAB ||
              self::$yy_lookahead[$i] != $iLookAhead) {
            return self::$yy_default[$stateno];
        } else {
            return self::$yy_action[$i];
        }
    }

    /**
     * Perform a shift action.
     * @param int The new state to shift in
     * @param int The major token to shift in
     * @param mixed the minor token to shift in
     */
    function yy_shift($yyNewState, $yyMajor, $yypMinor)
    {
        $this->yyidx++;
        if ($this->yyidx >= self::YYSTACKDEPTH) {
            $this->yyidx--;
            if (self::$yyTraceFILE) {
                fprintf(self::$yyTraceFILE, "%sStack Overflow!\n", self::$yyTracePrompt);
            }
            while ($this->yyidx >= 0) {
                $this->yy_pop_parser_stack();
            }
            /* Here code is inserted which will execute if the parser
            ** stack ever overflows */
            return;
        }
        $yytos = new ParseryyStackEntry;
        $yytos->stateno = $yyNewState;
        $yytos->major = $yyMajor;
        $yytos->minor = $yypMinor;
        array_push($this->yystack, $yytos);
        if (self::$yyTraceFILE && $this->yyidx > 0) {
            fprintf(self::$yyTraceFILE, "%sShift %d\n", self::$yyTracePrompt,
                $yyNewState);
            fprintf(self::$yyTraceFILE, "%sStack:", self::$yyTracePrompt);
            for ($i = 1; $i <= $this->yyidx; $i++) {
                fprintf(self::$yyTraceFILE, " %s",
                    self::$yyTokenName[$this->yystack[$i]->major]);
            }
            fwrite(self::$yyTraceFILE,"\n");
        }
    }

    /**
     * The following table contains information about every rule that
     * is used during the reduce.
     *
     * <pre>
     * array(
     *  array(
     *   int $lhs;         Symbol on the left-hand side of the rule
     *   int $nrhs;     Number of right-hand side symbols in the rule
     *  ),...
     * );
     * </pre>
     */
    static public $yyRuleInfo = array(
  array( 'lhs' => 23, 'rhs' => 4 ),
  array( 'lhs' => 25, 'rhs' => 1 ),
  array( 'lhs' => 27, 'rhs' => 1 ),
  array( 'lhs' => 24, 'rhs' => 3 ),
  array( 'lhs' => 28, 'rhs' => 1 ),
  array( 'lhs' => 28, 'rhs' => 0 ),
  array( 'lhs' => 29, 'rhs' => 1 ),
  array( 'lhs' => 29, 'rhs' => 1 ),
  array( 'lhs' => 30, 'rhs' => 1 ),
  array( 'lhs' => 30, 'rhs' => 0 ),
  array( 'lhs' => 26, 'rhs' => 3 ),
  array( 'lhs' => 26, 'rhs' => 2 ),
  array( 'lhs' => 26, 'rhs' => 0 ),
  array( 'lhs' => 32, 'rhs' => 1 ),
  array( 'lhs' => 32, 'rhs' => 1 ),
  array( 'lhs' => 32, 'rhs' => 1 ),
  array( 'lhs' => 32, 'rhs' => 3 ),
  array( 'lhs' => 32, 'rhs' => 1 ),
  array( 'lhs' => 35, 'rhs' => 2 ),
  array( 'lhs' => 37, 'rhs' => 1 ),
  array( 'lhs' => 37, 'rhs' => 1 ),
  array( 'lhs' => 37, 'rhs' => 1 ),
  array( 'lhs' => 38, 'rhs' => 2 ),
  array( 'lhs' => 38, 'rhs' => 1 ),
  array( 'lhs' => 39, 'rhs' => 2 ),
  array( 'lhs' => 39, 'rhs' => 3 ),
  array( 'lhs' => 40, 'rhs' => 5 ),
  array( 'lhs' => 40, 'rhs' => 3 ),
  array( 'lhs' => 41, 'rhs' => 1 ),
  array( 'lhs' => 41, 'rhs' => 1 ),
  array( 'lhs' => 41, 'rhs' => 0 ),
  array( 'lhs' => 34, 'rhs' => 3 ),
  array( 'lhs' => 44, 'rhs' => 1 ),
  array( 'lhs' => 44, 'rhs' => 0 ),
  array( 'lhs' => 42, 'rhs' => 1 ),
  array( 'lhs' => 42, 'rhs' => 1 ),
  array( 'lhs' => 43, 'rhs' => 3 ),
  array( 'lhs' => 43, 'rhs' => 2 ),
  array( 'lhs' => 46, 'rhs' => 1 ),
  array( 'lhs' => 46, 'rhs' => 1 ),
  array( 'lhs' => 33, 'rhs' => 2 ),
  array( 'lhs' => 45, 'rhs' => 2 ),
  array( 'lhs' => 45, 'rhs' => 1 ),
  array( 'lhs' => 47, 'rhs' => 4 ),
  array( 'lhs' => 47, 'rhs' => 2 ),
  array( 'lhs' => 36, 'rhs' => 4 ),
  array( 'lhs' => 48, 'rhs' => 1 ),
  array( 'lhs' => 49, 'rhs' => 2 ),
  array( 'lhs' => 49, 'rhs' => 1 ),
  array( 'lhs' => 49, 'rhs' => 0 ),
  array( 'lhs' => 31, 'rhs' => 1 ),
  array( 'lhs' => 31, 'rhs' => 1 ),
  array( 'lhs' => 31, 'rhs' => 1 ),
  array( 'lhs' => 31, 'rhs' => 1 ),
  array( 'lhs' => 50, 'rhs' => 3 ),
  array( 'lhs' => 50, 'rhs' => 1 ),
    );

    /**
     * The following table contains a mapping of reduce action to method name
     * that handles the reduction.
     * 
     * If a rule is not set, it has no handler.
     */
    static public $yyReduceMap = array(
        0 => 0,
        1 => 1,
        2 => 2,
        3 => 3,
        4 => 4,
        5 => 5,
        6 => 6,
        7 => 7,
        8 => 8,
        42 => 8,
        9 => 9,
        48 => 9,
        49 => 9,
        16 => 16,
        17 => 17,
        43 => 17,
        44 => 17,
        18 => 18,
        19 => 19,
        20 => 20,
        21 => 21,
        22 => 22,
        23 => 23,
        32 => 23,
        35 => 23,
        50 => 23,
        51 => 23,
        52 => 23,
        53 => 23,
        55 => 23,
        24 => 24,
        33 => 24,
        25 => 25,
        26 => 26,
        27 => 27,
        31 => 31,
        34 => 34,
        36 => 36,
        37 => 37,
        38 => 38,
        39 => 39,
        40 => 40,
        41 => 41,
        45 => 45,
        46 => 46,
        47 => 47,
        54 => 54,
    );
    /* Beginning here are the reduction cases.  A typical example
    ** follows:
    **  #line <lineno> <grammarfile>
    **   function yy_r0($yymsp){ ... }           // User supplied code
    **  #line <lineno> <thisfile>
    */
#line 315 "src/Parser.y"
    function yy_r0(){
    $this->result = $this->yystack[$this->yyidx + -3]->minor;
    }
#line 1306 "src/Parser.php"
#line 319 "src/Parser.y"
    function yy_r1(){
    $this->attributes[self::TOKEN_GRAPH][]  = [];
    $this->attributes[self::TOKEN_NODE][]   = [];
    $this->attributes[self::TOKEN_EDGE][]   = [];
    }
#line 1313 "src/Parser.php"
#line 325 "src/Parser.y"
    function yy_r2(){
    array_pop($this->attributes[self::TOKEN_GRAPH]);
    array_pop($this->attributes[self::TOKEN_NODE]);
    array_pop($this->attributes[self::TOKEN_EDGE]);
    array_pop($this->graphs);
    }
#line 1321 "src/Parser.php"
#line 332 "src/Parser.y"
    function yy_r3(){
    $graphtypes = [
        self::TOKEN_UNDIRECTED_EDGE => [MultiGraph::class, Graph::class],
        self::TOKEN_DIRECTED_EDGE   => [MultiDiGraph::class, DiGraph::class],
    ];
    $graphcls = $graphtypes[$this->yystack[$this->yyidx + -1]->minor][(int) $this->yystack[$this->yyidx + -2]->minor];
    $this->graphs[] = $this->_retvalue = new $graphcls($this->yystack[$this->yyidx + 0]->minor);
    }
#line 1331 "src/Parser.php"
#line 341 "src/Parser.y"
    function yy_r4(){  $this->_retvalue = true;     }
#line 1334 "src/Parser.php"
#line 342 "src/Parser.y"
    function yy_r5(){  $this->_retvalue = false;     }
#line 1337 "src/Parser.php"
#line 344 "src/Parser.y"
    function yy_r6(){ $this->_retvalue = self::TOKEN_UNDIRECTED_EDGE;     }
#line 1340 "src/Parser.php"
#line 345 "src/Parser.y"
    function yy_r7(){ $this->_retvalue = self::TOKEN_DIRECTED_EDGE;     }
#line 1343 "src/Parser.php"
#line 347 "src/Parser.y"
    function yy_r8(){ $this->_retvalue = $this->yystack[$this->yyidx + 0]->minor->value;     }
#line 1346 "src/Parser.php"
#line 348 "src/Parser.y"
    function yy_r9(){ $this->_retvalue = '';     }
#line 1349 "src/Parser.php"
#line 357 "src/Parser.y"
    function yy_r16(){
    $this->graphs[count($this->graphs) - 1]->attributes[$this->yystack[$this->yyidx + -2]->minor->value] = $this->yystack[$this->yyidx + 0]->minor->value;
    }
#line 1354 "src/Parser.php"
#line 360 "src/Parser.y"
    function yy_r17(){     }
#line 1357 "src/Parser.php"
#line 362 "src/Parser.y"
    function yy_r18(){
    $this->attributes[$this->yystack[$this->yyidx + -1]->minor][count($this->attributes[$this->yystack[$this->yyidx + -1]->minor]) - 1] =
        array_merge($this->attributes[$this->yystack[$this->yyidx + -1]->minor][count($this->attributes[$this->yystack[$this->yyidx + -1]->minor]) - 1],$this->yystack[$this->yyidx + 0]->minor);
    }
#line 1363 "src/Parser.php"
#line 367 "src/Parser.y"
    function yy_r19(){ $this->_retvalue = self::TOKEN_GRAPH;     }
#line 1366 "src/Parser.php"
#line 368 "src/Parser.y"
    function yy_r20(){ $this->_retvalue = self::TOKEN_NODE;     }
#line 1369 "src/Parser.php"
#line 369 "src/Parser.y"
    function yy_r21(){ $this->_retvalue = self::TOKEN_EDGE;     }
#line 1372 "src/Parser.php"
#line 371 "src/Parser.y"
    function yy_r22(){ $this->_retvalue = array_merge($this->yystack[$this->yyidx + -1]->minor, $this->yystack[$this->yyidx + 0]->minor);     }
#line 1375 "src/Parser.php"
#line 372 "src/Parser.y"
    function yy_r23(){ $this->_retvalue = $this->yystack[$this->yyidx + 0]->minor;     }
#line 1378 "src/Parser.php"
#line 374 "src/Parser.y"
    function yy_r24(){ $this->_retvalue = [];     }
#line 1381 "src/Parser.php"
#line 375 "src/Parser.y"
    function yy_r25(){ $this->_retvalue = $this->yystack[$this->yyidx + -1]->minor;     }
#line 1384 "src/Parser.php"
#line 377 "src/Parser.y"
    function yy_r26(){ $this->_retvalue = $this->yystack[$this->yyidx + -4]->minor; $this->_retvalue[$this->yystack[$this->yyidx + -2]->minor->value] = $this->yystack[$this->yyidx + 0]->minor->value;     }
#line 1387 "src/Parser.php"
#line 378 "src/Parser.y"
    function yy_r27(){ $this->_retvalue = [$this->yystack[$this->yyidx + -2]->minor->value => $this->yystack[$this->yyidx + 0]->minor->value];     }
#line 1390 "src/Parser.php"
#line 384 "src/Parser.y"
    function yy_r31(){
    $inheritedNode  = array_merge(...$this->attributes[self::TOKEN_NODE]);
    $inheritedEdge  = array_merge(...$this->attributes[self::TOKEN_EDGE]);
    $inheritedEdge  = array_merge($inheritedEdge, $this->yystack[$this->yyidx + 0]->minor);

    $sources = $this->yystack[$this->yyidx + -2]->minor;
    foreach ($this->yystack[$this->yyidx + -1]->minor as $destinations) {
        foreach ($sources->nodes as $source) {
            foreach ($destinations->nodes as $destination) {
                if (!$this->graphs[0]->hasNode($source->name)) {
                    $this->graphs[0]->addNode($source->name, $inheritedNode);
                }
                if (!$this->graphs[0]->hasNode($destination->name)) {
                    $this->graphs[0]->addNode($destination->name, $inheritedNode);
                }
                $this->graphs[0]->addEdge($source->name, $destination->name, $inheritedEdge);
            }
        }
        $sources = $destinations;
    }
    }
#line 1413 "src/Parser.php"
#line 409 "src/Parser.y"
    function yy_r34(){ $this->_retvalue = new SubGraph(''); $this->_retvalue->addNode($this->yystack[$this->yyidx + 0]->minor);     }
#line 1416 "src/Parser.php"
#line 412 "src/Parser.y"
    function yy_r36(){ $this->_retvalue = $this->yystack[$this->yyidx + -2]->minor; $this->_retvalue[] = $this->yystack[$this->yyidx + 0]->minor;     }
#line 1419 "src/Parser.php"
#line 413 "src/Parser.y"
    function yy_r37(){ $this->_retvalue = [$this->yystack[$this->yyidx + 0]->minor];     }
#line 1422 "src/Parser.php"
#line 415 "src/Parser.y"
    function yy_r38(){
    if ($this->graphs[0]::GRAPH_TYPE !== 'digraph') {
        throw new \Exception('Cannot add directed edge to undirected graph');
    }
    }
#line 1429 "src/Parser.php"
#line 420 "src/Parser.y"
    function yy_r39(){
    if ($this->graphs[0]::GRAPH_TYPE !== 'graph') {
        throw new \Exception('Cannot add undirected edge to directed graph');
    }
    }
#line 1436 "src/Parser.php"
#line 426 "src/Parser.y"
    function yy_r40(){
    $inherited  = array_merge(...$this->attributes[self::TOKEN_NODE]);
    $inherited  = array_merge($inherited, $this->yystack[$this->yyidx + 0]->minor);
    $this->graphs[count($this->graphs) - 1]->addNode($this->yystack[$this->yyidx + -1]->minor, $inherited);
    }
#line 1443 "src/Parser.php"
#line 435 "src/Parser.y"
    function yy_r41(){ $this->_retvalue = $this->yystack[$this->yyidx + -1]->minor->value;     }
#line 1446 "src/Parser.php"
#line 447 "src/Parser.y"
    function yy_r45(){
    $this->graphs[count($this->graphs) - 1]->addSubgraph($this->yystack[$this->yyidx + -3]->minor);
    $this->_retvalue = $this->yystack[$this->yyidx + -3]->minor;
    }
#line 1452 "src/Parser.php"
#line 452 "src/Parser.y"
    function yy_r46(){
    $this->_retvalue = $this->graphs[0]->getSubgraph($this->yystack[$this->yyidx + 0]->minor);
    if ($this->_retvalue === null) {
        $inherited  = array_merge(...$this->attributes[self::TOKEN_GRAPH]);
        $this->_retvalue = new SubGraph($this->yystack[$this->yyidx + 0]->minor, $inherited);
    }
    $this->graphs[] = $this->_retvalue;
    }
#line 1462 "src/Parser.php"
#line 461 "src/Parser.y"
    function yy_r47(){ $this->_retvalue = $this->yystack[$this->yyidx + 0]->minor->value;    }
#line 1465 "src/Parser.php"
#line 471 "src/Parser.y"
    function yy_r54(){ $this->_retvalue = $this->yystack[$this->yyidx + -2]->minor; $this->yystack[$this->yyidx + -2]->minor->value .= $this->yystack[$this->yyidx + 0]->minor->value;     }
#line 1468 "src/Parser.php"

    /**
     * placeholder for the left hand side in a reduce operation.
     * 
     * For a parser with a rule like this:
     * <pre>
     * rule(A) ::= B. { A = 1; }
     * </pre>
     * 
     * The parser will translate to something like:
     * 
     * <code>
     * function yy_r0(){$this->_retvalue = 1;}
     * </code>
     */
    private $_retvalue;

    /**
     * Perform a reduce action and the shift that must immediately
     * follow the reduce.
     * 
     * For a rule such as:
     * 
     * <pre>
     * A ::= B blah C. { dosomething(); }
     * </pre>
     * 
     * This function will first call the action, if any, ("dosomething();" in our
     * example), and then it will pop three states from the stack,
     * one for each entry on the right-hand side of the expression
     * (B, blah, and C in our example rule), and then push the result of the action
     * back on to the stack with the resulting state reduced to (as described in the .out
     * file)
     * @param int Number of the rule by which to reduce
     */
    function yy_reduce($yyruleno)
    {
        //int $yygoto;                     /* The next state */
        //int $yyact;                      /* The next action */
        //mixed $yygotominor;        /* The LHS of the rule reduced */
        //ParseryyStackEntry $yymsp;            /* The top of the parser's stack */
        //int $yysize;                     /* Amount to pop the stack */
        $yymsp = $this->yystack[$this->yyidx];
        if (self::$yyTraceFILE && $yyruleno >= 0 
              && $yyruleno < count(self::$yyRuleName)) {
            fprintf(self::$yyTraceFILE, "%sReduce (%d) [%s].\n",
                self::$yyTracePrompt, $yyruleno,
                self::$yyRuleName[$yyruleno]);
        }

        $this->_retvalue = $yy_lefthand_side = null;
        if (array_key_exists($yyruleno, self::$yyReduceMap)) {
            // call the action
            $this->_retvalue = null;
            $this->{'yy_r' . self::$yyReduceMap[$yyruleno]}();
            $yy_lefthand_side = $this->_retvalue;
        }
        $yygoto = self::$yyRuleInfo[$yyruleno]['lhs'];
        $yysize = self::$yyRuleInfo[$yyruleno]['rhs'];
        $this->yyidx -= $yysize;
        for ($i = $yysize; $i; $i--) {
            // pop all of the right-hand side parameters
            array_pop($this->yystack);
        }
        $yyact = $this->yy_find_reduce_action($this->yystack[$this->yyidx]->stateno, $yygoto);
        if ($yyact < self::YYNSTATE) {
            /* If we are not debugging and the reduce action popped at least
            ** one element off the stack, then we can push the new element back
            ** onto the stack here, and skip the stack overflow test in yy_shift().
            ** That gives a significant speed improvement. */
            if (!self::$yyTraceFILE && $yysize) {
                $this->yyidx++;
                $x = new ParseryyStackEntry;
                $x->stateno = $yyact;
                $x->major = $yygoto;
                $x->minor = $yy_lefthand_side;
                $this->yystack[$this->yyidx] = $x;
            } else {
                $this->yy_shift($yyact, $yygoto, $yy_lefthand_side);
            }
        } elseif ($yyact == self::YYNSTATE + self::YYNRULE + 1) {
            $this->yy_accept();
        }
    }

    /**
     * The following code executes when the parse fails
     * 
     * Code from %parse_fail is inserted here
     */
    function yy_parse_failed()
    {
        if (self::$yyTraceFILE) {
            fprintf(self::$yyTraceFILE, "%sFail!\n", self::$yyTracePrompt);
        }
        while ($this->yyidx >= 0) {
            $this->yy_pop_parser_stack();
        }
        /* Here code is inserted which will be executed whenever the
        ** parser fails */
    }

    /**
     * The following code executes when a syntax error first occurs.
     * 
     * %syntax_error code is inserted here
     * @param int The major type of the error token
     * @param mixed The minor type of the error token
     */
    function yy_syntax_error($yymajor, $TOKEN)
    {
    }

    /**
     * The following is executed when the parser accepts
     * 
     * %parse_accept code is inserted here
     */
    function yy_accept()
    {
        if (self::$yyTraceFILE) {
            fprintf(self::$yyTraceFILE, "%sAccept!\n", self::$yyTracePrompt);
        }
        while ($this->yyidx >= 0) {
            $stack = $this->yy_pop_parser_stack();
        }
        /* Here code is inserted which will be executed whenever the
        ** parser accepts */
    }

    /**
     * The main parser program.
     * 
     * The first argument is the major token number.  The second is
     * the token value string as scanned from the input.
     *
     * @param int   $yymajor      the token number
     * @param mixed $yytokenvalue the token value
     * @param mixed ...           any extra arguments that should be passed to handlers
     *
     * @return void
     */
    function doParse($yymajor, $yytokenvalue)
    {
//        $yyact;            /* The parser action. */
//        $yyendofinput;     /* True if we are at the end of input */
        $yyerrorhit = 0;   /* True if yymajor has invoked an error */
        
        /* (re)initialize the parser, if necessary */
        if ($this->yyidx === null || $this->yyidx < 0) {
            /* if ($yymajor == 0) return; // not sure why this was here... */
            $this->yyidx = 0;
            $this->yyerrcnt = -1;
            $x = new ParseryyStackEntry;
            $x->stateno = 0;
            $x->major = 0;
            $this->yystack = array();
            array_push($this->yystack, $x);
        }
        $yyendofinput = ($yymajor==0);
        
        if (self::$yyTraceFILE) {
            fprintf(
                self::$yyTraceFILE,
                "%sInput %s\n",
                self::$yyTracePrompt,
                self::$yyTokenName[$yymajor]
            );
        }
        
        do {
            $yyact = $this->yy_find_shift_action($yymajor);
            if ($yymajor < self::YYERRORSYMBOL
                && !$this->yy_is_expected_token($yymajor)
            ) {
                // force a syntax error
                $yyact = self::YY_ERROR_ACTION;
            }
            if ($yyact < self::YYNSTATE) {
                $this->yy_shift($yyact, $yymajor, $yytokenvalue);
                $this->yyerrcnt--;
                if ($yyendofinput && $this->yyidx >= 0) {
                    $yymajor = 0;
                } else {
                    $yymajor = self::YYNOCODE;
                }
            } elseif ($yyact < self::YYNSTATE + self::YYNRULE) {
                $this->yy_reduce($yyact - self::YYNSTATE);
            } elseif ($yyact == self::YY_ERROR_ACTION) {
                if (self::$yyTraceFILE) {
                    fprintf(
                        self::$yyTraceFILE,
                        "%sSyntax Error!\n",
                        self::$yyTracePrompt
                    );
                }
                if (self::YYERRORSYMBOL) {
                    /* A syntax error has occurred.
                    ** The response to an error depends upon whether or not the
                    ** grammar defines an error token "ERROR".  
                    **
                    ** This is what we do if the grammar does define ERROR:
                    **
                    **  * Call the %syntax_error function.
                    **
                    **  * Begin popping the stack until we enter a state where
                    **    it is legal to shift the error symbol, then shift
                    **    the error symbol.
                    **
                    **  * Set the error count to three.
                    **
                    **  * Begin accepting and shifting new tokens.  No new error
                    **    processing will occur until three tokens have been
                    **    shifted successfully.
                    **
                    */
                    if ($this->yyerrcnt < 0) {
                        $this->yy_syntax_error($yymajor, $yytokenvalue);
                    }
                    $yymx = $this->yystack[$this->yyidx]->major;
                    if ($yymx == self::YYERRORSYMBOL || $yyerrorhit ) {
                        if (self::$yyTraceFILE) {
                            fprintf(
                                self::$yyTraceFILE,
                                "%sDiscard input token %s\n",
                                self::$yyTracePrompt,
                                self::$yyTokenName[$yymajor]
                            );
                        }
                        $this->yy_destructor($yymajor, $yytokenvalue);
                        $yymajor = self::YYNOCODE;
                    } else {
                        while ($this->yyidx >= 0
                            && $yymx != self::YYERRORSYMBOL
                            && ($yyact = $this->yy_find_shift_action(self::YYERRORSYMBOL)) >= self::YYNSTATE
                        ) {
                            $this->yy_pop_parser_stack();
                        }
                        if ($this->yyidx < 0 || $yymajor==0) {
                            $this->yy_destructor($yymajor, $yytokenvalue);
                            $this->yy_parse_failed();
                            $yymajor = self::YYNOCODE;
                        } elseif ($yymx != self::YYERRORSYMBOL) {
                            $u2 = 0;
                            $this->yy_shift($yyact, self::YYERRORSYMBOL, $u2);
                        }
                    }
                    $this->yyerrcnt = 3;
                    $yyerrorhit = 1;
                } else {
                    /* YYERRORSYMBOL is not defined */
                    /* This is what we do if the grammar does not define ERROR:
                    **
                    **  * Report an error message, and throw away the input token.
                    **
                    **  * If the input token is $, then fail the parse.
                    **
                    ** As before, subsequent error messages are suppressed until
                    ** three input tokens have been successfully shifted.
                    */
                    if ($this->yyerrcnt <= 0) {
                        $this->yy_syntax_error($yymajor, $yytokenvalue);
                    }
                    $this->yyerrcnt = 3;
                    $this->yy_destructor($yymajor, $yytokenvalue);
                    if ($yyendofinput) {
                        $this->yy_parse_failed();
                    }
                    $yymajor = self::YYNOCODE;
                }
            } else {
                $this->yy_accept();
                $yymajor = self::YYNOCODE;
            }            
        } while ($yymajor != self::YYNOCODE && $this->yyidx >= 0);
    }
}
