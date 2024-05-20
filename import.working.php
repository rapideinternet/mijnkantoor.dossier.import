<?php

$bearerToken = '...';
$tenant = 'jab9vr74pkr46e87';
$baseURL = 'https://v2.api.mijnkantoorapp.nl/v1';
$rootDir = 'vxakb5kmx6woqy9p';

$customersCache = [];
function fillCustomersCache()
{
    global $bearerToken, $tenant, $baseURL, $customersCache;

    $curl = curl_init();

    curl_setopt_array($curl, [
        CURLOPT_URL => $baseURL . '/customers?limit=10000',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_CUSTOMREQUEST => 'GET',
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
            'Authorization: Bearer ' . $bearerToken,
            'X-Tenant: ' . $tenant,
        ],
    ]);

    $response = curl_exec($curl);

    curl_close($curl);

    $customersCache = array_column(@json_decode($response, true)['data'], 'id', 'number');
}

// Placeholder function for translating customer number to customer ID
function getCustomerId($customerNumber)
{
    echo "Translating customer number $customerNumber to customer ID.\n";

    global $customersCache;

    return $customersCache[$customerNumber] ?? null;
}


// Placeholder for creating directory in MijnKantoor
function createDirectoryAtMijnKantoor($dirName, $customerId, $parentId, $isLeaf = false)
{
    global $bearerToken, $tenant, $baseURL;

    echo "Creating directory '$dirName' at MijnKantoor under parent ID $parentId";

    if ($isLeaf) {
        echo " with isLeaf=true.\n";
    } else {
        echo " with isLeaf=false.\n";
    }

    $curl = curl_init();

    $data = [
        'name' => $dirName,
        'parent_id' => $parentId,
        'period_setting' => 'none',
        'is_leaf' => $isLeaf ? '1' : '0',
        'allow_non_global_children' => '1',
    ];

    curl_setopt_array($curl, [
        CURLOPT_URL => $baseURL . '/customers/' . $customerId . '/dossier_directories',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => $data,
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
            'Authorization: Bearer ' . $bearerToken,
            'X-Tenant: ' . $tenant,
        ],
    ]);

    $response = curl_exec($curl);

    curl_close($curl);

    $result = @json_decode($response, true)['data']['id'] ?? null;

    if(!$result)
    {
        echo "Error creating directory: " . $response . "\n";
    }

    return $result;
}

function containsFilesAndDirs($dir)
{
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
            return true;
        }
    }
    return false;
}

// Upload file to MijnKantoor
function uploadFileToMijnKantoor($customerId, $folderId, $filePath)
{
    global $bearerToken, $tenant;

    echo "Uploading file $filePath to MijnKantoor under customer ID $customerId and folder ID $folderId.\n";

    $curl = curl_init();

    curl_setopt_array($curl, [
        CURLOPT_URL => 'https://v2.api.mijnkantoorapp.nl/v1/dossier_items?include=creator%2Cpipeline_transitions',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => [
            'customer_id' => $customerId,
            'dossier_directory_id' => $folderId,
            'name' => basename($filePath),
            'resource' => new CURLFILE($filePath),
            'is_public' => '0',
            'suppress_async' => '1',
            'resource_type' => 's3'
        ],
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
            'Authorization: Bearer ' . $bearerToken,
            'X-Tenant: ' . $tenant
        ],
    ]);

    $response = curl_exec($curl);

    curl_close($curl);

    return @json_decode($response, true)['data'] || null;
}

// Recursive directory traversal and processing
function processCustomerFolder($path, $baseFolderPath)
{
    $customerNumber = getCustomerNumber(basename($path));
    $customerId = getCustomerId($customerNumber);

    if($customerNumber !== '8168')
    {
        return;
    }

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

            $folderId = handleDirectory($parentPath, $customerId, !$isHybridDir);  // Ensure we have an ID for the parent directory

            // if the folder contains both files and directories, we need to create a directory in MijnKantoor to store the files
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

// Extract customer number from folder name
function getCustomerNumber($folderName)
{
    preg_match('/^\d+/', $folderName, $matches);
    return $matches[0];
}

// Get the relative path of a directory based on the base customer folder path
function getRelativePath($path, $base)
{
    return substr($path, strlen($base) + 1);  // Remove the base path and leading slash
}

// Handle directory creation/check in MijnKantoor and store/retrieve ID
function handleDirectory($relativePath, $customerId, $forceLeaf = null)
{
    global $rootDir;

    // Split the path into parts to handle parent directories
    $pathParts = explode(DIRECTORY_SEPARATOR, $relativePath);
    $currentPath = "";
    $parentId = $rootDir; // Start from the root directory

    // Skip the first part as it is the customer root directory
    $isFirstPart = true;

    foreach ($pathParts as $index => $part) {
        if ($isFirstPart) {
            $isFirstPart = false;
            $currentPath = $part;
            continue;
        }

        if ($currentPath === "") {
            $currentPath = $part;
        } else {
            $currentPath .= DIRECTORY_SEPARATOR . $part;
        }

        $isLeaf = ($index === count($pathParts) - 1);

        if($isLeaf && $forceLeaf !== null)
        {
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

// Store folder ID with its path in storage
function putInStorage($relativePath, $folderId)
{
    $storageFile = "storage.txt";
    $content = "$relativePath:$folderId\n";
    file_put_contents($storageFile, $content, FILE_APPEND);
}

// Retrieve folder ID from storage
function getFromStorage($relativePath)
{
    $storageFile = "storage.txt";
    if (!file_exists($storageFile)) {
        file_put_contents($storageFile, "");  // Create storage file if it doesn't exist
        return null;
    }
    $lines = file($storageFile);
    foreach ($lines as $line) {
        list($path, $id) = explode(':', trim($line));
        if ($path == $relativePath) {
            return $id;
        }
    }
    return null;
}

fillCustomersCache();

// Example of usage
$customerFolderPath = "q:\Naar MijnKantoor";
$folders = [];

foreach (new DirectoryIterator($customerFolderPath) as $folder) {
    if ($folder->isDir() && !$folder->isDot()) {
        $folders[] = $folder->getPathname();
    }
}

// Process in reverse order
$index = 0;
$startFromIndex = 0;
foreach (array_reverse($folders) as $index => $folderPath) {
    // start from
    if ($index < $startFromIndex) {
        continue;
    }

    echo "Processing folder $folderPath ($index)\n";

    processCustomerFolder($folderPath, $customerFolderPath);
}
