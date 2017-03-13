<?php

namespace SVGGraph\Shape;

class Circle extends Shape
{
    protected $element = 'circle';
    protected $required = ['cx','cy','r'];
    protected $transform = ['r' => 'y'];
    protected $transform_pairs = [['cx', 'cy']];
}
