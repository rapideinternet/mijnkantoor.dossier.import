<?php namespace MijnKantoor;

use GuzzleHttp\Client;
use GuzzleHttp\Pool;

class MultiUploader
{
    private Client $client;
    private int $maxConcurrency;
    private array $pendingRequests = [];
    private array $failedRequests = [];
    private array $attemptCounts = [];
    protected int $maxRetryAttempts;
    private string $url;
    private string $method;
    private $headers;

    public function __construct(string $url = "", array $headers = [], int $maxConcurrency = 5, $maxRetryAttempts = 3)
    {
        $this->client = new Client();

        $this->url = $url;
        $this->method = 'post';
        $this->headers = $headers;

        $this->maxConcurrency = $maxConcurrency;
        $this->maxRetryAttempts = $maxRetryAttempts;
    }

    public function addRequest(array $data): void
    {
        $uniqueKey = $this->generateUniqueKey();
        $this->pendingRequests[$uniqueKey] = $this->createRequest($this->url, $this->method, $data, $this->headers);

        if (count($this->pendingRequests) >= $this->maxConcurrency) {
            $this->processPool();
        }
    }

    public function finalize(): void
    {
        while (!empty($this->pendingRequests) || !empty($this->failedRequests)) {
            $this->processPool();
        }
    }

    private function createRequest(string $url, string $method, array $data, array $headers): callable
    {
        return function () use ($url, $method, $data, $headers) {
            return $this->client->requestAsync(
                $method,
                $url,
                [
                    'multipart' => $data,
                    'headers' => $headers,
                    'verify' => false,
                    'timeout' => 60, // seconds
                    'connect_timeout' => 30, // seconds
                ],
            );
        };
    }

    private function processPool(): void
    {
        $pool = new Pool($this->client, $this->pendingRequests, [
            'concurrency' => $this->maxConcurrency,
            'fulfilled' => function ($response, $key) {
                echo "\t\tRequest {$key} completed\n";
            },
            'rejected' => function ($reason, $key) {
                echo "\t\tRequest {$key} failed\n";

                // remove new lines and add tab
                $message = str_replace("\n", "\n\t\t", $reason->getMessage());
                echo "\t\t" . $message . "\n";

                if (!isset($this->attemptCounts[$key])) {
                    $this->attemptCounts[$key] = 0;
                }

                $this->attemptCounts[$key]++;

                if ($this->attemptCounts[$key] < $this->maxRetryAttempts) {
                    $this->failedRequests[$key] = $this->pendingRequests[$key];
                    echo "\t\tRequeued request {$key} (attempt {$this->attemptCounts[$key]})\n";
                } else {
                    echo "\t\tRequest {$key} failed after {$this->attemptCounts[$key]} attempts\n";
                }
            },
        ]);

        $promise = $pool->promise();
        $promise->wait();

        $this->pendingRequests = $this->failedRequests;
        $this->failedRequests = [];
    }

    private function generateUniqueKey(): string
    {
        return uniqid('', true);
    }
}
