<?php

declare(strict_types=1);

$publicRoot = getenv('WORDLE_PUBLIC_ROOT');
if (!is_string($publicRoot) || '' === $publicRoot || !is_dir($publicRoot)) {
    http_response_code(503);
    header('Content-Type: text/plain; charset=utf-8');
    header('Cache-Control: no-store');
    echo "Preview output is unavailable.\n";

    return;
}

$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
if (!is_string($requestPath)) {
    $requestPath = '/';
}
$requestPath = rawurldecode($requestPath);
if (str_contains($requestPath, "\0") || str_contains($requestPath, '..') || !str_starts_with($requestPath, '/')) {
    serveError($publicRoot, 404);

    return;
}

if ('/wordle-answers/page/1/' === $requestPath) {
    header('Location: /wordle-answers/', true, 301);

    return;
}

if ('/' !== $requestPath && !str_ends_with($requestPath, '/') && '' === pathinfo($requestPath, PATHINFO_EXTENSION)) {
    $directoryCandidate = $publicRoot.str_replace('/', DIRECTORY_SEPARATOR, $requestPath);
    if (is_dir($directoryCandidate)) {
        $query = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_QUERY);
        header('Location: '.$requestPath.'/'.(is_string($query) && '' !== $query ? '?'.$query : ''), true, 301);

        return;
    }
}

$relative = '/' === $requestPath ? 'index.html' : ltrim($requestPath, '/');
if (str_ends_with($requestPath, '/')) {
    $relative .= 'index.html';
}
$candidate = $publicRoot.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative);
$rootReal = realpath($publicRoot);
$fileReal = realpath($candidate);
if (false === $rootReal || false === $fileReal || !isPathInsideRoot($fileReal, $rootReal) || !is_file($fileReal)) {
    serveError($publicRoot, 404);

    return;
}

serveFile($fileReal, $requestPath);

function isPathInsideRoot(string $path, string $root): bool
{
    $prefix = rtrim($root, '\\/').DIRECTORY_SEPARATOR;
    if ('\\' === DIRECTORY_SEPARATOR) {
        $path = strtolower($path);
        $prefix = strtolower($prefix);
    }

    return str_starts_with($path, $prefix);
}

function serveError(string $publicRoot, int $status): void
{
    http_response_code($status);
    $errorFile = $publicRoot.DIRECTORY_SEPARATOR.'404.html';
    $rootReal = realpath($publicRoot);
    $errorReal = realpath($errorFile);
    if (false !== $rootReal && false !== $errorReal && isPathInsideRoot($errorReal, $rootReal) && is_file($errorReal)) {
        serveFile($errorReal, '/404.html', $status);

        return;
    }
    header('Content-Type: text/plain; charset=utf-8');
    header('Cache-Control: no-store');
    echo "Not found.\n";
}

function serveFile(string $path, string $requestPath, int $status = 200): void
{
    http_response_code($status);
    $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    $contentTypes = [
        'css' => 'text/css; charset=utf-8',
        'html' => 'text/html; charset=utf-8',
        'js' => 'text/javascript; charset=utf-8',
        'json' => 'application/json; charset=utf-8',
        'txt' => 'text/plain; charset=utf-8',
        'woff2' => 'font/woff2',
        'xml' => 'application/xml; charset=utf-8',
    ];
    header('Content-Type: '.($contentTypes[$extension] ?? 'application/octet-stream'));
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: geolocation=(), camera=(), microphone=()');
    $modified = filemtime($path);
    if (false !== $modified) {
        header('Last-Modified: '.gmdate('D, d M Y H:i:s', $modified).' GMT');
    }
    if ('html' === $extension) {
        $contents = file_get_contents($path);
        if (false === $contents) {
            http_response_code(503);
            header('Cache-Control: no-store');

            return;
        }
        $hashes = [];
        if (preg_match_all('/<script type="application\/ld\+json">(.*?)<\/script>/s', $contents, $matches)) {
            foreach ($matches[1] as $inlineScript) {
                $hashes[] = "'sha256-".base64_encode(hash('sha256', $inlineScript, true))."'";
            }
        }
        header("Content-Security-Policy: default-src 'self'; script-src 'self' ".implode(' ', $hashes)."; style-src 'self'; font-src 'self'; img-src 'self' data:; connect-src 'self'; base-uri 'none'; frame-ancestors 'none'; form-action 'self'");
        header(str_starts_with($requestPath, '/wordle-answers/20') ? 'Cache-Control: max-age=300, must-revalidate' : 'Cache-Control: max-age=60, must-revalidate');
        echo $contents;

        return;
    }
    if (1 === preg_match('/\.[a-f0-9]{12}\.(?:css|js|json|woff2)$/', basename($path))) {
        header('Cache-Control: public, max-age=31536000, immutable');
    } elseif ('/assets/data/published-dates.json' === $requestPath || '/sitemap.xml' === $requestPath) {
        header('Cache-Control: max-age=60, must-revalidate');
    } else {
        header('Cache-Control: max-age=300, must-revalidate');
    }
    readfile($path);
}
