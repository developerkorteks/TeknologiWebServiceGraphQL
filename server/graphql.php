<?php
// =====================================================================
// graphql.php  --  SATU-SATUNYA endpoint GraphQL
//
// Berbeda dengan REST (banyak URL: /mhs, /mhs/1, /log ...), GraphQL hanya
// punya SATU alamat. Semua permintaan dikirim dengan  POST  ke sini, berisi:
//
//     { "query": "...", "variables": { ... }, "operationName": "..." }
//
// Alur file ini:
//   1. baca body JSON      2. cek token (jika ada)     3. jalankan query
//   4. catat log           5. kirim hasil sebagai JSON
// =====================================================================

require __DIR__ . '/lib/autoload.php';     // pustaka GraphQL
require __DIR__ . '/koneksi.php';          // $db, $jwt_secret
require __DIR__ . '/jwt.php';              // cek_token()
require __DIR__ . '/schema.php';           // $sdl, $resolver

use GraphQL\GraphQL;
use GraphQL\Utils\BuildSchema;

// ---------------- Header: JSON + CORS (agar client web di alamat lain boleh memanggil) ----------------
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Methods: POST, OPTIONS');
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {      // permintaan "izin" dari browser
    http_response_code(204);
    exit;
}

// ---------------- Fungsi mencatat log (tabel log_api, sama seperti Pertemuan 6-7) ----------------
function catat_log($db, $status, $username, $pesan)
{
    try {
        $db->prepare('INSERT INTO log_api (waktu, method, path, status, ip, username, pesan) VALUES (?,?,?,?,?,?,?)')
           ->execute([date('Y-m-d H:i:s'), $_SERVER['REQUEST_METHOD'], '/graphql', $status,
                      $_SERVER['REMOTE_ADDR'] ?? '-', $username, mb_substr($pesan, 0, 250)]);
    } catch (Exception $e) { /* kegagalan log tidak boleh merusak respons */ }
}

// ---------------- 1) Baca body JSON ----------------
if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    http_response_code(405);
    echo json_encode(['errors' => [['message' => 'Gunakan metode POST dengan body JSON {"query": "..."}']]]);
    exit;
}
$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input) || !isset($input['query']) || !is_string($input['query'])) {
    catat_log($db, 400, null, 'Body bukan JSON / tidak ada query');
    http_response_code(400);
    echo json_encode(['errors' => [['message' => 'Body harus JSON berisi field "query"']]]);
    exit;
}

// ---------------- 2) Cek token (opsional: tanpa token = tamu) ----------------
// CATATAN: Apache (XAMPP / Laragon) sering TIDAK menaruh header Authorization ke $_SERVER,
// jadi kita cari juga lewat getallheaders() (nama header tidak peka huruf besar/kecil).
$pengguna = null;
$header_auth = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
if ($header_auth === '' && function_exists('getallheaders')) {
    foreach (getallheaders() as $nama => $nilai) {
        if (strtolower($nama) === 'authorization') {
            $header_auth = $nilai;
            break;
        }
    }
}
if (preg_match('/^Bearer\s+(\S+)$/i', trim($header_auth), $cocok)) {
    $pengguna = cek_token($cocok[1], $jwt_secret);        // payload, atau null jika tidak sah
}

// ---------------- 3) Jalankan query ----------------
$schema   = BuildSchema::build($sdl);                           // ubah teks SDL menjadi schema
$konteks  = ['db' => $db, 'pengguna' => $pengguna];             // data yang bisa dipakai semua resolver

$hasil = GraphQL::executeQuery(
    $schema,
    $input['query'],
    $resolver,                                                  // "rootValue": kumpulan resolver
    $konteks,
    $input['variables'] ?? null,
    $input['operationName'] ?? null
)->toArray();

// ---------------- 4) Catat log ----------------
$ringkas = preg_replace('/\s+/', ' ', $input['query']);          // query dijadikan satu baris
// KEAMANAN: jangan menyimpan password di log! Jika password ditulis langsung di query,
// ganti isinya dengan ***.   (Jika memakai variables, password memang tidak ikut tersimpan.)
$ringkas = preg_replace('/password\s*:\s*"[^"]*"/i', 'password:"***"', $ringkas);
$status_log = isset($hasil['errors']) ? 'ERROR' : 'OK';
catat_log($db, 200, $pengguna['username'] ?? null, "[$status_log] $ringkas");

// ---------------- 5) Kirim hasil ----------------
// Catatan: GraphQL biasanya tetap membalas HTTP 200 walaupun ada error;
// kesalahannya dibaca dari field "errors" di dalam JSON.
echo json_encode($hasil, JSON_UNESCAPED_UNICODE);
