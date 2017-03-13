<?php

namespace SVGGraph\Graph;

/**
 * Class for pattern fills
 */
class PatternList
{
    /** @var Graph */
    private $graph;
    private $patternMap = [];
    private $patterns = [];

    public function __construct($graph)
    {
        $this->graph = $graph;
    }

    /**
     * Adds a pattern to the list
     */
    public function Add($pattern)
    {
        $hash = md5(serialize($pattern));
        if (isset($this->patternMap[$hash])) {
            return $this->patternMap[$hash];
        }

        if (method_exists($this, $pattern['pattern'])) {
            if (!isset($pattern['size'])) {
                $pattern['size'] = 10;
            }
            $pattern['width'] = $pattern['height'] = $pattern['size'];
            $opacity = null;
            if (strpos($pattern[0], ':') !== false) {
                list($colour, $opacity) = explode(':', $pattern[0]);
            } else {
                $colour = $pattern[0];
            }
            $pattern['colour'] = $colour;
            if (is_numeric($opacity)) {
                $pattern['opacity'] = $opacity;
            }
            $pattern = call_user_func([$this, $pattern['pattern']], $pattern);
        }

        // validate pattern content a bit
        $e = false;
        $s = strpos($pattern['pattern'], '<');
        if (false !== $s) {
            $e = strpos($pattern['pattern'], '>', $s);
        }
        if (false === $s || false === $e) {
            throw new \Exception('Invalid pattern');
        }

        // validate width and height
        if (!isset($pattern['width']) || !isset($pattern['height'])) {
            throw new \Exception('Pattern width and height not set');
        }

        $id = $this->graph->NewID();

        // check background colour
        $back_colour = '#fff';
        $back_opacity = '';
        if (array_key_exists('back_colour', $pattern)) {
            $back_colour = $pattern['back_colour'];
            if (!is_null($back_colour) && strpos($back_colour, ':') !== false) {
                list($back_colour, $back_opacity) = explode(':', $back_colour);
            }
        }

        if (!is_null($back_colour)) {
            $rect = [
                'width'  => $pattern['width'],
                'height' => $pattern['height'],
                'fill'   => $back_colour,
            ];
            if (is_numeric($back_opacity)) {
                $rect['opacity'] = $back_opacity;
            }
            $pattern['pattern'] = $this->graph->Element('rect', $rect) .
                $pattern['pattern'];
        }

        $pat = [
            'id'           => $id,
            'x'            => 0,
            'y'            => 0,
            'width'        => $pattern['width'],
            'height'       => $pattern['height'],
            'patternUnits' => 'userSpaceOnUse',
        ];
        if (isset($pattern['angle'])) {
            $pat['patternTransform'] = "rotate({$pattern['angle']})";
        }

        $this->patterns[$id] = $this->graph->Element('pattern', $pat, null,
            $pattern['pattern']);
        $this->patternMap[$hash] = $id;
        return $id;
    }

    /**
     * Adds the stored patterns to the list of definitions
     */
    public function MakePatterns(&$defs)
    {
        foreach ($this->patterns as $pat) {
            $defs[] = $pat;
        }
    }

    /**
     * Spots - circles with diameter half of pattern size
     */
    private function Spot($pattern, $scale = 0.25)
    {
        $spot = ['cx' => $pattern['size'] * $scale];
        $spot['cy'] = $spot['r'] = $spot['cx'];
        $spot['fill'] = $pattern['colour'];
        if (isset($pattern['opacity'])) {
            $spot['opacity'] = $pattern['opacity'];
        }
        $pattern['pattern'] = $this->graph->Element('circle', $spot);
        return $pattern;
    }

    /**
     * Polka dots - spots in a checked pattern
     */
    private function PolkaDot($pattern, $scale = 0.25)
    {
        $spot = ['cx' => $pattern['size'] * $scale];
        $spot['cy'] = $spot['r'] = $spot['cx'];
        $spot['fill'] = $pattern['colour'];
        if (isset($pattern['opacity'])) {
            $spot['opacity'] = $pattern['opacity'];
        }
        $pattern['pattern'] = $this->graph->Element('circle', $spot);

        $spot['cx'] = $spot['cy'] = ($pattern['size'] / 2) + $spot['r'];
        $pattern['pattern'] .= $this->graph->Element('circle', $spot);
        return $pattern;
    }

