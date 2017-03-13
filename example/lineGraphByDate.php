<?php

require_once __DIR__ . '/../vendor/autoload.php';

$settings = [
    'datetime_keys' => true,
];

$makeValues = function () {
    $p = 500;
    $k = 7000;
    $r = 0.2;
    $values = [];

    foreach (range(0, 30) as $d) {
        $key = (new \DateTime('2017-03-01'))->add(new \DateInterval('P' . $d . 'D'))->format(\DateTime::W3C);
        $values[$key] = (($k * $p * exp($r * $d))/($k + $p * (exp($r * $d) - 1)));
    }

    return $values;
};

$values = $makeValues();

$colours = [['red', 'yellow'], ['blue', 'white']];

$graph = new \SVGGraph\SVGGraph(500, 500, $settings);
$graph->colours = $colours;

$graph->values($values);
echo $graph->fetch('SVGGraph\\Graph\\LineGraph');
