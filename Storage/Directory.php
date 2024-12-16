<?php namespace Storage;

class Directory Implements FileSystemEntryContract {
    public function __construct(
        public string $dirname,
        public string $absolutePath,
        public string $relativePath,
    )
    {

    }

    public function __toString()
    {
        return $this->dirname;
    }

    public function getRelativePath(): string
    {
        return $this->relativePath;
    }
}