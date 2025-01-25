<?php namespace Storage;

class File Implements FileSystemEntryContract {
    public function __construct(
        public $filename,
        public $absolutePath, // this is the full path from the root of the filesystem
        public $relativePath, // this is relative to the root of the action
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