    /**
     * Check pattern
     */
    private function Check($pattern)
    {
        $rect = ['width' => $pattern['size'] / 2];
        $rect['height'] = $rect['width'];
        $rect['fill'] = $pattern['colour'];
        if (isset($pattern['opacity'])) {
            $rect['opacity'] = $pattern['opacity'];
        }
        $pattern['pattern'] = $this->graph->Element('rect', $rect);
        $rect['x'] = $rect['y'] = $rect['width'];
        $pattern['pattern'] .= $this->graph->Element('rect', $rect);
        return $pattern;
    }

    /**
     * Squares
     */
    private function Square($pattern, $scale = 0.8)
    {
        $rect = ['width' => $pattern['size'] * $scale];
        $rect['height'] = $rect['width'];
        $rect['fill'] = $pattern['colour'];
        if (isset($pattern['opacity'])) {
            $rect['opacity'] = $pattern['opacity'];
        }
        $pattern['pattern'] = $this->graph->Element('rect', $rect);
        return $pattern;
    }

    /**
     * Hatching using a single line
     */
    private function Line($pattern, $thickness = 0.8)
    {
        $w = $pattern['size'];
        $y = $w / 2;
        $line = [
            'd'            => "M0 {$y}h{$w}",
            'stroke'       => $pattern['colour'],
            'stroke-width' => $pattern['size'] * $thickness
        ];
        if (isset($pattern['opacity'])) {
            $line['opacity'] = $pattern['opacity'];
        }
        $pattern['pattern'] = $this->graph->Element('path', $line);
        return $pattern;
    }

    /**
     * Hatching using crossed lines
     */
    private function Cross($pattern, $thickness = 0.4)
    {
        $w = $pattern['size'];
        $y = $w / 2;
        $line = [
            'd'            => "M0 {$y}h{$w}M{$y} 0v{$w}",
            'stroke'       => $pattern['colour'],
            'stroke-width' => $pattern['size'] * $thickness
        ];
        if (isset($pattern['opacity'])) {
            $line['opacity'] = $pattern['opacity'];
        }
        $pattern['pattern'] = $this->graph->Element('path', $line);
        return $pattern;
    }

    /**
     * Spot2, Spot3 use smaller spots
     */
    private function Spot2($pattern)
    {
        return $this->Spot($pattern, 0.16666);
    }

    private function Spot3($pattern)
    {
        return $this->Spot($pattern, 0.125);
    }

    /**
     * Circles are bigger spots
     */
    private function Circle($pattern)
    {
        return $this->Spot($pattern, 0.45);
    }

    private function Circle2($pattern)
    {
        return $this->Spot($pattern, 0.4);
    }

    private function Circle3($pattern)
    {
        return $this->Spot($pattern, 0.33333);
    }

    /**
     * PolkaDot2 etc use smaller dots
     */
    private function PolkaDot2($pattern)
    {
        return $this->PolkaDot($pattern, 0.16666);
    }

    private function PolkaDot3($pattern)
    {
        return $this->PolkaDot($pattern, 0.125);
    }

    /**
     * Differently proportioned squares
     */
    private function Square2($pattern)
    {
        return $this->Square($pattern, 0.6);
    }

    private function Square3($pattern)
    {
        return $this->Square($pattern, 0.4);
    }

    private function Square4($pattern)
    {
        return $this->Square($pattern, 0.2);
    }

    /**
     * Thinner lines
     */
    private function Line2($pattern)
    {
        return $this->Line($pattern, 0.6);
    }

    private function Line3($pattern)
    {
        return $this->Line($pattern, 0.4);
    }

    private function Line4($pattern)
    {
        return $this->Line($pattern, 0.2);
    }

    private function Cross2($pattern)
    {
        return $this->Cross($pattern, 0.25);
    }

    private function Cross3($pattern)
    {
        return $this->Cross($pattern, 0.1);
    }
}

