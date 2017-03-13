<?php

namespace SVGGraph\Axis;

/**
 * Axis with fixed measurements
 */
class AxisFixed extends Axis
{

    protected $step;
    protected $orig_max_value;
    protected $orig_min_value;

    public function __construct(
        $length,
        $max_val,
        $min_val,
        $step,
        $unitsBefore,
        $unitsAfter,
        $decimalDigits,
        $labelCallback,
        $values
    ) {
    
        // minUnit = 1, minSpace = 1, fit = false
        parent::__construct(
            $length,
            $max_val,
            $min_val,
            1,
            1,
            false,
            $unitsBefore,
            $unitsAfter,
            $decimalDigits,
            $labelCallback,
            $values
        );
        $this->orig_max_value = $max_val;
        $this->orig_min_value = $min_val;
        $this->step = $step;
    }

  /**
   * Calculates a grid based on min, max and step
   * min and max will be adjusted to fit step
   */
    protected function Grid()
    {
        // use the original min/max to prevent compounding of floating-point
        // rounding problems
        $min = $this->orig_min_value;
        $max = $this->orig_max_value;

        // if min and max are the same side of 0, only adjust one of them
        if ($max * $min >= 0) {
            $count = $max - $min;
            if (abs($max) >= abs($min)) {
                $this->maxValue = $min + $this->step * ceil($count / $this->step);
            } else {
                $this->minValue = $max - $this->step * ceil($count / $this->step);
            }
        } else {
            $this->maxValue = $this->step * ceil($max / $this->step);
            $this->minValue = $this->step * floor($min / $this->step);
        }

        $count = ($this->maxValue - $this->minValue) / $this->step;
        $ulen = $this->maxValue - $this->minValue;
        if ($ulen == 0) {
            throw new \Exception("Zero length axis (min >= max)");
        }
        $this->unitSize = $this->length / $ulen;
        $grid = $this->length / $count;
        $this->zero = (-$this->minValue / $this->step) * $grid;
        return $grid;
    }

  /**
   * Sets the bar style, adding an extra unit
   */
    public function Bar()
    {
        if (!$this->roundedUp) {
            $this->orig_max_value += $this->minUnit;
            parent::Bar();
        }
    }
}
