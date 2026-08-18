<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$projectRoot = realpath(dirname(__DIR__));
if ($projectRoot === false) {
    fwrite(STDERR, "Unable to resolve the project root.\n");
    exit(1);
}

$bootstrap = file_get_contents($projectRoot . '/einvoice_pro.php');
if ($bootstrap === false || !preg_match('/^Version:\s*([0-9]+\.[0-9]+\.[0-9]+)\s*$/m', $bootstrap, $matches)) {
    fwrite(STDERR, "Unable to read the module version.\n");
    exit(1);
}

$version = $matches[1];
$outputPath = $argv[1] ?? $projectRoot . '/dist/einvoice_pro-' . $version . '.zip';
$outputDirectory = dirname($outputPath);

if (!is_dir($outputDirectory) && !mkdir($outputDirectory, 0755, true) && !is_dir($outputDirectory)) {
    fwrite(STDERR, "Unable to create the package directory.\n");
    exit(1);
}

$includedFiles = [
    'einvoice_pro.php',
    'LICENSE',
    'README.md',
    'CHANGELOG.md',
];
$includedDirectories = [
    'assets',
    'controllers',
    'helpers',
    'language',
    'libraries',
    'migrations',
    'resources',
    'views',
];

foreach ($includedDirectories as $directory) {
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(
            $projectRoot . '/' . $directory,
            FilesystemIterator::SKIP_DOTS
        )
    );

    foreach ($iterator as $file) {
        if (!$file->isFile() || $file->isLink()) {
            continue;
        }

        $relativePath = substr($file->getPathname(), strlen($projectRoot) + 1);
        $includedFiles[] = str_replace(DIRECTORY_SEPARATOR, '/', $relativePath);
    }
}

$includedFiles = array_values(array_unique($includedFiles));
sort($includedFiles, SORT_STRING);

$archive = new ZipArchive();
if ($archive->open($outputPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    fwrite(STDERR, "Unable to create the package archive.\n");
    exit(1);
}

$fixedTimestamp = strtotime('2000-01-01T00:00:00Z');
foreach ($includedFiles as $relativePath) {
    $sourcePath = $projectRoot . '/' . $relativePath;
    if (!is_file($sourcePath)) {
        $archive->close();
        fwrite(STDERR, 'Missing package file: ' . $relativePath . PHP_EOL);
        exit(1);
    }

    $archivePath = 'einvoice_pro/' . $relativePath;
    if (!$archive->addFile($sourcePath, $archivePath)) {
        $archive->close();
        fwrite(STDERR, 'Unable to add package file: ' . $relativePath . PHP_EOL);
        exit(1);
    }

    $archive->setMtimeName($archivePath, $fixedTimestamp);
}

if (!$archive->close()) {
    fwrite(STDERR, "Unable to finalize the package archive.\n");
    exit(1);
}

$hash = hash_file('sha256', $outputPath);
if ($hash === false) {
    fwrite(STDERR, "Unable to hash the package archive.\n");
    exit(1);
}

fwrite(STDOUT, $outputPath . PHP_EOL);
fwrite(STDOUT, 'SHA-256 ' . $hash . PHP_EOL);
