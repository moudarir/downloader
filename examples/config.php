<?php

use Moudarir\File\Enum\MimeType;

return [
    'root_path' => dirname(__DIR__) . DIRECTORY_SEPARATOR,
    'urls' => [
        'file' => 'http://127.0.0.1:8080/file.php',
        'file-partial' => 'http://127.0.0.1:8080/file-partial.php',
        'data' => 'http://127.0.0.1:8080/data.php',
    ],
    'resources' => [
        'files' => [
            'mov' => [
                'basename' => 'video.mov',
                'filename' => 'test video.mov',
                'mime' => MimeType::MOV,
            ],
            'pdf' => [
                'basename' => 'document.pdf',
                'filename' => 'example.pdf',
                'mime' => MimeType::PDF,
            ],
            'bin' => [
                'basename' => 'resource.bin',
                'filename' => 'resource.bin',
                'mime' => MimeType::OCTET_STREAM,
            ],
            'txt' => [
                'basename' => 'text.txt',
                'filename' => 'text.txt',
                'mime' => MimeType::TEXT_PLAIN,
            ],
        ],
        'data' => [
            'content' => "lorem ipsum dolor sit amet lorem ipsum dolor sit amet lorem ipsum dolor sit amet",
            'filename' => 'data.txt',
            'mime' => MimeType::TEXT_PLAIN,
            'etag' => '9faa0b435bc1eec824d2e54ca30deb04',
        ],
    ],
    'multipart' => [
        'boundary' => '3d6b6a416f9b5d3b',
        'mime' => MimeType::TEXT_PLAIN,
    ],
];
