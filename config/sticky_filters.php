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
        // WarRoom.jsx menyemai keadaan penapisnya daripada rememberedFilters
        // (initialFilters()) dan menghantar reset_filters apabila FilterBar
        // dikosongkan (requestParams()) — kedua-dua keping front end yang
        // membolehkan skop ini diaktifkan dengan selamat. Laluan halaman dan
        // enam XHR tab berkongsi skop yang sama supaya pengambilan data biasa
        // merangkap penyimpanan tanpa panggilan "simpan" berasingan.
        'routes' => [
            'pilihanraya.war-room',
            'pilihanraya.api.overview',
            'pilihanraya.api.composition',
            'pilihanraya.api.sentiment',
            'pilihanraya.api.seat-scores',
            'pilihanraya.api.battlefield',
            'pilihanraya.api.alerts',
        ],
        'keys' => [
            'negeri_id', 'parlimen_id', 'kadun_id',
            'tarikh_dari', 'tarikh_hingga',
            'umur_dari', 'umur_hingga', 'status_pengundi',
        ],
    ],

    // Skrin berasaskan URL — semuanya sudah menyemai daripada prop `filters`
    // sedia ada dan menghantar SEMUA kunci penapisnya pada setiap pertukaran
    // dropdown (hadir-tetapi-kosong apabila dikosongkan), jadi tiada butang
    // "Set Semula" berasingan yang boleh menghantar permintaan kosong.
    'user_log' => [
        'routes' => ['user-log.index'],
        'keys' => ['tab', 'user_id', 'event', 'date_from', 'date_to'],
    ],

    'keanggotaan_senarai' => [
        'routes' => ['keanggotaan.senarai'],
        'keys' => [
            'status_kawasan', 'parlimen', 'dun', 'daerah_mengundi', 'lokaliti',
            'bangsa', 'jantina', 'status_anggota', 'sentimen', 'sayap',
        ],
    ],

    'keanggotaan_analisa' => [
        'routes' => ['keanggotaan.analisa'],
        'keys' => [
            'status_kawasan', 'parlimen', 'dun', 'daerah_mengundi', 'lokaliti',
            'bangsa', 'jantina', 'status_anggota', 'sentimen', 'sayap',
        ],
    ],

    'jawatankuasa' => [
        'routes' => ['pilihanraya.jawatankuasa.index'],
        'keys' => ['jenis', 'parlimen', 'dun'],
    ],

    'hasil_culaan' => [
        'routes' => ['reports.hasil-culaan.index'],
        'keys' => ['umur', 'bangsa', 'negeri', 'bandar', 'lokaliti'],
    ],

    // kaum_dm/minima: 'kawasan' bukan penapis pilihan — KawasanSelect sentiasa
    // memaksa satu DUN sebenar (tiada pilihan "Semua"), jadi tiada konsep
    // "kosongkan" langsung untuk dipecahkan. Memulihkan DUN terakhir semasa
    // navigasi kosong ialah gelagat yang betul di sini.
    'kaum_dm' => [
        'routes' => ['pilihanraya.kaum-dm'],
        'keys' => ['kawasan'],
    ],

    'minima' => [
        'routes' => ['pilihanraya.minima'],
        'keys' => ['kawasan'],
    ],

    // SENGAJA KOSONG — laluan sebenar: users.index. Butang "Set Semula" di
    // Users/Index.jsx (resetFilters(), ~baris 111-121) memanggil
    // router.get(route('users.index')) TANPA sebarang parameter dan TANPA
    // reset_filters — permintaan kosong ini tidak dapat dibezakan daripada
    // navigasi biasa, jadi middleware akan membangkitkan semula penapis lama
    // dan Set Semula tidak akan berfungsi. Aktifkan hanya selepas resetFilters()
    // dikemas kini menghantar reset_filters (lihat filters.js requestParams()).
    'users' => [
        'routes' => [],
        'keys' => ['role', 'status', 'negeri_id', 'bandar_id', 'kadun_id'],
    ],

    // SENGAJA KOSONG — laluan sebenar: reports.data-pengundi.index. handleReset()
    // di Reports/DataPengundi/Index.jsx (~baris 86-92) memanggil
    // router.visit(route('reports.data-pengundi.index')) TANPA parameter dan
    // TANPA reset_filters — hazard sama seperti 'users' di atas.
    'data_pengundi' => [
        'routes' => [],
        'keys' => ['date_from', 'date_to'],
    ],

    // SENGAJA KOSONG — laluan sebenar: master-data.bandar.index. Butang Reset
    // di MasterData/Bandar/Index.jsx (~baris 134-146) memanggil
    // router.get(route('master-data.bandar.index')) TANPA parameter dan TANPA
    // reset_filters apabila search/negeri_id diisi — hazard sama seperti 'users'.
    'masterdata_bandar' => [
        'routes' => [],
        'keys' => ['negeri_id'],
    ],

    // SENGAJA KOSONG — laluan sebenar: master-data.parlimen.index. Butang Reset
    // di MasterData/Parlimen/Index.jsx (~baris 134-146) berkongsi kod dan hazard
    // yang sama seperti 'masterdata_bandar' di atas.
    'masterdata_parlimen' => [
        'routes' => [],
        'keys' => ['negeri_id'],
    ],
];
