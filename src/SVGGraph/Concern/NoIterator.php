<?php

namespace SVGGraph\Concern;

trait NoIterator
{
    /**
     * Implement Iterator interface to prevent iteration...
     */
    private function notIterator()
    {
        throw new \Exception("Cannot iterate " . __CLASS__);
    }

    public function current()
    {
        $this->notIterator();
    }

    public function key()
    {
        $this->notIterator();
    }

    public function next()
    {
        $this->notIterator();
    }

    public function rewind()
    {
        $this->notIterator();
    }

    public function valid()
    {
        $this->notIterator();
    }
}