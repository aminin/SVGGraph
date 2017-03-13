<?php

namespace SVGGraph\Axis;

use SVGGraph\Graph\Graph;

/**
 * Class for calculating axis measurements
 */
class Axis
{
    protected $length;
    protected $maxValue;
    protected $minValue;

    protected $unitSize;
    protected $minUnit;
    protected $minSpace;

    protected $fit;
    protected $zero;

    protected $unitsBefore;
    protected $unitsAfter;

    protected $decimalDigits;
    protected $gridSpacing;

    protected $uneven = false;
    protected $roundedUp = false;
    protected $direction = 1;
    protected $labelCallback = false;

    protected $values = false;

    public function __construct(
        $length,
        $maxValue,
        $minValue,
        $minUnit,
        $minSpace,
        $fit,
        $unitsBefore,
        $unitsAfter,
        $decimalDigits,
        $labelCallback,
        $values
    ) {
        if ($maxValue <= $minValue && $minUnit == 0) {
            throw new \Exception('Zero length axis (min >= max)');
        }
        $this->length = $length;
        $this->maxValue = $maxValue;
        $this->minValue = $minValue;
        $this->minUnit = $minUnit;
        $this->minSpace = $minSpace;
        $this->fit = $fit;
        $this->unitsBefore = $unitsBefore;
        $this->unitsAfter = $unitsAfter;
        $this->decimalDigits = $decimalDigits;
        $this->labelCallback = $labelCallback;
        $this->values = $values;
    }

    /**
     * Allow length adjustment
     */
    public function setLength($length)
    {
        $this->length = $length;
    }

    /**
     * Returns TRUE if the number $n is 'nice'
     * @param $number
     * @return bool
     */
    private function isNice($number)
    {
        if (is_integer($number) && ($number % 100 == 0 || $number % 10 == 0 || $number % 5 == 0)) {
            return true;
        }

        if ($this->minUnit) {
            $d = $number / $this->minUnit;
            if ($d != floor($d)) {
                return false;
            }
        }
        $s = (string)$number;
        if (preg_match('/^\d(\.\d{1,1})$/', $s)) {
            return true;
        }
        if (preg_match('/^\d+$/', $s)) {
            return true;
        }

        return false;
    }


    /**
     * Subdivide when the divisions are too large
     */
    private function subDivision(
        $length,
        $min,
        &$count,
        &$neg_count,
        &$magnitude
    ) {
        $m = $magnitude * 0.5;
        $magnitude = $m;
        $count *= 2;
        $neg_count *= 2;
    }

    /**
     * Determine the axis divisions
     */
    private function findDivision($length, $min, &$count, &$neg_count, &$magnitude) {
        if ($length / $count >= $min) {
            return;
        }

        $c = $count - 1;
        $inc = 0;
        while ($c > 1) {
            $m = ($count + $inc) / $c;
            $l = $length / $c;
            $test_below = $neg_count ? $c * $neg_count / $count : 1;
            if ($this->isNice($m, $count + $inc)) {
                if ($l >= $min && $test_below - floor($test_below) == 0) {
                    $magnitude *= ($count + $inc) / $c;
                    $neg_count *= $c / $count;
                    $count = $c;
                    return;
                }
                --$c;
                $inc = 0;
            } elseif (!$this->fit && $count % 2 == 1 && $inc == 0) {
                $inc = 1;
            } else {
                --$c;
                $inc = 0;
            }
        }

        // try to balance the +ve and -ve a bit
        if ($neg_count) {
            $c = $count + 1;
            $p_count = $count - $neg_count;
            if ($p_count > $neg_count && ($neg_count == 1 || $c % $neg_count)) {
                ++$neg_count;
            }
            ++$count;
        }
    }

    /**
     * Sets the bar style (which means an extra unit)
     */
    public function bar()
    {
        if (!$this->roundedUp) {
            $this->maxValue += $this->minUnit;
            $this->roundedUp = true;
        }
    }

