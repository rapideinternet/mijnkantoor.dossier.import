<?php namespace SourceFilesystem;

class File implements FileSystemEntryContract
{
    public function __construct(
        public $filename,
        public $absolutePath, // this is the full path from the root of the filesystem
        public $relativePath, // this is relative to the root of the action
        public $id = null, // only for cloud storage
        public $createdAt = null,
        public $year = null,
        public $period = null,
        public $parentId = null, // only for cloud storage
    )
    {

    }

public
function __toString()
{
    $string = $this->relativePath . '/' . $this->filename;

    // add year and period if available
    if ($this->year) {
        $string .= ' (' . implode(',', [$this->year, $this->period ?? 'none']) . ')';
    }
    return $string;
}

public
function getRelativePath(): string
{
    return $this->relativePath;
}
}