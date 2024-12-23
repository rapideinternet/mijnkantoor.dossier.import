<?php

use MijnKantoor\MultiUploader;

require 'vendor/autoload.php';


// Example usage:
$uploader = new MultiUploader('http://pastebin.test?index', [], 5, 10);

for ($i = 0; $i < 12; $i++) {
    $uploader->addRequest(
        [
            [
                'name' => 'file',
                'contents' => "File contents for {$i}",
                'filename' => "file{$i}.txt"
            ]
        ],
    );
}

// Finalize remaining requests
$uploader->finalize();

