<?php

namespace SVGGraph\Shape;

class Path extends Shape
{
    protected $element = 'path';
    protected $required = ['d'];
}
