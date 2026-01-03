<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();
readfile(__DIR__ . '/index.html');
exit;
