<?php
session_start();

echo "<pre>";
echo "session_id: " . session_id() . "\n";
echo "session.save_path: " . ini_get('session.save_path') . "\n\n";

var_dump($_SESSION);

$_SESSION['teste'] = 'abc123';

echo "\nDepois de definir:\n";
var_dump($_SESSION);