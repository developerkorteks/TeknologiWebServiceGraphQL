<?php
// router.php -- hanya untuk pengujian dengan:  php -S localhost:8000 router.php
// (Dengan XAMPP / Laragon file ini TIDAK dipakai.)
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if ($path == '/graphql' || $path == '/graphql.php') {
    require __DIR__ . '/graphql.php';
    return true;
}
return false;
