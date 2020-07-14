<?php
/*
 *  This examples shows how to export a graph to a DOT file,
 *  including how to make use of DOT's subgroups.
 */

// Install composer's autoloader.
require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';

// Create a new graph with several nodes and edges.
$graph = new \fpoirotte\DotGraph\DiGraph('My graph', ['bgcolor' => 'lightgray']);
$graph[] = ['A' => 'B'];
$graph[] = ['A' => 'C'];

// Add a subgraph to the graph. The arguments for the constructor are the same
// as for a regular graph (a name and an array of attributes to set).
//
// A subgraph can be thought of as a grouping concept: nodes which belong
// to the same subgraph are usually represented together.
// Attributes set on a subgraph usually impact the way the group of nodes
// is rendered.
//
// DOT also has a special concept of "clusters" (subgraphs whose name begins
// with the word "cluster"), which also impacts a visual rendering of the
// subgraph (usually by drawing a box around the subgraph's nodes).
//
// See http://www.graphviz.org/doc/info/lang.html for more information
// on subgraphs and clusters, but please note that not all features of
// actual subgraphs may be supported by this library.
$subgraph = new \fpoirotte\DotGraph\SubGraph('cluster VIP');
$graph->addSubGraph($subgraph);

// Add the nodes "A" and "B" to the subgraph. Since the subgraph is defined
// as a cluster, those nodes will be visually rendered inside a box.
$subgraph[] = ['A', 'B'];

// Casting a graph to a string returns its representation
// using the DOT language.
// You can call this script like so to display the resulting graph on screen:
//      php exporting.php | dot -Tx11
echo (string) $graph;