    /**
     * Sets the direction of axis points
     */
    public function reverse()
    {
        $this->direction = -1;
    }

    /**
     * Returns the grid spacing
     */
    protected function grid()
    {
        $minSpace = $this->minSpace;
        $this->uneven = false;
        $negative = $this->minValue < 0;
        $min_sub = max($minSpace, $this->length / 200);

        if ($this->minValue == $this->maxValue) {
            $this->maxValue += $this->minUnit;
        }
        $scale = $this->maxValue - $this->minValue;

        // get magnitude from greater of |+ve|, |-ve|
        $abs_min = abs($this->minValue);
        $magnitude = max(pow(10, floor(log10($scale))), $this->minUnit);
        if ($this->fit) {
            $count = ceil($scale / $magnitude);
        } else {
            $count = ceil($this->maxValue / $magnitude) -
                floor($this->minValue / $magnitude);
        }

        if ($count <= 5 && $magnitude > $this->minUnit) {
            $magnitude *= 0.1;
            $count = ceil($this->maxValue / $magnitude) -
                floor($this->minValue / $magnitude);
        }

        $neg_count = ceil($abs_min / $magnitude);
        $this->findDivision($this->length, $min_sub, $count, $neg_count,
            $magnitude);
        $grid = $this->length / $count;

        // guard this loop in case the numbers are too awkward to fit
        $guard = 10;
        while ($grid < $minSpace && --$guard) {
            $this->findDivision($this->length, $min_sub, $count, $neg_count,
                $magnitude);
            $grid = $this->length / $count;
        }
        if ($guard == 0) {
            // could not find a division
            while ($grid < $minSpace && $count > 1) {
                $count *= 0.5;
                $neg_count *= 0.5;
                $magnitude *= 2;
                $grid = $this->length / $count;
                $this->uneven = true;
            }

        } elseif (!$this->fit && $magnitude > $this->minUnit &&
            $grid / $minSpace > 2
        ) {
            // division still seems a bit coarse
            $this->subDivision($this->length, $min_sub, $count, $neg_count,
                $magnitude);
            $grid = $this->length / $count;
        }

        $this->unitSize = $this->length / ($magnitude * $count);
        $this->zero = $negative ? $neg_count * $grid :
            -$this->minValue * $grid / $magnitude;

        return $grid;
    }

    /**
     * Returns the size of a unit in grid space
     */
    public function unit()
    {
        if (!isset($this->unitSize)) {
            $this->grid();
        }

        return $this->unitSize;
    }

    /**
     * Returns the distance along the axis where 0 should be
     */
    public function zero()
    {
        if (!isset($this->zero)) {
            $this->grid();
        }

        return $this->zero;
    }

    /**
     * Returns TRUE if the grid spacing does not fill the grid
     */
    public function uneven()
    {
        return $this->uneven;
    }

    /**
     * Returns the position of a value on the axis
     */
    public function position($index, $item = null)
    {
        if (is_null($item) || $this->values->associativeKeys()) {
            $value = $index;
        } else {
            $value = $item->key;
        }
        return $this->zero() + ($value * $this->unit());
    }

    /**
     * Returns the position of an associative key, if possible
     */
    public function positionByKey($key)
    {
        if ($this->values && $this->values->associativeKeys()) {

            // only need to look through dataset 0 because multi-dataset graphs
            // convert to structured
            $index = 0;
            foreach ($this->values[0] as $item) {
                if ($item->key == $key) {
                    return $this->zero() + ($index * $this->unit());
                }
                ++$index;
            }
        }
        return null;
    }

    /**
     * Returns the position of the origin
     */
    public function origin()
    {
        // for a linear axis, it should be the zero point
        return $this->zero();
    }

    /**
     * Returns the value at a position on the axis
     */
    public function value($position)
    {
        return ($position - $this->zero()) / $this->unit();
    }

    /**
     * Return the before units text
     */
    public function beforeUnits()
    {
        return $this->unitsBefore;
    }

    /**
     * Return the after units text
     */
    public function afterUnits()
    {
        return $this->unitsAfter;
    }

