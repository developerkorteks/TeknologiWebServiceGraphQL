<?php
// =====================================================================
// test_client.php  --  CLIENT penguji API GraphQL (Pertemuan 9)
//
// Berperan sebagai "pengguna" GraphQL: mengirim query dan mutation, lalu
// memeriksa apakah jawabannya benar. Hasil: PASS / FAIL.
//
// Jalankan (dari terminal):
//   php test_client.php http://localhost/api-mhs-graphql/server/graphql.php
//   php test_client.php http://localhost:8000/graphql      <- jika memakai php -S
// =====================================================================

$url = isset($argv[1]) ? $argv[1] : 'https://tws.app:8443/api-mhs-graphql/server/graphql.php';

// HTTPS lokal di Laragon memakai sertifikat buatan sendiri (self-signed) yang tidak dikenal curl.
// Solusi AMAN: daftarkan sertifikat itu sebagai tepercaya (CURLOPT_CAINFO),
// BUKAN mematikan pemeriksaan SSL (VERIFYPEER = false membuka celah penyadapan).
// Hanya dipakai bila server berada di komputer ini (alamatnya 127.0.0.1).
$sertifikat_lokal = null;
if (parse_url($url, PHP_URL_SCHEME) == 'https'
    && gethostbyname(parse_url($url, PHP_URL_HOST)) == '127.0.0.1'
    && is_file('C:/laragon/etc/ssl/laragon.crt')) {
    $sertifikat_lokal = 'C:/laragon/etc/ssl/laragon.crt';
}

$lulus = 0;
$gagal = 0;

// ---------------------------------------------------------------------
// gql() : mengirim satu operasi GraphQL.
//   Selalu  POST  ke SATU alamat, body JSON {"query": ..., "variables": ...}
//   Mengembalikan [status HTTP, hasil JSON sebagai array]
// ---------------------------------------------------------------------
function gql($query, $variables = null, $token = null)
{
    global $url, $sertifikat_lokal;

    $header = ['Content-Type: application/json'];
    if ($token !== null) {
        $header[] = 'Authorization: Bearer ' . $token;
    }
    $body = ['query' => $query];
    if ($variables !== null) {
        $body['variables'] = $variables;
    }

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    if ($sertifikat_lokal) {
        curl_setopt($ch, CURLOPT_CAINFO, $sertifikat_lokal);    // percayai sertifikat Laragon
    }

    $isi    = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $galat  = curl_error($ch);
    unset($ch);   // sejak PHP 8 koneksi ditutup otomatis; curl_close() usang (deprecated) di PHP 8.5

    if ($isi === false) {
        echo "Server tidak bisa dihubungi: $galat\n";
        if (stripos($galat, 'certificate') !== false) {
            echo "Petunjuk: sertifikat HTTPS server tidak dipercaya. Coba alamat http:// atau periksa sertifikatnya.\n";
        }
        exit(2);
    }
    return [$status, json_decode($isi, true)];
}

function cek($nama, $kondisi)
{
    global $lulus, $gagal;
    if ($kondisi) { $lulus++; echo "  [PASS] $nama\n"; }
    else          { $gagal++; echo "  [FAIL] $nama\n"; }
}

// Mengambil kode error (extensions.code) dari hasil, atau null jika tidak ada error
function kode($hasil)
{
    return isset($hasil['errors'][0]['extensions']['code']) ? $hasil['errors'][0]['extensions']['code'] : null;
}

// ---------------------------------------------------------------------
echo "Menguji server: $url\n\n";

list($status, $h) = gql('{ halo }');
cek('Server GraphQL menjawab query { halo }', $status == 200 && isset($h['data']['halo']));

// ---------------------------------------------------------------------
echo "\n1. QUERY: client menentukan bentuk datanya\n";
list($status, $h) = gql('{ daftarMahasiswa { nim name } }');
$baris = isset($h['data']['daftarMahasiswa']) ? $h['data']['daftarMahasiswa'] : [];
cek('daftarMahasiswa mengembalikan banyak baris', count($baris) >= 3);
cek('Hanya field yang diminta (nim, name) yang dikirim server', $baris && array_keys($baris[0]) == ['nim', 'name']);

