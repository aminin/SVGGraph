<?php

namespace SVGGraph\Shape;

class PolyLine extends Shape
{
    protected $element = 'polyline';
    protected $required = ['points'];

    public function __construct(&$attrs, $depth)
    {
        parent::__construct($attrs, $depth);
        if (!is_array($this->attrs['points'])) {
            $this->attrs['points'] = explode(' ', $this->attrs['points']);
        }
        $count = count($this->attrs['points']);
        if ($count < 4 || $count % 2 == 1) {
            throw new Exception("Shape must have at least 2 pairs of points");
        }
    }

  /**
   * Override to transform pairs of points
   */
    protected function TransformCoordinates(&$attributes)
    {
        $count = count($attributes['points']);
        for ($i = 0; $i < $count; $i += 2) {
            $x = $attributes['points'][$i];
            $y = $attributes['points'][$i + 1];
            $coords = $this->coords->TransformCoords($x, $y);
            $attributes['points'][$i] = $coords[0];
            $attributes['points'][$i + 1] = $coords[1];
        }
    }

  /**
   * Override to build the points attribute
   */
    protected function DrawElement(&$graph, &$attributes)
    {
        $attributes['points'] = implode(' ', $attributes['points']);
        return parent::DrawElement($graph, $attributes);
    }
}
