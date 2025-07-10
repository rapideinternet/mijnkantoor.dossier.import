<?php namespace MijnKantoor;

use Carbon\Carbon;
use GuzzleHttp\Client;

class ApiClient
{
    protected MultiUploader $multiUploader;
    protected array $cache = [];

    public function __construct(protected $config)
    {
        $required = ['access_token', 'tenant', 'base_uri'];
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

    public function call($method, $url, $data = [], $encode = 'json', $retries = 1, $delaySeconds = 1)
    {
        $client = new Client();
        $options = $this->getOptions();

        match ($encode) {
            'json' => $options['json'] = $data,
            'multipart' => $options['multipart'] = $data,
            default => throw new \Exception('Unknown encoding: ' . $encode),
        };

        $url = trim($this->config['base_uri'], '/') . '/' . trim($url, '/');

        $attempt = 0;
        do {
            try {
                $response = $client->request($method, $url, $options);

                // Check for non-JSON response early
                if (!str_contains($response->getHeader('Content-Type')[0], 'application/json')) {
                    return $response->getBody()->getContents();
                }

                return @json_decode($response->getBody()->getContents()) ?? null;

            } catch (\Exception $e) {
                $attempt++;

                // Critical errors: don't retry
                if ($e->getCode() === 401 || $e->getCode() === 403) {
                    throw new \Exception("Unauthorized. Please update the access token.");
                }

                // Retryable error: retry if attempts remain
                if ($attempt >= $retries) {
                    throw new \Exception("Failed to call $method $url after $retries attempts: " . $e->getMessage());
                }

                // Optional: sleep before retry
                sleep($delaySeconds);
            }
        } while ($attempt < $retries);

        // Shouldn't reach here
        return null;
    }


    public function allDirectoriesWithParentAndPath($limit = 1000, $byId = false, $customerId = null): array
    {
        $cacheKey = 'directories_' . $customerId. '_' . $limit;

        // Fetch from cache if available
        if (isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }

        // Fetch the directories
        if ($customerId) {
            $response = $this->call('get', '/customers/' . $customerId . '/dossier_directories?limit=' . $limit);
        } else {
            $response = $this->call('get', '/dossier_directories?limit=' . $limit);
        }


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

            $result[strtolower($byId ? $dir->id : $path)] = new DossierDirectory(
                id: $dir->id,
                parent_id: $dir->parent_id,
                is_leaf: $dir->is_leaf,
                name: $dir->name,
                path: $path,
            );
        }

        // Cache the result
        $this->cache[$cacheKey] = $result;

        return $result;
    }

    public function uploadAsync(array $data): void
    {
        // convert data to multipart format
        $multipartData = array_map(function ($key, $value) {
            return ['name' => $key, 'contents' => $value, 'filename' => $key === 'resource' ? 'file' : null];
        }, array_keys($data), $data);

        $this->multiUploader->addRequest($multipartData);
    }

    public function finalizeUploads(): void
    {
        $this->multiUploader->finalize();
    }

    public function allCustomerByKey($key, $limit = 10000): array
    {
        $cacheKey = 'customers_' . $key . '_' . $limit;

        // Fetch from cache if available
        if (isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }

        $response = $this->call('get', '/customers?all=1&limit=' . $limit);

        $customers = [];

        foreach ($response->data as $customer) {
            // skip the ones without number
            if (!$customer->number) {
                continue;
            }

            if (!in_array($customer->type, ['business', 'person'])) {
                continue;
            }

            $customer->number = ltrim($customer->number, '0');

            // give warning if key already exists
            if (isset($customers[$customer->$key])) {
                throw new \Exception("Duplicate customer key found: " . $customer->$key);
            }

            $customers[$customer->$key] = new Customer(
                id: $customer->id,
                name: $customer->name,
                number: $customer->number,
            );
        }

        // Cache the result
        $this->cache[$cacheKey] = $customers;

        return $customers;
    }

    public function allCustomerByNumber($limit = 10000)
    {
        return $this->allCustomerByKey('number', $limit);
    }

    public function createCustomer($data)
    {
        $this->call('post', '/customers', $data);
    }

    public function allDossierItemsByCustomer($customerId)
    {
        $page = 0;
        $limit = 100;

        do {
            $url = "/search/dossier_items?customer_id={$customerId}&show_children=1&page={$page}&limit={$limit}&orderBy=created_at&sortedBy=desc";

            $response = $this->call('get', $url);

            foreach ($response->data ?? [] as $item) {
                yield new DossierItem(
                    id: $item->id,
                    original_filename: $item->original_filename,
                    name: $item->name,
                    dossier_directory_id: $item->dossier_directory_id,
                    customer_id: $item->customer_id,
                    created_at: Carbon::parse($item->created_at),
                    year: $item->year,
                    period: $item->period,
                    parentId: $item->parent_id ?? null,
                );
            }

            $paginationLinks = $response->meta->pagination->links->next ?? null;

            $page++;
        } while ($paginationLinks);
    }

    public function downloadDossierItem($id)
    {
        return $this->call('get', "/dossier_items/{$id}/download");
    }

    public function dossierItemExistsByCustomerNumberAndFilename(string $customerNumber, string $filename): bool
    {
        $customers = $this->allCustomerByKey('number');
        $customerId = $customers[$customerNumber]->id ?? null;

        if (!$customerId) {
            return false; // Customer not found
        }

        $query = urlencode($filename);
        $response = $this->call(
            method: 'get',
            url: "/search/dossier_items?customer_id={$customerId}&query={$query}",
            retries: 3
        );

        return isset($response->data) && count($response->data);
    }
}