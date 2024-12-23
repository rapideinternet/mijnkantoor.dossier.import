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

    protected $processedItems = [];

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

                yield new File(
                    filename: $item->getFilename(),
                    absolutePath: $path,
                    relativePath: substr($path, strlen($root) + 1),
                );
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

    protected function loadProcessedItemLog(): void
    {
        $path = $this->getProcessedItemsLogPath();

        if (!file_exists($path)) {
            $this->processedItems = [];
            return;
        }

        echo "Warning: Loading processed items log from previous run, processing will continue from where it left off\n";

        $this->processedItems = array_flip(explode("\n", trim(file_get_contents($path))));
    }

    protected function getProcessedItemsLogPath()
    {
        return 'processed_items.log';
    }

    protected function addItemToProcessedLog($dir): void
    {
        // also add to memory to avoid reading the file again
        $this->processedItems[$dir] = time();

        // save to file
        file_put_contents($this->getProcessedItemsLogPath(), $dir . "\n", FILE_APPEND);
    }

    public function itemProcessed($dir): bool
    {
        return isset($this->processedItems[$dir]);
    }
}