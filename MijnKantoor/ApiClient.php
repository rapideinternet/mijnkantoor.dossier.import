<?php namespace MijnKantoor;

use GuzzleHttp\Client;

class ApiClient
{
    private MultiUploader $multiUploader;

    public function __construct(protected $config)
    {
        $required = ['access_token', 'tenant'];
        foreach ($required as $key) {
            if (!isset($this->config[$key])) {
                throw new \Exception("Missing required config key: $key");
            }
        }

        // This allows us to upload multiple files concurrently
        $this->multiUploader = new MultiUploader(
            url: $this->config['base_uri'] . '/dossier_items',
            headers: $this->getOptions()['headers'],
            maxConcurrency: $this->config['max_concurrency'] ?? 3,
        );
    }

    protected function getOptions(): array
    {
        return [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->config['access_token'],
                'Accept' => 'application/json',
                'X-Tenant' => $this->config['tenant'],
            ],
            'verify' => $this->config['verify_ssl'] ?? true,
        ];
    }

    public function call($method, $url, $data = [], $encode = 'json')
    {
        $client = new Client();
        $options = $this->getOptions();

        match ($encode) {
            'json' => $options['json'] = $data,
            'multipart' => $options['multipart'] = $data,
            default => throw new \Exception('Unknown encoding: ' . $encode),
        };

        $url = trim($this->config['base_uri'], '/') . '/' . trim($url, '/');

        $response = $client->request($method, $url, $options);

        return @json_decode($response->getBody()->getContents()) ?? null;
    }

    public function allDirectoriesWithParentAndPath($limit = 1000): array
    {
        // Fetch the directories
        $response = $this->call('get', '/dossier_directories?limit=' . $limit);

        // Convert data to an associative array for faster lookup
        $directories = $response->data ?? [];
        $directoryMap = [];
        foreach ($directories as $dir) {
            $directoryMap[$dir->id] = $dir;
        }

        // Closure to build paths
        $buildPath = function ($dirId) use ($directoryMap, &$buildPath) {
            $path = [];
            while (isset($directoryMap[$dirId])) {
                $dir = $directoryMap[$dirId];
                array_unshift($path, $dir->name);
                $dirId = $dir->parent_id; // Move to the parent
            }
            return implode('/', $path);
        };

        // Build the result list
        $result = [];
        foreach ($directories as $dir) {
            $path = $buildPath($dir->id);
            $result[strtolower($path)] = new DossierDirectory(
                id: $dir->id,
                parent_id: $dir->parent_id,
                name: $dir->name,
                path: $path,
            );
        }
        return $result;
    }

    public function uploadAsync(array $data): void
    {
        $multipartData = array_map(function ($key, $value) {
            return ['name' => $key, 'contents' => $value];
        }, array_keys($data), $data);

        $this->multiUploader->addRequest($multipartData);
    }

    public function finalizeUploads(): void
    {
        $this->multiUploader->finalize();
    }

    public function allCustomerByNumber($limit = 10000): array
    {
        $response = $this->call('get', '/customers?all=1&limit=' . $limit);

        foreach ($response->data as $customer) {
            // skip the ones without number
            if (!$customer->number) {
                continue;
            }

            $customers[$customer->number] = new Customer(
                id: $customer->id,
                name: $customer->name,
                number: $customer->number
            );
        }

        return $customers;
    }
}