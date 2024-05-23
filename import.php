<?php

loadEnv('.env');

$bearerToken = getenv('BEARER_TOKEN');
$tenant = getenv('TENANT');
$baseURL = getenv('BASE_URL');
$rootDir = getenv('ROOT_DIR');
$dryRun = filter_var(getenv('DRY_RUN'), FILTER_VALIDATE_BOOLEAN);
$customerFolderPath = getenv('CUSTOMER_FOLDER_PATH');

$customersCache = [];
$storageCache = [];
$directoryContentsCache = [];

function loadEnv($file)
{
    if (!file_exists($file)) {
        throw new Exception("$file does not exist.");
    }

    $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) {
            continue;
        }
        list($key, $value) = explode('=', $line, 2);
        putenv(sprintf('%s=%s', trim($key), trim($value)));
    }
}

function fillCustomersCache()
{
    global $customersCache, $dryRun;

    if ($dryRun) {
        $customersCache = ['1234' => 'cust_1', '5678' => 'cust_2'];
        return;
    }

    $response = makeApiCall('GET', '/customers?limit=10000');
    $customersCache = array_column(@json_decode($response, true)['data'], 'id', 'number');

    if (!$customersCache) {
        echo "Error fetching customers: " . $response . "\n";
        exit(1);
    }
}

function makeApiCall($method, $endpoint, $data = null)
{
    global $bearerToken, $tenant, $baseURL;

    $curl = curl_init();

    $options = [
        CURLOPT_URL => $baseURL . $endpoint,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
            'Authorization: Bearer ' . $bearerToken,
            'X-Tenant: ' . $tenant,
        ],
    ];

    if ($data) {
        $options[CURLOPT_POSTFIELDS] = $data;
    }

    curl_setopt_array($curl, $options);

    $response = curl_exec($curl);
    curl_close($curl);

    return $response;
}

function getCustomerId($customerNumber)
{
    global $customersCache;

    return $customersCache[$customerNumber] ?? null;
}

function createDirectoryAtMijnKantoor($dirName, $customerId, $parentId, $isLeaf = false)
{
    global $dryRun;

    echo "Creating directory '$dirName' at MijnKantoor under parent ID $parentId";

    if ($isLeaf) {
        echo " with isLeaf=true.\n";
    } else {
        echo " with isLeaf=false.\n";
    }

    if ($dryRun) {
        return 'mock_id_' . uniqid();
    }

    $data = [
        'name' => $dirName,
        'parent_id' => $parentId,
        'period_setting' => 'none',
        'is_leaf' => $isLeaf ? '1' : '0',
        'allow_non_global_children' => '1',
    ];

    $response = makeApiCall('POST', '/customers/' . $customerId . '/dossier_directories', $data);

    $result = @json_decode($response, true)['data']['id'] ?? null;

    if (!$result) {
        echo "Error creating directory: " . $response . "\n";
    }

    return $result;
}

function containsFilesAndDirs($dir)
{
    global $directoryContentsCache;

    if (isset($directoryContentsCache[$dir])) {
        return $directoryContentsCache[$dir];
    }

    $files = false;
    $dirs = false;
    foreach (new DirectoryIterator($dir) as $item) {
        if ($item->isDot()) {
            continue;
        }
        if ($item->isDir()) {
            $dirs = true;
        }
        if ($item->isFile()) {
            $files = true;
        }
        if ($files && $dirs) {
            $directoryContentsCache[$dir] = true;
            return true;
        }
    }
    $directoryContentsCache[$dir] = false;
    return false;
}

function uploadFileToMijnKantoor($customerId, $folderId, $filePath)
{
    global $bearerToken, $tenant, $dryRun;

    echo "Uploading file $filePath to MijnKantoor under customer ID $customerId and folder ID $folderId.\n";

    if ($dryRun) {
        return 'mock_upload_id_' . uniqid();
    }

    $data = [
        'customer_id' => $customerId,
        'dossier_directory_id' => $folderId,
        'name' => basename($filePath),
        'resource' => new CURLFILE($filePath),
        'is_public' => '0',
        'suppress_async' => '1',
        'resource_type' => 's3'
    ];

    $response = makeApiCall('POST', '/dossier_items?include=creator%2Cpipeline_transitions', $data);

    return @json_decode($response, true)['data'] ?? null;
}

