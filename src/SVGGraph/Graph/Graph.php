<?php

namespace SVGGraph\Graph;

use SVGGraph\Colours;
use SVGGraph\Data;
use SVGGraph\DataLabels;
use SVGGraph\Javascript;
use SVGGraph\Legend\Legend;
use SVGGraph\Shape\Shape;
use SVGGraph\Shape\ShapeList;
use SVGGraph\StructuredData;

/**
 * Base class for all graph types
 */
abstract class Graph
{
    /** @var Colours|null */
    public $colours = null;
    public static $key_format = null;

    protected $settings = [];
    protected $values = [];
    protected $link_base = '';
    protected $link_target = '_blank';

    protected $links = [];
    protected $gradients = [];
    protected $gradient_map = [];
    protected $pattern_list = null;
    protected $defs = [];

    protected $back_matter = '';
    protected $namespaces = [];
    protected $legend_reverse = false;
    protected $force_assoc = false;
    protected $repeated_keys = 'error';
    protected $require_structured = false;
    protected $require_integer_keys = true;
    protected $multi_graph = null;
    protected $legend = null;

    protected static $javascript = null;

    private static $last_id = 0;
    private static $precision = 5;
    private static $decimal = '.';
    private static $thousands = ',';

    public function __construct($w, $h, $settings = null)
    {
        $this->width = $w;
        $this->height = $h;

        // get settings from ini file that are relevant to this class
        $iniFile = __DIR__ . '/../svggraph.ini';
        if (!file_exists($iniFile)) {
            $ini_settings = false;
        } else {
            $ini_settings = parse_ini_file($iniFile, true);
        }
        if ($ini_settings === false) {
            die("Ini file [{$iniFile}] not found -- exiting");
        }

        $class = get_class($this);
        $hierarchy = [$class];
        while ($class = get_parent_class($class)) {
            array_unshift($hierarchy, $class);
        }

        while (count($hierarchy)) {
            $class = array_shift($hierarchy);
            $classShortName = preg_replace('~^.+\\\\(.+)$~i', '$1', $class);
            if (array_key_exists($classShortName, $ini_settings)) {
                $this->settings = array_merge($this->settings, $ini_settings[$classShortName]);
            }
        }

        if (is_array($settings)) {
            $this->settings($settings);
        }
    }


    /**
     * Retrieves properties from the settings array if they are not
     * already available as properties
     */
    public function __get($name)
    {
        $this->{$name} = isset($this->settings[$name]) ? $this->settings[$name] : null;
        return $this->{$name};
    }

    /**
     * Make empty($this->option) more robust
     */
    public function __isset($name)
    {
        return isset($this->settings[$name]);
    }

    /**
     * Sets the options
     * @param array $settings
     */
    public function settings($settings)
    {
        foreach ($settings as $key => $value) {
            $this->settings[$key] = $value;
            $this->{$key} = $value;
        }
    }

    /**
     * Sets the graph values
     * @param array $values
     */
    public function values($values)
    {
        $new_values = [];
        $v = func_get_args();
        if (count($v) == 1) {
            $v = array_shift($v);
        }

        $set_values = true;
        if (is_array($v)) {
            reset($v);
            $first_key = key($v);
            if (!is_null($first_key) && is_array($v[$first_key])) {
                foreach ($v as $data_set) {
                    $new_values[] = $data_set;
                }
                $set_values = false;
            }
        }

        if ($set_values) {
            $new_values[] = $v;
        }

        if ($this->scatter_2d) {
            $this->scatter_2d = false;
            if (empty($this->structure)) {
                $this->structure = ['key' => 0, 'value' => 1, 'datasets' => true];
            }
        }

        if ($this->datetime_keys && $this->datetime_key_format) {
            Graph::$key_format = $this->datetime_key_format;
        }

        if ($this->structured_data || is_array($this->structure)) {
            $this->structured_data = true;
            $this->values = new StructuredData\Data($new_values, $this->force_assoc,
                $this->datetime_keys, $this->structure, $this->repeated_keys,
                $this->require_integer_keys, $this->require_structured);
        } else {
            $this->values = new Data\Data($new_values, $this->force_assoc, $this->datetime_keys);
            if (!$this->values->error && !empty($this->require_structured)) {
                $this->values->error = get_class($this) . ' requires structured data';
            }
        }
    }

    /**
     * Sets the links from each item
     */
    public function links()
    {
        $this->links = func_get_args();
    }

    protected function getMinValue()
    {
        if (!is_null($this->multi_graph)) {
            return $this->multi_graph->getMinValue();
        }
        return $this->values->getMinValue();
    }

    protected function getMaxValue()
    {
        if (!is_null($this->multi_graph)) {
            return $this->multi_graph->getMaxValue();
        }
        return $this->values->getMaxValue();
    }

    protected function getMinKey()
    {
        if (!is_null($this->multi_graph)) {
            return $this->multi_graph->getMinKey();
        }
        return $this->values->getMinKey();
    }

    protected function getMaxKey()
    {
        if (!is_null($this->multi_graph)) {
            return $this->multi_graph->getMaxKey();
        }
        return $this->values->getMaxKey();
    }

    protected function getKey($i)
    {
        if (!is_null($this->multi_graph)) {
            return $this->multi_graph->getKey($i);
        }
        return $this->values->getKey($i);
    }

    /**
     * Draws the selected graph
     */
    public function DrawGraph()
    {
        $canvas_id = $this->NewID();
        $this->InitLegend();

        $contents = $this->Canvas($canvas_id);
        $contents .= $this->DrawTitle();
        $contents .= $this->Draw();
        $contents .= $this->DrawDataLabels();
        $contents .= $this->DrawBackMatter();
        $contents .= $this->DrawLegend();

        // rounded rects might need a clip path
        if ($this->back_round && $this->back_round_clip) {
            $group = ['clip-path' => "url(#{$canvas_id})"];
            return $this->Element('g', $group, null, $contents);
        }
        return $contents;
    }


    /**
     * Adds any markup that goes after the graph
     */
    protected function DrawBackMatter()
    {
        return $this->back_matter;
    }


