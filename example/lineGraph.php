<?php

require_once __DIR__ . '/../vendor/autoload.php';

$settings = [
    'back_colour'        => '#eee',
    'stroke_colour'      => '#000',
    'back_stroke_width'  => 0,
    'back_stroke_colour' => '#eee',
    'axis_colour'        => '#333',
    'axis_overlap'       => 2,
    'axis_font'          => 'Georgia',
    'axis_font_size'     => 10,
    'grid_colour'        => '#666',
    'label_colour'       => '#000',
    'pad_right'          => 20,
    'pad_left'           => 20,
    'link_base'          => '/',
    'link_target'        => '_top',
    'fill_under'         => [true, false],
    'marker_size'        => 3,
    'marker_type'        => ['circle', 'square'],
    'marker_colour'      => ['blue', 'red']
];

$values = [
    ['Dough' => 30, 'Ray' => 50, 'Me' => 40, 'So' => 25, 'Far' => 45, 'Lard' => 35],
    [
        'Dough' => 20,
        'Ray'   => 30,
        'Me'    => 20,
        'So'    => 15,
        'Far'   => 25,
        'Lard'  => 35,
        'Tea'   => 45
    ]
];

$colours = [['red', 'yellow'], ['blue', 'white']];
$links = [
    'Dough' => 'jpegsaver.php',
    'Ray'   => 'crcdropper.php',
    'Me'    => 'svggraph.php'
];

$graph = new \SVGGraph\SVGGraph(300, 200, $settings);
$graph->colours = $colours;

$graph->values($values);
$graph->links($links);
echo $graph->fetch('SVGGraph\\Graph\\LineGraph');
