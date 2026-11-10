<?php
// Compatibility constants without installation-specific credentials.
foreach (['DB_HOST', 'DB_NAME', 'DB_USER'] as $key) {
    if (!defined($key)) define($key, \App\Core\Env::required($key));
}
if (!defined('DB_PASS')) define('DB_PASS', \App\Core\Env::secret('DB_PASS'));