    /**
     * Sets up the legend class
     */
    protected function InitLegend()
    {
        // see if the legend is needed
        if (!$this->show_legend || (empty($this->legend_entries) &&
                (!isset($this->structure) || !isset($this->structure['legend_text'])))
        ) {
            $this->legend = null;
            return;
        }
        $this->legend = new Legend($this, $this->legend_reverse);
    }

    /**
     * Draws the legend
     */
    protected function DrawLegend()
    {
        if (is_null($this->legend)) {
            return '';
        }
        return $this->legend->Draw();
    }

    /**
     * Parses a position string, returning x and y coordinates
     */
    public function ParsePosition($pos, $w = 0, $h = 0, $pad = 0)
    {
        $inner = true;
        $parts = preg_split('/\s+/', $pos);
        if (count($parts)) {
            // if 'outer' is found after 'inner', it takes precedence
            $parts = array_reverse($parts);
            $inner_at = array_search('inner', $parts);
            $outer_at = array_search('outer', $parts);

            if ($outer_at !== false && ($inner_at === false || $inner_at < $outer_at)) {
                $inner = false;
            }
        }

        if ($inner) {
            $t = $this->pad_top;
            $l = $this->pad_left;
            $b = $this->height - $this->pad_bottom;
            $r = $this->width - $this->pad_right;
            // make sure it fits to keep RelativePosition happy
            if ($w > $r - $l) {
                $w = $r - $l;
            }
            if ($h > $b - $t) {
                $h = $b - $t;
            }
        } else {
            $t = $l = 0;
            $b = $this->height;
            $r = $this->width;
        }

        // ParsePosition is always inside canvas or graph, defaulted to top left
        $pos = "top left " . str_replace('outer', 'inner', $pos);
        return Graph::RelativePosition($pos, $t, $l, $b, $r, $w, $h, $pad);
    }

    /**
     * Returns [hpos,vpos,offset_x,offset_y] positions derived from full
     * position string
     */
    public static function TranslatePosition($pos)
    {
        $parts = preg_split('/\s+/', strtolower($pos));
        $offset_x = $offset_y = 0;
        $inside = true;
        $vpos = 'm';
        $hpos = 'c';

        // translated positions:
        // ot, t, m, b, ob = outside top, top, middle, bottom, outside bottom
        // ol, l, c, r, or = outside left, left, centre, right, outside right
        while (count($parts)) {
            $part = array_shift($parts);
            switch ($part) {
                case 'outer' :
                case 'outside' :
                    $inside = false;
                    break;
                case 'inner' :
                case 'inside' :
                    $inside = true;
                    break;
                case 'top' :
                    $vpos = $inside ? 't' : 'ot';
                    break;
                case 'bottom' :
                    $vpos = $inside ? 'b' : 'ob';
                    break;
                case 'left' :
                    $hpos = $inside ? 'l' : 'ol';
                    break;
                case 'right' :
                    $hpos = $inside ? 'r' : 'or';
                    break;
                case 'above' :
                    $inside = false;
                    $vpos = 'ot';
                    break;
                case 'below' :
                    $inside = false;
                    $vpos = 'ob';
                    break;
                default:
                    if (is_numeric($part)) {
                        $offset_x = $part;
                        if (count($parts) && is_numeric($parts[0])) {
                            $offset_y = array_shift($parts);
                        }
                    }
            }
        }
        return [$hpos, $vpos, $offset_x, $offset_y];
    }

    /**
     * Returns [x,y,text-anchor,hpos,vpos] position that is $pos relative to the
     * top, left, bottom and right.
     * When $text is true, x and y are adjusted for text-anchor position
     */
    public static function RelativePosition(
        $pos,
        $top,
        $left,
        $bottom,
        $right,
        $width,
        $height,
        $pad,
        $text = false
    ) {
        list($hpos, $vpos, $offset_x, $offset_y) = Graph::TranslatePosition($pos);

        // if the containers have no thickness, position outside
        $translate = ['l' => 'ol', 'r' => 'or', 't' => 'ot', 'b' => 'ob'];
        if ($top == $bottom && isset($translate[$vpos])) {
            $vpos = $translate[$vpos];
        }
        if ($left == $right && isset($translate[$hpos])) {
            $hpos = $translate[$hpos];
        }

        switch ($vpos) {
            case 'ot' :
                $y = $top - $height - $pad;
                break;
            case 't' :
                $y = $top + $pad;
                break;
            case 'b' :
                $y = $bottom - $height - $pad;
                break;
            case 'ob' :
                $y = $bottom + $pad;
                break;
            case 'm' :
            default :
                $y = $top + ($bottom - $top - $height) / 2;
                break;
        }

        if (($hpos == 'r' || $hpos == 'l') && $right - $left - $pad - $width < 0) {
            $hpos = 'c';
        }
        switch ($hpos) {
            case 'ol' :
                $x = $left - $width - $pad;
                break;
            case 'l' :
                $x = $left + $pad;
                break;
            case 'r' :
                $x = $right - $width - $pad;
                break;
            case 'or' :
                $x = $right + $pad;
                break;
            case 'c' :
            default :
                $x = $left + ($right - $left - $width) / 2;
                break;
        }

        $y += $offset_y;
        $x += $offset_x;

        // third return value is text alignment
        $align_map = [
            'ol' => 'end',
            'l'  => 'start',
            'c'  => 'middle',
            'r'  => 'end',
            'or' => 'start'
        ];
        $text_align = $align_map[$hpos];

        // in text mode, adjust X for text alignment
        if ($text && $hpos != 'l' && $hpos != 'or') {
            if ($hpos == 'c') {
                $x += $width / 2;
            } else {
                $x += $width;
            }
        }
        return [$x, $y, $text_align, $hpos, $vpos];
    }

    /**
     * Sets the style info for the legend
     */
    protected function SetLegendEntry($dataset, $index, $item, $style_info)
    {
        if (is_null($this->legend)) {
            return;
        }
        $this->legend->SetEntry($dataset, $index, $item, $style_info);
    }

    /**
     * Subclasses must draw the entry, if they can
     */
    protected function DrawLegendEntry($x, $y, $w, $h, $entry)
    {
        return '';
    }

