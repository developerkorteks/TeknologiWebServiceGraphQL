<?php
// =====================================================================
// schema.php  --  INTI GraphQL: SCHEMA (kontrak) + RESOLVER (pelaksana)
//
//   SCHEMA   = daftar apa saja yang boleh ditanyakan / diubah client.
//              Ditulis dalam bahasa khusus bernama SDL (Schema Definition Language).
//   RESOLVER = fungsi PHP yang mengambil / mengubah data sesungguhnya.
//
// Hubungan keduanya:  setiap baris di type Query / Mutation pada SDL
// punya SATU fungsi resolver dengan NAMA YANG SAMA di bagian bawah file ini.
// =====================================================================

use GraphQL\Error\ClientAware;
use GraphQL\Error\ProvidesExtensions;

// ---------------------------------------------------------------------
// 1) SCHEMA (SDL)
//    Tanda "!" berarti WAJIB / tidak boleh null.   [X!]! = daftar yang berisi X.
// ---------------------------------------------------------------------
$sdl = '
    "Satu baris data mahasiswa"
    type Mahasiswa {
        id: Int!
        nim: String!
        name: String!
        major: String!
        created_at: String
        "Tidak ada di tabel: dihitung dari 2 angka pertama NIM, mis. 23 -> 2023"
        angkatan: String
    }

    "Hasil login: token JWT yang harus dikirim pada mutation"
    type AuthPayload {
        token: String!
        username: String!
        role: String!
    }

    "Input untuk membuat mahasiswa baru"
    input MahasiswaInput {
        nim: String!
        name: String!
        major: String!
    }

    "Statistik jumlah mahasiswa per jurusan"
    type StatistikJurusan {
        jurusan: String!
        jumlah: Int!
    }

    "READ: operasi untuk MEMBACA data"
    type Query {
        halo: String!
        jumlahMahasiswa: Int!
        "Daftar mahasiswa. Argumen opsional: jurusan (filter), cari (LIKE nama), page dan limit (paginasi, limit max 100)."
        daftarMahasiswa(jurusan: String, cari: String, page: Int, limit: Int): [Mahasiswa!]!
        mahasiswa(id: Int!): Mahasiswa
        "Jumlah mahasiswa dikelompokkan per jurusan (GROUP BY major)."
        statistikJurusan: [StatistikJurusan!]!
    }

    "WRITE: operasi untuk MENGUBAH data"
    type Mutation {
        login(username: String!, password: String!): AuthPayload!
        tambahMahasiswa(input: MahasiswaInput!): Mahasiswa!
        "Ubah data mahasiswa berdasarkan id. Hanya admin."
        ubahMahasiswa(id: Int!, input: MahasiswaInput!): Mahasiswa!
        "Hapus mahasiswa berdasarkan id. Hanya admin. Mengembalikan true jika berhasil."
        hapusMahasiswa(id: Int!): Boolean!
    }
';

// ---------------------------------------------------------------------
// Error khusus yang membawa "kode" (UNAUTHENTICATED / FORBIDDEN / dst.)
// GraphQL tidak memakai status HTTP 401/403; kodenya ditaruh di
// bagian errors[].extensions.code pada isi JSON.
// ---------------------------------------------------------------------
class ErrorGQL extends Exception implements ClientAware, ProvidesExtensions
{
    private $kode;
    public function __construct($pesan, $kode)
    {
        parent::__construct($pesan);
        $this->kode = $kode;
    }
    public function isClientSafe(): bool { return true; }      // pesan boleh dilihat client
    public function getExtensions(): ?array { return ['code' => $this->kode]; }
}

// ---------------------------------------------------------------------
// Fungsi bantu otorisasi (konsepnya sama dengan Pertemuan 7)
//   $konteks['pengguna'] berisi payload token, atau null jika belum login.
// ---------------------------------------------------------------------
function wajib_login($konteks)
{
    if (!$konteks['pengguna']) {
        throw new ErrorGQL('Belum login: sertakan header Authorization: Bearer <token>', 'UNAUTHENTICATED');
    }
}

function wajib_admin($konteks)
{
    wajib_login($konteks);
    if ($konteks['pengguna']['role'] != 'admin') {
        throw new ErrorGQL('Akses ditolak: hanya admin yang boleh melakukan ini', 'FORBIDDEN');
    }
}

// Mengubah baris database menjadi bentuk yang sesuai type Mahasiswa.
function ke_mahasiswa($baris)
{
    $baris['id']       = (int) $baris['id'];
    $baris['angkatan'] = '20' . substr($baris['nim'], 0, 2);   // field hitungan
    return $baris;
}

