<?php
/**
 * Copyright (C) 2014-2015 Graham Breach
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace SVGGraph;

/**
 * For more information, please contact <graham@goat1000.com>
 */
class Colours implements \Countable
{
    private $colours = [];
    private $datasetCount = 0;
    private $fallback = false;

    public function __construct($colours = -1)
    {
        $defaultColours = [
            '#11c',
            '#c11',
            '#cc1',
            '#1c1',
            '#c81',
            '#116',
            '#611',
            '#661',
            '#161',
            '#631'
        ];

        // default colours
        if (is_null($colours)) {
            $colours = $defaultColours;
        }

        if (is_array($colours)) {
            // fallback to old behaviour
            $this->fallback = $colours;
            return;
        }

        $this->colours[0] = new Colours\ColourArray($defaultColours);
        $this->datasetCount = 1;
        return;
    }

    /**
     * Setup based on graph requirements
     */
    public function setup($count, $datasets = null)
    {
        if ($this->fallback !== false) {
            if (!is_null($datasets)) {
                foreach ($this->fallback as $colour) {
                    // in fallback, each dataset gets one colour
                    $this->colours[] = new Colours\ColourArray([$colour]);
                }
            } else {
                $this->colours[] = new Colours\ColourArray($this->fallback);
            }
            $this->datasetCount = count($this->colours);
        }

        foreach ($this->colours as $clist) {
            $clist->setup($count);
        }
    }

    /**
     * Returns the colour for an index and dataset
     */
    public function getColour($index, $dataset = null)
    {
        // default is for a colour per dataset
        if (is_null($dataset)) {
            $dataset = 0;
        }

        // see if specific dataset exists
        if (array_key_exists($dataset, $this->colours)) {
            return $this->colours[$dataset][$index];
        }

        // try mod
        $dataset = $dataset % $this->datasetCount;
        if (array_key_exists($dataset, $this->colours)) {
            return $this->colours[$dataset][$index];
        }

        // just use first dataset
        reset($this->colours);
        $clist = current($this->colours);
        return $clist[$index];
    }

    /**
     * Implement Countable to make it non-countable
     */
    public function count()
    {
        throw new \Exception("Cannot count SVGGraph\\Colours class");
    }

    /**
     * Assign a colour array for a dataset
     */
    public function set($dataset, $colours)
    {
        if (is_null($colours)) {
            if (array_key_exists($dataset, $this->colours)) {
                unset($this->colours[$dataset]);
            }
            return;
        }
        $this->colours[$dataset] = new Colours\ColourArray($colours);
        $this->datasetCount = count($this->colours);
    }

    /**
     * Set up RGB colour range
     */
    public function rangeRgb($dataset, $r1, $g1, $b1, $r2, $g2, $b2)
    {
        $rng = new Colours\ColourRangeRGB($r1, $g1, $b1, $r2, $g2, $b2);
        $this->colours[$dataset] = $rng;
        $this->datasetCount = count($this->colours);
    }

    /**
     * HSL colour range, with option to go the long way
     */
    public function rangeHsl($dataset, $h1, $s1, $l1, $h2, $s2, $l2, $reverse = false)
    {
        $rng = new Colours\ColourRangeHSL($h1, $s1, $l1, $h2, $s2, $l2);
        if ($reverse) {
            $rng->reverse();
        }
        $this->colours[$dataset] = $rng;
        $this->datasetCount = count($this->colours);
    }

    /**
     * HSL colour range from RGB values, with option to go the long way
     */
    public function rangeRgbToHsl($dataset, $r1, $g1, $b1, $r2, $g2, $b2, $reverse = false)
    {
        $rng = Colours\ColourRangeHSL::fromRgb($r1, $g1, $b1, $r2, $g2, $b2);
        if ($reverse) {
            $rng->reverse();
        }
        $this->colours[$dataset] = $rng;
        $this->datasetCount = count($this->colours);
    }

    /**
     * RGB colour range from two RGB hex codes
     */
    public function rangeHexRgb($dataset, $c1, $c2)
    {
        list($r1, $g1, $b1) = $this->hexRgb($c1);
        list($r2, $g2, $b2) = $this->hexRgb($c2);
        $this->rangeRgb($dataset, $r1, $g1, $b1, $r2, $g2, $b2);
    }

    /**
     * HSL colour range from RGB hex codes
     */
    public function rangeHexHsl($dataset, $c1, $c2, $reverse = false)
    {
        list($r1, $g1, $b1) = $this->hexRgb($c1);
        list($r2, $g2, $b2) = $this->hexRgb($c2);
        $this->rangeRgbToHSL($dataset, $r1, $g1, $b1, $r2, $g2, $b2, $reverse);
    }

    /**
     * Convert a colour code to RGB array
     */
    public static function hexRgb($c)
    {
        $r = $g = $b = 0;
        if (strlen($c) == 7) {
            sscanf($c, '#%2x%2x%2x', $r, $g, $b);
        } elseif (strlen($c) == 4) {
            sscanf($c, '#%1x%1x%1x', $r, $g, $b);
            $r += 16 * $r;
            $g += 16 * $g;
            $b += 16 * $b;
        }
        return [$r, $g, $b];
    }
}
