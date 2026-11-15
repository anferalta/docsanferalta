<?php
// The application keeps its existing bootstrap for every URL except GET/HEAD /.
$marketingPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$marketingMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$marketingRoot = dirname(__DIR__, 2) . '/public/anferalta-site/index.html';
if ($marketingPath === '/' && in_array($marketingMethod, ['GET', 'HEAD'], true)
    && is_file($marketingRoot) && is_readable($marketingRoot)) {
    header('Content-Type: text/html; charset=UTF-8');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data:; connect-src 'none'; object-src 'none'; base-uri 'none'; frame-ancestors 'self'; form-action 'none'");
    if ($marketingMethod === 'GET') {
        echo str_replace(
            ['href="assets/favicon.svg"', 'href="styles.css"', 'src="app.js"'],
            ['href="/anferalta-site/assets/favicon.svg"', 'href="/anferalta-site/styles.css"', 'src="/anferalta-site/app.js"'],
            file_get_contents($marketingRoot)
        );
    }
    exit;
}