    /**
     * Returns the text for a grid point
     */
    protected function getText($value)
    {
        $text = $value;

        // try structured data first
        if ($this->values && $this->values->getData($value, 'axis_text', $text)) {
            return $text;
        }

        // use the key if it is not the same as the value
        $key = $this->values ? $this->values->getKey($value) : $value;

        // if there is a callback, use it
        if (is_callable($this->labelCallback)) {
            $text = call_user_func($this->labelCallback, $value, $key);
        } else {
            if ($key !== $value) {
                $text = $key;
            } else {
                $text = $this->unitsBefore . Graph::numString($value, $this->decimalDigits) . $this->unitsAfter;
            }
        }
        return $text;
    }

    /**
     * Returns the grid points as an array of GridPoints
     */
    public function getGridPoints($start)
    {
        $spacing = $this->grid();
        $c = $pos = 0;
        $dlength = $this->length + $spacing * 0.5;
        $points = [];

        if ($dlength / $spacing > 10000) {
            $pcount = $dlength / $spacing;
            throw new \Exception("Too many grid points ({$this->minValue}->{$this->maxValue} = {$pcount})");
        }

        while ($pos < $dlength) {
            $value = ($pos - $this->zero) / $this->unitSize;
            $text = $this->getText($value);
            $position = $start + ($this->direction * $pos);
            $points[] = new GridPoint($position, $text, $value);
            $pos = ++$c * $spacing;
        }
        // uneven means the divisions don't fit exactly, so add the last one in
        if ($this->uneven) {
            $pos = $this->length - $this->zero;
            $value = $pos / $this->unitSize;
            $text = $this->getText($value);
            $position = $start + ($this->direction * $this->length);
            $points[] = new GridPoint($position, $text, $value);
        }

        // since PHP 5.5
        usort($points, ($this->direction < 0 ? [GridPoint::class, 'rsort'] : [GridPoint::class, 'sort']));
        $this->gridSpacing = $spacing;
        return $points;
    }

    /**
     * Returns the grid subdivision points as an array
     */
    public function getGridSubdivisions($minSpace, $minUnit, $start, $fixed)
    {
        if (!$this->gridSpacing) {
            throw new \Exception('gridSpacing not set');
        }

        $subdivs = [];
        $spacing = $this->findSubdiv($this->gridSpacing, $minSpace, $minUnit,
            $fixed);
        if (!$spacing) {
            return $subdivs;
        }

        $c = $pos1 = $pos2 = 0;
        $pos1 = $c * $this->gridSpacing;
        while ($pos1 + $spacing < $this->length) {
            $d = 1;
            $pos2 = $d * $spacing;
            while ($pos2 < $this->gridSpacing) {
                $subdivs[] = new GridPoint($start + (($pos1 + $pos2) * $this->direction), '', 0);
                ++$d;
                $pos2 = $d * $spacing;
            }
            ++$c;
            $pos1 = $c * $this->gridSpacing;
        }
        return $subdivs;
    }

    /**
     * Find the subdivision size
     */
    private function findSubdiv($gridDiv, $min, $minUnit, $fixed)
    {
        if (is_numeric($fixed)) {
            return $this->unitSize * $fixed;
        }

        $D = $gridDiv / $this->unitSize;  // D = actual division size
        $min = max($min, $minUnit * $this->unitSize); // use the larger minimum value
        $maxDivisions = (int)floor($gridDiv / $min);

        // can we subdivide at all?
        if ($maxDivisions <= 1) {
            return null;
        }

        // convert $D to an integer in the 100's range
        $D1 = (int)round(100 * (pow(10, -floor(log10($D)))) * $D);
        for ($divisions = $maxDivisions; $divisions > 1; --$divisions) {
            // if $D1 / $divisions is not an integer, $divisions is no good
            $dq = $D1 / $divisions;
            if ($dq - floor($dq) == 0) {
                return $gridDiv / $divisions;
            }
        }
        return null;
    }
}
