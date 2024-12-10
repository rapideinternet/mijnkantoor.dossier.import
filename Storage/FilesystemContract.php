<?php namespace Storage;

interface FilesystemContract {
    public function __construct(array $config = []);

    /**
     * Generator function to traverse the directory structure and return the path of each file.
     *
     * @param string $path
     * @return array
     */
    public function traverse(string $folder = null) : \Generator;

    /* @description List items and convert to array of File or Directory objects
     * @param string|null $folder
     * @return \Generator
     */
    public function list(string $folder = null) : \Generator;

    /**
     * @description Get the content of a file
     * @param File $file
     * @return string
     */
    public function getContent(File $file) : string;
}