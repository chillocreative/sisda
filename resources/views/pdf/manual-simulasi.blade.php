<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="utf-8">
    <title>Manual Pengguna — Pusat Simulasi Pilihanraya</title>
    <style>
        /* dompdf-safe: tables + inline CSS only, no flexbox/grid */
        * { box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10.5px; color: #0f172a; margin: 0; padding: 24px 28px; }

        /* Cover */
        .cover { text-align: center; padding: 60px 0 40px 0; border-bottom: 3px solid #10b981; margin-bottom: 30px; }
        .cover-label { font-size: 9px; letter-spacing: 2px; text-transform: uppercase; color: #64748b; margin-bottom: 8px; }
        .cover-title { font-size: 26px; font-weight: bold; color: #0f172a; margin: 0 0 6px 0; }
        .cover-sub { font-size: 13px; color: #475569; margin: 0 0 20px 0; }
        .cover-meta { font-size: 9px; color: #94a3b8; margin-top: 12px; }
        .cover-badge { display: inline-block; background: #0f172a; color: #10b981; font-size: 9px;
                       letter-spacing: 1px; text-transform: uppercase; padding: 3px 10px; margin-bottom: 16px; }

        /* TOC */
        .toc-title { font-size: 13px; font-weight: bold; color: #0f172a; margin: 0 0 10px 0; border-bottom: 1px solid #e2e8f0; padding-bottom: 6px; }
        .toc-row { margin: 3px 0; }
        .toc-num { color: #10b981; font-weight: bold; }
        .toc-sub { margin: 2px 0 2px 18px; color: #475569; font-size: 9.5px; }

        /* Section headings */
        h1 { font-size: 16px; color: #0f172a; margin: 28px 0 6px 0; border-left: 4px solid #10b981; padding-left: 8px; }
        h2 { font-size: 12px; color: #0f172a; margin: 18px 0 5px 0; border-bottom: 1px solid #e2e8f0; padding-bottom: 4px; }
        h3 { font-size: 10.5px; color: #1e293b; margin: 12px 0 4px 0; font-weight: bold; }
        p { margin: 4px 0 6px 0; line-height: 1.55; color: #334155; }

        /* Lists */
        ul { margin: 4px 0 8px 16px; padding: 0; }
        ol { margin: 4px 0 8px 18px; padding: 0; }
        li { margin: 3px 0; line-height: 1.45; color: #334155; }

        /* Tables */
        table { width: 100%; border-collapse: collapse; margin: 8px 0 14px 0; }
        th { background: #0f172a; color: #f8fafc; text-align: left; padding: 5px 7px; font-size: 9px;
             text-transform: uppercase; letter-spacing: 0.5px; }
        td { border-bottom: 1px solid #e2e8f0; padding: 5px 7px; font-size: 9.5px; vertical-align: top; }
        tr:nth-child(even) td { background: #f8fafc; }
        .col-label { font-weight: bold; color: #0f172a; width: 28%; }
        .col-desc { color: #334155; }
        .col-tip { color: #64748b; font-size: 9px; width: 32%; }

        /* Info / tip boxes */
        .box { border: 1px solid #e2e8f0; padding: 8px 11px; margin: 10px 0; background: #f8fafc; }
        .box-green { border-color: #10b981; background: #f0fdf4; }
        .box-amber { border-color: #f59e0b; background: #fffbeb; }
        .box-red { border-color: #ef4444; background: #fef2f2; }
        .box-blue { border-color: #3b82f6; background: #eff6ff; }
        .box-title { font-weight: bold; font-size: 10px; margin-bottom: 4px; }

        /* Colour indicators */
        .swatch { display: inline-block; width: 10px; height: 10px; border-radius: 2px; margin-right: 4px; vertical-align: middle; }
        .sw-putih { background: #10b981; }
        .sw-hitam { background: #ef4444; }
        .sw-kelabu { background: #94a3b8; }
        .sw-amber  { background: #f59e0b; }
        .sw-blue   { background: #3b82f6; }
        .sw-violet { background: #8b5cf6; }

        /* Metric highlight row */
        .metric-label { font-weight: bold; color: #10b981; }

        /* Page break */
        .pagebreak { page-break-after: always; }

        /* Footer */
        .footer { margin-top: 28px; color: #94a3b8; font-size: 8.5px; border-top: 1px solid #e2e8f0; padding-top: 6px; text-align: center; }

        /* Glossary */
        .gloss-term { font-weight: bold; color: #0f172a; }
        .gloss-def { color: #475569; }

        /* Step numbering */
        .step { background: #0f172a; color: #10b981; font-weight: bold; font-size: 9px;
                padding: 1px 5px; margin-right: 5px; }

        /* Separator */
        hr { border: 0; border-top: 1px solid #e2e8f0; margin: 16px 0; }
    </style>
</head>
<body>

<!-- ============================================================ COVER -->
<div class="cover">
    <div class="cover-badge">SISDA &mdash; Digital War Room</div>
    <div class="cover-label">Manual Pengguna Rasmi</div>
    <div class="cover-title">Pusat Simulasi Pilihanraya</div>
    <div class="cover-sub">Panduan Lengkap: Cara Membaca dan Menggunakan Setiap Panel</div>
    <table style="width:60%;margin:20px auto 0 auto;border:1px solid #e2e8f0;background:#f8fafc;">
        <tr>
            <td style="padding:6px 10px;color:#64748b;font-size:9px;width:50%;">Sistem</td>
            <td style="padding:6px 10px;font-size:9px;font-weight:bold;">SISDA (Sistem Data Pengundi)</td>
        </tr>
        <tr>
            <td style="padding:6px 10px;color:#64748b;font-size:9px;">Modul</td>
            <td style="padding:6px 10px;font-size:9px;font-weight:bold;">Pilihanraya &rarr; Pusat Simulasi</td>
        </tr>
        <tr>
            <td style="padding:6px 10px;color:#64748b;font-size:9px;">Akses</td>
            <td style="padding:6px 10px;font-size:9px;font-weight:bold;">Super Admin sahaja</td>
        </tr>
        <tr>
            <td style="padding:6px 10px;color:#64748b;font-size:9px;">Tarikh Dokumen</td>
            <td style="padding:6px 10px;font-size:9px;font-weight:bold;">{{ now()->format('d F Y') }}</td>
        </tr>
        <tr>
            <td style="padding:6px 10px;color:#64748b;font-size:9px;">Versi</td>
            <td style="padding:6px 10px;font-size:9px;font-weight:bold;">1.0</td>
        </tr>
    </table>
    <div class="cover-meta">SULIT &mdash; Untuk Kegunaan Dalaman Sahaja</div>
</div>

<!-- ============================================================ TOC -->
<div class="toc-title">ISI KANDUNGAN</div>
<div class="toc-row"><span class="toc-num">1.</span> Pengenalan Pusat Simulasi</div>
<div class="toc-row"><span class="toc-num">2.</span> Bar Penapis Global</div>
<div class="toc-row"><span class="toc-num">3.</span> Tab Ramalan — Ramalan AI Pilihanraya</div>
<div class="toc-sub">3.1 Tolok Kebarangkalian (Gauge)</div>
<div class="toc-sub">3.2 Kad KPI Utama</div>
<div class="toc-sub">3.3 Analisis Strategik</div>
<div class="toc-sub">3.4 Jadual Unjuran Kerusi Utama</div>
<div class="toc-sub">3.5 Penunjuk Mod Sandaran (Fallback)</div>
<div class="toc-row"><span class="toc-num">4.</span> Tab What-If — Simulasi Senario</div>
<div class="toc-sub">4.1 Panel Kawalan Slider</div>
<div class="toc-sub">4.2 Kad Ringkasan Nasional</div>
<div class="toc-sub">4.3 Jadual Unjuran Mengikut Kerusi</div>
<div class="toc-row"><span class="toc-num">5.</span> Tab War Gaming — Soal Jawab AI Strategik</div>
<div class="toc-sub">5.1 Cara Bertanya</div>
<div class="toc-sub">5.2 Membaca Respons AI</div>
<div class="toc-row"><span class="toc-num">6.</span> Tab Sumber — Peruntukan Sumber Kempen</div>
<div class="toc-sub">6.1 Kad Peruntukan</div>
<div class="toc-sub">6.2 Rumusan Strategi</div>
<div class="toc-row"><span class="toc-num">7.</span> Tab Taklimat — Taklimat Eksekutif</div>
<div class="toc-sub">7.1 Memilih Skop Taklimat</div>
<div class="toc-sub">7.2 Membaca Kandungan Taklimat</div>
<div class="toc-sub">7.3 Mengeksport ke Excel dan PDF</div>
<div class="toc-row"><span class="toc-num">8.</span> Panduan Kod Warna</div>
<div class="toc-row"><span class="toc-num">9.</span> Glosari Istilah</div>
<div class="toc-row"><span class="toc-num">10.</span> Soalan Lazim (FAQ)</div>

<div class="pagebreak"></div>

<!-- ============================================================ 1. INTRO -->
<h1>1. Pengenalan Pusat Simulasi</h1>

<p>
    <strong>Pusat Simulasi Pilihanraya</strong> ialah bahagian dalam modul Pilihanraya SISDA yang menyediakan
    alat ramalan, simulasi, dan pelaporan berasaskan data culaan pengundi serta rekod daftar pemilih rasmi.
    Ia direka khas untuk membantu pasukan strategik membuat keputusan berasaskan data secara masa nyata.
</p>

<div class="box box-blue">
    <div class="box-title">Sumber Data</div>
    Semua analisis menggunakan gabungan dua sumber: <strong>(1) Daftar Pemilih Rasmi (DPR)</strong> yang
    dimuat naik melalui halaman Upload Database, dan <strong>(2) rekod culaan pengundi</strong> daripada
    modul Hasil Culaan dan Data Pengundi. Tiada data pengundi individu (PII) didedahkan kepada AI — hanya
    agregat dikongsi.
</div>

<p>Pusat Simulasi mengandungi <strong>5 tab</strong> dengan fungsi berbeza:</p>

<table>
    <thead>
        <tr>
            <th>Tab</th>
            <th>Fungsi</th>
            <th>Jenis Analisis</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td class="col-label">Ramalan</td>
            <td>Jana ramalan kemenangan oleh Claude AI atau model deterministik</td>
            <td>Automatik / AI</td>
        </tr>
        <tr>
            <td class="col-label">What-If</td>
            <td>Laraskan slider dan lihat kesan senario secara langsung</td>
            <td>Interaktif / Sisi Pelanggan</td>
        </tr>
        <tr>
            <td class="col-label">War Gaming</td>
            <td>Soal enjin AI tentang senario hipotetikal dalam bahasa semula jadi</td>
            <td>Soal Jawab AI</td>
        </tr>
        <tr>
            <td class="col-label">Sumber</td>
            <td>Dapatkan cadangan pengagihan sumber kempen berdasarkan kerusi prioriti</td>
            <td>Cadangan AI</td>
        </tr>
        <tr>
            <td class="col-label">Taklimat</td>
            <td>Jana dokumen taklimat eksekutif untuk peringkat nasional, negeri, parlimen atau KADUN</td>
            <td>Laporan AI / Export</td>
        </tr>
    </tbody>
</table>

<div class="box box-amber">
    <div class="box-title">Penting: Keperluan Data</div>
    Analisis AI memerlukan sekurang-kurangnya satu batch pangkalan data pengundi yang <strong>aktif</strong>.
    Jika tiada batch aktif, sistem akan memaparkan amaran. Pergi ke
    <strong>Upload Database</strong> untuk memuat naik dan mengaktifkan data DPR terlebih dahulu.
</div>

<!-- ============================================================ 2. FILTER BAR -->
<h1>2. Bar Penapis Global</h1>

<p>
    Bar penapis di bahagian atas halaman membolehkan anda menyempitkan analisis kepada kawasan geografi
    tertentu. Penapis ini mempengaruhi semua tab kecuali <em>Taklimat</em> (yang mempunyai pemilih skop
    sendiri).
</p>

<table>
    <thead>
        <tr>
            <th style="width:22%;">Medan</th>
            <th style="width:42%;">Penerangan</th>
            <th>Nota</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td class="col-label">Negeri</td>
            <td>Tapis mengikut negeri. Memilih negeri akan memuat semula senarai Parlimen dan KADUN secara automatik.</td>
            <td class="col-tip">Kosongkan untuk paparan seluruh negara.</td>
        </tr>
        <tr>
            <td class="col-label">Parlimen</td>
            <td>Tapis mengikut kawasan Parlimen (Bandar). Akan menyempitkan senarai KADUN kepada yang berada di bawah Parlimen tersebut.</td>
            <td class="col-tip">Bergantung kepada pilihan Negeri.</td>
        </tr>
        <tr>
            <td class="col-label">KADUN</td>
            <td>Tapis kepada satu kerusi dewan undangan negeri sahaja. Berguna untuk analisis kerusi tunggal.</td>
            <td class="col-tip">Bergantung kepada pilihan Parlimen.</td>
        </tr>
    </tbody>
</table>

<div class="box">
    <div class="box-title">Cara Penggunaan Terbaik</div>
    Gunakan <strong>tanpa penapis</strong> (skop nasional) untuk ramalan keseluruhan. Gunakan penapis
    <strong>Parlimen</strong> untuk analisis kawasan bagi perancangan kempen tempatan. Penapis
    <strong>KADUN</strong> berguna ketika menyemak kerusi berayun secara individu dalam War Gaming.
</div>

<div class="pagebreak"></div>

<!-- ============================================================ 3. RAMALAN -->
<h1>3. Tab Ramalan — Ramalan AI Pilihanraya</h1>

<p>
    Tab ini menggunakan model bahasa besar Claude AI untuk menganalisis data sentimen, demografi,
    liputan culaan, dan tren minggu demi minggu bagi menghasilkan ramalan kemenangan pilihanraya.
    Klik butang <strong>"Jana Ramalan AI"</strong> untuk memulakan analisis (masa pemprosesan: 30–120 saat).
</p>

<h2>3.1 Tolok Kebarangkalian (Gauge)</h2>

<p>Tiga tolok bulatan dipaparkan di bahagian atas selepas ramalan dijana:</p>

<table>
    <thead>
        <tr>
            <th style="width:30%;">Tolok</th>
            <th style="width:40%;">Maksud</th>
            <th>Cara Membaca</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td class="col-label">
                <span class="swatch sw-putih"></span>Kebarangkalian PH Menang
            </td>
            <td>
                Peratusan keyakinan AI bahawa PH akan memenangi majoriti kerusi dalam skop semasa berdasarkan
                data culaan, sentimen, dan tren terkini.
            </td>
            <td class="col-tip">
                <strong>&gt;70%</strong> — Kedudukan kukuh.<br>
                <strong>50–70%</strong> — Berayun, perlu perhatian.<br>
                <strong>&lt;50%</strong> — Situasi kritikal.
            </td>
        </tr>
        <tr>
            <td class="col-label">
                <span class="swatch sw-amber"></span>Kebarangkalian Berayun
            </td>
            <td>
                Kemungkinan bahawa keputusan akhir masih boleh berubah arah — menunjukkan tahap
                ketidaktentuan keseluruhan. Dikira berdasarkan nisbah kerusi berayun (skor 45–54)
                dan liputan culaan yang rendah.
            </td>
            <td class="col-tip">
                Nilai tinggi bermaksud ramai kerusi belum pasti. Fokus sumber kempen di sini.
            </td>
        </tr>
        <tr>
            <td class="col-label">
                <span class="swatch sw-hitam"></span>Skor Risiko
            </td>
            <td>
                Tahap risiko keseluruhan gabungan pelbagai faktor: kerusi kritikal, amaran awal aktif,
                liputan culaan rendah, dan momentum negatif. Skor lebih tinggi = risiko lebih besar.
            </td>
            <td class="col-tip">
                <strong>&lt;30</strong> — Risiko rendah.<br>
                <strong>30–60</strong> — Sederhana, pantau.<br>
                <strong>&gt;60</strong> — Tindakan segera diperlukan.
            </td>
        </tr>
    </tbody>
</table>

<h2>3.2 Kad KPI Utama</h2>

<p>Tiga kad ringkasan di bawah tolok memberikan angka strategik tambahan:</p>

<table>
    <thead>
        <tr>
            <th style="width:30%;">Kad</th>
            <th style="width:44%;">Penerangan</th>
            <th>Interpretasi</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td class="col-label">
                <span class="swatch sw-hitam"></span>Kebarangkalian Pembangkang
            </td>
            <td>
                Kebarangkalian pihak pembangkang memenangi majoriti kerusi. Ini adalah <em>komplemen</em>
                kepada Kebarangkalian PH Menang. Kedua-duanya sentiasa berjumlah 100%.
            </td>
            <td class="col-tip">
                PH 68% + Pembangkang 32% = PH dijangka menang dengan selesa.
            </td>
        </tr>
        <tr>
            <td class="col-label">
                <span class="swatch sw-blue"></span>Unjuran Majoriti Kerusi
            </td>
            <td>
                Bilangan kerusi lebih yang dijangka dimenangi berbanding pembangkang. Nilai positif (+)
                bermaksud kemenangan; negatif (−) bermaksud kerugian majoriti.
            </td>
            <td class="col-tip">
                <strong>+8</strong> bermaksud PH dijangka menang 8 kerusi lebih daripada pembangkang.
            </td>
        </tr>
        <tr>
            <td class="col-label">
                <span class="swatch sw-violet"></span>Tahap Keyakinan
            </td>
            <td>
                Keyakinan AI terhadap ketepatan ramalannya sendiri, berdasarkan kecukupan data culaan
                dan konsistensi tren.
            </td>
            <td class="col-tip">
                <strong>TINGGI</strong> — Data mencukupi, ramalan boleh dipercayai.<br>
                <strong>SEDERHANA</strong> — Data sederhana, ambil berhati-hati.<br>
                <strong>RENDAH</strong> — Liputan culaan tipis, ramalan adalah anggaran kasar.
            </td>
        </tr>
    </tbody>
</table>

<h2>3.3 Analisis Strategik</h2>

<p>
    Bahagian teks naratif yang ditulis oleh Claude AI merangkum gambaran keseluruhan situasi pilihanraya
    dalam Bahasa Malaysia. Ia menjelaskan faktor-faktor penentu utama, kawasan yang memerlukan perhatian,
    dan konteks di sebalik angka-angka yang dipaparkan.
</p>

<div class="box">
    <div class="box-title">Cara Membaca Analisis Strategik</div>
    Baca bahagian ini sebagai ringkasan eksekutif. Ia tidak menggantikan data mentah tetapi memberikan
    interpretasi kontekstual yang membantu mesyuarat strategik dan pembentangan kepada pemimpin.
</div>

<h2>3.4 Jadual Unjuran Kerusi Utama</h2>

<p>
    Jadual ini menyenaraikan sehingga 20 kerusi yang paling signifikan dari sudut strategik, diklasifikasikan
    oleh AI mengikut kepentingan dan kebarangkalian.
</p>

<table>
    <thead>
        <tr>
            <th style="width:18%;">Lajur</th>
            <th>Penerangan</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td class="col-label">Kerusi</td>
            <td>Nama kawasan KADUN yang dianalisis.</td>
        </tr>
        <tr>
            <td class="col-label">Kebarangkalian PH</td>
            <td>
                Bar dan peratusan menunjukkan keyakinan AI bahawa PH akan memenangi kerusi ini.
                Bar <span class="swatch sw-putih"></span> hijau bermaksud &gt;50%; bar
                <span class="swatch sw-hitam"></span> merah bermaksud &lt;50%.
            </td>
        </tr>
        <tr>
            <td class="col-label">Kategori</td>
            <td>
                Klasifikasi strategik AI untuk kerusi tersebut. Contoh: <em>Selamat</em>,
                <em>Cenderung Kuat</em>, <em>Berayun</em>, <em>Kritikal</em>, <em>Risiko Kalah</em>.
            </td>
        </tr>
        <tr>
            <td class="col-label">Catatan</td>
            <td>
                Ulasan ringkas AI mengenai faktor penentu utama untuk kerusi tersebut — contohnya
                liputan culaan rendah, tren sentimen, atau komposisi demografi.
            </td>
        </tr>
    </tbody>
</table>

<h2>3.5 Penunjuk Mod Sandaran (Fallback)</h2>

<p>
    Jika Claude AI tidak tersedia (tiada kunci API atau had kadar tercapai), sistem secara automatik
    menggunakan <strong>model logistik deterministik</strong> untuk menghasilkan ramalan berdasarkan
    data skor kesihatan kerusi. Banner amaran kuning akan muncul:
</p>

<div class="box box-amber">
    "AI tidak tersedia — unjuran deterministik dipaparkan. Aktifkan Tetapan Claude AI untuk analisis penuh."
</div>

<p>
    Ramalan sandaran ini masih berguna — ia menggunakan formula logistik berasaskan nisbah putih/hitam/kelabu
    dan liputan culaan. Namun, ia tidak mengandungi naratif atau catatan kerusi dari AI.
</p>

<div class="pagebreak"></div>

<!-- ============================================================ 4. WHAT-IF -->
<h1>4. Tab What-If — Simulasi Senario</h1>

<p>
    Tab What-If membenarkan anda mensimulasikan kesan perubahan faktor-faktor kempen secara masa nyata
    <strong>tanpa sebarang permintaan ke pelayan</strong>. Gerakkan slider dan jadual akan dikemas kini
    serta-merta. Model ini menggunakan formula logistik yang sama dengan ramalan sandaran, memastikan
    konsistensi.
</p>

<h2>4.1 Panel Kawalan Slider</h2>

<p>Slider dibahagikan kepada tiga kumpulan:</p>

<h3>Kumpulan A: Anjakan Kaum</h3>

<table>
    <thead>
        <tr>
            <th style="width:28%;">Slider</th>
            <th style="width:44%;">Penerangan</th>
            <th>Julat &amp; Impak</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td class="col-label">Anjakan Melayu</td>
            <td>
                Anggaran perubahan dalam sokongan pengundi Melayu kepada PH berbanding asas semasa.
                Nilai positif bermaksud lebih ramai pengundi Melayu beralih ke PH; negatif bermaksud
                mereka beralih ke pembangkang.
            </td>
            <td class="col-tip">
                −20 hingga +20 mata peratusan.<br>
                Kesan paling besar di kawasan majoritinya Melayu.
            </td>
        </tr>
        <tr>
            <td class="col-label">Anjakan Cina</td>
            <td>
                Perubahan sokongan pengundi Cina kepada PH. Kesan paling ketara di kawasan bandar
                dengan majoriti pengundi Cina.
            </td>
            <td class="col-tip">
                −20 hingga +20 mata peratusan.
            </td>
        </tr>
        <tr>
            <td class="col-label">Anjakan India</td>
            <td>
                Perubahan sokongan pengundi India kepada PH. Kesan paling ketara di kawasan ladang
                atau estet dengan komuniti India yang besar.
            </td>
            <td class="col-tip">
                −20 hingga +20 mata peratusan.
            </td>
        </tr>
    </tbody>
</table>

<div class="box">
    <div class="box-title">Cara Sistem Mengira Anjakan Kaum</div>
    Sistem mengesan komposisi kaum sebenar bagi setiap kerusi daripada data culaan. Anjakan
    dikenakan secara berkadaran mengikut peratusan kaum tersebut dalam daftar pemilih kerusi itu.
    Contoh: Anjakan Melayu +10 pt di kerusi dengan 80% pengundi Melayu akan memberi kesan lebih besar
    daripada di kerusi dengan 30% pengundi Melayu.
</div>

<h3>Kumpulan B: Keluar Mengundi (Turnout)</h3>

<table>
    <thead>
        <tr>
            <th style="width:28%;">Slider</th>
            <th style="width:44%;">Penerangan</th>
            <th>Julat &amp; Impak</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td class="col-label">Belia (18–29 tahun)</td>
            <td>
                Anggaran peratusan pengundi berusia 18–29 tahun yang dijangka keluar mengundi.
                Kadar lalai adalah 70%. Pengundi belia secara amnya lebih menyokong PH dalam data
                culaan semasa.
            </td>
            <td class="col-tip">
                40% hingga 95%.<br>
                Tingkatkan jika ada program UNDI18 atau mobilisasi belia.
            </td>
        </tr>
        <tr>
            <td class="col-label">Warga Emas (50+)</td>
            <td>
                Anggaran peratusan pengundi berusia 50 tahun ke atas yang dijangka keluar mengundi.
                Kadar lalai adalah 80%. Kumpulan ini secara umumnya lebih konsisten keluar mengundi.
            </td>
            <td class="col-tip">
                40% hingga 95%.<br>
                Kurangkan jika ada cabaran pengangkutan atau cuaca.
            </td>
        </tr>
    </tbody>
</table>

<h3>Kumpulan C: Jentera Kempen</h3>

<table>
    <thead>
        <tr>
            <th style="width:28%;">Slider</th>
            <th style="width:44%;">Penerangan</th>
            <th>Julat &amp; Impak</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td class="col-label">Penukaran Atas Pagar</td>
            <td>
                Peratusan pengundi yang tidak menyatakan pilihan (kelabu / atas pagar) yang
                dapat "ditukar" atau dimobilisasi melalui usaha kempen. 0% bermaksud tiada pengundi
                kelabu bergerak; 100% bermaksud semua pengundi kelabu keluar mengundi.
            </td>
            <td class="col-tip">
                0% hingga 100%.<br>
                Nilai realistik: 20–50% dalam tempoh kempen biasa.
            </td>
        </tr>
        <tr>
            <td class="col-label">Keberkesanan Kempen (→ PH)</td>
            <td>
                Daripada pengundi kelabu yang berjaya "ditukar" (lihat slider atas), berapa peratus
                yang akan menyokong PH. 50% bermaksud separo ke PH dan separo ke pembangkang.
                100% bermaksud semua pengundi yang ditukar pergi ke PH.
            </td>
            <td class="col-tip">
                0% hingga 100%.<br>
                Gunakan 50% sebagai titik neutral dan laraskan berdasarkan kekuatan mesej kempen.
            </td>
        </tr>
    </tbody>
</table>

<div class="box box-green">
    <div class="box-title">Contoh Senario: Serangan Hari Mengundi</div>
    Untuk mensimulasikan kempen hari mengundi yang agresif: tetapkan
    <strong>Penukaran Atas Pagar = 60%</strong> dan <strong>Keberkesanan Kempen = 65%</strong>.
    Ini bermaksud 60% pengundi kelabu turun mengundi, dengan 65% daripada mereka menyokong PH.
</div>

<h2>4.2 Kad Ringkasan Nasional</h2>

<p>Empat kad di bahagian atas menunjukkan keputusan simulasi secara keseluruhan:</p>

<table>
    <thead>
        <tr>
            <th style="width:28%;">Kad</th>
            <th>Penerangan</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td class="col-label">Kerusi Dimenangi</td>
            <td>
                Bilangan kerusi yang dijangka dimenangi PH (P(Menang) &gt; 50%) berbanding jumlah
                keseluruhan kerusi dalam skop. Format: <em>Menang / Jumlah</em>. Contoh: 18/28.
            </td>
        </tr>
        <tr>
            <td class="col-label">Jangkaan Kerusi</td>
            <td>
                Angka kerusi yang lebih tepat berdasarkan jumlah kebarangkalian semua kerusi
                (bukan sekadar kiraan menang/kalah). Contoh: jika 5 kerusi masing-masing ada P(Menang)
                60%, jangkaan = 3.0 (bukan 5). Lebih konservatif daripada kiraan "Dimenangi".
            </td>
        </tr>
        <tr>
            <td class="col-label">Majoriti</td>
            <td>
                Perbezaan antara kerusi yang dijangka dimenangi PH dengan kerusi yang dijangka
                dimenangi pembangkang. Nilai positif (hijau) bermaksud majoriti; negatif (merah)
                bermaksud kerugian.
            </td>
        </tr>
        <tr>
            <td class="col-label">Undi Popular PH</td>
            <td>
                Peratusan undi popular keseluruhan PH (putih) dalam senario semasa, berwajaran
                mengikut saiz daftar pemilih setiap kerusi. Sub-label menunjukkan peratusan
                pembangkang (hitam).
            </td>
        </tr>
    </tbody>
</table>

<h2>4.3 Jadual Unjuran Mengikut Kerusi</h2>

<p>
    Jadual terperinci di sebelah kanan disusun mengikut <em>tahap persaingan</em> — kerusi paling
    rapat di atas, kerusi paling selamat di bawah. Ini membantu anda mengenal pasti kerusi yang
    paling memerlukan perhatian.
</p>

<table>
    <thead>
        <tr>
            <th style="width:18%;">Lajur</th>
            <th>Penerangan</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td class="col-label">KADUN</td>
            <td>
                Nama kawasan. Tanda <strong>(*)</strong> di selepas nama bermaksud data culaan untuk
                kerusi ini adalah <em>nipis</em> (kurang daripada 30 rekod culaan unik). Kebarangkalian
                dikecilkan secara automatik ke arah 50% untuk mencerminkan ketidakpastian data.
            </td>
        </tr>
        <tr>
            <td class="col-label">Asas P / K / H</td>
            <td>
                Agihan sentimen <em>sebelum</em> sebarang slider dilaraskan, berdasarkan data culaan
                sebenar. P = Putih (PH), K = Kelabu (atas pagar), H = Hitam (pembangkang).
                Ketiga-tiganya berjumlah 100%.
            </td>
        </tr>
        <tr>
            <td class="col-label">Senario P / K / H</td>
            <td>
                Agihan sentimen <em>selepas</em> slider dilaraskan. Warna hijau untuk P (putih),
                kelabu untuk K, merah untuk H. Bandingkan dengan lajur Asas untuk melihat kesan
                perubahan slider anda.
            </td>
        </tr>
        <tr>
            <td class="col-label">Margin</td>
            <td>
                Perbezaan antara bahagian putih dan hitam dalam senario: <em>P − H</em>.
                Warna hijau bermaksud PH unggul; merah bermaksud pembangkang unggul.
                Contoh: +12.5 bermaksud PH ada 12.5 mata peratusan lebih daripada pembangkang.
            </td>
        </tr>
        <tr>
            <td class="col-label">P(Menang)</td>
            <td>
                Kebarangkalian akhir PH memenangi kerusi ini dalam senario semasa, dikira
                menggunakan formula logistik <em>p = 0.5 + (logistic(12 × margin) − 0.5) × keyakinan</em>.
                Badge hijau = &gt;50% (dijangka menang); badge merah = &lt;50% (berisiko kalah).
            </td>
        </tr>
    </tbody>
</table>

<div class="box box-amber">
    <div class="box-title">Memahami Formula Kebarangkalian Logistik</div>
    Sistem tidak hanya melihat siapa yang lebih banyak dalam senario. Sebaliknya, ia menggunakan
    fungsi logistik yang <em>mampat</em> margin ke dalam kebarangkalian antara 0–100%. Tambahan pula,
    kebarangkalian disusutkan (<em>shrunk toward 50%</em>) berdasarkan liputan culaan — kerusi dengan
    kurang daripada 30% penduduk dicuali akan mendapat kebarangkalian lebih hampir kepada 50%
    berbanding kerusi dengan liputan penuh.
</div>

<div class="pagebreak"></div>

<!-- ============================================================ 5. WAR GAMING -->
<h1>5. Tab War Gaming — Soal Jawab AI Strategik</h1>

<p>
    Tab War Gaming membolehkan anda menanya Claude AI soalan dalam bahasa semula jadi tentang senario
    pilihanraya hipotetikal. AI akan menganalisis data culaan agregat, skor kerusi semasa, dan tetapan
    slider What-If anda untuk memberikan respons yang bersandarkan data.
</p>

<div class="box box-blue">
    <div class="box-title">Hubungan dengan Tab What-If</div>
    Slider yang anda tetapkan dalam tab What-If <strong>turut dihantar</strong> ke War Gaming.
    Ini bermaksud soalan anda akan dianalisis dalam konteks senario slider semasa anda, bukan
    sahaja data asas. Tetapkan slider terlebih dahulu, kemudian gunakan War Gaming untuk soalan
    yang lebih mendalam.
</div>

<h2>5.1 Cara Bertanya</h2>

<p>Terdapat dua cara untuk mengemukakan soalan:</p>

<ol>
    <li>
        <strong>Butang cadangan pantas</strong> — Klik mana-mana soalan prasyarat yang dipaparkan
        untuk bertanya tanpa menaip. Soalan ini direka untuk senario kempen yang paling lazim
        ditanya.
    </li>
    <li>
        <strong>Kotak teks</strong> — Taip soalan anda sendiri sehingga 1,000 aksara dan tekan
        butang <em>Tanya</em> atau kekunci Enter.
    </li>
</ol>

<p>Contoh soalan berkesan:</p>

<table>
    <thead>
        <tr>
            <th>Soalan</th>
            <th>Kegunaan</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>"Apa berlaku jika sokongan Melayu meningkat 5%?"</td>
            <td>Mengkaji impak anjakan sokongan kaum tertentu</td>
        </tr>
        <tr>
            <td>"Apa berlaku jika keluar mengundi jatuh 10%?"</td>
            <td>Mensimulasikan senario keluar mengundi rendah</td>
        </tr>
        <tr>
            <td>"Kerusi mana boleh dimenangi dengan usaha tambahan?"</td>
            <td>Mengenal pasti kerusi swing yang boleh diubah</td>
        </tr>
        <tr>
            <td>"Di mana sumber kempen patut difokuskan?"</td>
            <td>Cadangan peruntukan sumber strategik</td>
        </tr>
        <tr>
            <td>"Apakah laluan terpantas ke arah kemenangan?"</td>
            <td>Strategi minimum-kerusi untuk majoriti</td>
        </tr>
    </tbody>
</table>

<h2>5.2 Membaca Respons AI</h2>

<p>Setiap respons mengandungi tiga bahagian:</p>

<table>
    <thead>
        <tr>
            <th style="width:22%;">Bahagian</th>
            <th>Penerangan</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td class="col-label">Jawapan (answer)</td>
            <td>
                Analisis utama AI dalam Bahasa Malaysia. Menjelaskan impak senario yang ditanya,
                kerusi yang terjejas, dan konteks strategik yang relevan.
            </td>
        </tr>
        <tr>
            <td class="col-label">Kerusi Terjejas</td>
            <td>
                Senarai kerusi yang paling dipengaruhi oleh senario tersebut, ditandakan dengan
                badge warna:<br>
                <span class="swatch sw-putih"></span><strong>Hijau (positif)</strong> — Senario menguntungkan PH di kerusi ini.<br>
                <span class="swatch sw-hitam"></span><strong>Merah (negatif)</strong> — Senario merugikan PH di kerusi ini.<br>
                <span class="swatch sw-kelabu"></span><strong>Kelabu (neutral)</strong> — Kesan minimal atau tidak jelas.<br>
                Jika AI menyatakan anggaran perubahan (contoh: "+5%"), ia akan ditunjukkan di sebelah nama kerusi.
            </td>
        </tr>
        <tr>
            <td class="col-label">Cadangan</td>
            <td>
                Senarai tindakan praktikal yang disyorkan AI berdasarkan analisis. Biasanya 3–6 cadangan
                khusus berkaitan strategi kempen, pengagihan sumber, atau mesej kepada segmen pengundi.
            </td>
        </tr>
    </tbody>
</table>

<div class="box">
    <div class="box-title">Sejarah Soalan</div>
    Semua soalan dan respons dalam sesi semasa disimpan di halaman ini (terbaru di atas). Ia tidak
    disimpan ke pangkalan data. Jika anda refresh halaman, sejarah akan hilang. Eksport ke tab
    Taklimat jika anda ingin menyimpan dapatan penting.
</div>

<div class="pagebreak"></div>

<!-- ============================================================ 6. SUMBER -->
<h1>6. Tab Sumber — Peruntukan Sumber Kempen</h1>

<p>
    Tab Sumber menggunakan Claude AI untuk menghasilkan cadangan pengagihan sumber kempen —
    jentera, sukarelawan, dan program — mengikut kerusi mengikut keutamaan. Analisis dilakukan
    berdasarkan skor kesihatan kerusi, liputan culaan, kategori kerusi, dan kerusi yang berpotensi
    berayun.
</p>

<p>Klik <strong>"Jana Cadangan Peruntukan"</strong> untuk memulakan analisis (30–120 saat).</p>

<h2>6.1 Rumusan Strategi</h2>

<p>
    Bahagian teks di bahagian atas memberikan gambaran keseluruhan tentang strategi peruntukan sumber
    yang disyorkan — termasuk logik di sebalik keutamaan, kerusi yang perlu dipertahankan, dan kawasan
    yang berpeluang direbut.
</p>

<h2>6.2 Kad Peruntukan</h2>

<p>Setiap kerusi yang disenaraikan mempunyai kad peruntukan dengan maklumat berikut:</p>

<table>
    <thead>
        <tr>
            <th style="width:25%;">Elemen Kad</th>
            <th>Penerangan</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td class="col-label">Nombor &amp; Nama Kawasan</td>
            <td>
                Nombor ranking keutamaan (#1 = tertinggi) dan nama kawasan KADUN atau Parlimen.
                Kerusi ranking atas adalah yang paling memerlukan peruntukan sumber segera.
            </td>
        </tr>
        <tr>
            <td class="col-label">Skor Keutamaan</td>
            <td>
                Angka 0–100 yang dikira AI berdasarkan gabungan: sejauh mana kerusi itu boleh dipengaruhi
                (berayun), saiz daftar pemilih, liputan culaan semasa, dan kebarangkalian kemenangan.
                Bar hijau menggambarkan skor secara visual.
            </td>
        </tr>
        <tr>
            <td class="col-label">Impak Dijangka</td>
            <td>
                Deskripsi AI tentang apakah yang dijangka berlaku jika sumber ditumpukan di kawasan ini —
                contohnya peratusan kemenangan yang boleh ditambah, atau kelompok pengundi yang boleh
                dimobilisasi.
            </td>
        </tr>
        <tr>
            <td class="col-label">Tindakan Disyorkan</td>
            <td>
                Arahan tindakan khusus yang disyorkan untuk kawasan ini — contohnya
                "Tingkatkan canvassing di Lokaliti X", "Fokus mesej kepada pengundi warga emas",
                atau "Gerakkan 50 sukarelawan pada minggu terakhir".
            </td>
        </tr>
    </tbody>
</table>

<div class="box box-green">
    <div class="box-title">Logik Keutamaan AI</div>
    AI memberi keutamaan kepada kerusi <strong>Berayun</strong> dan <strong>Kritikal</strong> berbanding
    kerusi Selamat (tidak memerlukan bantuan) atau kerusi Risiko Kalah (terlalu sukar diubah). Kerusi dengan
    liputan culaan rendah akan diflagskan untuk <em>canvassing</em>, bukan pujukan — kerana masalahnya
    adalah maklumat yang kurang, bukan sokongan yang kurang.
</div>

<div class="pagebreak"></div>

<!-- ============================================================ 7. TAKLIMAT -->
<h1>7. Tab Taklimat — Taklimat Eksekutif</h1>

<p>
    Tab Taklimat menghasilkan dokumen taklimat eksekutif yang boleh dicetak dalam Bahasa Malaysia,
    sesuai untuk dibentangkan kepada ketua pasukan atau pemimpin yang memerlukan ringkasan terstruktur
    tentang kedudukan pilihanraya bagi kawasan tertentu.
</p>

<h2>7.1 Memilih Skop Taklimat</h2>

<p>Terdapat dua tetapan sebelum menekan butang jana:</p>

<table>
    <thead>
        <tr>
            <th style="width:22%;">Medan</th>
            <th>Penerangan</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td class="col-label">Peringkat</td>
            <td>
                Pilih skop geografi taklimat:<br>
                <strong>Nasional</strong> — Merangkumi semua kawasan dalam sistem.<br>
                <strong>Negeri</strong> — Laporan khusus untuk satu negeri.<br>
                <strong>Parlimen</strong> — Laporan untuk kawasan Parlimen/Bandar.<br>
                <strong>KADUN</strong> — Laporan terperinci untuk satu kerusi ADUN.
            </td>
        </tr>
        <tr>
            <td class="col-label">Kawasan</td>
            <td>
                Pilih kawasan spesifik daripada senarai mengikut peringkat yang dipilih.
                Medan ini dilumpuhkan jika peringkat <em>Nasional</em> dipilih. Anda mesti
                memilih kawasan sebelum butang jana diaktifkan (kecuali untuk Nasional).
            </td>
        </tr>
    </tbody>
</table>

<h2>7.2 Membaca Kandungan Taklimat</h2>

<p>Taklimat yang dijana mengandungi struktur berikut:</p>

<table>
    <thead>
        <tr>
            <th style="width:25%;">Bahagian</th>
            <th>Penerangan</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td class="col-label">Tajuk &amp; Tarikh</td>
            <td>
                Tajuk taklimat yang dibina secara automatik dan tarikh penjanaannya. Dipaparkan
                di bahagian atas dokumen.
            </td>
        </tr>
        <tr>
            <td class="col-label">Seksyen 1–6</td>
            <td>
                Setiap seksyen mempunyai tajuk, kandungan teks naratif, dan senarai poin bullet
                untuk fakta-fakta utama. Bilangan seksyen bergantung kepada kedalaman analisis
                yang diperlukan (biasanya 4–6 seksyen).
            </td>
        </tr>
        <tr>
            <td class="col-label">Kesimpulan</td>
            <td>
                Rumusan keseluruhan AI tentang kedudukan dan cadangan umum untuk kawasan tersebut.
            </td>
        </tr>
        <tr>
            <td class="col-label">Tindakan Segera</td>
            <td>
                Panel kuning dengan senarai tindakan yang memerlukan perhatian <em>segera</em>
                sebelum hari mengundi — perkara kritikal yang perlu dilaksanakan dalam tempoh terdekat.
            </td>
        </tr>
    </tbody>
</table>

<div class="box box-amber">
    <div class="box-title">Nota Keselamatan</div>
    Taklimat adalah dokumen sensitif yang mengandungi maklumat strategik pilihanraya. Pastikan
    fail yang dieksport disimpan dengan selamat dan hanya dikongsi dengan pihak yang diberi kuasa.
    Dokumen ditandakan "SULIT" secara automatik.
</div>

<h2>7.3 Mengeksport ke Excel dan PDF</h2>

<p>
    Setelah taklimat dipaparkan, dua butang eksport tersedia di bahagian atas — <strong>Excel</strong>
    dan <strong>PDF</strong>. Kedua-duanya menghantar kandungan taklimat ke pelayan untuk dijana
    dan dimuat turun secara automatik.
</p>

<table>
    <thead>
        <tr>
            <th style="width:18%;">Format</th>
            <th style="width:42%;">Kandungan</th>
            <th>Kegunaan</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td class="col-label">Excel (.xlsx)</td>
            <td>
                Dua helaian: <em>Ringkasan</em> (tajuk, seksyen, kesimpulan, tindakan segera)
                dan <em>Skor Kerusi</em> (jadual kerusi dengan skor, kategori, dan liputan).
            </td>
            <td class="col-tip">
                Untuk analisis lanjut, penapisan, atau berkongsi dalam format data.
            </td>
        </tr>
        <tr>
            <td class="col-label">PDF</td>
            <td>
                Dokumen berformat A4 dengan susun atur cetak, sedia untuk dicetak atau dikongsi
                sebagai lampiran emel. Merangkumi semua seksyen, kesimpulan, tindakan segera,
                dan jadual skor kerusi.
            </td>
            <td class="col-tip">
                Untuk pembentangan, mesyuarat, atau arkib rasmi.
            </td>
        </tr>
    </tbody>
</table>

<div class="pagebreak"></div>

<!-- ============================================================ 8. COLOUR GUIDE -->
<h1>8. Panduan Kod Warna</h1>

<p>
    Sistem menggunakan kod warna yang konsisten di seluruh modul Pilihanraya. Memahami kod warna ini
    membantu anda membaca carta, badge, dan tolok dengan pantas.
</p>

<table>
    <thead>
        <tr>
            <th style="width:15%;">Warna</th>
            <th style="width:20%;">Kategori</th>
            <th>Maksud &amp; Penggunaan</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td><span class="swatch sw-putih"></span> Hijau Zamrud</td>
            <td><strong>Putih / PH</strong></td>
            <td>
                Mewakili sokongan kepada PH (Pakatan Harapan). Digunakan pada: bar putih dalam carta,
                badge P(Menang) &gt;50%, skor kerusi Selamat/Cenderung, angka margin positif,
                bar skor keutamaan peruntukan.
            </td>
        </tr>
        <tr>
            <td><span class="swatch sw-hitam"></span> Merah</td>
            <td><strong>Hitam / Pembangkang</strong></td>
            <td>
                Mewakili sokongan kepada pembangkang. Digunakan pada: bar hitam dalam carta,
                badge P(Menang) &lt;50%, skor kerusi Kritikal/Risiko Kalah, angka margin negatif,
                tolok Skor Risiko.
            </td>
        </tr>
        <tr>
            <td><span class="swatch sw-kelabu"></span> Kelabu</td>
            <td><strong>Kelabu / Atas Pagar</strong></td>
            <td>
                Mewakili pengundi yang belum memihak kepada mana-mana pihak. Digunakan pada:
                bar kelabu dalam carta sentimen, nilai K dalam jadual What-If.
            </td>
        </tr>
        <tr>
            <td><span class="swatch sw-amber"></span> Kuning / Amber</td>
            <td><strong>Amaran / Berayun</strong></td>
            <td>
                Digunakan pada: tolok Kebarangkalian Berayun, banner amaran mod sandaran (fallback),
                panel Tindakan Segera dalam taklimat, amaran awal (early warning).
            </td>
        </tr>
        <tr>
            <td><span class="swatch sw-blue"></span> Biru</td>
            <td><strong>Neutral / Maklumat</strong></td>
            <td>
                Digunakan pada: kad Unjuran Majoriti, maklumat umum, kotak panduan.
            </td>
        </tr>
        <tr>
            <td><span class="swatch sw-violet"></span> Ungu</td>
            <td><strong>Keyakinan AI</strong></td>
            <td>
                Digunakan pada: kad Tahap Keyakinan ramalan AI.
            </td>
        </tr>
    </tbody>
</table>

<h2>Kategori Skor Kesihatan Kerusi</h2>

<table>
    <thead>
        <tr>
            <th style="width:25%;">Kategori</th>
            <th style="width:20%;">Julat Skor</th>
            <th>Tafsiran &amp; Tindakan</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td class="col-label" style="color:#10b981;">Selamat</td>
            <td>75 – 100</td>
            <td>Kedudukan kukuh. Sumber boleh dialihkan ke kerusi lain yang lebih memerlukan.</td>
        </tr>
        <tr>
            <td class="col-label" style="color:#34d399;">Cenderung Kuat</td>
            <td>65 – 74</td>
            <td>Condong kepada PH dengan keyakinan sederhana. Pantau dan kekalkan momentum.</td>
        </tr>
        <tr>
            <td class="col-label" style="color:#a3e635;">Cenderung</td>
            <td>55 – 64</td>
            <td>Sedikit kehadapan PH tetapi masih rentan. Perlu sumber berterusan.</td>
        </tr>
        <tr>
            <td class="col-label" style="color:#f59e0b;">Berayun</td>
            <td>45 – 54</td>
            <td>Terlalu rapat — boleh ke mana-mana arah. Tumpukan sumber di sini.</td>
        </tr>
        <tr>
            <td class="col-label" style="color:#f97316;">Kritikal</td>
            <td>35 – 44</td>
            <td>Pembangkang unggul tetapi masih boleh diubah. Campur tangan strategik diperlukan.</td>
        </tr>
        <tr>
            <td class="col-label" style="color:#ef4444;">Risiko Kalah</td>
            <td>0 – 34</td>
            <td>Kerugian hampir pasti kecuali ada perubahan besar. Nilai kos vs manfaat sebelum tumpukan sumber.</td>
        </tr>
    </tbody>
</table>

<div class="pagebreak"></div>

<!-- ============================================================ 9. GLOSSARY -->
<h1>9. Glosari Istilah</h1>

<table>
    <thead>
        <tr>
            <th style="width:30%;">Istilah</th>
            <th>Takrifan</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td class="col-label">Putih</td>
            <td>Label warna yang digunakan untuk pengundi yang menyokong PH dalam rekod culaan. Bukan nama kaum.</td>
        </tr>
        <tr>
            <td class="col-label">Hitam</td>
            <td>Label warna untuk pengundi yang menyokong pembangkang dalam rekod culaan.</td>
        </tr>
        <tr>
            <td class="col-label">Kelabu</td>
            <td>Pengundi yang tidak memihak, tidak pasti, atau menolak untuk menyatakan pilihan semasa culaan.</td>
        </tr>
        <tr>
            <td class="col-label">Liputan Culaan (Coverage)</td>
            <td>
                Peratusan pengundi dalam daftar pemilih rasmi yang telah dijalankan culaan. Dikira sebagai:
                bilangan pengundi unik yang dicuali ÷ jumlah daftar pemilih. Liputan &lt;30% menyebabkan
                kebarangkalian disusutkan ke 50%.
            </td>
        </tr>
        <tr>
            <td class="col-label">Skor Kesihatan Kerusi</td>
            <td>
                Angka 0–100 yang menggabungkan nisbah putih/hitam/kelabu dan liputan culaan menjadi
                satu ukuran kedudukan kerusi. Formula: 50 + (margin mentah) × keyakinan (berdasarkan liputan).
            </td>
        </tr>
        <tr>
            <td class="col-label">Kebarangkalian Logistik</td>
            <td>
                Formula matematik yang menukar margin pengundian kepada kebarangkalian kemenangan
                antara 0–100%. Menggunakan fungsi sigmoid dengan faktor kekeliruan 12 dan susutan
                keyakinan berdasarkan liputan culaan.
            </td>
        </tr>
        <tr>
            <td class="col-label">Data Culaan / Canvass</td>
            <td>
                Rekod lawatan rumah ke rumah oleh pasukan kempen yang merekodkan pendirian pengundi
                (putih/hitam/kelabu). Disimpan dalam modul Hasil Culaan dan Data Pengundi.
            </td>
        </tr>
        <tr>
            <td class="col-label">DPR / DPPR</td>
            <td>
                Daftar Pemilih Rasmi — senarai rasmi semua pengundi berdaftar yang dimuat naik
                melalui halaman Upload Database dalam format ZIP.
            </td>
        </tr>
        <tr>
            <td class="col-label">Mod Sandaran (Fallback)</td>
            <td>
                Apabila Claude AI tidak tersedia, sistem menggunakan model deterministik logistik
                sebagai alternatif untuk menghasilkan ramalan. Ramalan sandaran masih berguna
                tetapi tidak mempunyai naratif atau konteks AI.
            </td>
        </tr>
        <tr>
            <td class="col-label">Anjakan (Swing)</td>
            <td>
                Perubahan dalam bahagian undian bagi kumpulan tertentu berbanding asas semasa.
                Contoh: Anjakan Melayu +5pt bermaksud 5% pengundi Melayu yang sebelumnya menyokong
                pembangkang kini beralih kepada PH dalam senario simulasi.
            </td>
        </tr>
        <tr>
            <td class="col-label">Atas Pagar / Fence-sitter</td>
            <td>
                Pengundi kelabu yang belum membuat keputusan. Dalam simulasi, slider
                "Penukaran Atas Pagar" menentukan berapa peratus yang dapat dimobilisasi,
                manakala "Keberkesanan Kempen" menentukan ke mana mereka akhirnya pergi.
            </td>
        </tr>
        <tr>
            <td class="col-label">KADUN</td>
            <td>Kawasan Dewan Undangan Negeri — kerusi dewan undangan negeri (ADUN). Unit analisis utama dalam Pusat Simulasi.</td>
        </tr>
        <tr>
            <td class="col-label">Parlimen / Bandar</td>
            <td>Kawasan Parlimen — gabungan beberapa KADUN di bawah satu Ahli Parlimen.</td>
        </tr>
        <tr>
            <td class="col-label">Majoriti</td>
            <td>Dalam konteks Pusat Simulasi: perbezaan antara kerusi yang dijangka dimenangi PH dengan kerusi yang dijangka dimenangi pembangkang.</td>
        </tr>
        <tr>
            <td class="col-label">Jangkaan Kerusi (Expected Seats)</td>
            <td>Jumlah kebarangkalian semua kerusi — lebih tepat daripada kiraan menang/kalah semata-mata kerana mengambil kira ketidakpastian setiap kerusi.</td>
        </tr>
        <tr>
            <td class="col-label">Claude AI</td>
            <td>
                Model bahasa besar dari Anthropic yang digunakan oleh SISDA untuk analisis strategik,
                war gaming, cadangan sumber, dan penjanaan taklimat. Memerlukan kunci API yang dikonfigurasi
                dalam Tetapan Claude AI.
            </td>
        </tr>
    </tbody>
</table>

<div class="pagebreak"></div>

<!-- ============================================================ 10. FAQ -->
<h1>10. Soalan Lazim (FAQ)</h1>

<table>
    <thead>
        <tr>
            <th style="width:45%;">Soalan</th>
            <th>Jawapan</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td class="col-label">Mengapa halaman Pusat Simulasi menunjukkan ralat "500 Internal Server Error"?</td>
            <td>
                Migrasi pangkalan data mungkin belum dijalankan di pelayan. Jalankan
                <code>php artisan migrate</code> di pelayan. Ralat biasanya berlaku apabila jadual
                <em>pilihanraya_forecasts</em> belum wujud.
            </td>
        </tr>
        <tr>
            <td class="col-label">Slider What-If tidak mengubah sebarang keputusan. Mengapa?</td>
            <td>
                Pastikan data culaan (Hasil Culaan atau Data Pengundi) telah dimasukkan untuk
                kawasan yang dipilih. Simulasi memerlukan rekod culaan yang mengandungi
                nilai <em>voter_color</em> (putih/hitam/kelabu). Semak tab War Room &gt; Sentimen.
            </td>
        </tr>
        <tr>
            <td class="col-label">Berapa lama proses Jana Ramalan AI mengambil masa?</td>
            <td>
                Antara 30–120 saat bergantung kepada saiz data dan bebanan pelayan Claude AI.
                Had masa ditetapkan pada 130 saat. Jika melebihi had ini, segarkan halaman dan cuba semula.
            </td>
        </tr>
        <tr>
            <td class="col-label">Apakah yang bermaksud "* data culaan nipis"?</td>
            <td>
                Kerusi tersebut mempunyai kurang daripada 30 pengundi unik yang dicuali. Kebarangkalian
                kemenangan secara automatik disusutkan ke arah 50% untuk mencerminkan ketidakpastian.
                Lakukan lebih banyak culaan di kawasan tersebut untuk mendapatkan unjuran yang lebih tepat.
            </td>
        </tr>
        <tr>
            <td class="col-label">Bolehkah saya menggunakan Pusat Simulasi tanpa DPR (daftar pemilih) aktif?</td>
            <td>
                Ya, tetapi dengan had. Tab What-If dan Ramalan memerlukan sekurang-kurangnya satu
                batch DPR aktif untuk data daftar pemilih. Tanpa DPR, liputan culaan tidak dapat
                dikira dan kebarangkalian akan disusutkan kepada 50% untuk semua kerusi.
            </td>
        </tr>
        <tr>
            <td class="col-label">Adakah soalan War Gaming disimpan dalam sistem?</td>
            <td>
                Tidak. Soalan dan respons hanya wujud dalam sesi pelayar semasa dan akan hilang
                apabila halaman disegar semula. Eksport dapatan penting ke tab Taklimat.
            </td>
        </tr>
        <tr>
            <td class="col-label">Mengapa ramalan berbeza apabila saya menukar penapis Negeri/Parlimen/KADUN?</td>
            <td>
                Setiap ramalan dikira berdasarkan data dalam skop yang dipilih. Mengecilkan skop
                kepada satu KADUN menghasilkan ramalan berdasarkan data kerusi tersebut sahaja,
                bukan keseluruhan. Ini adalah tingkah laku yang betul.
            </td>
        </tr>
        <tr>
            <td class="col-label">Bolehkah ramalan AI dihasilkan semula untuk mendapat versi berbeza?</td>
            <td>
                Ya. Klik semula "Jana Ramalan AI" untuk menjalankan analisis baharu. Setiap
                janaian menggunakan data semasa dan mungkin menghasilkan naratif yang sedikit
                berbeza. Rekod terakhir disimpan dalam pangkalan data untuk rujukan.
            </td>
        </tr>
        <tr>
            <td class="col-label">Bagaimana jika saya menutup tab semasa AI sedang menganalisis?</td>
            <td>
                Analisis di pelayan akan diteruskan tetapi anda tidak akan menerima respons.
                Buka semula halaman dan klik "Jana Ramalan AI" semula — rekod terakhir (jika berjaya)
                akan dimuatkan secara automatik.
            </td>
        </tr>
        <tr>
            <td class="col-label">Apakah perbezaan antara Tab Sumber dan cadangan dalam War Gaming?</td>
            <td>
                Tab Sumber menghasilkan jadual peruntukan sumber yang berstruktur dan terperinci
                dengan skor keutamaan, khusus untuk tujuan perancangan sumber kempen. War Gaming
                pula adalah soal jawab bebas untuk senario hipotetikal — lebih fleksibel tetapi
                kurang terstruktur.
            </td>
        </tr>
    </tbody>
</table>

<hr>

<div class="footer">
    SISDA (Sistem Data Pengundi) &mdash; Manual Pengguna: Pusat Simulasi Pilihanraya &mdash; Versi 1.0 &mdash;
    Dijana: {{ now()->format('d/m/Y H:i') }} &mdash; SULIT — Untuk Kegunaan Dalaman Sahaja
</div>

</body>
</html>