    /**
     * Draws the graph title, if there is one
     */
    protected function DrawTitle()
    {
        // graph_title is available for all graph types
        if (SVGGraphStrlen($this->graph_title, $this->encoding) <= 0) {
            return '';
        }

        $pos = $this->graph_title_position;
        $text = [
            'font-size'   => $this->graph_title_font_size,
            'font-family' => $this->graph_title_font,
            'font-weight' => $this->graph_title_font_weight,
            'text-anchor' => 'middle',
            'fill'        => $this->graph_title_colour
        ];
        $lines = $this->CountLines($this->graph_title);
        $title_space = $this->graph_title_font_size * $lines +
            $this->graph_title_space;
        if ($pos != 'top' && $pos != 'bottom' && $pos != 'left' && $pos != 'right') {
            $pos = 'top';
        }
        $pad_side = 'pad_' . $pos;

        // ensure outside padding is at least the title space
        if ($this->{$pad_side} < $this->graph_title_space) {
            $this->{$pad_side} = $this->graph_title_space;
        }

        if ($pos == 'left') {
            $text['x'] = $this->pad_left + $this->graph_title_font_size;
            $text['y'] = $this->height / 2;
            $text['transform'] = "rotate(270,$text[x],$text[y])";
        } elseif ($pos == 'right') {
            $text['x'] = $this->width - $this->pad_right -
                $this->graph_title_font_size;
            $text['y'] = $this->height / 2;
            $text['transform'] = "rotate(90,$text[x],$text[y])";
        } elseif ($pos == 'bottom') {
            $text['x'] = $this->width / 2;
            $text['y'] = $this->height - $this->pad_bottom -
                $this->graph_title_font_size * ($lines - 1);
        } else {
            $text['x'] = $this->width / 2;
            $text['y'] = $this->pad_top + $this->graph_title_font_size;
        }
        // increase padding by size of text
        $this->{$pad_side} += $title_space;

        // the Text function will break it into lines
        return $this->Text($this->graph_title, $this->graph_title_font_size,
            $text);
    }


    /**
     * This should be overridden by subclass!
     */
    abstract protected function Draw();

    /**
     * Displays the background image
     */
    protected function BackgroundImage()
    {
        if (!$this->back_image) {
            return '';
        }
        $image = [
            'width'               => $this->back_image_width,
            'height'              => $this->back_image_height,
            'x'                   => $this->back_image_left,
            'y'                   => $this->back_image_top,
            'xlink:href'          => $this->back_image,
            'preserveAspectRatio' =>
                ($this->back_image_mode == 'stretch' ? 'none' : 'xMinYMin')
        ];
        $style = [];
        if ($this->back_image_opacity) {
            $style['opacity'] = $this->back_image_opacity;
        }

        $contents = '';
        if ($this->back_image_mode == 'tile') {
            $image['x'] = 0;
            $image['y'] = 0;
            $im = $this->Element('image', $image, $style);
            $pattern = [
                'id'           => $this->NewID(),
                'width'        => $this->back_image_width,
                'height'       => $this->back_image_height,
                'x'            => $this->back_image_left,
                'y'            => $this->back_image_top,
                'patternUnits' => 'userSpaceOnUse'
            ];
            // tiled image becomes a pattern to replace background colour
            $this->defs[] = $this->Element('pattern', $pattern, null, $im);
            $this->back_colour = "url(#{$pattern['id']})";
        } else {
            $im = $this->Element('image', $image, $style);
            $contents .= $im;
        }
        return $contents;
    }

    /**
     * Displays the background
     */
    protected function Canvas($id)
    {
        $bg = $this->BackgroundImage();
        $colour = $this->ParseColour($this->back_colour);
        $opacity = 1;
        if (strpos($colour, ':') !== false) {
            list($colour, $opacity) = explode(':', $colour);
        }

        $canvas = [
            'width'        => '100%',
            'height'       => '100%',
            'fill'         => $colour,
            'stroke-width' => 0
        ];
        if ($opacity < 1) {
            if ($opacity <= 0) {
                $canvas['fill'] = 'none';
            } else {
                $canvas['opacity'] = $opacity;
            }
        }

        if ($this->back_round) {
            $canvas['rx'] = $canvas['ry'] = $this->back_round;
        }
        if ($bg == '' && $this->back_stroke_width) {
            $canvas['stroke-width'] = $this->back_stroke_width;
            $canvas['stroke'] = $this->back_stroke_colour;
        }
        $c_el = $this->Element('rect', $canvas);

        // create a clip path for rounded rectangle
        if ($this->back_round) {
            $this->defs[] = $this->Element('clipPath', ['id' => $id],
                null, $c_el);
        }
        // if the background image is an element, insert it between the background
        // colour and border rect
        if ($bg != '') {
            $c_el .= $bg;
            if ($this->back_stroke_width) {
                $canvas['stroke-width'] = $this->back_stroke_width;
                $canvas['stroke'] = $this->back_stroke_colour;
                $canvas['fill'] = 'none';
                $c_el .= $this->Element('rect', $canvas);
            }
        }
        return $c_el;
    }

    /**
     * Fits text to a box - text will be bottom-aligned
     */
    protected function TextFit(
        $text,
        $x,
        $y,
        $w,
        $h,
        $attribs = null,
        $styles = null
    ) {
        $pos = ['onload' => "textFit(evt,$x,$y,$w,$h)"];
        if (is_array($attribs)) {
            $pos = array_merge($attribs, $pos);
        }
        $txt = $this->Element('text', $pos, $styles, $text);

        /** Uncomment to see the box
         * $rect = array('x' => $x, 'y' => $y, 'width' => $w, 'height' => $h,
         * 'fill' => 'none', 'stroke' => 'black');
         * $txt .= $this->Element('rect', $rect);
         **/
        $this->AddFunction('textFit');
        return $txt;
    }

