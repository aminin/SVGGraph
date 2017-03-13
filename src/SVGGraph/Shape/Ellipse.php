<?php

namespace SVGGraph\Shape;

class Ellipse extends Shape
{
    protected $element = 'ellipse';
    protected $required = ['cx','cy','rx','ry'];
    protected $transform = ['rx' => 'x', 'ry' => 'y'];
    protected $transform_pairs = [['cx', 'cy']];
}
