<?php

namespace SVGGraph\StructuredData;

use SVGGraph\Concern\NoIterator;

/**
 * Class for structured data
 */
class Data implements \Countable, \ArrayAccess, \Iterator
{
    use NoIterator;

    public $error = null;

    private $dataSetsCount = 0;
    private $keyField = 0;
    private $datasetFields = [];

    private $data;
    private $forceAssoc = false;

    private $isAssoc = null;
    private $isDatetime;
    private $repeatedKeys;
    private $assocTest;
    private $structure = [];

    private $maxKeysByDataSets = [];
    private $minKeysByDataSets = [];
    private $maxValuesByDataSets = [];
    private $minValuesByDataSets = [];

    public function __construct(
        &$data,
        $forceAssoc,
        $datetimeKeys,
        $structure,
        $repeatedKeys,
        $integerKeys,
        $requirements,
        $rekeyDone = false
    ) {
        if (!is_null($structure) && !empty($structure)) {
            // structure provided, is it valid?
            foreach (['key', 'value'] as $field) {
                if (!array_key_exists($field, $structure)) {
                    $this->error = $field . ' field not set for structured data';
                    return;
                }
            }

            if (!is_array($structure['value'])) {
                $structure['value'] = [$structure['value']];
            }
            $this->keyField = $structure['key'];
            $this->datasetFields = is_array($structure['value']) ? $structure['value'] : [$structure['value']];
        } else {
            // find key and datasets
            $keys = array_keys($data[0]);
            $this->keyField = array_shift($keys);
            $this->datasetFields = $keys;

            // check for more datasets
            foreach ($data as $item) {
                foreach (array_keys($item) as $key) {
                    if ($key !== $this->keyField && array_search($key, $this->datasetFields) === false) {
                        $this->datasetFields[] = $key;
                    }
                }
            }

            // default structure
            $structure = [
                'key' => $this->keyField,
                'value' => $this->datasetFields
            ];
        }

        // check any extra requirements
        if (is_array($requirements)) {
            $missing = [];
            foreach ($requirements as $req) {
                if (!isset($structure[$req])) {
                    $missing[] = $req;
                }
            }
            if (!empty($missing)) {
                $missing = implode(', ', $missing);
                $this->error = "Required field(s) [{$missing}] not set in data structure";
                return;
            }
        }

        $this->structure = $structure;
        // check if it really has more than one dataset
        if (isset($structure['datasets']) && $structure['datasets'] && is_array(current($data)) && is_array(current(current($data)))) {
            $this->scatter2DDatasets($data);
        } else {
            $this->data = &$data;
        }

        $this->dataSetsCount = count($this->datasetFields);
        $this->forceAssoc = $forceAssoc;
        $this->assocTest = $integerKeys ? 'is_int' : 'is_numeric';

        if ($datetimeKeys || $this->associativeKeys()) {
            // reindex the array to 0, 1, 2, ...
            $this->data = array_values($this->data);
            if ($datetimeKeys) {
                if ($rekeyDone || $this->Rekey('SVGGraphDateConvert')) {
                    $this->isDatetime = true;
                    $this->isAssoc = false;
                } else {
                    $this->error = 'Too many date/time conversion errors';
                    return;
                }
                $GLOBALS['SVGGraphFieldSortField'] = $this->keyField;
                usort($this->data, 'SVGGraphFieldSort');
            }
        } elseif (!is_null($this->keyField)) {
            // if not associative, sort by key field
            $GLOBALS['SVGGraphFieldSortField'] = $this->keyField;
            usort($this->data, 'SVGGraphFieldSort');
        }

        if ($this->repeatedKeys()) {
            if ($repeatedKeys == 'force_assoc') {
                $this->forceAssoc = true;
            } elseif ($repeatedKeys != 'accept') {
                $this->error = 'Repeated keys in data';
            }
        }
    }

    /**
     * Sets up normal structured data from scatter_2d datasets
     */
    private function scatter2DDatasets(&$data)
    {
        $newdata = [];
        $key_field = $this->structure['key'];
        $value_field = $this->structure['value'][0];

        // update structure
        $this->structure['key'] = 0;
        $this->structure['value'] = [];
        $this->keyField = 0;
        $this->datasetFields = [];
        $set = 1;
        foreach ($data as $dataset) {
            foreach ($dataset as $item) {
                if (isset($item[$key_field]) && isset($item[$value_field])) {
                    // no need to dedupe keys - no extra data and scatter_2d
                    // only supported by scatter graphs
                    $newdata[] = [0 => $item[$key_field], $set => $item[$value_field]];
                }
            }
            $this->structure['value'][] = $set;
            $this->datasetFields[] = $set;
            ++$set;
        }
        $this->data = $newdata;
    }

