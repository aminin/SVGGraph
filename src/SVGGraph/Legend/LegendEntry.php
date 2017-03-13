<?php

namespace SVGGraph\Legend;

/**
 * A class to hold the details of an entry in the legend
 */
class LegendEntry
{
    public $item = null;
    public $text = null;
    public $style = null;
    public $width = 0;
    public $height = 0;

    public function __construct($item, $text, $style)
    {
        $this->item = $item;
        $this->text = $text;
        $this->style = $style;
    }
}

