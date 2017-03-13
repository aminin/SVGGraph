<?php

namespace SVGGraph\Shape;

class Line extends Shape
{
    protected $element = 'line';
    protected $required = ['x1','y1','x2','y2'];
    protected $transform_pairs = [['x1', 'y1'], ['x2','y2']];
}