    /**
     * Returns a text element, with tspans for multiple lines
     */
    public function Text($text, $line_spacing, $attribs, $styles = null)
    {
        // strip special characters
        $text = htmlspecialchars($text, ENT_COMPAT, $this->encoding);

        // put entities back in
        $text = preg_replace('/&amp;(amp|#x[a-f0-9]+|#\d+);/', '&$1;', $text);

        if (strpos($text, "\n") === false) {
            $content = ($text == '' ? ' ' : $text);
        } else {
            $lines = explode("\n", $text);
            $content = '';
            $tspan = ['x' => $attribs['x'], 'dy' => 0];
            foreach ($lines as $line) {
                // blank tspan elements collapse to nothing, so insert a space
                if ($line == '') {
                    $line = ' ';
                }

                $content .= $this->Element('tspan', $tspan, null, $line);
                $tspan['dy'] = $line_spacing;
            }
        }
        return $this->Element('text', $attribs, $styles, $content);
    }

    /**
     * Returns [width,height] of text
     * $text = string OR text length
     */
    public static function TextSize(
        $text,
        $font_size,
        $font_adjust,
        $encoding,
        $angle = 0,
        $line_spacing = 0
    ) {
        $height = $font_size;
        if (is_int($text)) {
            $len = $text;
        } else {
            // replace all entities with an underscore (just for measurement)
            $text = preg_replace('/&[^;]+;/', '_', $text);
            if ($line_spacing > 0) {
                $len = 0;
                $lines = explode("\n", $text);
                foreach ($lines as $l) {
                    if (SVGGraphStrlen($l, $encoding) > $len) {
                        $len = SVGGraphStrlen($l, $encoding);
                    }
                }
                $height += $line_spacing * (count($lines) - 1);
            } else {
                $len = SVGGraphStrlen($text, $encoding);
            }
        }
        $width = $len * $font_size * $font_adjust;
        if ($angle % 180 != 0) {
            if ($angle % 90 == 0) {
                $w = $height;
                $height = $width;
                $width = $w;
            } else {
                $a = deg2rad($angle);
                $sa = abs(sin($a));
                $ca = abs(cos($a));
                $w = $ca * $width + $sa * $height;
                $h = $sa * $width + $ca * $height;
                $width = $w;
                $height = $h;
            }
        }
        return [$width, $height];
    }

    /**
     * Returns the number of lines in a string
     */
    public static function CountLines($text)
    {
        $c = 1;
        $pos = 0;
        while (($pos = strpos($text, "\n", $pos)) !== false) {
            ++$c;
            ++$pos;
        }
        return $c;
    }

    /**
     * Displays readable (hopefully) error message
     */
    protected function ErrorText($error)
    {
        $text = ['x' => $this->pad_left, 'y' => $this->height - 3];
        $style = [
            'font-family' => 'monospace',
            'font-size'   => '11px',
            'font-weight' => 'bold',
        ];

        $e = $this->ContrastText($text['x'], $text['y'], $error, 'blue',
            'white', $style);
        return $e;
    }

    /**
     * Displays high-contrast text
     */
    protected function ContrastText(
        $x,
        $y,
        $text,
        $fcolour = 'black',
        $bcolour = 'white',
        $properties = null,
        $styles = null
    ) {
        $props = [
            'transform' => 'translate(' . $x . ',' . $y . ')',
            'fill'      => $fcolour
        ];
        if (is_array($properties)) {
            $props = array_merge($properties, $props);
        }

        $bg = $this->Element('text',
            ['stroke-width' => '2px', 'stroke' => $bcolour], null, $text);
        $fg = $this->Element('text', null, null, $text);
        return $this->Element('g', $props, $styles, $bg . $fg);
    }

    /**
     * Builds an element
     */
    public function Element($name, $attribs = null, $styles = null, $content = null) {
        // these properties require units to work well
        $require_units = [
            'stroke-width'      => 1,
            'stroke-dashoffset' => 1,
            'font-size'         => 1,
            'baseline-shift'    => 1,
            'kerning'           => 1,
            'letter-spacing'    => 1,
            'word-spacing'      => 1
        ];

        if ($this->namespace && strpos($name, ':') === false) {
            $name = 'svg:' . $name;
        }
        $element = '<' . $name;
        if (is_array($attribs)) {
            foreach ($attribs as $attr => $val) {
                // if units required, add px
                if (is_numeric($val)) {
                    if (isset($require_units[$attr])) {
                        $val .= 'px';
                    }
                } else {
                    $val = htmlspecialchars($val, ENT_COMPAT, $this->encoding);
                }
                $element .= ' ' . $attr . '="' . $val . '"';
            }
        }

        if (is_array($styles)) {
            $element .= ' style="';
            foreach ($styles as $attr => $val) {
                // check units again
                if (is_numeric($val)) {
                    if (isset($require_units[$attr])) {
                        $val .= 'px';
                    }
                } else {
                    $val = htmlspecialchars($val, ENT_COMPAT, $this->encoding);
                }
                $element .= $attr . ':' . $val . ';';
            }
            $element .= '"';
        }

        if (is_null($content)) {
            $element .= "/>\n";
        } else {
            $element .= '>' . $content . '</' . $name . ">\n";
        }

        return $element;
    }

    /**
     * Returns a link URL or NULL if none
     */
    protected function GetLinkURL($item, $key, $row = 0)
    {
        $link = is_null($item) ? null : $item->Data('link');
        if (is_null($link) && is_array($this->links[$row]) &&
            isset($this->links[$row][$key])
        ) {
            $link = $this->links[$row][$key];
        }

        // check for absolute links
        if (!is_null($link) && strpos($link, '//') === false) {
            $link = $this->link_base . $link;
        }

        return $link;
    }

    /**
     * Retrieves a link
     */
    protected function GetLink($item, $key, $content, $row = 0)
    {
        $link = $this->GetLinkURL($item, $key, $row);
        if (is_null($link)) {
            return $content;
        }

        $link_attr = ['xlink:href' => $link, 'target' => $this->link_target];
        return $this->Element('a', $link_attr, null, $content);
    }

    /**
     * Sets up the colour class
     */
    protected function ColourSetup($count, $datasets = null)
    {
        $this->colours->Setup($count, $datasets);
    }

