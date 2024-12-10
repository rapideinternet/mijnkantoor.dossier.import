<?php namespace Storage;

use DirectoryIterator;
use Exception;
use FilesystemIterator;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use InvalidArgumentException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class Local implements FilesystemContract
{

    private Client $client;

    public function __construct(protected array $config = [])
    {

    }


    public function traverse(string $folder = null): \Generator
    {
        if (!is_dir($folder)) {
            throw new InvalidArgumentException("The provided path is not a directory: $folder");
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(
                $folder,
                FilesystemIterator::SKIP_DOTS
            )
        );

        foreach ($iterator as $item) {

            if($this->config['debug'] ?? false) {
                echo $item->getFilename() . PHP_EOL;
            }

            if ($item->isFile()) {
                yield new File(
                    filename: $item->getFilename(),
                    absolutePath: $item->getPath(),
                    relativePath: substr($item->getPath(), strlen($folder) + 1),
                );
            }
        }
    }

    public function list(string $folder = null): \Generator
    {
        if (!is_dir($folder)) {
            throw new InvalidArgumentException("The provided path is not a directory: $folder");
        }

        $iterator = new DirectoryIterator($folder);

        foreach ($iterator as $item) {
            if ($item->isDot()) {
                continue;
            }

            if ($item->isDir()) {
                yield new Directory(
                    dirname: $item->getFilename(),
                    absolutePath: $item->getPath(),
                    relativePath: substr($item->getPath(), strlen($folder) + 1),
                );
            }

            if ($item->isFile()) {
                yield new File(
                    filename: $item->getFilename(),
                    absolutePath: $item->getPath(),
                    relativePath: substr($item->getPath(), strlen($folder) + 1),
                );
            }
        }
    }

    public function getContent(File $file): string
    {
        return file_get_contents($file->absolutePath . '/' . $file->filename);
    }
}