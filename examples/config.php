<?php
return [
    'root_path' => dirname(__DIR__) . DIRECTORY_SEPARATOR,
    'urls' => [
        'file' => 'http://localhost:8080/file.php',
        'file-partial' => 'http://localhost:8080/file-partial.php',
        'data' => 'http://localhost:8080/data.php',
        'data-partial' => 'http://localhost:8080/data-partial.php',
    ],
    'resources' => [
        'files' => [
            'mov' => [
                'basename' => 'video.mov',
                'filename' => 'test video.mov',
                'mime' => 'video/quicktime',
            ],
            'pdf' => [
                'basename' => 'document.pdf',
                'filename' => 'example.pdf',
                'mime' => 'application/pdf',
            ],
            'bin' => [
                'basename' => 'resource.bin',
                'filename' => 'resource.bin',
                'mime' => 'application/octet-stream',
            ],
            'sql' => [
                'basename' => 'database.sql',
                'filename' => 'database.sql',
                'mime' => true,
            ],
            'txt' => [
                'basename' => 'text.txt',
                'filename' => 'text.txt',
                'mime' => 'text/plain',
            ],
        ],
        'data' => [
            'content' => "lorem ipsum dolor sit amet lorem ipsum dolor sit amet lorem ipsum dolor sit amet",
            'filename' => 'data.txt',
            'mime' => 'text/plain',
            'etag' => '9faa0b435bc1eec824d2e54ca30deb04',
        ],
    ],
    'multipart' => [
        'boundary' => '3d6b6a416f9b5d3b',
        'mime' => 'text/plain',
    ],
];