    /**
     * Returns a colour reference
     */
    protected function GetColour(
        $item,
        $key,
        $dataset = null,
        $no_gradient = false,
        $allow_pattern = false
    ) {
        $colour = 'none';
        $icolour = is_null($item) ? null : $item->Data('colour');
        if (!is_null($icolour)) {
            $colour = $icolour;
            $key = null; // don't reuse existing colours
        } else {
            $c = $this->colours->GetColour($key, $dataset);
            if (!is_null($c)) {
                $colour = $c;
            }

            // make key reflect dataset as well (for gradients)
            if (!is_null($dataset)) {
                $key = "{$dataset}:{$key}";
            }
        }
        return $this->ParseColour($colour, $key, $no_gradient, $allow_pattern);
    }

    /**
     * Converts a SVGGraph colour/gradient/pattern to a SVG attribute
     */
    public function ParseColour(
        $colour,
        $key = null,
        $no_gradient = false,
        $allow_pattern = false,
        $radial_gradient = false
    ) {
        if (is_array($colour)) {
            if (!isset($colour['pattern'])) {
                $allow_pattern = false;
            }
            if (count($colour) < 2 || ($no_gradient && !$allow_pattern)) {
                $colour = $this->SolidColour($colour);
            } elseif (isset($colour['pattern'])) {
                $pattern_id = $this->AddPattern($colour);
                $colour = "url(#{$pattern_id})";
            } else {
                $err = array_diff_key($colour, array_keys(array_keys($colour)));
                if ($err) {
                    throw new Exception('Malformed gradient/pattern');
                }
                $gradient_id = $this->AddGradient($colour, $key, $radial_gradient);
                $colour = "url(#{$gradient_id})";
            }
        }
        return $colour;
    }

    /**
     * Returns the solid colour from a gradient
     */
    protected static function SolidColour($c)
    {
        if (is_array($c)) {
            // grab the first colour in the array, discarding opacity
            $c = $c[0];
            $colon = strpos($c, ':');
            if ($colon) {
                $c = substr($c, 0, $colon);
            }
        }
        return $c;
    }

    /**
     * Returns the first non-empty argument
     */
    public static function GetFirst()
    {
        $opts = func_get_args();
        foreach ($opts as $opt) {
            if (!empty($opt) || $opt === 0) {
                return $opt;
            }
        }
    }

    /**
     * Returns an option from array, or non-array option
     */
    public static function ArrayOption($o, $i)
    {
        return is_array($o) ? $o[$i % count($o)] : $o;
    }

    /**
     * Checks that the data are valid
     */
    protected function CheckValues()
    {
        if ($this->values->error) {
            throw new Exception($this->values->error);
        }
    }

    /**
     * Sets the stroke options for an element
     */
    protected function SetStroke(&$attr, &$item, $set = 0, $line_join = null)
    {
        $stroke_width = $this->GetFromItemOrMember('stroke_width', $set, $item);
        if ($stroke_width > 0) {
            $attr['stroke'] = $this->GetFromItemOrMember('stroke_colour', $set, $item);
            $attr['stroke-width'] = $stroke_width;
            if (!is_null($line_join)) {
                $attr['stroke-linejoin'] = $line_join;
            } else {
                unset($attr['stroke-linejoin']);
            }

            $dash = $this->GetFromItemOrMember('stroke_dash', $set, $item);
            if (!empty($dash)) {
                $attr['stroke-dasharray'] = $dash;
            } else {
                unset($attr['stroke-dasharray']);
            }
        } else {
            unset($attr['stroke'], $attr['stroke-width'], $attr['stroke-linejoin'],
                $attr['stroke-dasharray']);
        }
    }

    /**
     * Creates a new ID for an element
     */
    public function NewID()
    {
        return $this->id_prefix . 'e' . base_convert(++Graph::$last_id, 10, 36);
    }

    /**
     * Adds markup to be inserted between graph and legend
     */
    public function AddBackMatter($fragment)
    {
        $this->back_matter .= $fragment;
    }

    /**
     * Loads the Javascript class
     */
    private function LoadJavascript()
    {
        if (!isset(Graph::$javascript)) {
            Graph::$javascript = new Javascript($this->settings, $this);
        }
    }

    /**
     * Adds a javascript function
     */
    protected function AddFunction($name, $realname = null)
    {
        $this->LoadJavascript();
        Graph::$javascript->AddFunction($name, $realname);
    }

    /**
     * Adds a Javascript variable
     * - use $value:$more for assoc
     * - use null:$more for array
     */
    public function InsertVariable($var, $value, $more = null, $quote = true)
    {
        $this->LoadJavascript();
        Graph::$javascript->InsertVariable($var, $value, $more, $quote);
    }

    /**
     * Insert a comment into the Javascript section - handy for debugging!
     */
    public function InsertComment($details)
    {
        $this->LoadJavascript();
        Graph::$javascript->InsertComment($details);
    }

    /**
     * Adds a pattern, returning the element ID
     */
    public function AddPattern($pattern)
    {
        if (is_null($this->pattern_list)) {
            $this->pattern_list = new PatternList($this);
        }
        return $this->pattern_list->Add($pattern);
    }

    /**
     * Adds a gradient to the list, returning the element ID for use in url
     */
    public function AddGradient($colours, $key = null, $radial = false)
    {
        if (is_null($key) || !isset($this->gradients[$key])) {

            if ($radial) {
                // if this is a radial gradient, it must end with 'r'
                $last = count($colours) - 1;
                if (strlen($colours[$last]) == 1) {
                    $colours[$last] = 'r';
                } else {
                    $colours[] = 'r';
                }
            }

            // find out if this gradient already stored
            $hash = serialize($colours);
            if (isset($this->gradient_map[$hash])) {
                return $this->gradient_map[$hash];
            }

            $id = $this->NewID();
            if (is_null($key)) {
                $key = $id;
            }
            $this->gradients[$key] = [
                'id'      => $id,
                'colours' => $colours
            ];
            $this->gradient_map[$hash] = $id;
            return $id;
        }
        return $this->gradients[$key]['id'];
    }

