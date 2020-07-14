<?php
/*
 *  This examples shows how to parse an existing DOT graph
 *  and how to retrieve some of its properties.
 */

// Install composer's autoloader.
require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';

// Parse the graph.
$graph = \fpoirotte\DotGraph\Parser::parse(
    file_get_contents(
        dirname(__DIR__) .
        DIRECTORY_SEPARATOR . 'tests' .
        DIRECTORY_SEPARATOR . 'testdata' .
        DIRECTORY_SEPARATOR . 'misc.dot'
    )
);

// Display the number of nodes inside the graph.
// A slightly less efficient and longer version is:
//      var_dump(count($graph->nodes));
var_dump(count($graph));

// Display the nodes's name.
// $graph->nodes is an associative array with the graph's nodes
// indexed using their name.
var_dump(array_keys($graph->nodes));

// An alternative (and less efficient) way to display the nodes's name
// would be to iterate over each node and query its name, like so:
foreach ($graph->nodes as $node) {
    var_dump($node->name);
}

// For edges, the only way to display their source and destination
// is to iterate other them, like so:
foreach ($graph->edges as $edge) {
    var_dump($edge->source . " -> " . $edge->destination);
}

// Retrieve an instance of \fpoirotte\DotGraph\Node representing the node
// named "K" (or null if a node with that name does not exist in the graph).
$k = $graph['K'];

// Retrieve an instance of \fpoirotte\DotGraph\Edge representing the edge
// between "K" and "L", or null if no such edge exists.
//
// Notes:
//      In a directed graph, the order of the node names is important.
//      In this directed graph example, $graph[ ['K', 'L'] ] will return
//      an object (because an edge does exist between "K" and "L"),
//      while $graph[ ['L', 'K'] ] will return null because there is no edge
//      going from "L" to "K".
//
//      In contrast, in a non-directed graph, $graph[ ['K', 'L'] ] is
//      effectively the same as $graph[ ['L', 'K'] ].
//
//      In a multi-graph (whether a directed or undirected one),
//      the first edge found is returned.
//      If you need to iterate over all edges between two nodes,
//      use the iterEdges() method described below instead. 
$kl = $graph[ ['K', 'L'] ];

// Similar code, using iterEdges() which returns a list of edges between
// two nodes instead.
//
// Notes:
//      The same warning regarding the arguments's order applies here
//      as for the previous syntax ($graph[ ['K', 'L'] ]).
//
//      iterEdges() offers a lot of flexibility for directed graphs
//      because null can be passed as either (or both) of its arguments,
//      limiting the returned values to those edges where the source/destination
//      matches the remaining arguments.
//      In effect, $graph->iterEdges(null, null) is the same as $graph->edges.
$kl2 = $graph->iterEdges('K', 'L')->current();

// Displays predecessors for a node named "K", that is, predecessors()
// returns a list with the names of all the node which have an edge leading to "K".
// In this case, the list contains only one node: "J".
var_dump($graph->predecessors('K'));

// Displays successors for a node named "K", that is, successors()
// returns a list with the names of all the nodes which have an edge leaving "K".
// In this case, the list contains only one node: "L".
var_dump($graph->successors('K'));

// Displays neighbors for a node named "K", that is, neighbors()
// returns the union of all predecessors and successors for "K".
// In this case, the list contains "J" and "L".
var_dump($graph->neighbors('K'));