list($status, $h) = gql('{ daftarMahasiswa { id nim name major angkatan } }');
cek('Field hitungan "angkatan" tersedia (mis. 2023)', isset($h['data']['daftarMahasiswa'][0]['angkatan']) && strlen($h['data']['daftarMahasiswa'][0]['angkatan']) == 4);
// Ambil 2 id yang benar-benar ada (data bisa sudah dihapus/diubah saat latihan sebelumnya).
$id1 = isset($h['data']['daftarMahasiswa'][0]['id']) ? $h['data']['daftarMahasiswa'][0]['id'] : 1;
$id2 = isset($h['data']['daftarMahasiswa'][1]['id']) ? $h['data']['daftarMahasiswa'][1]['id'] : 2;

list($status, $h) = gql('query($j: String) { daftarMahasiswa(jurusan: $j) { major } }', ['j' => 'Informatika']);
$semua_if = true;
foreach ($h['data']['daftarMahasiswa'] as $m) { if ($m['major'] != 'Informatika') $semua_if = false; }
cek('Argumen jurusan (lewat variables) memfilter data', $semua_if && count($h['data']['daftarMahasiswa']) > 0);

list($status, $h) = gql('query($id: Int!) { mahasiswa(id: $id) { id name } }', ['id' => $id1]);
cek("mahasiswa(id: $id1) mengembalikan 1 data", isset($h['data']['mahasiswa']['id']) && $h['data']['mahasiswa']['id'] == $id1);

list($status, $h) = gql('{ mahasiswa(id: 999999) { id } }');
cek('id tidak ada -> data null (bukan error)', $status == 200 && array_key_exists('mahasiswa', $h['data']) && $h['data']['mahasiswa'] === null);

list($status, $h) = gql('{ jumlahMahasiswa  daftarMahasiswa { nim } }');
cek('2 query dalam 1 request (REST butuh 2 request)', isset($h['data']['jumlahMahasiswa']) && isset($h['data']['daftarMahasiswa']));

list($status, $h) = gql('query($x: Int!, $y: Int!) { a: mahasiswa(id: $x) { name }  b: mahasiswa(id: $y) { name } }', ['x' => $id1, 'y' => $id2]);
cek('Alias a dan b: 2 data sekaligus', isset($h['data']['a']['name']) && isset($h['data']['b']['name']));

// ---------------------------------------------------------------------
echo "\n2. VALIDASI SCHEMA: query yang melanggar kontrak ditolak\n";
list($status, $h) = gql('{ mahasiswa(id: 1) { gaji } }');
cek('Field yang tidak ada di schema -> errors', isset($h['errors']) && !isset($h['data']));
cek('Pesan error menyebut nama field yang salah', isset($h['errors'][0]['message']) && strpos($h['errors'][0]['message'], 'gaji') !== false);

list($status, $h) = gql('{ mahasiswa(id: "abc") { id } }');
cek('Tipe argumen salah (id harus Int) -> errors', isset($h['errors']));

list($status, $h) = gql('{ mahasiswa { id } }');
cek('Argumen wajib (id!) tidak dikirim -> errors', isset($h['errors']));

list($status, $h) = gql('{ halo ');
cek('Query bersintaks salah -> errors', isset($h['errors']));

// ---------------------------------------------------------------------
echo "\n3. MUTATION + OTORISASI\n";
$nimBaru = '99' . rand(100000, 999999);
$m = 'mutation($in: MahasiswaInput!) { tambahMahasiswa(input: $in) { id nim name angkatan } }';
$in = ['in' => ['nim' => $nimBaru, 'name' => 'Mahasiswa Uji', 'major' => 'Informatika']];

list($status, $h) = gql($m, $in);
cek('Tambah tanpa token -> UNAUTHENTICATED', kode($h) == 'UNAUTHENTICATED');
cek('Walau ditolak, HTTP status tetap 200 (ciri GraphQL)', $status == 200);

