<?php namespace Storage;

use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;

class SharePoint implements FilesystemContract
{

    protected $accessToken;
    protected $tokenExpiresAt;
    /**
     * @var string[]
     */
    protected array $processedItems = [];

    public function __construct(protected array $config = [])
    {
        // @todo: validate config
    }

    /*
     * @description Fetches the access token of $config['expires_in'] is less than 5 minutes
     */
    protected function fetchTokenIfNeeded()
    {
        // Check if we have a token and if it will expire in less than 5 minutes
        if (!$this->accessToken || (time() >= $this->tokenExpiresAt - 300)) {
            // Fetch a new token
            $this->fetchNewToken();
        }

        return $this->accessToken;
    }

    protected function fetchNewToken()
    {
        $data = [
            'form_params' => [
                'client_id' => $this->config['client_id'],
                'client_secret' => $this->config['client_secret'],
                'grant_type' => 'client_credentials',
                'scope' => 'https://graph.microsoft.com/.default',
            ],
        ];

        // use a different client for token requests
        $client = new Client();

        try {
            $response = $client->post($this->config['token_url'], $data);
            $responseBody = json_decode($response->getBody(), true);

            if (isset($responseBody['error'])) {
                throw new Exception('Error fetching token: ' . $responseBody['error_description']);
            }

            $this->accessToken = $responseBody['access_token'];
            $this->tokenExpiresAt = time() + $responseBody['expires_in'];
        } catch (RequestException $e) {
            throw new Exception('Error fetching token: ' . $e->getMessage());
        }
    }

    protected function call($method, $uri, $data = null, $encoding = 'json')
    {
        $this->fetchTokenIfNeeded();

        $options = [
            'base_uri' => 'https://graph.microsoft.com/v1.0/',
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $this->accessToken,
            ]
        ];

        if ($data) {
            $options[$encoding === 'json' ? 'json' : 'form_params'] = $data;
        }

        $client = new Client($options);

        return $client->request($method, $uri, $options);
    }


    /**
     * @description Traverse the SharePoint drive and yield files not directories
     * @param string|null $root
     * @return \Generator
     * @throws GuzzleException
     */
    public function traverse(string $root = null): \Generator
    {
        $siteId = $this->config['site_id'] ?? null;
        $debug = $this->config['debug'] ?? false;

        $driveId = $this->getDriveId($siteId);

        // take of where we left off by skipping the already processed directories
        $this->loadProcessedItemLog();

        if ($root !== '/') {
            $folderId = $this->getFolderIdFromPath($driveId, $root);
        }

        yield from $this->fetchFilesRecursively($root, $driveId, $folderId);
    }

    protected function fetchFilesRecursively($root, $driveId, $folderId): \Generator
    {
        $filesUrl = "https://graph.microsoft.com/v1.0/drives/$driveId/items/$folderId/children";

        do {
            $response = $this->call('get', $filesUrl);
            $response = json_decode($response->getBody()->getContents(), true);

            foreach ($response['value'] as $item) {

                echo "Processing: " . $item['parentReference']['path'] . "/" . $item['name'] . "\n";

                if ($this->itemProcessed($item['id'])) {
                    echo "\tSkipping (already processed)\n";
                    continue;
                }

                if ($item['folder'] ?? false) {
                    // If it's a folder, recursively yield its contents
                    yield from $this->fetchFilesRecursively($root, $driveId, $item['id']);
                } else {
                    $dir = explode('root:', $item['parentReference']['path'])[1];

                    // If it's a file, yield it
                    yield new File(
                        filename: $item['name'],
                        absolutePath: $dir,
                        relativePath: substr($dir, strlen($root) + 1),
                        id: $item['id'],
                    );
                }

                $this->addItemToProcessedLog($item['id']);
            }

            // Check for next page
            $filesUrl = $response['@odata.nextLink'] ?? null;

        } while ($filesUrl);
    }
//
//    /* @description List items and convert to array of File or Directory objects
//     * @param string|null $folder
//     * @return \Generator
//     * @throws GuzzleException
//     */
//    public function list(string $folder = null): \Generator
//    {
//        $siteId = $this->config['site_id'] ?? null;
//        $driveId = $this->getDriveId($siteId);
//
//        if ($folder !== '/') {
//            $folderId = $this->getFolderIdFromPath($driveId, $folder);
//        }
//
//        $url = "drives/$driveId/items/$folderId/children";
//
//        do {
//            $response = $this->call("get", $url);
//            $data = json_decode($response->getBody()->getContents(), true);
//            $items = $data['value'];
//
//            foreach ($items as $item) {
//                $dir = explode('root:', $item['parentReference']['path'])[1];
//
//                if ($item['folder'] ?? false) {
//                    $entry = new Directory(
//                        dirname: $item['name'],
//                        absolutePath: $dir,
//                        relativePath: substr($dir, strlen($folder) + 1)
//                    );
//                } else {
//                    $entry = new File(
//                        filename: $item['name'],
//                        absolutePath: $dir,
//                        relativePath: substr($dir, strlen($folder) + 1),
//                        id: $item['id'],
//                    );
//                }
//
//                yield ($entry);
//            }
//
//            // Check for nextLink to continue pagination
//            $url = $data['@odata.nextLink'] ?? null;
//        } while ($url);
//    }

    /**
     * @throws GuzzleException
     */
    protected function getDriveId($siteId): string
    {
        $driveId = $this->config['drive_id'] ?? null;

        // Default drive if none provided
        if (is_null($driveId)) {
            $response = $this->call("get", "sites/$siteId/drives");
            $drives = json_decode($response->getBody()->getContents(), true);
            $driveId = $drives['value'][0]['id']; // Assuming the first drive is default
        }

        return $driveId;
    }

    /**
     * @param $driveId
     * @param $relativePath
     * @return string
     * @throws GuzzleException
     * @throws Exception
     */
    function getFolderIdFromPath($driveId, $relativePath): string
    {
        try {
            // prefix path with / if not already
            if ($relativePath[0] !== '/') {
                $relativePath = '/' . $relativePath;
            }

            // Replace spaces with %20 for URL encoding
            $encodedPath = str_replace(' ', '%20', $relativePath);

            // Resolve the folder ID by path
            $response = $this->call("get", "drives/$driveId/root:$encodedPath");
            $data = json_decode($response->getBody()->getContents(), true);

            // Return the folder ID
            return $data['id'];
        } catch (Exception $e) {
            throw new Exception("Error resolving path '$relativePath': " . $e->getMessage());
        }
    }

    public function getContent(File $file): string
    {
        $siteId = $this->config['site_id'] ?? null;
        $driveId = $this->getDriveId($siteId);

        // try catch try 3 times
        $tries = 0;
        do {
            try {
                $response = $this->call("get", "drives/$driveId/items/{$file->id}/content");
                return $response->getBody()->getContents();
            } catch (Exception $e) {
                $tries++;
            }
        } while ($tries < 3);

        throw new Exception("Error fetching content for file: {$file->filename}");
    }

    protected function getProcessedItemsLogPath()
    {
        return 'processed_items-' . $this->config['site_id'] . '.log';
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