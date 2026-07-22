<?php

// Senarai putih penapis yang diingat sepanjang sesi log masuk.
//
// SEMPADAN KESELAMATAN: hanya kunci yang disenaraikan di sini akan disimpan
// atau digabungkan ke dalam permintaan. Middleware TIDAK BOLEH menyuntik
// parameter yang pengawal tidak jangka.
//
// `routes` menerima corak Str::is, jadi beberapa laluan boleh berkongsi satu
// skop. Endpoint XHR yang dipanggil sesuatu halaman MESTI berkongsi skop
// halaman itu — dengan itu pengambilan data biasa halaman merangkap
// penyimpanan, tanpa panggilan "simpan" berasingan.
return [
    'dashboard' => [
        'routes' => ['dashboard'],
        'keys' => ['negeri_id', 'bandar_id', 'kadun_id', 'mpkk_id', 'tarikh_dari', 'tarikh_hingga'],
    ],

    'war_room' => [
        // SENGAJA KOSONG buat masa ini. Nama laluan di bawah sudah disahkan betul,
        // tetapi mengaktifkan skop ini SEBELUM front end War Room dikemas kini
        // menghasilkan keadaan yang lebih teruk daripada tiada ingatan langsung:
        // FilterBar memaparkan "Semua Negeri" sementara penapis sesi menyempitkan
        // angka di bawahnya, dan reset FilterBar menanggalkan nilai kosong
        // (cleanParams) lalu menghantar permintaan KOSONG — yang membangkitkan
        // semula penapis yang baru sahaja dibuang. Diaktifkan semula dalam tugasan
        // yang menyemai WarRoom.jsx dan menghantar reset_filters daripada FilterBar.
        // Laluan sebenar: pilihanraya.war-room, pilihanraya.api.overview,
        // .composition, .sentiment, .seat-scores, .battlefield, .alerts
        'routes' => [],
        'keys' => [
            'negeri_id', 'parlimen_id', 'kadun_id',
            'tarikh_dari', 'tarikh_hingga',
            'umur_dari', 'umur_hingga', 'status_pengundi',
        ],
    ],
];