list($status, $h) = gql('mutation { login(username: "admin", password: "salah") { token } }');
cek('Login password salah -> UNAUTHENTICATED', kode($h) == 'UNAUTHENTICATED');

list($status, $h) = gql('mutation { login(username: "budi", password: "budi123") { token role } }');
$tokenUser = isset($h['data']['login']['token']) ? $h['data']['login']['token'] : null;
cek('Login budi (role user) -> dapat token', $tokenUser !== null && $h['data']['login']['role'] == 'user');

list($status, $h) = gql($m, $in, $tokenUser);
cek('Tambah dengan token user -> FORBIDDEN', kode($h) == 'FORBIDDEN');

list($status, $h) = gql('mutation { login(username: "admin", password: "admin123") { token role } }');
$tokenAdmin = isset($h['data']['login']['token']) ? $h['data']['login']['token'] : null;
cek('Login admin -> dapat token', $tokenAdmin !== null && $h['data']['login']['role'] == 'admin');
if ($tokenAdmin === null) {
    echo "\nLogin admin gagal. Pastikan database sudah diimpor dari database/mhs.sql.\n";
    exit(1);
}

list($status, $h) = gql($m, $in, $tokenAdmin);
cek('Tambah dengan token admin -> berhasil, data baru dikembalikan', isset($h['data']['tambahMahasiswa']['id']) && $h['data']['tambahMahasiswa']['nim'] == $nimBaru);
$idBaru = isset($h['data']['tambahMahasiswa']['id']) ? $h['data']['tambahMahasiswa']['id'] : 0;

list($status, $h) = gql('{ mahasiswa(id: ' . (int) $idBaru . ') { nim } }');
cek('Data baru bisa dibaca lewat query', isset($h['data']['mahasiswa']['nim']) && $h['data']['mahasiswa']['nim'] == $nimBaru);

list($status, $h) = gql($m, $in, $tokenAdmin);
cek('NIM kembar -> CONFLICT', kode($h) == 'CONFLICT');

$in['in']['nim'] = 'abc';
list($status, $h) = gql($m, $in, $tokenAdmin);
cek('NIM bukan angka -> VALIDATION', kode($h) == 'VALIDATION');

list($status, $h) = gql($m, $in, 'token.palsu.sekali');
cek('Token palsu -> UNAUTHENTICATED', kode($h) == 'UNAUTHENTICATED');

// ---------------------------------------------------------------------
echo "\n4. VARIABLES: query yang sama dipakai ulang\n";
$cocok = true;
foreach (['Informatika', 'Sistem Informasi'] as $j) {
    list($status, $h) = gql('query Per($j: String) { daftarMahasiswa(jurusan: $j) { nim } }', ['j' => $j]);
    if (!isset($h['data']['daftarMahasiswa'])) $cocok = false;
}
cek('Query yang sama dipakai ulang dengan variables berbeda', $cocok);

// ---------------------------------------------------------------------
// 5. MUTATION: ubahMahasiswa dan hapusMahasiswa  (Tugas P9)
// Buat mahasiswa sementara untuk diuji ubah & hapus
// ---------------------------------------------------------------------
echo "\n5. MUTATION: ubahMahasiswa dan hapusMahasiswa\n";

$nimTemp = '77' . rand(100000, 999999);
$mTambah = 'mutation($in: MahasiswaInput!) { tambahMahasiswa(input: $in) { id nim } }';
list(, $hT) = gql($mTambah, ['in' => ['nim' => $nimTemp, 'name' => 'Mahasiswa Temp', 'major' => 'Informatika']], $tokenAdmin);
$idTemp = isset($hT['data']['tambahMahasiswa']['id']) ? $hT['data']['tambahMahasiswa']['id'] : 0;

// --- ubahMahasiswa ---
$mUbah = 'mutation($id: Int!, $in: MahasiswaInput!) { ubahMahasiswa(id: $id, input: $in) { id nim name major angkatan } }';