    /**
     * ArrayAccess methods
     */
    public function offsetExists($offset)
    {
        return array_key_exists($offset, $this->datasetFields);
    }

    public function offsetGet($offset)
    {
        return new DataIterator($this->data, $offset, $this->structure);
    }

    /**
     * Don't allow writing to the data
     */
    public function offsetSet($offset, $value)
    {
        throw new \Exception('Read-only');
    }

    public function offsetUnset($offset)
    {
        throw new \Exception('Read-only');
    }

    /**
     * Countable method
     */
    public function count()
    {
        return $this->dataSetsCount;
    }

    /**
     * Returns minimum data value for a dataset
     */
    public function getMinValue($dataset = 0)
    {
        if (isset($this->minValuesByDataSets[$dataset])) {
            return $this->minValuesByDataSets[$dataset];
        }

        $min = null;
        $key = $this->datasetFields[$dataset];
        foreach ($this->data as $item) {
            if (isset($item[$key]) && (is_null($min) || $item[$key] < $min)) {
                $min = $item[$key];
            }
        }

        return ($this->minValuesByDataSets[$dataset] = $min);
    }

    /**
     * Returns maximum data value for a dataset
     */
    public function getMaxValue($dataset = 0)
    {
        if (isset($this->maxValuesByDataSets[$dataset])) {
            return $this->maxValuesByDataSets[$dataset];
        }

        $max = null;
        $key = $this->datasetFields[$dataset];
        foreach ($this->data as $item) {
            if (isset($item[$key]) && (is_null($max) || $item[$key] > $max)) {
                $max = $item[$key];
            }
        }

        return ($this->maxValuesByDataSets[$dataset] = $max);
    }

    /**
     * Returns the minimum key value
     */
    public function getMinKey($dataset = 0)
    {
        if (isset($this->minKeysByDataSets[$dataset])) {
            return $this->minKeysByDataSets[$dataset];
        }

        if ($this->associativeKeys()) {
            return ($this->minKeysByDataSets[$dataset] = 0);
        }

        $min = null;
        $key = $this->keyField;
        $set = $this->datasetFields[$dataset];
        if (is_null($key)) {
            foreach ($this->data as $k => $item) {
                if (isset($item[$set]) && (is_null($min) || $k < $min)) {
                    $min = $k;
                }
            }
        } else {
            foreach ($this->data as $item) {
                if (isset($item[$key]) && isset($item[$set]) &&
                    (is_null($min) || $item[$key] < $min)
                ) {
                    $min = $item[$key];
                }
            }
        }

        return ($this->minKeysByDataSets[$dataset] = $min);
    }

    /**
     * Returns the maximum key value for a dataset
     */
    public function getMaxKey($dataset = 0)
    {
        if (isset($this->maxKeysByDataSets[$dataset])) {
            return $this->maxKeysByDataSets[$dataset];
        }

        if ($this->associativeKeys()) {
            return ($this->maxKeysByDataSets[$dataset] = count($this->data) - 1);
        }

        $max = null;
        $key = $this->keyField;
        $set = $this->datasetFields[$dataset];
        if (is_null($key)) {
            foreach ($this->data as $k => $item) {
                if (isset($item[$set]) && (is_null($max) || $k > $max)) {
                    $max = $k;
                }
            }
        } else {
            foreach ($this->data as $item) {
                if (isset($item[$key]) && isset($item[$set]) &&
                    (is_null($max) || $item[$key] > $max)
                ) {
                    $max = $item[$key];
                }
            }
        }

        return ($this->maxKeysByDataSets[$dataset] = $max);
    }

    /**
     * Returns the key at a given index
     */
    public function getKey($index, $dataset = 0)
    {
        if (!$this->associativeKeys()) {
            return $index;
        }
        $index = (int)round($index);
        if (isset($this->data[$index])) {
            if (is_null($this->keyField)) {
                return $index;
            }
            $item = $this->data[$index];
            if (isset($item[$this->keyField])) {
                return $item[$this->keyField];
            }
        }
        return null;
    }