function processCustomerFolder($path, $baseFolderPath)
{
    $customerNumber = getCustomerNumber(basename($path));
    $customerId = getCustomerId($customerNumber);

    if (!$customerId) {
        echo "Warning: Customer ID not found for customer number $customerNumber.\n";
        return;
    }

    $dirIterator = new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS);
    $iterator = new RecursiveIteratorIterator($dirIterator, RecursiveIteratorIterator::SELF_FIRST);

    foreach ($iterator as $item) {
        if ($item->isFile()) {
            $parentPath = getRelativePath($item->getPath(), $baseFolderPath);
            $isHybridDir = containsFilesAndDirs($item->getPath());

            echo $item->getPath() . " is hybrid: " . ($isHybridDir ? "true" : "false") . "\n";

            $folderId = handleDirectory($parentPath, $customerId, !$isHybridDir);

            if ($isHybridDir) {
                $parentId  = $folderId;
                $parentPath = getRelativePath($item->getPath() . '/Overige', $baseFolderPath);

                $folderId = getFromStorage($parentPath);
                if (!$folderId) {
                    $folderId = createDirectoryAtMijnKantoor('Overige', $customerId, $parentId, true);
                    putInStorage($parentPath, $folderId);
                }
            }

            uploadFileToMijnKantoor($customerId, $folderId, $item->getPathname());
        }
    }
}

function getCustomerNumber($folderName)
{
    preg_match('/^\d+/', $folderName, $matches);
    return $matches[0];
}

function getRelativePath($path, $base)
{
    return substr($path, strlen($base) + 1);
}

function handleDirectory($relativePath, $customerId, $forceLeaf = null)
{
    global $rootDir;

    $pathParts = explode(DIRECTORY_SEPARATOR, $relativePath);
    $currentPath = "";
    $parentId = $rootDir;
    $isFirstPart = true;

    foreach ($pathParts as $index => $part) {
        if ($isFirstPart) {
            $isFirstPart = false;
            $currentPath = $part;
            continue;
        }

        $currentPath = $currentPath ? $currentPath . DIRECTORY_SEPARATOR . $part : $part;

        $isLeaf = ($index === count($pathParts) - 1);
        if ($isLeaf && $forceLeaf !== null) {
            $isLeaf = $forceLeaf;
        }

        $folderId = getFromStorage($currentPath);
        if (!$folderId) {
            $folderId = createDirectoryAtMijnKantoor($part, $customerId, $parentId, $isLeaf);
            putInStorage($currentPath, $folderId);
        }
        $parentId = $folderId;
    }
    return $parentId;
}

function putInStorage($relativePath, $folderId)
{
    global $storageCache;

    $storageCache[$relativePath] = $folderId;
    file_put_contents("storage.txt", "$relativePath:$folderId\n", FILE_APPEND);
}

function getFromStorage($relativePath)
{
    global $storageCache;

    if (isset($storageCache[$relativePath])) {
        return $storageCache[$relativePath];
    }

    $storageFile = "storage.txt";
    if (!file_exists($storageFile)) {
        file_put_contents($storageFile, "");
        return null;
    }

    $lines = file($storageFile);
    foreach ($lines as $line) {
        list($path, $id) = explode(':', trim($line));
        if ($path == $relativePath) {
            $storageCache[$relativePath] = $id;
            return $id;
        }
    }
    return null;
}

fillCustomersCache();

$folders = [];

// Collect customer folders
foreach (new DirectoryIterator($customerFolderPath) as $folder) {
    if ($folder->isDir() && !$folder->isDot()) {
        $folders[] = $folder->getPathname();
    }
}

// Process folders in reverse order
foreach (array_reverse($folders) as $index => $folderPath) {
    echo "Processing folder $folderPath (index: $index)\n";
    processCustomerFolder($folderPath, $customerFolderPath);
}
