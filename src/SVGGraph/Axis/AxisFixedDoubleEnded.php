<?php

namespace SVGGraph\Axis;

/**
 * Axis with fixed measurements
 */
class AxisFixedDoubleEnded extends AxisDoubleEnded
{

    protected $step;

    public function __construct(
        $length,
        $max_val,
        $min_val,
        $step,
        $unitsBefore,
        $unitsAfter,
        $decimalDigits,
        $labelCallback
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
            $labelCallback
        );
        $this->step = $step;
    }

  /**
   * Calculates a grid based on min, max and step
   * min and max will be adjusted to fit step
   */
    protected function Grid()
    {
        // if min and max are the same side of 0, only adjust one of them
        if ($this->maxValue * $this->minValue >= 0) {
            $count = $this->maxValue - $this->minValue;
            if (abs($this->maxValue) >= abs($this->minValue)) {
                $this->maxValue = $this->minValue +
                $this->step * ceil($count / $this->step);
            } else {
                $this->minValue = $this->maxValue -
                $this->step * ceil($count / $this->step);
            }
        } else {
            $this->maxValue = $this->step * ceil($this->maxValue / $this->step);
            $this->minValue = $this->step * floor($this->minValue / $this->step);
        }

        $count = ($this->maxValue - $this->minValue) / $this->step;
        $ulen = $this->maxValue - $this->minValue;
        if ($ulen == 0) {
            throw new \Exception("Zero length axis");
        }
        $this->unitSize = $this->length / $ulen;
        $grid = $this->length / $count;
        $this->zero = (-$this->minValue / $this->step) * $grid;
        return $grid;
    }
}
