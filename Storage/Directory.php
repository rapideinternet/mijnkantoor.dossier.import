<?php namespace Storage;

class Directory implements FileSystemEntryContract
{
    public function __construct(
        public string $dirname,
        public string $absolutePath,
        public string $relativePath,
    )
    {

    }

    public function __toString()
    {
        return $this->relativePath . '/' . $this->dirname;
    }

    public function getRelativePath(): string
    {
        return $this->relativePath;
    }
}