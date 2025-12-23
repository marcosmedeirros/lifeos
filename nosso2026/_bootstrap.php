<?php
// Bootstrap para páginas do módulo Nosso2026
require_once __DIR__ . '/../config.php';
$IS_LOCAL = (strpos($_SERVER['HTTP_HOST'] ?? 'localhost', 'localhost') !== false);
$SELF_BASE = $IS_LOCAL ? '/lifeos/nosso2026' : '';
function n26_link($path){
    global $SELF_BASE; return rtrim($SELF_BASE, '/') . '/' . ltrim($path, '/');
}
?>