    /**
     * Creates a linear gradient element
     */
    private function MakeLinearGradient($key)
    {
        $stops = '';
        $direction = 'v';
        $type = 'linearGradient';
        $colours = $this->gradients[$key]['colours'];
        $id = $this->gradients[$key]['id'];

        if (in_array($colours[count($colours) - 1], ['h', 'v', 'r'])) {
            $direction = array_pop($colours);
        }
        if ($direction == 'r') {
            $type = 'radialGradient';
            $gradient = ['id' => $id];
        } else {
            $x2 = $direction == 'v' ? 0 : '100%';
            $y2 = $direction == 'h' ? 0 : '100%';
            $gradient = [
                'id' => $id,
                'x1' => 0,
                'x2' => $x2,
                'y1' => 0,
                'y2' => $y2
            ];
        }

        $col_mul = 100 / (count($colours) - 1);
        $offset = 0;
        foreach ($colours as $pos => $colour) {
            $opacity = null;
            $poffset = $pos * $col_mul;
            if (strpos($colour, ':') !== false) {
                // opacity, stop offset or both
                $parts = explode(':', $colour);
                if (is_numeric($parts[0])) {
                    $poffset = array_shift($parts);
                }
                $colour = array_shift($parts);
                $opacity = array_shift($parts); // NULL if not set
            }
            // set the offset to the most meaningful number
            $offset = min(100, max(0, $offset, $poffset));
            $stop = [
                'offset'     => $offset . '%',
                'stop-color' => $colour
            ];
            if (is_numeric($opacity)) {
                $stop['stop-opacity'] = $opacity;
            }
            $stops .= $this->Element('stop', $stop);
        }

        return $this->Element($type, $gradient, null, $stops);
    }

    /**
     * Adds an inline event handler to an element's array
     */
    protected function AddEventHandler(&$array, $evt, $code)
    {
        $this->LoadJavascript();
        Graph::$javascript->AddEventHandler($array, $evt, $code);
    }

    /**
     * Makes an item draggable
     */
    public function SetDraggable(&$element)
    {
        $this->LoadJavascript();
        Graph::$javascript->SetDraggable($element);
    }

    /**
     * Makes something auto-hide
     */
    public function AutoHide(&$element)
    {
        $this->LoadJavascript();
        Graph::$javascript->AutoHide($element);
    }


    /**
     * Default tooltip contents are key and value, or whatever
     * $key is if $value is not set
     */
    protected function SetTooltip(
        &$element,
        &$item,
        $dataset,
        $key,
        $value = null,
        $duplicate = false
    ) {
        if (is_callable($this->tooltip_callback)) {
            if (is_null($value)) {
                $value = $key;
            }
            $text = call_user_func($this->tooltip_callback, $dataset, $key, $value);
        } elseif (is_array($this->structure) && isset($this->structure['tooltip'])) {
            // use structured data tooltips if specified
            $text = $item->Data('tooltip');
        } else {
            $text = $this->FormatTooltip($item, $dataset, $key, $value);
        }
        if (is_null($text)) {
            return;
        }
        $text = addslashes(str_replace("\n", '\n', $text));
        Graph::$javascript->SetTooltip($element, $text, $duplicate);
    }

    /**
     * Default format is value only
     */
    protected function FormatTooltip(&$item, $dataset, $key, $value)
    {
        return $this->units_before_tooltip . Graph::NumString($value) .
        $this->units_tooltip;
    }


    /**
     * Sets the fader for an element
     * @param array  &$element  Element that should cause fading
     * @param number $in        Fade in speed
     * @param number $out       Fade out speed
     * @param string $id        ID of element to be faded
     * @param bool   $duplicate TRUE to create transparent overlay
     */
    protected function SetFader(
        &$element,
        $in,
        $out,
        $target = null,
        $duplicate = false
    ) {
        $this->LoadJavascript();
        Graph::$javascript->SetFader($element, $in, $out, $target, $duplicate);
    }

    /**
     * Sets click visibility for $target when $element is clicked
     */
    protected function SetClickShow(
        &$element,
        $target,
        $hidden,
        $duplicate = false
    ) {
        $this->LoadJavascript();
        Graph::$javascript->SetClickShow($element, $target, $hidden, $duplicate);
    }

    public function SetPopFront(&$element, $target, $duplicate = false)
    {
        $this->LoadJavascript();
        Graph::$javascript->SetPopFront($element, $target, $duplicate);
    }

    /**
     * Add an overlaid copy of an element, with opacity of 0
     * $from and $to are the IDs of the source and destination
     */
    protected function AddOverlay($from, $to)
    {
        $this->LoadJavascript();
        Graph::$javascript->AddOverlay($from, $to);
    }

    /**
     * Adds a data label to the list
     */
    protected function AddDataLabel(
        $dataset,
        $index,
        &$element,
        &$item,
        $x,
        $y,
        $w,
        $h,
        $content = null,
        $duplicate = true
    ) {
        if (!$this->ArrayOption($this->show_data_labels, $dataset)) {
            return false;
        }
        if (!isset($this->data_labels)) {
            $this->data_labels = new DataLabels($this);
        }

        // set up fading for this label?
        $id = null;
        $fade_in = $this->ArrayOption($this->data_label_fade_in_speed, $dataset);
        $fade_out = $this->ArrayOption($this->data_label_fade_out_speed, $dataset);
        $click = $this->ArrayOption($this->data_label_click, $dataset);
        $popup = $this->ArrayOption($this->data_label_popfront, $dataset);
        if ($click == 'hide' || $click == 'show') {
            $id = $this->NewID();
            $this->SetClickShow($element, $id, $click == 'hide',
                $duplicate && !$this->compat_events);
        }
        if ($popup) {
            if (!$id) {
                $id = $this->NewID();
            }
            $this->SetPopFront($element, $id, $duplicate && !$this->compat_events);
        }
        if ($fade_in || $fade_out) {
            $speed_in = $fade_in ? $fade_in / 100 : 0;
            $speed_out = $fade_out ? $fade_out / 100 : 0;
            if (!$id) {
                $id = $this->NewID();
            }
            $this->SetFader($element, $speed_in, $speed_out, $id,
                $duplicate && !$this->compat_events);
        }
        $this->data_labels->AddLabel($dataset, $index, $item, $x, $y, $w, $h, $id,
            $content, $fade_in, $click);
        return true;
    }

    /**
     * Adds a label for non-data text
     */
    protected function AddContentLabel($dataset, $index, $x, $y, $w, $h, $content)
    {
        if (!isset($this->data_labels)) {
            $this->data_labels = new DataLabels($this);
        }

        $this->data_labels->AddContentLabel($dataset, $index, $x, $y, $w, $h,
            $content);
        return true;
    }

