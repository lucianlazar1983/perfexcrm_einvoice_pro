<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$path = dirname(__DIR__) . '/resources/validation/manifest.json';
$manifest = json_decode((string) file_get_contents($path), true);
if (!is_array($manifest) || json_last_error() !== JSON_ERROR_NONE) {
    fwrite(STDERR, "The validation manifest is not valid JSON.\n");
    exit(1);
}

if (($manifest['schema'] ?? null) !== 1 || !isset($manifest['artifacts']) || !is_array($manifest['artifacts'])) {
    fwrite(STDERR, "The validation manifest structure is invalid.\n");
    exit(1);
}

foreach ($manifest['artifacts'] as $artifact) {
    if (!is_array($artifact)
        || !isset($artifact['name'], $artifact['version'], $artifact['source'], $artifact['sha256'], $artifact['license'])
        || !is_string($artifact['sha256'])
        || !preg_match('/^[a-f0-9]{64}$/D', $artifact['sha256'])
        || filter_var($artifact['source'], FILTER_VALIDATE_URL) === false
    ) {
        fwrite(STDERR, "The validation manifest contains an invalid artifact.\n");
        exit(1);
    }
}

fwrite(STDOUT, "E-Invoice RO validation manifest passed.\n");
