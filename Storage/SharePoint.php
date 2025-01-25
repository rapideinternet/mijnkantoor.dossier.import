<?php namespace Storage;

use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;

class SharePoint implements FilesystemContract
{

    protected $accessToken;
    protected $tokenExpiresAt;

    use ProcessedLogTrait;

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

        $folderId = $this->getFolderIdFromPath($driveId, $root);

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
            // prefix path with / if not already and not root
            if ($relativePath == '/') {
                $relativePath = '';
            }
            elseif ($relativePath[0] !== '/'){
                $relativePath = ':/' . $relativePath;
            }

            // Replace spaces with %20 for URL encoding
            $encodedPath = str_replace(' ', '%20', $relativePath);

            // Resolve the folder ID by path
            $response = $this->call("get", "drives/$driveId/root$encodedPath");
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
}