// T1: ubahMahasiswa tanpa token -> UNAUTHENTICATED
list($status, $h) = gql($mUbah, ['id' => $idTemp, 'in' => ['nim' => $nimTemp, 'name' => 'X', 'major' => 'Y']]);
cek('ubahMahasiswa tanpa token -> UNAUTHENTICATED', kode($h) == 'UNAUTHENTICATED');
cek('ubahMahasiswa ditolak: HTTP status tetap 200', $status == 200);

// T2: ubahMahasiswa dengan token user (budi) -> FORBIDDEN
list($status, $h) = gql($mUbah, ['id' => $idTemp, 'in' => ['nim' => $nimTemp, 'name' => 'X', 'major' => 'Y']], $tokenUser);
cek('ubahMahasiswa token user -> FORBIDDEN', kode($h) == 'FORBIDDEN');

// T3: ubahMahasiswa id tidak ada -> NOT_FOUND
list($status, $h) = gql($mUbah, ['id' => 999999, 'in' => ['nim' => $nimTemp, 'name' => 'X', 'major' => 'Y']], $tokenAdmin);
cek('ubahMahasiswa id tidak ada -> NOT_FOUND', kode($h) == 'NOT_FOUND');

// T4: ubahMahasiswa data valid dengan token admin -> berhasil
$nimEdit = '76' . rand(100000, 999999);
list($status, $h) = gql($mUbah, ['id' => $idTemp, 'in' => ['nim' => $nimEdit, 'name' => 'Mahasiswa Diedit', 'major' => 'Sistem Informasi']], $tokenAdmin);
cek('ubahMahasiswa token admin -> berhasil, data berubah', isset($h['data']['ubahMahasiswa']['id']) && $h['data']['ubahMahasiswa']['nim'] == $nimEdit);
cek('ubahMahasiswa: nama berubah', ($h['data']['ubahMahasiswa']['name'] ?? '') == 'Mahasiswa Diedit');
cek('ubahMahasiswa: major berubah ke Sistem Informasi', ($h['data']['ubahMahasiswa']['major'] ?? '') == 'Sistem Informasi');

// T5: ubahMahasiswa NIM diubah ke NIM yang sudah ada mahasiswa lain -> CONFLICT
// Ambil NIM mahasiswa pertama (pasti ada)
list(, $hDaftar) = gql('{ daftarMahasiswa { nim } }');
$nimLain = null;
foreach ($hDaftar['data']['daftarMahasiswa'] ?? [] as $m) {
    if ($m['nim'] != $nimEdit) { $nimLain = $m['nim']; break; }
}
if ($nimLain) {
    list($status, $h) = gql($mUbah, ['id' => $idTemp, 'in' => ['nim' => $nimLain, 'name' => 'X', 'major' => 'Y']], $tokenAdmin);
    cek('ubahMahasiswa NIM milik mahasiswa lain -> CONFLICT', kode($h) == 'CONFLICT');
}

// T6: ubahMahasiswa NIM format salah -> VALIDATION
list($status, $h) = gql($mUbah, ['id' => $idTemp, 'in' => ['nim' => 'abc', 'name' => 'X', 'major' => 'Y']], $tokenAdmin);
cek('ubahMahasiswa NIM format salah -> VALIDATION', kode($h) == 'VALIDATION');

// Verifikasi perubahan bisa dibaca lewat query
list(, $hV) = gql('query($id: Int!) { mahasiswa(id: $id) { nim name major } }', ['id' => $idTemp]);
cek('Data yang diubah bisa dibaca lewat query', ($hV['data']['mahasiswa']['nim'] ?? '') == $nimEdit);

// --- hapusMahasiswa ---
$mHapus = 'mutation($id: Int!) { hapusMahasiswa(id: $id) }';

// T7: hapusMahasiswa tanpa token -> UNAUTHENTICATED
list($status, $h) = gql($mHapus, ['id' => $idTemp]);
cek('hapusMahasiswa tanpa token -> UNAUTHENTICATED', kode($h) == 'UNAUTHENTICATED');

