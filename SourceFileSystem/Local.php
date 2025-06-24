<?php namespace SourceFilesystem;

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
    use ProcessedLogTrait;

    private Client $client;

    public function __construct(protected array $config = [])
    {

    }


    public function traverse(string $root = null): \Generator
    {
        // take of where we left off by skipping the already processed directories
        $this->loadProcessedItemLog();

        if (!is_dir($root)) {
            throw new InvalidArgumentException("The provided path is not a directory: $root");
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(
                $root,
                FilesystemIterator::SKIP_DOTS
            ),
            RecursiveIteratorIterator::SELF_FIRST // also include the directories
        );

        foreach ($iterator as $item) {


            $logKey = $item->getPath();

            if ($item->isFile()) {
               $logKey .= '/' . $item->getFilename();
            }

            if ($this->itemProcessed($logKey)) {
                echo "\tSkipping (already processed)\n";
                continue;
            }

            try {
                $path = str_replace('\\', '/', $item->getPath());

                if ($this->config['debug'] ?? false) {
                    echo $path . '/' . $item->getFilename() . PHP_EOL;
                }

                if($item->isFile()) {
                    yield new File(
                        filename: $item->getFilename(),
                        absolutePath: $path,
                        relativePath: substr($path, strlen($root) + 1),
                    );
                }
            } catch (Exception $e) {
                echo 'Warning: ' . $e->getMessage() . PHP_EOL;
            }

            $this->addItemToProcessedLog($logKey);
        }
    }

    public function getContent(File $file): string
    {
        return file_get_contents($file->absolutePath . '/' . $file->filename);
    }
}