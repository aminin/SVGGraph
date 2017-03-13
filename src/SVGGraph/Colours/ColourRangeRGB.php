<?php

namespace SVGGraph\Colours;

/**
 * Colour range for RGB values
 */
class ColourRangeRGB extends ColourRange
{
    private $r1;
    private $g1;
    private $b1;
    private $rdiff;
    private $gdiff;
    private $bdiff;

    /**
     * RGB range
     */
    public function __construct($r1, $g1, $b1, $r2, $g2, $b2)
    {
        $this->r1 = $this->clamp($r1, 0, 255);
        $this->g1 = $this->clamp($g1, 0, 255);
        $this->b1 = $this->clamp($b1, 0, 255);
        $this->rdiff = $this->clamp($r2, 0, 255) - $this->r1;
        $this->gdiff = $this->clamp($g2, 0, 255) - $this->g1;
        $this->bdiff = $this->clamp($b2, 0, 255) - $this->b1;
    }

    /**
     * Return the colour from the range
     */
    public function offsetGet($offset)
    {
        $c = max($this->count - 1, 1);
        $offset = $this->clamp($offset, 0, $c);
        $r = $this->r1 + $offset * $this->rdiff / $c;
        $g = $this->g1 + $offset * $this->gdiff / $c;
        $b = $this->b1 + $offset * $this->bdiff / $c;
        return sprintf('#%02x%02x%02x', $r, $g, $b);
    }
}
