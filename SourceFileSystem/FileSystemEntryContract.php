<?php namespace SourceFilesystem;

interface FileSystemEntryContract {
    public function getRelativePath(): string;
}