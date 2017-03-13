<?php

namespace SVGGraph\Data;

use SVGGraph\Concern\NoIterator;
use SVGGraph\Graph\Graph;

/**
 * Class for standard data
 */
class Data implements \Countable, \ArrayAccess, \Iterator
{
    use NoIterator;

    private $dataSetsCount = 0;
    private $data;

    private $isAssoc = null;
    private $isDatetime = null;

    private $minValuesByDataSets = [];
    private $maxValuesByDataSets = [];
    private $minKeysByDataSets = [];
    private $maxKeysByDataSets = [];

    public $error = null;

    public function __construct($data, $forceAssoc, $datetimeKeys)
    {
        if (empty($data[0])) {
            $this->error = 'No data';
            return;
        }
        $this->data = $data;
        $this->dataSetsCount = count($data);
        if ($forceAssoc) {
            $this->isAssoc = true;
        }
        if ($datetimeKeys) {
            if ($this->rekey('SVGGraphDateConvert')) {
                $this->isDatetime = true;
                $this->isAssoc = false;
            } else {
                $this->error = 'Too many date/time conversion errors';
            }
        }
    }

    /**
     * ArrayAccess methods
     * @param mixed $offset
     * @return bool
     */
    public function offsetExists($offset)
    {
        return array_key_exists($offset, $this->data);
    }

    public function offsetGet($offset)
    {
        return new DataIterator($this->data, $offset);
    }

    /**
     * Don't allow writing to the data
     * @param mixed $offset
     * @param mixed $value
     * @throws \Exception
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
     * @param int $dataSetIndex
     * @return
     */
    public function getMinValue($dataSetIndex = 0)
    {
        if (!isset($this->minValuesByDataSets[$dataSetIndex])) {
            if (count($this->data[$dataSetIndex])) {
                $this->minValuesByDataSets[$dataSetIndex] = Graph::min($this->data[$dataSetIndex]);
            } else {
                $this->minValuesByDataSets[$dataSetIndex] = null;
            }
        }
        return $this->minValuesByDataSets[$dataSetIndex];
    }

    /**
     * Returns maximum data value for a dataset
     * @param int $dataSetIndex
     * @return
     */
    public function getMaxValue($dataSetIndex = 0)
    {
        if (!isset($this->maxValuesByDataSets[$dataSetIndex])) {
            if (count($this->data[$dataSetIndex])) {
                $this->maxValuesByDataSets[$dataSetIndex] = max($this->data[$dataSetIndex]);
            } else {
                $this->maxValuesByDataSets[$dataSetIndex] = null;
            }
        }
        return $this->maxValuesByDataSets[$dataSetIndex];
    }

    /**
     * Returns the minimum key value
     * @param int $dataSetIndex
     * @return
     */
    public function getMinKey($dataSetIndex = 0)
    {
        if (!isset($this->minKeysByDataSets[$dataSetIndex])) {
            if (count($this->data[$dataSetIndex])) {
                $this->minKeysByDataSets[$dataSetIndex] = $this->associativeKeys() ? 0 :
                    min(array_keys($this->data[$dataSetIndex]));
            } else {
                $this->minKeysByDataSets[$dataSetIndex] = null;
            }
        }
        return $this->minKeysByDataSets[$dataSetIndex];
    }

    /**
     * Returns the maximum key value
     * @param int $dataSetIndex
     * @return
     */
    public function getMaxKey($dataSetIndex = 0)
    {
        if (!isset($this->maxKeysByDataSets[$dataSetIndex])) {
            if (count($this->data[$dataSetIndex])) {
                $this->maxKeysByDataSets[$dataSetIndex] = $this->associativeKeys() ?
                    count($this->data[$dataSetIndex]) - 1 :
                    max(array_keys($this->data[$dataSetIndex]));
            } else {
                $this->maxKeysByDataSets[$dataSetIndex] = null;
            }
        }
        return $this->maxKeysByDataSets[$dataSetIndex];
    }

    /**
     * Returns the key at a given index
     * @param     $index
     * @param int $dataSetIndex
     * @return int|null|string
     */
    public function getKey($index, $dataSetIndex = 0)
    {
        if (!$this->associativeKeys()) {
            return $index;
        }

        // round index to nearest integer, or PHP will floor() it
        $index = (int) round($index);
        if ($index >= 0) {
            $slice = array_slice($this->data[$dataSetIndex], $index, 1, true);
            // use foreach to get key and value
            foreach ($slice as $k => $v) {
                return $k;
            }
        }
        return null;
    }

    /**
     * Returns TRUE if the keys are associative
     */
    public function associativeKeys()
    {
        if (!is_null($this->isAssoc)) {
            return $this->isAssoc;
        }

        foreach (array_keys($this->data[0]) as $k) {
            if (!is_integer($k)) {
                return ($this->isAssoc = true);
            }
        }
        return ($this->isAssoc = false);
    }

    /**
     * Returns the number of data items
     * @param int $dataSetIndex
     * @return int
     */
    public function itemsCount($dataSetIndex = 0)
    {
        if ($dataSetIndex < 0) {
            $dataSetIndex = 0;
        }
        return count($this->data[$dataSetIndex]);
    }

    /**
     * Returns the min and max sum values
     * @param int  $start
     * @param null $end
     * @return array
     * @throws \Exception
     */
    public function getMinMaxSumValues($start = 0, $end = null)
    {
        if ($start != 0 || (!is_null($end) && $end != 0)) {
            throw new \Exception('Dataset not found');
        }

        // structured data is used for multi-data, so just
        // return the min and max
        return [$this->getMinValue(), $this->getMaxValue()];
    }

    /**
     * Returns TRUE if the item exists, setting the $value
     */
    public function getData($index, $name, &$value)
    {
        // base class doesn't support this, so always return false
        return false;
    }

    /**
     * Transforms the keys using a callback function
     * @param $callback
     * @return bool
     */
    public function rekey($callback)
    {
        $newData = [];
        $count = $invalid = 0;
        for ($d = 0; $d < $this->dataSetsCount; ++$d) {
            $newData[$d] = [];
            foreach ($this->data[$d] as $key => $value) {
                $new_key = call_user_func($callback, $key);

                // if the callback returns NULL, skip the value
                if (!is_null($new_key)) {
                    $newData[$d][$new_key] = $value;
                } else {
                    ++$invalid;
                }
            }
            ++$count;
        }

        // if too many invalid, probably a format error
        if ($count && $invalid / $count > 0.05) {
            return false;
        }

        $this->data = $newData;
        // forget previous min/max
        $this->minKeysByDataSets = [];
        $this->maxKeysByDataSets = [];

        return true;
    }
}

