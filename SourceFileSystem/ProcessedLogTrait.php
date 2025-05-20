<?php namespace SourceFilesystem;

trait ProcessedLogTrait
{
    protected array $processedItems = [];

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

    protected function getProcessedItemsLogPath(): string
    {
        global $argv;

        $scriptPath = $argv[0];              // e.g. "foo/bar.php"
        $relativeDir = dirname($scriptPath);

        return $relativeDir . '/processed_items.log';
    }

    protected function addItemToProcessedLog($key): void
    {
        // key can be a path or an id, so we need to hash it
        $key = md5($key);

        // also add to memory to avoid reading the file again
        $this->processedItems[$key] = time();

        // save to file
        file_put_contents($this->getProcessedItemsLogPath(), $key . "\n", FILE_APPEND);
    }

    public function itemProcessed($key): bool
    {
        $dir = md5($key);

        return isset($this->processedItems[$key]);
    }
}