    /**
     * Draws the data labels
     */
    protected function DrawDataLabels()
    {
        if (isset($this->settings['label'])) {
            if (!isset($this->data_labels)) {
                $this->data_labels = new DataLabels($this);
            }
            $this->data_labels->Load($this->settings);
        }
        if (isset($this->data_labels)) {
            return $this->data_labels->GetLabels();
        }
        return '';
    }

    /**
     * Returns the position for a data label
     */
    public function DataLabelPosition(
        $dataset,
        $index,
        &$item,
        $x,
        $y,
        $w,
        $h,
        $label_w,
        $label_h
    ) {
        $pos = $this->ArrayOption($this->data_label_position, $dataset);
        if (empty($pos)) {
            $pos = 'above';
        }
        return $pos;
    }


    public function LoadShapes()
    {
        $this->shapes = new ShapeList($this);
        $this->shapes->Load($this->settings);
    }

    public function UnderShapes()
    {
        if (!isset($this->shapes) && isset($this->settings['shape'])) {
            $this->LoadShapes();
        }
        return isset($this->shapes) ? $this->shapes->Draw(Shape::SHAPE_BELOW) : '';
    }

    public function OverShapes()
    {
        return isset($this->shapes) ? $this->shapes->Draw(Shape::SHAPE_ABOVE) : '';
    }

    /**
     * Returns TRUE if the position is inside the item
     */
    public static function IsPositionInside($pos)
    {
        list($hpos, $vpos) = Graph::TranslatePosition($pos);
        return strpos($hpos . $vpos, 'o') === false;
    }

    /**
     * Sets the styles for data labels
     */
    public function DataLabelStyle($dataset, $index, &$item)
    {
        $style = [
            'type'           => $this->ArrayOption($this->data_label_type, $dataset),
            'font'           => $this->ArrayOption($this->data_label_font, $dataset),
            'font_size'      => $this->ArrayOption($this->data_label_font_size, $dataset),
            'font_adjust'    => $this->ArrayOption($this->data_label_font_adjust, $dataset),
            'font_weight'    => $this->ArrayOption($this->data_label_font_weight, $dataset),
            'colour'         => $this->ArrayOption($this->data_label_colour, $dataset),
            'altcolour'      => $this->ArrayOption($this->data_label_colour_outside, $dataset),
            'back_colour'    => $this->ArrayOption($this->data_label_back_colour, $dataset),
            'back_altcolour' => $this->ArrayOption($this->data_label_back_colour_outside, $dataset),
            'space'          => $this->ArrayOption($this->data_label_space, $dataset),
            'angle'          => $this->ArrayOption($this->data_label_angle, $dataset),
            'pad_x'          => $this->GetFirst(
                $this->ArrayOption($this->data_label_padding_x, $dataset),
                $this->ArrayOption($this->data_label_padding, $dataset)),
            'pad_y'          => $this->GetFirst(
                $this->ArrayOption($this->data_label_padding_y, $dataset),
                $this->ArrayOption($this->data_label_padding, $dataset)),
            'round'          => $this->ArrayOption($this->data_label_round, $dataset),
            'stroke'         => $this->ArrayOption($this->data_label_outline_colour, $dataset),
            'stroke_width'   => $this->ArrayOption($this->data_label_outline_thickness, $dataset),
            'fill'           => $this->ArrayOption($this->data_label_fill, $dataset),
            'tail_width'     => $this->ArrayOption($this->data_label_tail_width, $dataset),
            'tail_length'    => $this->ArrayOption($this->data_label_tail_length, $dataset),
            'shadow_opacity' => $this->ArrayOption($this->data_label_shadow_opacity, $dataset),
        ];
        return $style;
    }

    /**
     * Tail direction is required for some types of label
     */
    public function DataLabelTailDirection($dataset, $index, $hpos, $vpos)
    {
        // angle starts at right, goes clockwise
        $angle = 90;
        $pos = str_replace(['i', 'o', 'm'], '', $vpos) .
            str_replace(['i', 'o', 'c'], '', $hpos);
        switch ($pos) {
            case 'l' :
                $angle = 0;
                break;
            case 'tl' :
                $angle = 45;
                break;
            case 't' :
                $angle = 90;
                break;
            case 'tr' :
                $angle = 135;
                break;
            case 'r' :
                $angle = 180;
                break;
            case 'br' :
                $angle = 225;
                break;
            case 'b' :
                $angle = 270;
                break;
            case 'bl' :
                $angle = 315;
                break;
        }
        return $angle;
    }

    /**
     * Builds and returns the body of the graph
     */
    private function BuildGraph()
    {
        $this->CheckValues($this->values);

        if ($this->show_tooltips) {
            $this->LoadJavascript();
        }

        // body content comes from the subclass
        return $this->DrawGraph();
    }

