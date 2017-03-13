<?php

namespace SVGGraph\Axis;

/**
 * Class for calculating logarithmic axis measurements
 */
class AxisLog extends Axis
{
    protected $lgmin;
    protected $lgmax;
    protected $base = 10;
    protected $divisions;
    protected $grid_space;
    protected $grid_split = 0;
    protected $negative = false;
    protected $lgmul;
    protected $int_base = true;

    public function __construct(
        $length,
        $max_val,
        $min_val,
        $minUnit,
        $minSpace,
        $fit,
        $unitsBefore,
        $unitsAfter,
        $decimalDigits,
        $base,
        $divisions,
        $labelCallback
    ) {
    
        if ($min_val == 0 || $max_val == 0) {
            throw new \Exception('0 value on log axis');
        }
        if ($min_val < 0 && $max_val > 0) {
            throw new \Exception('-ve and +ve on log axis');
        }
        if ($max_val <= $min_val && $minUnit == 0) {
            throw new \Exception('Zero length axis (min >= max)');
        }
        $this->length = $length;
        $this->minUnit = $minUnit;
        $this->minSpace = $minSpace;
        $this->unitsBefore = $unitsBefore;
        $this->unitsAfter = $unitsAfter;
        $this->decimalDigits = $decimalDigits;
        $this->labelCallback = $labelCallback;
        if (is_numeric($base) && $base > 1) {
            $this->base = $base * 1.0;
            $this->int_base = $this->base == floor($this->base);
        }
        $this->uneven = false;
        if ($min_val < 0) {
            $this->negative = true;
            $m = $min_val;
            $min_val = abs($max_val);
            $max_val = abs($m);
        }
        if (is_numeric($divisions)) {
            $this->divisions = $divisions;
        }

        $this->lgmin = floor(log($min_val, $this->base));
        $this->lgmax = ceil(log($max_val, $this->base));

        // if all the values are the same, and a power of the base
        if ($this->lgmax <= $this->lgmin) {
            --$this->lgmin;
        }

        $this->lgmul = $this->length / ($this->lgmax - $this->lgmin);
        $this->minValue = pow($this->base, $this->lgmin);
        $this->maxValue = pow($this->base, $this->lgmax);
    }

  /**
   * Returns the grid points as an associative array:
   * array($value => $position)
   */
    public function GetGridPoints($start)
    {
        $points = [];
        $max_div = $this->length / $this->minSpace;
        $pow_div = $this->lgmax - $this->lgmin;

        $div = 1;
        $this->grid_space = $this->length / $pow_div * $div;

        $spoints = [];
        if ($this->divisions) {
            $this->grid_split = $this->divisions;
        } else {
            $this->grid_split = $this->FindDivision($this->grid_space, $this->minSpace);
        }

        if ($this->grid_split) {
            for ($l = $this->grid_split; $l < $this->base; $l += $this->grid_split) {
                $spoints[] = log($l, $this->base);
            }
        }

        $l = $this->lgmin;
        while ($l <= $this->lgmax) {
            $val = pow($this->base, $l) * ($this->negative ? -1 : 1);
            $text = $this->GetText($val);
            $pos = $this->Position($val);
            $position = $start + ($this->direction * $pos);
            $points[] = new GridPoint($position, $text, $val);

            // add in divisions between powers
            if ($l < $this->lgmax) {
                foreach ($spoints as $l1) {
                    $val = pow($this->base, $l + $l1) * ($this->negative ? -1 : 1);
                    $text = $this->GetText($val);
                    $pos = $this->Position($val);
                    $position = $start + ($this->direction * $pos);
                    $points[] = new GridPoint($position, $text, $val);
                }
            }
            ++$l;
        }

        usort($points, ($this->direction < 0 ? [GridPoint::class, 'rsort'] : [GridPoint::class, 'sort']));
        return $points;
    }

  /**
   * Returns the grid subdivision points as an array
   */
    public function GetGridSubdivisions($minSpace, $minUnit, $start, $fixed)
    {
        $points = [];
        if ($this->int_base) {
            $split = $this->FindDivision(
                $this->grid_space,
                $minSpace,
                $this->grid_split
            );
            if ($split) {
                for ($l = $this->lgmin; $l < $this->lgmax; ++$l) {
                    for ($l1 = $split; $l1 < $this->base; $l1 += $split) {
                        if ($this->grid_split == 0 || $l1 % $this->grid_split) {
                            $p = log($l1, $this->base);
                            $val = pow($this->base, $l + $p);
                            $position = $start + $this->Position($val) * $this->direction;
                            $points[] = new GridPoint($position, '', $val);
                        }
                    }
                }
            }
        }
        return $points;
    }

  /**
   * Returns the position of a value on the axis, or NULL if the position is
   * not possible
   */
    public function Position($index, $item = null)
    {
        if (is_null($item) || $this->values->AssociativeKeys()) {
            $value = $index;
        } else {
            $value = $item->key;
        }
        if ($this->negative) {
            if ($value >= 0) {
                return null;
            }
            $abs_value = abs($value);
            if ($abs_value < $this->minValue) {
                return null;
            }
            return $this->length - (log($abs_value, $this->base) - $this->lgmin) *
            $this->lgmul;
        }
        if ($value <= 0 || $value < $this->minValue) {
            return null;
        }
        return (log($value, $this->base) - $this->lgmin) * $this->lgmul;
    }

  /**
   * Returns the position of the origin
   */
    public function Origin()
    {
        // not the position of 0, because that doesn't exist
        return $this->negative ? $this->length : 0;
    }

  /**
   * Returns the value at a position on the axis
   */
    public function Value($position)
    {
        $p = pow($this->base, $this->lgmin + $position / $this->lgmul);
        return $p;
    }

  /**
   * Finds an even division of the given space that is >= minSpace
   */
    private function FindDivision($space, $minSpace, $main_division = 0)
    {
        $split = 0;
        if ($this->int_base) {
            $division = $main_division ? $main_division : $this->base;
            $l = $this->base - 1;
            $lgs = $space * log($l, $this->base);

            $smallest = $space - $lgs;
            if ($smallest < $minSpace) {
                $max_split = floor($division / 2);
                for ($i = 2; $smallest < $minSpace && $i <= $max_split; ++$i) {
                    if ($division % $i == 0) {
                        // the smallest gap is the one before the next power
                        $l = $this->base - $i;
                        $lgs = $space * log($l, $this->base);
                        $smallest = $space - $lgs;
                        $split = $i;
                    }
                }
                if ($smallest < $minSpace) {
                    $split = 0;
                }
            } else {
                $split = 1;
            }
        }
        return $split;
    }


  /**
   * Not actually 0, but the position of the axis
   */
    public function Zero()
    {
        if ($this->negative) {
            return $this->length;
        }
        return 0;
    }
}
