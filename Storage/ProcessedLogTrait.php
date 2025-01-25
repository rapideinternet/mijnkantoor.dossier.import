<?php namespace Storage;

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
        return 'processed_items.log';
    }

    protected function addItemToProcessedLog($dir): void
    {
        // hash the dir to avoid long paths
        $dir = md5($dir);

        // also add to memory to avoid reading the file again
        $this->processedItems[$dir] = time();

        // save to file
        file_put_contents($this->getProcessedItemsLogPath(), $dir . "\n", FILE_APPEND);
    }

    public function itemProcessed($dir): bool
    {
        $dir = md5($dir);

        return isset($this->processedItems[$dir]);
    }
}