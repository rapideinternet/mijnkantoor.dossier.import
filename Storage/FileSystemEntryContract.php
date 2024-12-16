<?php namespace Storage;

interface FileSystemEntryContract {
    public function getRelativePath(): string;
}