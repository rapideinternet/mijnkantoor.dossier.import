<?php namespace Storage;

class File Implements FileSystemEntryContract {
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

    public function getRelativePath(): string
    {
        return $this->relativePath;
    }
}