    /**
     * Returns the SVG document
     *
     * @param bool $addXmlHeader
     * @param bool $deferJavascript
     * @return mixed|string
     */
    public function fetch($addXmlHeader = true, $deferJavascript = true)
    {
        $content = '';
        if ($addXmlHeader) {
            $content .= '<?xml version="1.0"';
            // encoding comes before standalone
            if (strlen($this->encoding) > 0) {
                $content .= " encoding=\"{$this->encoding}\"";
            }
            // '>' is with \n so as not to confuse syntax highlighting
            $content .= " standalone=\"no\"?" . ">\n";
            if ($this->doctype) {
                $content .= '<!DOCTYPE svg PUBLIC "-//W3C//DTD SVG 1.1//EN" ' .
                    '"http://www.w3.org/Graphics/SVG/1.1/DTD/svg11.dtd">' . "\n";
            }
        }

        // set the precision - PHP default is 14 digits!
        Graph::$precision = $this->settings['precision'];
        $old_precision = ini_set('precision', Graph::$precision);
        // set decimal and thousands for NumString
        Graph::setNumStringOptions($this->settings['decimal'], $this->settings['thousands']);

        // display title and description if available
        $heading = '';
        if ($this->title) {
            $heading .= $this->Element('title', null, null, $this->title);
        }
        if ($this->description) {
            $heading .= $this->Element('desc', null, null, $this->description);
        }

        if ($this->exception_throw) {
            $body = $this->BuildGraph();
        } else {
            try {
                $body = $this->BuildGraph();
            } catch (\Exception $e) {
                $err = $e->getMessage();
                if ($this->exception_details) {
                    $err .= " [" . basename($e->getFile()) . ' #' . $e->getLine() . ']';
                }
                $body = $this->ErrorText($err);
            }
        }

        $svg = [
            'width'       => $this->width,
            'height'      => $this->height,
            'version'     => '1.1',
            'xmlns:xlink' => 'http://www.w3.org/1999/xlink'
        ];
        if ($this->auto_fit) {
            $svg['viewBox'] = "0 0 {$this->width} {$this->height}";
            $svg['width'] = $svg['height'] = '100%';
        }
        if ($this->svg_class) {
            $svg['class'] = $this->svg_class;
        }

        if (!$deferJavascript) {
            $js = $this->FetchJavascript();
            if ($js != '') {
                $heading .= $js;
                $onload = Graph::$javascript->GetOnload();
                if ($onload != '') {
                    $svg['onload'] = $onload;
                }
            }
        }

        // insert any gradients that are used
        foreach ($this->gradients as $key => $gradient) {
            $this->defs[] = $this->MakeLinearGradient($key);
        }
        // and any patterns
        if (!is_null($this->pattern_list)) {
            $this->pattern_list->MakePatterns($this->defs);
        }

        // show defs and body content
        if (count($this->defs)) {
            $heading .= $this->Element('defs', null, null, implode('', $this->defs));
        }
        if ($this->namespace) {
            $svg['xmlns:svg'] = "http://www.w3.org/2000/svg";
        } else {
            $svg['xmlns'] = "http://www.w3.org/2000/svg";
        }

        // add any extra namespaces
        foreach ($this->namespaces as $ns => $url) {
            $svg['xmlns:' . $ns] = $url;
        }

        // display version string
        if ($this->show_version) {
            $text = ['x' => $this->pad_left, 'y' => $this->height - 3];
            $style = [
                'font-family' => 'monospace',
                'font-size'   => '12px',
                'font-weight' => 'bold',
            ];
            $body .= $this->ContrastText($text['x'], $text['y'], SVGGRAPH_VERSION,
                'blue', 'white', $style);
        }

        $content .= $this->Element('svg', $svg, null, $heading . $body);
        // replace PHP's precision
        ini_set('precision', $old_precision);

        if ($this->minify) {
            $content = preg_replace('/\>\s+\</', '><', $content);
        }
        return $content;
    }

    /**
     * Renders the SVG document
     */
    public function render($header = true, $setContentTypeHeader = true, $defer_javascript = false)
    {
        $mimeHeader = 'Content-type: image/svg+xml; charset=UTF-8';
        if ($setContentTypeHeader) {
            header($mimeHeader);
        }
        if ($this->exception_throw) {
            echo $this->fetch($header, $defer_javascript);
        } else {
            try {
                echo $this->fetch($header, $defer_javascript);
            } catch (\Exception $e) {
                $this->errorText($e);
            }
        }
    }

    /**
     * When using the defer_javascript option, this returns the
     * Javascript block
     */
    public function fetchJavascript($onload_immediate = true, $cdata_wrap = true, $no_namespace = true)
    {
        $js = '';
        if (isset(Graph::$javascript)) {
            $variables = Graph::$javascript->GetVariables();
            $functions = Graph::$javascript->GetFunctions();
            $onload = Graph::$javascript->GetOnload();

            if ($variables != '' || $functions != '') {
                if ($onload_immediate) {
                    $functions .= "\n" . "setTimeout(function(){ {$onload} }, 20);";
                }
                $script_attr = ['type' => 'application/ecmascript'];
                $script = "$variables\n$functions\n";
                if (is_callable($this->minify_js)) {
                    $script = call_user_func($this->minify_js, $script);
                }
                if ($cdata_wrap) {
                    $script = "// <![CDATA[\n$script\n// ]]>";
                }
                $namespace = $this->namespace;
                if ($no_namespace) {
                    $this->namespace = false;
                }
                $js = $this->Element('script', $script_attr, null, $script);
                if ($no_namespace) {
                    $this->namespace = $namespace;
                }
            }
        }
        return $js;
    }

    /**
     * Returns a value from the $item, or the member % set
     */
    protected function GetFromItemOrMember($member, $set, &$item, $ikey = null)
    {
        $value = is_null($item) ? null : $item->Data(is_null($ikey) ? $member : $ikey);
        if (is_null($value)) {
            $value = is_array($this->{$member}) ?
                $this->{$member}[$set % count($this->{$member})] :
                $this->{$member};
        }
        return $value;
    }

    /**
     * Converts number to string
     */
    public static function numString($n, $decimals = null, $precision = null)
    {
        if (is_int($n)) {
            $d = is_null($decimals) ? 0 : $decimals;
        } else {

            if (is_null($precision)) {
                $precision = Graph::$precision;
            }

            // if there are too many zeroes before other digits, round to 0
            $e = floor(log(abs($n), 10));
            if (-$e > $precision) {
                $n = 0;
            }

            // subtract number of digits before decimal point from precision
            // for precision-based decimals
            $d = is_null($decimals) ? $precision - ($e > 0 ? $e : 0) : $decimals;
        }
        $s = number_format($n, $d, Graph::$decimal, Graph::$thousands);

        if (is_null($decimals) && $d && strpos($s, Graph::$decimal) !== false) {
            list($a, $b) = explode(Graph::$decimal, $s);
            $b1 = rtrim($b, '0');
            if ($b1 != '') {
                return $a . Graph::$decimal . $b1;
            }
            return $a;
        }
        return $s;
    }

    /**
     * Sets the number format characters
     */
    public static function setNumStringOptions($decimal, $thousands)
    {
        static::$decimal = $decimal;
        static::$thousands = $thousands;
    }

    /**
     * Returns the minimum value in the array, ignoring NULLs
     */
    public static function min(array $a)
    {
        return min(array_filter($a));
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
}

