<?php
/**
 * Optional hosting adapter for www/index.php. Does not modify anferaltadocs/.
 * First COPY the existing application entrypoint to www/index-aplicacao.php.
 * Upload the static site into www/anferalta-site/ and validate it before activation.
 */
$anferaltaSiteMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$anferaltaSiteUri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$anferaltaSiteHomepage = __DIR__ . '/anferalta-site/index.html';
$anferaltaSiteApplication = __DIR__ . '/index-aplicacao.php';

if (in_array($anferaltaSiteMethod, ['GET', 'HEAD'], true)
    && $anferaltaSiteUri === '/'
    && is_file($anferaltaSiteHomepage) && is_readable($anferaltaSiteHomepage)) {
    header('Content-Type: text/html; charset=UTF-8');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data:; connect-src 'none'; object-src 'none'; base-uri 'none'; frame-ancestors 'self'; form-action 'none'");
    if ($anferaltaSiteMethod !== 'HEAD') {
        $anferaltaSiteHtml = file_get_contents($anferaltaSiteHomepage);
        // Resolve resources for GET / without changing the portable HTML file
        // or using <base>, which would also change in-page anchor navigation.
        echo str_replace(
            ['href="assets/favicon.svg"', 'href="styles.css"', 'src="app.js"'],
            ['href="/anferalta-site/assets/favicon.svg"', 'href="/anferalta-site/styles.css"', 'src="/anferalta-site/app.js"'],
            $anferaltaSiteHtml
        );
    }
    exit;
}

// All application URLs, methods and query parameters retain their original entrypoint.
if (is_file($anferaltaSiteApplication) && is_readable($anferaltaSiteApplication)) {
    require $anferaltaSiteApplication;
    exit;
}

http_response_code(503);
header('Content-Type: text/plain; charset=UTF-8');
header('Retry-After: 60');
echo 'A página encontra-se temporariamente indisponível.';
