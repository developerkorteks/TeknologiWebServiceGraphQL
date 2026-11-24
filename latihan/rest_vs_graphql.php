<?php
// =====================================================================
// latihan/rest_vs_graphql.php  --  Membuktikan "over-fetching" dan "under-fetching"
//
// Jalankan:
//   php rest_vs_graphql.php http://localhost/api-mhs-graphql/server/graphql.php
//
// Program mengirim beberapa query lalu MENGHITUNG ukuran jawabannya (byte).
//   Query A : minta SEMUA field  -> mirip jawaban REST (GET /mhs mengirim semua kolom)
//   Query B : minta 2 field saja -> hanya yang dibutuhkan client
//   Query C : dua kebutuhan (jumlah + daftar) dalam SATU request
// =====================================================================

$url = isset($argv[1]) ? $argv[1] : 'https://tws.app:8443/api-mhs-graphql/server/graphql.php';

// HTTPS lokal Laragon (self-signed): percayai sertifikat Laragon, jangan matikan pemeriksaan SSL.
// (Penjelasan lengkap ada di client/test_client.php.)
$sertifikat_lokal = null;
if (parse_url($url, PHP_URL_SCHEME) == 'https'
    && gethostbyname(parse_url($url, PHP_URL_HOST)) == '127.0.0.1'
    && is_file('C:/laragon/etc/ssl/laragon.crt')) {
    $sertifikat_lokal = 'C:/laragon/etc/ssl/laragon.crt';
}

function kirim($url, $query)
{
    global $sertifikat_lokal;
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['query' => $query]));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    if ($sertifikat_lokal) {
        curl_setopt($ch, CURLOPT_CAINFO, $sertifikat_lokal);
    }
    $isi   = curl_exec($ch);
    $galat = curl_error($ch);
    unset($ch);   // pengganti curl_close() (usang di PHP 8.5)
    if ($isi === false) { echo "Server tidak bisa dihubungi: $galat\n"; exit(2); }
    return $isi;
}

$a = kirim($url, '{ daftarMahasiswa { id nim name major angkatan created_at } }');
$b = kirim($url, '{ daftarMahasiswa { nim name } }');
$c = kirim($url, '{ jumlahMahasiswa daftarMahasiswa { nim name } }');

$ukA = strlen($a);
$ukB = strlen($b);

echo "KASUS 1: OVER-FETCHING (data berlebih)\n";
echo "  Query A (semua 6 field) : $ukA byte\n";
echo "  Query B (hanya nim+name): $ukB byte\n";
echo "  Hemat                   : " . round((1 - $ukB / $ukA) * 100) . " %\n\n";

echo "KASUS 2: UNDER-FETCHING (kurang data -> request berulang)\n";
echo "  REST   : GET /mhs/jumlah  +  GET /mhs      = 2 request\n";
echo "  GraphQL: { jumlahMahasiswa daftarMahasiswa { ... } } = 1 request, " . strlen($c) . " byte\n\n";

echo "Pertanyaan diskusi:\n";
echo "  1. Mengapa penghematan lebih terasa di jaringan seluler (4G) daripada di localhost?\n";
echo "  2. Apa risiko jika client bebas meminta data apa saja? (lihat slide keamanan)\n";