// ---------------------------------------------------------------------
// 2) RESOLVER
//    Tanda tangan fungsi:  function ($root, $args, $konteks)
//      $args    = argumen dari query, mis. ['id' => 2]
//      $konteks = data bersama: koneksi database ($konteks['db']) dan pengguna
// ---------------------------------------------------------------------
$resolver = [

    // ----- QUERY -----
    'halo' => function ($root, $args, $konteks) {
        return 'Halo dari GraphQL!';
    },

    'jumlahMahasiswa' => function ($root, $args, $konteks) {
        return (int) $konteks['db']->query('SELECT COUNT(*) FROM mhs')->fetchColumn();
    },

    'daftarMahasiswa' => function ($root, $args, $konteks) {
        $db = $konteks['db'];

        // ---- Tantangan tambahan: validasi limit ----
        $limit = isset($args['limit']) ? (int) $args['limit'] : null;
        if ($limit !== null && $limit > 100) {
            throw new ErrorGQL('Limit tidak boleh lebih dari 100', 'VALIDATION');
        }
        if ($limit !== null && $limit < 1) {
            throw new ErrorGQL('Limit harus minimal 1', 'VALIDATION');
        }

        // ---- Bangun query SQL secara dinamis ----
        $sql    = 'SELECT * FROM mhs WHERE 1=1';
        $params = [];

        // Filter jurusan (sama seperti sebelumnya)
        if (isset($args['jurusan']) && $args['jurusan'] !== null) {
            $sql      .= ' AND major = ?';
            $params[]  = $args['jurusan'];
        }

        // Tantangan: pencarian nama (LIKE %)
        if (isset($args['cari']) && $args['cari'] !== null && trim($args['cari']) !== '') {
            $sql      .= ' AND name LIKE ?';
            $params[]  = '%' . trim($args['cari']) . '%';
        }

        $sql .= ' ORDER BY id';

        // Tantangan: paginasi (LIMIT + OFFSET)
        // Di MySQL/PDO, parameter LIMIT/OFFSET jika lewat execute() akan di-bind sebagai string ber-tanda petik ('2'),
        // yang menyebabkan syntax error SQL 1064. Karena $limit dan $offset sudah strictly integer (int),
        // aman dan standar dimasukkan langsung ke teks query.
        if ($limit !== null) {
            $page     = isset($args['page']) ? max(1, (int) $args['page']) : 1;
            $offset   = (int) (($page - 1) * $limit);
            $limitInt = (int) $limit;
            $sql     .= " LIMIT $limitInt OFFSET $offset";
        }

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return array_map('ke_mahasiswa', $stmt->fetchAll());
    },

    'mahasiswa' => function ($root, $args, $konteks) {
        $stmt = $konteks['db']->prepare('SELECT * FROM mhs WHERE id = ?');
        $stmt->execute([$args['id']]);
        $baris = $stmt->fetch();
        return $baris ? ke_mahasiswa($baris) : null;                    // tidak ada -> null
    },

    // ----- QUERY: statistikJurusan (Tantangan Tambahan) -----
    // Mengembalikan daftar {jurusan, jumlah} dari GROUP BY major.
    'statistikJurusan' => function ($root, $args, $konteks) {
        $rows = $konteks['db']
            ->query('SELECT major AS jurusan, COUNT(*) AS jumlah FROM mhs GROUP BY major ORDER BY jumlah DESC')
            ->fetchAll();
        // Pastikan jumlah adalah integer
        return array_map(function ($r) {
            return ['jurusan' => $r['jurusan'], 'jumlah' => (int) $r['jumlah']];
        }, $rows);
    },

    // ----- MUTATION -----
    'login' => function ($root, $args, $konteks) {
        global $jwt_secret, $jwt_berlaku;
        $stmt = $konteks['db']->prepare('SELECT * FROM users WHERE username = ?');
        $stmt->execute([$args['username']]);
        $akun = $stmt->fetch();

        // Pesan SAMA untuk username salah dan password salah (lihat Pertemuan 7).
        if (!$akun || !password_verify($args['password'], $akun['password'])) {
            throw new ErrorGQL('Username atau password salah', 'UNAUTHENTICATED');
        }
        $token = buat_token(
            ['sub' => (int) $akun['id'], 'username' => $akun['username'], 'role' => $akun['role']],
            $jwt_secret,
            $jwt_berlaku
        );
        return ['token' => $token, 'username' => $akun['username'], 'role' => $akun['role']];
    },

    'tambahMahasiswa' => function ($root, $args, $konteks) {
        wajib_admin($konteks);                                          // OTORISASI dulu

        $in = $args['input'];
        $nim = trim($in['nim']); $name = trim($in['name']); $major = trim($in['major']);

        // VALIDASI input (GraphQL hanya memeriksa tipe; aturan bisnis kita periksa sendiri)
        if (!ctype_digit($nim) || strlen($nim) < 8 || strlen($nim) > 12) {
            throw new ErrorGQL('NIM harus berupa angka 8 sampai 12 digit', 'VALIDATION');
        }
        if ($name === '' || $major === '') {
            throw new ErrorGQL('Nama dan jurusan tidak boleh kosong', 'VALIDATION');
        }

        $db = $konteks['db'];
        $cek = $db->prepare('SELECT COUNT(*) FROM mhs WHERE nim = ?');
        $cek->execute([$nim]);
        if ($cek->fetchColumn() > 0) {
            throw new ErrorGQL("NIM $nim sudah terdaftar", 'CONFLICT');
        }

        $db->prepare('INSERT INTO mhs (nim, name, major) VALUES (?, ?, ?)')->execute([$nim, $name, $major]);
        $id = (int) $db->lastInsertId();

        $stmt = $db->prepare('SELECT * FROM mhs WHERE id = ?');
        $stmt->execute([$id]);
        return ke_mahasiswa($stmt->fetch());                            // kembalikan data baru
    },

    // ----- MUTATION: ubahMahasiswa -----
    // Mengubah data mahasiswa berdasarkan id.
    // Hanya admin. Validasi sama dengan tambah.
    // NIM tidak boleh dipakai mahasiswa lain (CONFLICT), id tidak ada -> NOT_FOUND.
    'ubahMahasiswa' => function ($root, $args, $konteks) {
        wajib_admin($konteks);                                          // OTORISASI: hanya admin

        $id  = (int) $args['id'];
        $in  = $args['input'];
        $nim   = trim($in['nim']);
        $name  = trim($in['name']);
        $major = trim($in['major']);

        // VALIDASI format NIM
        if (!ctype_digit($nim) || strlen($nim) < 8 || strlen($nim) > 12) {
            throw new ErrorGQL('NIM harus berupa angka 8 sampai 12 digit', 'VALIDATION');
        }
        if ($name === '' || $major === '') {
            throw new ErrorGQL('Nama dan jurusan tidak boleh kosong', 'VALIDATION');
        }

        $db = $konteks['db'];

        // Periksa apakah id ada di tabel
        $cekId = $db->prepare('SELECT COUNT(*) FROM mhs WHERE id = ?');
        $cekId->execute([$id]);
        if ($cekId->fetchColumn() == 0) {
            throw new ErrorGQL("Mahasiswa dengan id $id tidak ditemukan", 'NOT_FOUND');
        }

        // Periksa NIM tidak dipakai mahasiswa LAIN (boleh pakai NIM yang sama jika miliknya sendiri)
        $cekNim = $db->prepare('SELECT COUNT(*) FROM mhs WHERE nim = ? AND id != ?');
        $cekNim->execute([$nim, $id]);
        if ($cekNim->fetchColumn() > 0) {
            throw new ErrorGQL("NIM $nim sudah dipakai mahasiswa lain", 'CONFLICT');
        }

        // Lakukan UPDATE
        $db->prepare('UPDATE mhs SET nim = ?, name = ?, major = ? WHERE id = ?')
           ->execute([$nim, $name, $major, $id]);

        // Kembalikan data yang sudah diperbarui
        $stmt = $db->prepare('SELECT * FROM mhs WHERE id = ?');
        $stmt->execute([$id]);
        return ke_mahasiswa($stmt->fetch());
    },

    // ----- MUTATION: hapusMahasiswa -----
    // Menghapus mahasiswa berdasarkan id.
    // Hanya admin. Mengembalikan true jika berhasil; id tidak ada -> NOT_FOUND.
    'hapusMahasiswa' => function ($root, $args, $konteks) {
        wajib_admin($konteks);                                          // OTORISASI: hanya admin

        $id = (int) $args['id'];
        $db = $konteks['db'];

        // Periksa apakah id ada sebelum dihapus
        $cek = $db->prepare('SELECT COUNT(*) FROM mhs WHERE id = ?');
        $cek->execute([$id]);
        if ($cek->fetchColumn() == 0) {
            throw new ErrorGQL("Mahasiswa dengan id $id tidak ditemukan", 'NOT_FOUND');
        }

        // Lakukan DELETE
        $db->prepare('DELETE FROM mhs WHERE id = ?')->execute([$id]);
        return true;
    },
];
