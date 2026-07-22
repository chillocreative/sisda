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

    // date_from/date_to SENGAJA TIDAK disenaraikan walaupun
    // ReportsController::applyHasilCulaanFilters() menyokongnya: disemak
    // Reports/HasilCulaan/Index.jsx (baris ~72-79) dan filterState di situ
    // TIDAK mengandungi date_from/date_to langsung — tiada medan input
    // tarikh pun wujud pada skrin ini (hanya search/umur/bangsa/negeri/
    // bandar/lokaliti). Skrin tidak dapat menghantar kunci ini
    // hadir-tetapi-kosong (tiada UI untuk itu), jadi tiada cara ia dapat
    // "dikosongkan" — menambahnya di sini hanya akan mengingat nilai daripada
    // URL manual (jika ada) yang skrin sendiri tidak pernah pulihkan ke
    // dalam sebarang kawalan. Tambah hanya selepas UI tarikh sebenar wujud
    // pada skrin ini dan mengikut corak hadir-tetapi-kosong yang sama
    // seperti kunci lain di atas.
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

    // Analisa/KawasanPicker.jsx menyemai negeriId/bandarId/kadunId daripada
    // rememberedFilters dan mengosongkan skop sepenuhnya (EmptyState, tiada
    // XHR) apabila dikosongkan — tiada risiko "Semua" palsu sambil data
    // masih ditapis. SENGAJA guna senarai laluan EKSPLISIT, bukan corak
    // 'pilihanraya.analisa.*': pilihanraya.analisa.seat-baseline dikongsi
    // dengan Simulasi.jsx (parlimen_id/kadun_id, tanpa negeri_id) — wildcard
    // akan membiarkan trafik Simulasi menulis ke skop ini dan menghakis
    // negeri_id yang baru dipilih. KeanggotaanCard.jsx turut dibetulkan untuk
    // menyertakan negeri_id (dahulu tertinggal) supaya SETIAP fetch menyimpan
    // ketiga-tiga kunci dengan lengkap, bukan menghapuskan negeri_id secara senyap.
    //
    // KETERBATASAN JUJUR (bukan bahaya UI menipu, TIDAK dibetulkan di sini):
    // KeanggotaanCard.jsx sendiri kembali awal (return) apabila bandar_id
    // tiada, jadi ia TIDAK PERNAH menghantar permintaan "dikosongkan" bagi
    // dirinya sendiri. Skrin ini tidak dapat menyatakan "kosong" bagi kad
    // ini — sesi mengekalkan nilai lama dan lawatan seterusnya
    // memulihkannya; pengguna tidak dapat kembali ke "tiada penapis" tanpa
    // log keluar. Ini SELAMAT (kawalan yang dipulihkan dan data yang
    // dipulihkan sentiasa sepadan kerana kedua-duanya datang daripada nilai
    // yang sama) tetapi BUKAN "boleh dikosongkan sepenuhnya". Ubatan yang
    // ditetapkan untuk sesiapa yang membetulkannya kelak: requestParams() di
    // resources/js/Pages/Pilihanraya/filters.js menghantar {reset_filters:1}
    // apabila semua penapis kosong — KeanggotaanCard perlu menghantar isyarat
    // itu dan bukan sekadar diam apabila bandar_id kosong.
    'analisa' => [
        'routes' => ['pilihanraya.analisa', 'pilihanraya.analisa.keanggotaan-card'],
        'keys' => ['negeri_id', 'bandar_id', 'kadun_id'],
    ],

    // Scoreboard (INTERNAL sahaja — Public/Scoreboard.jsx tidak disentuh).
    // fetchData() dibetulkan untuk turut menghantar negeri_id/parlimen_id
    // (dahulu hanya kadun_id) supaya penapis tidak terhakis oleh tinjauan
    // (poll) 4 saat sendiri — tanpa pembetulan ini, dropdown Negeri/Parlimen
    // akan menjadi kosong/lumpuh pada lawatan seterusnya walaupun DUN masih
    // betul, keadaan "kawalan tidak sepadan data" yang dilarang.
    //
    // Wildcard 'pilihanraya.scoreboard.*' DISEMAK (bukan diandaikan selamat
    // seperti kesilapan 'borang14' di atas): di bawah prefix
    // pilihanraya/scoreboard hanya wujud DUA laluan GET — .data (fetch
    // ditapis di atas) dan laluan asas itu sendiri; .settings ialah POST,
    // jadi RememberFilters (GET sahaja) tidak pernah mencapainya walaupun
    // namanya sepadan dengan corak. scoreboard.public / scoreboard.public.data
    // (awam, tanpa log masuk) TIDAK sepadan — nama laluannya tidak bermula
    // dengan 'pilihanraya.scoreboard.'. Semak semula senarai GET setiap kali
    // laluan baharu ditambah di bawah prefix ini.
    //
    // KETERBATASAN JUJUR (bukan bahaya UI menipu, TIDAK dibetulkan di sini):
    // fetchData() kembali awal (return) apabila kadunId kosong dan TIDAK
    // PERNAH menghantar permintaan "dikosongkan". Skrin ini tidak dapat
    // menyatakan "kosong" — sesi mengekalkan kadun_id/negeri_id/parlimen_id
    // lama dan lawatan seterusnya memulihkannya; pengguna tidak dapat
    // kembali ke "tiada DUN dipilih" tanpa log keluar. Ini SELAMAT (kawalan
    // yang dipulihkan dan data yang dipulihkan sentiasa sepadan) tetapi
    // BUKAN "boleh dikosongkan sepenuhnya". Ubatan yang ditetapkan: hantar
    // {reset_filters:1} (lihat requestParams() di
    // resources/js/Pages/Pilihanraya/filters.js) apabila kadunId dikosongkan.
    'scoreboard' => [
        'routes' => ['pilihanraya.scoreboard', 'pilihanraya.scoreboard.*'],
        'keys' => ['negeri_id', 'parlimen_id', 'kadun_id'],
    ],

    // Borang 14 — tab Keyin dan Papar berkongsi satu skop dengan sengaja.
    // NOTA KETERBATASAN (didedahkan, diterima — lihat laporan Tugasan 7):
    // fetch geografi KeyinTab (pilihanraya.borang-14.data) menghantar
    // kawasan_type/jenis_pr/tahun tetapi TIDAK PERNAH negeri_id/parlimen_id/
    // kadun_id (ia guna kawasan_id gabungan), dan fetch senarai PaparTab
    // (pilihanraya.borang-14.senarai) menghantar negeri_id/kadun_id tetapi
    // bukan kawasan_type/jenis_pr/tahun. Ini bermakna kedua-dua kumpulan
    // kunci saling menghapuskan antara satu sama lain sepanjang sesi, jadi
    // "geographyComplete" TIDAK PERNAH pulih automatik sepenuhnya — bukan
    // bahaya (tiada UI menipu; picker sentiasa kelihatan dan mesti disiapkan
    // secara manual sebelum jadual dipaparkan), tetapi ciri "ingat" ini tidak
    // berfungsi sepenuhnya untuk geografi. TIDAK dibetulkan di sini kerana
    // pembetulan memerlukan menyentuh fetch KeyinTab.jsx yang dilarang oleh
    // amaran Borang 14 (elak sebarang perubahan selain nilai awal `picker`).
    //
    // KETERBATASAN JUJUR TAMBAHAN (bukan bahaya UI menipu, TIDAK dibetulkan
    // di sini): KeyinTab.jsx kembali awal (return) dalam kedua-dua kesan
    // fetch selagi geographyComplete palsu, jadi ia TIDAK PERNAH menghantar
    // permintaan "dikosongkan" apabila picker dikosongkan. Skrin ini tidak
    // dapat menyatakan "kosong" — sesi mengekalkan geografi lama dan lawatan
    // seterusnya memulihkannya; pengguna tidak dapat kembali ke "tiada
    // geografi dipilih" tanpa log keluar. Ubatan yang ditetapkan untuk
    // sesiapa yang membetulkannya kelak: requestParams() di
    // resources/js/Pages/Pilihanraya/filters.js menghantar {reset_filters:1}
    // apabila semua penapis kosong.
    // SENGAJA SENARAI EKSPLISIT, BUKAN WILDCARD 'pilihanraya.borang-14.*':
    // wildcard itu menangkap TIGA laluan GET lain yang tidak patut berkongsi
    // skop ini —
    //   - .pdf ialah tindakan CETAK; ia menghantar kawasan_type/jenis_pr/tahun
    //     bagi baris arkib yang dicetak, jadi ia mengambil cabang SIMPAN dan
    //     menulis ganti seluruh set kunci (kunci yang tiada jadi null) —
    //     mencetak satu pilihan raya lama akan menghapuskan negeri/parlimen/
    //     kadun yang baru sahaja dipilih untuk kemasukan undi.
    //   - .upload.sejarah (panel Sejarah Muat Naik) TIDAK menghantar sebarang
    //     parameter, jadi ia mengambil cabang GABUNG dan disuntik dengan
    //     negeri_id/kadun_id yang diingat — panel ini TIADA UI penapis, jadi
    //     operator tidak dapat mengesan atau membersihkan penapisan senyap
    //     ini; risiko malam pilihan raya: muat naik scoresheet kerusi lain
    //     tidak kelihatan dalam sejarah, operator sangka gagal, muat naik
    //     semula -> data undi Borang 14 bertindan.
    //   - .upload.fail (muat turun fail scoresheet asal) tidak berbahaya
    //     tetapi juga tidak sengaja termasuk.
    // Jangan sekali-kali kembalikan corak ini kepada wildcard tanpa menyemak
    // setiap laluan GET baharu di bawah pilihanraya/borang-14/* dahulu.
    'borang14' => [
        'routes' => ['pilihanraya.borang-14', 'pilihanraya.borang-14.data', 'pilihanraya.borang-14.senarai'],
        'keys' => ['negeri_id', 'parlimen_id', 'kadun_id', 'kawasan_type', 'jenis_pr', 'tahun'],
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