// T8: hapusMahasiswa token user -> FORBIDDEN
list($status, $h) = gql($mHapus, ['id' => $idTemp], $tokenUser);
cek('hapusMahasiswa token user -> FORBIDDEN', kode($h) == 'FORBIDDEN');

// T9: hapusMahasiswa id tidak ada -> NOT_FOUND
list($status, $h) = gql($mHapus, ['id' => 999999], $tokenAdmin);
cek('hapusMahasiswa id tidak ada -> NOT_FOUND', kode($h) == 'NOT_FOUND');

// T10: hapusMahasiswa token admin -> berhasil (true)
list($status, $h) = gql($mHapus, ['id' => $idTemp], $tokenAdmin);
cek('hapusMahasiswa token admin -> true', ($h['data']['hapusMahasiswa'] ?? null) === true);
cek('hapusMahasiswa: HTTP status tetap 200', $status == 200);

// Verifikasi data benar-benar terhapus: query harus null
list(, $hDel) = gql('query($id: Int!) { mahasiswa(id: $id) { id } }', ['id' => $idTemp]);
cek('Setelah hapus, mahasiswa(id) = null (data hilang)', array_key_exists('mahasiswa', $hDel['data'] ?? []) && $hDel['data']['mahasiswa'] === null);

// ---------------------------------------------------------------------
// 6. TANTANGAN TAMBAHAN: statistikJurusan, cari, dan pagination
// ---------------------------------------------------------------------
echo "\n6. TANTANGAN TAMBAHAN: statistikJurusan, pencarian, dan paginasi\n";

// Query statistikJurusan
list($status, $hStat) = gql('{ statistikJurusan { jurusan jumlah } }');
$statData = $hStat['data']['statistikJurusan'] ?? [];
cek('statistikJurusan mengembalikan array data', is_array($statData) && count($statData) > 0);
cek('statistikJurusan memiliki field jurusan dan jumlah (int)', isset($statData[0]['jurusan']) && is_int($statData[0]['jumlah']));

// Pencarian nama (cari: String)
list($status, $hCari) = gql('{ daftarMahasiswa(cari: "Budi") { name } }');
$hasilCari = $hCari['data']['daftarMahasiswa'] ?? [];
$semuaMengandungBudi = count($hasilCari) > 0;
foreach ($hasilCari as $m) {
    if (stripos($m['name'], 'Budi') === false) {
        $semuaMengandungBudi = false;
        break;
    }
}
cek('Argumen cari memfilter mahasiswa yang namanya mengandung kata kunci', $semuaMengandungBudi);

// Paginasi: limit dan page
list($status, $hP1) = gql('{ daftarMahasiswa(limit: 2, page: 1) { id } }');
list($status, $hP2) = gql('{ daftarMahasiswa(limit: 2, page: 2) { id } }');
$p1 = $hP1['data']['daftarMahasiswa'] ?? [];
$p2 = $hP2['data']['daftarMahasiswa'] ?? [];
cek('Paginasi limit: 2 mengembalikan tepat 2 baris', count($p1) == 2 && count($p2) == 2);
cek('Paginasi page: 1 dan page: 2 mengembalikan baris yang berbeda', isset($p1[0]['id'], $p2[0]['id']) && $p1[0]['id'] != $p2[0]['id']);

// Validasi limit > 100 -> VALIDATION
list($status, $hLimOver) = gql('{ daftarMahasiswa(limit: 150) { id } }');
cek('Argumen limit > 100 ditolak dengan kode VALIDATION', kode($hLimOver) == 'VALIDATION');

// Validasi limit < 1 -> VALIDATION
list($status, $hLimZero) = gql('{ daftarMahasiswa(limit: 0) { id } }');
cek('Argumen limit < 1 ditolak dengan kode VALIDATION', kode($hLimZero) == 'VALIDATION');

// ---------------------------------------------------------------------
$total = $lulus + $gagal;
echo "\nHASIL: $lulus dari $total pengujian lulus\n";
exit($gagal == 0 ? 0 : 1);
