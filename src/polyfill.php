<?php

/**
 * Function for sorting by fields
 */
function SVGGraphFieldSort($a, $b)
{
    $f = $GLOBALS['SVGGraphFieldSortField'];
    // check that fields are present
    if(!isset($a[$f]) || !isset($b[$f]))
        return 0;
    if($a[$f] == $b[$f])
        return 0;
    return $a[$f] > $b[$f] ? 1 : -1;
}

/**
 * Field to sort by
 */
$SVGGraphFieldSortField = 0;

/**
 * Converts a string key to a unix timestamp, or NULL if invalid
 */
function SVGGraphDateConvert($k)
{
    // if the format is set, try it
    if (!is_null(\SVGGraph\Graph\Graph::$key_format)) {
        $dt = date_create_from_format(\SVGGraph\Graph\Graph::$key_format, $k);

        // if the specified format fails, try default format
        if ($dt === false) {
            $dt = date_create($k);
        }
    } else {
        $dt = date_create($k);
    }
    if ($dt === false) {
        return null;
    }
    // this works in 64-bit on 32-bit systems, getTimestamp() doesn't
    return $dt->format('U');
}

if (!function_exists('SVGGraphStrlen')) {
    if (extension_loaded('mbstring')) {
        function SVGGraphStrlen($s, $e)
        {
            return mb_strlen($s, $e);
        }

        function SVGGraphSubstr($s, $b, $l, $e)
        {
            return mb_substr($s, $b, $l, $e);
        }
    } else {
        function SVGGraphStrlen($s, $e)
        {
            return strlen($s);
        }

        function SVGGraphSubstr($s, $b, $l, $e)
        {
            return is_null($l) ? substr($s, $b) : substr($s, $b, $l);
        }
    }
}
