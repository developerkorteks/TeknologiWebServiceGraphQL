<?php
// =====================================================================
// lib/autoload.php  --  Pemuat otomatis pustaka webonyx/graphql-php
//
// Pustaka GraphQL (versi 15.39, lisensi MIT) sudah disertakan di folder
// lib/src, jadi TIDAK perlu Composer. File ini hanya memberi tahu PHP
// di mana mencari class berawalan  GraphQL\...
//   contoh: GraphQL\GraphQL  ->  lib/src/GraphQL.php
// =====================================================================
spl_autoload_register(function ($class) {
    $awalan = 'GraphQL\\';
    if (strpos($class, $awalan) !== 0) {
        return;                                            // bukan class GraphQL
    }
    $file = __DIR__ . '/src/' . str_replace('\\', '/', substr($class, strlen($awalan))) . '.php';
    if (is_file($file)) {
        require $file;
    }
});
