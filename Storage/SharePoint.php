<?php namespace Storage;

use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;

class SharePoint implements FilesystemContract
{

    protected $accessToken;
    protected $tokenExpiresAt;

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
     * @param string|null $folder
     * @return \Generator
     * @throws GuzzleException
     */
    public function traverse(string $folder = null): \Generator
    {
        $siteId = $this->config['site_id'] ?? null;
        $debug = $this->config['debug'] ?? false;

        $driveId = $this->getDriveId($siteId);

        if ($folder !== '/') {
            $folderId = $this->getFolderIdFromPath($driveId, $folder);
        }

        $queue = [$folderId ?? 'root']; // Start from the root if folderId is not provided

        while (!empty($queue)) {
            $currentFolder = array_shift($queue);
            $response = $this->call('get', "drives/$driveId/items/$currentFolder/children");
            $items = json_decode($response->getBody()->getContents(), true)['value'];

            foreach ($items as $item) {
                // get the full path left of the root: path
                $dir = explode('root:', $item['parentReference']['path'])[1];

                if ($debug) {
                    echo $dir . PHP_EOL;
                }

                if ($item['folder'] ?? false) {
                    // skip folders with 0 child count
                    if ($item['folder']['childCount'] === 0) {
                        continue;
                    }

                    // dont queue when folder is in blacklist
                    if (in_array($dir, $this->config['blacklist'] ?? [])) {
                        continue;
                    }

                    $queue[] = $item['id'];
                } else {
                    $file = new File(
                        filename: $item['name'],
                        absolutePath: $dir,
                        relativePath: substr($dir, strlen($folder) + 1),
                        id: $item['id'],
                    );

                    yield ($file);
                }
            }
        }
    }

    /* @description List items and convert to array of File or Directory objects
     * @param string|null $folder
     * @return \Generator
     * @throws GuzzleException
     */
    public function list(string $folder = null): \Generator
    {
        $siteId = $this->config['site_id'] ?? null;
        $driveId = $this->getDriveId($siteId);

        if ($folder !== '/') {
            $folderId = $this->getFolderIdFromPath($driveId, $folder);
        }

        $response = $this->call("get","drives/$driveId/items/$folderId/children");
        $items = json_decode($response->getBody()->getContents(), true)['value'];

        foreach ($items as $item) {
            $dir = explode('root:', $item['parentReference']['path'])[1];

            if ($item['folder'] ?? false) {
                $entry = new Directory(
                    dirname: $item['name'],
                    absolutePath: $dir,
                    relativePath: substr($dir, strlen($folder) + 1)
                );
            } else {
                $entry = new File(
                    filename: $item['name'],
                    absolutePath: $dir,
                    relativePath: substr($dir, strlen($folder) + 1),
                    id: $item['id'],
                );
            }

            yield ($entry);
        }
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

        $response = $this->call("get", "drives/$driveId/items/{$file->id}/content");
        return $response->getBody()->getContents();
    }
}