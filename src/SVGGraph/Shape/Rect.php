<?php

namespace SVGGraph\Shape;

class Rect extends Shape
{
    protected $element = 'rect';
    protected $required = ['x','y','width','height'];
    protected $transform = ['width' => 'x', 'height' => 'y'];
    protected $transform_pairs = [['x', 'y']];
}
