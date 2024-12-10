<?php namespace Storage;

class File {
    public function __construct(
        public $filename,
        public $absolutePath,
        public $relativePath,
        public $id = null // only for cloud storage
    )
    {

    }

    public function __toString()
    {
        return $this->relativePath . '/' . $this->filename;
    }
}