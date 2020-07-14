<?php
/*
 *  This examples shows how to create a directed graph and edit its nodes
 *  and edges. It also explains the differences between the various kinds
 *  of graphs.
 */

// Install composer's autoloader.
require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';

// Create a strict directed graph named "My graph" and set it so that
// the graph's background color will be a light gray when rendered.
//
// Both arguments to the class constructor are optional.
// If an empty string is passed as the graph's name, an anonymous
// graph is created.
//
// The following classes can be used to create graphs depending on your needs:
// * \fpoirotte\DotGraph\DiGraph for a strict directed graph
// * \fpoirotte\DotGraph\Graph for a strict non-directed graph
// * \fpoirotte\DotGraph\MultiDiGraph for a directed multi-edges graph
// * \fpoirotte\DotGraph\MultiGraph for a non-directed multi-edges graph
//
// A directed graph is one where edges have a "direction", meaning that
// they have a source and a destination.
// In this case, an edge going from "A" to "B" is different from an edge
// going from "B" to "A". Even though they share the same nodes,
// they have opposite directions.
//
// In contrast, edges in a non-directed graph to not have this concept,
// so that both "A to B" and "B to A" refer to the same edge.
//
// The term "strict" means that there can only be one edge between
// any pair of source/destination at any given time (including from
// a node to itself).
//
// In contrast, a "multi-edges graph" is one where multiple edges going from
// the same source to the same destination are allowed (incluging multiple
// edges going from a node to itself).
//
// Useful links:
// * http://www.graphviz.org/doc/info/lang.html for more information
//   on the meaning of the words "strict", "graph" and "digraph"
//   inside the DOT language
// * http://www.graphviz.org/doc/info/attrs.html for more information
//   about valid graph attributes
// * http://www.graphviz.org/doc/info/colors.html for a list of valid
//   color names depending on the rendering context
$graph = new \fpoirotte\DotGraph\DiGraph('My graph', ['bgcolor' => 'lightgray']);

// Add a few nodes...
// ...first, add node "A" which will be rendered in red.
$graph['A'] = ['color' => 'red'];

// ...then add node "B" which has no specific properties.
// This is effectively a shortcut for: $graph['B'] = [].
$graph[] = 'B';

// Add a blue edge from "A" to "B".
$graph[ ['A', 'B'] ] = ['color' => 'blue'];

// And an edge from "B" to "A".
// This is effectively a shortcut for: $graph[ ['B', 'A'] ] = [].
$graph[] = ['B', 'A'];

// Overwrite the color of node "A".
// Since the node has already been defined, the new attributes
// are merged with the previous definition, overwriting the value
// of existing attributes in the process.
$graph['B'] = ['color' => 'green'];

// Likewise, an edge's attributes will be overwritten
// if it already exists and the graph is a Graph or DiGraph.
// Note: in a MultiGraph or MultiDiGraph, a new edge will be
//       created with the given attributes instead.
$graph[ ['A', 'B'] ] = ['color' => 'yellow'];

// Referencing nodes within an edge which have not yet been defined
// for this graph will automatically declare them.
// The following code is effectively equivalent to this one:
//      $graph[] = 'X';
//      $graph[] = 'Y';
//      $graph[ ['X', 'Y'] ] = ['color' => 'red'];
$graph[ ['X', 'Y'] ] = ['color' => 'red'];

// Removing an edge does not automatically remove its nodes from the graph.
// Therefore, the nodes "X" and "Y" still exist inside the graph after
// the following line is executed.
unset($graph[ ['X', 'Y'] ]);

// On the other hand, removing a node from the graph also removes
// all of the edges that referenced it.
// After the following line of code gets executed, the graph is left
// with 3 nodes ("A", "X" and "Y") and no edges.
unset($graph['B']);