    /**
     * Returns TRUE if the keys are associative
     */
    public function associativeKeys()
    {
        if ($this->forceAssoc) {
            return true;
        }

        if (!is_null($this->isAssoc)) {
            return $this->isAssoc;
        }

        // use either is_int or is_numeric to test
        $test = $this->assocTest;
        if (is_null($this->keyField)) {
            foreach ($this->data as $k => $item) {
                if (!$test($k)) {
                    return ($this->isAssoc = true);
                }
            }
        } else {
            foreach ($this->data as $item) {
                if (isset($item[$this->keyField]) && !$test($item[$this->keyField])) {
                    return ($this->isAssoc = true);
                }
            }
        }
        return ($this->isAssoc = false);
    }

    /**
     * Returns the number of data items in a dataset
     * If $dataset is -1, returns number of items across all datasets
     */
    public function itemsCount($dataset = 0)
    {
        if ($dataset == -1) {
            return count($this->data);
        }

        if (!isset($this->datasetFields[$dataset])) {
            return 0;
        }
        $count = 0;
        $key = $this->datasetFields[$dataset];
        foreach ($this->data as $item) {
            if (isset($item[$key])) {
                ++$count;
            }
        }
        return $count;
    }

    /**
     * Returns TRUE if there are repeated keys
     * (also culls items without key field)
     */
    public function repeatedKeys()
    {
        if (!is_null($this->repeatedKeys)) {
            return $this->repeatedKeys;
        }
        if (is_null($this->keyField)) {
            return false;
        }
        $keys = [];
        foreach ($this->data as $k => $item) {
            if (!isset($item[$this->keyField])) {
                unset($this->data[$k]);
            } else {
                $keys[] = $item[$this->keyField];
            }
        }
        $len = count($keys);
        $ukeys = array_unique($keys);
        return ($this->repeatedKeys = ($len != count($ukeys)));
    }

    /**
     * Returns the min and max sum values for some datasets
     */
    public function getMinMaxSumValues($start = 0, $end = null)
    {
        if ($start >= $this->dataSetsCount || (!is_null($end) && $end >= $this->dataSetsCount)) {
            throw new \Exception('Dataset not found');
        }

        if (is_null($end)) {
            $end = $this->dataSetsCount - 1;
        }
        $min_stack = [];
        $max_stack = [];

        foreach ($this->data as $item) {
            $smin = $smax = 0;
            for ($dataset = $start; $dataset <= $end; ++$dataset) {
                $vfield = $this->datasetFields[$dataset];
                if (!isset($item[$vfield])) {
                    continue;
                }
                $value = $item[$vfield];
                if (!is_null($value) && !is_numeric($value)) {
                    throw new \Exception('Non-numeric value');
                }
                if ($value > 0) {
                    $smax += $value;
                } else {
                    $smin += $value;
                }
            }
            $min_stack[] = $smin;
            $max_stack[] = $smax;
        }
        if (!count($min_stack)) {
            return [null, null];
        }
        return [min($min_stack), max($max_stack)];
    }

    /**
     * Returns TRUE if the data field exists, setting $value
     */
    public function getData($index, $name, &$value)
    {
        if (!isset($this->structure[$name])) {
            return false;
        }

        $index = (int)round($index);
        $dataset = 0;
        $item = isset($this->data[$index]) ? $this->data[$index] : null;
        $field = $this->structure[$name];
        if (is_null($item) || !isset($item[$field])) {
            return false;
        }
        $value = $item[$field];
        return true;
    }

    /**
     * Transforms the keys using a callback function
     */
    public function rekey($callback)
    {
        // use a tab character as the new key name
        $rekey_name = "\t";
        $invalid = 0;
        foreach ($this->data as $index => $item) {
            $key = $item[$this->keyField];
            $new_key = call_user_func($callback, $key);

            // if the callback returns NULL, NULL the data item
            if (is_null($new_key)) {
                $this->data[$index] = [$rekey_name => null];
                ++$invalid;
            } else {
                $this->data[$index][$rekey_name] = $new_key;
            }
        }

        // if too many invalid, probably a format error
        if (count($this->data) && $invalid / count($this->data) > 0.05) {
            return false;
        }

        // forget previous min/max and assoc settings
        $this->minKeysByDataSets = [];
        $this->maxKeysByDataSets = [];
        $this->isAssoc = null;
        $this->keyField = $this->structure['key'] = $rekey_name;

        return true;
    }
}
