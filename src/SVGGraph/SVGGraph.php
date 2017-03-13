<?php

namespace SVGGraph;

use SVGGraph\Graph\Graph;

define('SVGGRAPH_VERSION', 'dev');

class SVGGraph
{
    public $values = [];
    public $links = null;
    public $colours = null;

    /**
     * @var \SVGGraph\Graph\Graph
     */
    protected static $lastInstance = null;

    private $width = 100;
    private $height = 100;
    private $settings = [];

    public function __construct($w, $h, $settings = null)
    {
        $this->width = $w;
        $this->height = $h;

        if (is_array($settings)) {
            // structured_data, when FALSE disables structure
            if (isset($settings['structured_data']) && !$settings['structured_data']) {
                unset($settings['structure']);
            }
            $this->settings = $settings;
        }
    }

    public function values($values)
    {
        if (is_array($values)) {
            $this->values = $values;
        } else {
            $this->values = func_get_args();
        }
    }

    public function links($links)
    {
        if (is_array($links)) {
            $this->links = $links;
        } else {
            $this->links = func_get_args();
        }
    }

    /**
     * Assign a single colour set for use across datasets
     * @param Colours $colours
     */
    public function setColours($colours)
    {
        $this->colours = $colours;
    }

    public function getColours()
    {
        return $this->colours ?: $this->colours = new Colours();
    }

    /**
     * Sets colours for a single dataset
     */
    public function colourSet($dataset, $colours)
    {
        $this->getColours()->set($dataset, $colours);
    }

    /**
     * Sets up RGB colour range
     */
    public function colourRangeRgb($dataset, $r1, $g1, $b1, $r2, $g2, $b2)
    {
        $this->getColours()->rangeRgb($dataset, $r1, $g1, $b1, $r2, $g2, $b2);
    }

    /**
     * RGB colour range from hex codes
     */
    public function colourRangeHexRgb($dataset, $c1, $c2)
    {
        $this->getColours()->rangeHexRgb($dataset, $c1, $c2);
    }

    /**
     * Sets up HSL colour range
     */
    public function colourRangeHsl($dataset, $h1, $s1, $l1, $h2, $s2, $l2, $reverse = false)
    {
        $this->getColours()->rangeHsl($dataset, $h1, $s1, $l1, $h2, $s2, $l2, $reverse);
    }

    /**
     * HSL colour range from hex codes
     */
    public function colourRangeHexHsl($dataset, $c1, $c2, $reverse = false)
    {
        $this->getColours()->rangeHexHsl($dataset, $c1, $c2, $reverse);
    }

    /**
     * Sets up HSL colour range from RGB values
     */
    public function colourRangeRgbToHsl($dataset, $r1, $g1, $b1, $r2, $g2, $b2, $reverse = false)
    {
        $this->getColours()->rangeRgbToHsl($dataset, $r1, $g1, $b1, $r2, $g2, $b2, $reverse);
    }

    /**
     * Fetch the content
     */
    public function fetch($class, $header = true, $deferJs = true)
    {
        static::$lastInstance = $this->setup($class);
        return static::$lastInstance->fetch($header, $deferJs);
    }

    /**
     * Pass in the type of graph to display
     */
    public function render($class, $header = true, $contentType = true, $deferJs = false)
    {
        static::$lastInstance = $this->setup($class);
        static::$lastInstance->render($header, $contentType, $deferJs);
    }

    /**
     * Fetch the Javascript for ALL graphs that have been Fetched
     */
    public static function fetchJavascript()
    {
        if (!is_null(static::$lastInstance)) {
            return static::$lastInstance->fetchJavascript(true, true, true);
        }
        return null;
    }

    /**
     * Instantiate the correct class
     * @param $class
     * @return Graph
     */
    private function setup($class)
    {
        /** @var Graph $g */
        $g = new $class($this->width, $this->height, $this->settings);
        $g->values($this->values);
        $g->links($this->links);
        if (is_object($this->colours)) {
            $g->colours = $this->colours;
        } else {
            $g->colours = new Colours($this->colours);
        }

        return $g;
    }
}
