<?php namespace Storage;

class Directory {
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
}