<?php

namespace SVGGraph\Shape;

/**
 * Arbitrary shapes for adding to graphs
 */
class ShapeList
{
    private $graph;
    private $shapes = [];

    public function __construct(&$graph)
    {
        $this->graph = $graph;
    }

  /**
   * Load shapes from options list
   */
    public function Load(&$settings)
    {
        if (!isset($settings['shape'])) {
            return;
        }
  
        if (!is_array($settings['shape']) || !isset($settings['shape'][0])) {
            throw new \Exception('Malformed shape option');
        }

        if (!is_array($settings['shape'][0])) {
            $this->AddShape($settings['shape']);
        } else {
            foreach ($settings['shape'] as $shape) {
                $this->AddShape($shape);
            }
        }
    }

  /**
   * Draw all the shapes for the selected depth
   */
    public function Draw($depth)
    {
        $content = [];
        foreach ($this->shapes as $shape) {
            if ($shape->Depth($depth)) {
                $content[] = $shape->Draw($this->graph);
            }
        }
        return implode($content);
    }

  /**
   * Adds a shape from config array
   */
    private function AddShape(&$shape_array)
    {
        $shape = $shape_array[0];
        unset($shape_array[0]);

        $class_map = [
            'circle'   => Circle::class,
            'ellipse'  => Ellipse::class,
            'rect'     => Rect::class,
            'line'     => Line::class,
            'polyline' => PolyLine::class,
            'polygon'  => Polygon::class,
            'path'     => Path::class,
        ];

        if (isset($class_map[$shape]) && class_exists($class_map[$shape])) {
            $depth = Shape::SHAPE_BELOW;
            if (isset($shape_array['depth'])) {
                if ($shape_array['depth'] == 'above') {
                    $depth = Shape::SHAPE_ABOVE;
                }
            }
            if (isset($shape_array['clip_to_grid']) && $shape_array['clip_to_grid'] &&
            method_exists($this->graph, 'GridClipPath')) {
                $clip_id = $this->graph->GridClipPath();
                $shape_array['clip-path'] = "url(#{$clip_id})";
            }
            unset($shape_array['depth'], $shape_array['clip_to_grid']);
            $this->shapes[] = new $class_map[$shape]($shape_array, $depth);
        } else {
            throw new \Exception("Unknown shape [{$shape}]");
        }
    }
}
