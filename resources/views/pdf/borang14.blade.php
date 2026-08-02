@php
    // --- helpers -------------------------------------------------------------
    // Serupa bit dengan Borang14Controller::cellKey() — contest dahulu, kerana
    // sel PRU dan PRN boleh berkongsi (pusat, saluran, slot) yang sama.
    $key = fn ($pusat, $saluran, $slot) => $contest . '|' . ($pusat ?? '') . '|' . $saluran . '|' . $slot;
    $nParties = (int) $penjuru;
    $partyNames = [];
    for ($i = 0; $i < $nParties; $i++) {
        $partyNames[$i] = $parties[$i]['nama'] ?? ('Parti ' . ($i + 1));
    }
    $vote = fn ($pusat, $saluran, $slot) => (int) ($votes[$key($pusat, $saluran, $slot)] ?? 0);

    // Flatten to one block per pusat mengundi.
    $blocks = [];
    foreach ($reference['daerah_mengundi'] as $dm) {
        foreach ($dm['pusat_mengundi'] as $p) {
            $berdaftar = $p['jumlah_berdaftar'] ?? array_sum(array_column($p['saluran'], 'berdaftar'));
            $blocks[] = ['dm' => $dm['nama'], 'pusat' => $p['nama'], 'berdaftar' => $berdaftar, 'saluran' => $p['saluran']];
        }
    }
    $nf = fn ($n) => number_format((int) $n);
    // '—' whenever the denominator (berdaftar) is unknown (null) OR zero —
    // the scoresheet has NO registered-voter count, so a missing berdaftar
    // must never be treated as "0 registered" (which would fabricate a
    // turnout percentage of 0%/—% off a lie).
    $pctf = fn ($num, $den) => ($den !== null && $den > 0) ? number_format($num / $den * 100, 1) . '%' : '—';

    // Undi Awal & Undi Pos: one combined row for Buloh Kasap DUN (matches how
    // the votes were saved on-screen), two separate rows otherwise (every
    // other DUN, and every Parlimen — the merge is a DUN-only exception).
    // berdaftar stays NULL (never coerced to 0) when unknown — see below.
    $undiAwalBerdaftar = $reference['undi_awal']['berdaftar'] ?? null;
    $undiPosBerdaftar = $reference['undi_pos']['berdaftar'] ?? null;
    $sumBerdaftarOrNull = fn ($a, $b) => ($a === null && $b === null) ? null : (((int) $a) + ((int) $b));
    $awalPosRows = ($isBulohKasap ?? false)
        ? [['label' => 'UNDI AWAL & POS', 'berdaftar' => $sumBerdaftarOrNull($undiAwalBerdaftar, $undiPosBerdaftar)]]
        : [['label' => 'UNDI AWAL', 'berdaftar' => $undiAwalBerdaftar], ['label' => 'UNDI POS', 'berdaftar' => $undiPosBerdaftar]];

    // Highest value in a row → green (lead), the rest → red (low); an
    // all-zero row stays neutral. Mirrors the on-screen leadStatus() logic.
    $leadStatus = function (array $values) {
        $max = max(array_merge([0], $values));
        if ($max <= 0) {
            return array_fill(0, count($values), 'none');
        }
        return array_map(fn ($v) => $v === $max ? 'lead' : 'low', $values);
    };
    $cellClass = fn ($status) => $status === 'lead' ? 'lead' : ($status === 'low' ? 'low' : '');

    $partyLogos = array_map(fn ($pn) => \App\Support\PartyLogo::dataUri($pn), $partyNames);

    // --- ringkasan muka pertama ----------------------------------------------
    // Dijumlahkan daripada SEL UNDI yang sama seperti jadual terperinci di
    // bawah (bukan daripada mana-mana lajur ringkasan yang disimpan berasingan)
    // supaya muka 1 dan muka-muka seterusnya mustahil bercanggah.
    //
    // Slot 90 (Ditolak) dan 91 (Tak Dimasukkan) TIDAK PERNAH masuk ke dalam
    // jumlah parti — hanya ke dalam Jumlah Keluar — tepat seperti formula
    // pada setiap blok.
    //
    // 'berdaftar' kekal NULL apabila tiada satu pun saluran melaporkannya.
    // Angka 0 di sini akan menghasilkan peratus keluar palsu; '—' ialah
    // jawapan yang betul bagi "tidak diketahui".
    $sumSlots = array_fill(0, $nParties, 0);
    $sumDitolak = 0; $sumTidakMasuk = 0; $sumUndian = 0; $sumKeluar = 0;
    $sumBerdaftar = 0; $sumBerdaftarKnown = false;
    $bilPusat = count($blocks); $bilSaluran = 0;

    $kutip = function ($pusat, $saluran, $berdaftar) use (
        $vote, $nParties, &$sumSlots, &$sumDitolak, &$sumTidakMasuk, &$sumUndian,
        &$sumKeluar, &$sumBerdaftar, &$sumBerdaftarKnown
    ) {
        for ($i = 0; $i < $nParties; $i++) {
            $v = $vote($pusat, $saluran, $i + 1);
            $sumSlots[$i] += $v;
            $sumUndian += $v;
            $sumKeluar += $v;
        }
        $c = $vote($pusat, $saluran, 90);
        $d = $vote($pusat, $saluran, 91);
        $sumDitolak += $c;
        $sumTidakMasuk += $d;
        $sumKeluar += $c + $d;
        if ($berdaftar !== null) {
            $sumBerdaftar += (int) $berdaftar;
            $sumBerdaftarKnown = true;
        }
    };

    foreach ($blocks as $b) {
        foreach ($b['saluran'] as $s) {
            $bilSaluran++;
            $kutip($b['pusat'], (string) $s['no'], array_key_exists('berdaftar', $s) ? $s['berdaftar'] : null);
        }
    }
    foreach ($awalPosRows as $row) {
        $kutip('', $row['label'], $row['berdaftar']);
    }

    $sumBerdaftarNilai = $sumBerdaftarKnown ? $sumBerdaftar : null;
    $sumTakKeluar = $sumBerdaftarKnown ? max(0, $sumBerdaftar - $sumKeluar) : null;

    // Kedudukan calon mengikut undi. Susunan stabil: seri dikekalkan mengikut
    // nombor slot supaya cetakan berulang bagi data yang sama tidak berbeza.
    $ranking = [];
    $adaCalonDariScoreboard = false;
    for ($i = 0; $i < $nParties; $i++) {
        $adaCalonDariScoreboard = $adaCalonDariScoreboard || ! empty($parties[$i]['calon_dari_scoreboard']);
        $ranking[] = [
            'slot'  => $i + 1,
            'idx'   => $i,
            'nama'  => $partyNames[$i],
            'calon' => $parties[$i]['calon'] ?? null,
            'undi'  => $sumSlots[$i],
        ];
    }
    usort($ranking, fn ($a, $b) => ($b['undi'] <=> $a['undi']) ?: ($a['slot'] <=> $b['slot']));

    $adaUndi = $sumUndian > 0;
    // Seri di tempat teratas: TIADA pemenang diisytiharkan, dan majoriti tiada
    // makna. Lebih baik kosong daripada mengisytiharkan pemenang yang salah.
    $seriTeratas = $adaUndi && count($ranking) > 1 && $ranking[1]['undi'] === $ranking[0]['undi'];
    $juara = ($adaUndi && ! $seriTeratas) ? $ranking[0] : null;
    $majoriti = ($juara && count($ranking) > 1) ? $ranking[0]['undi'] - $ranking[1]['undi'] : null;
@endphp
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="utf-8">
    <style>
        * { font-family: 'DejaVu Sans', sans-serif; }
        @page { margin: 28px 44px; }
        body { color: #0f172a; font-size: 9px; }
        .head { text-align: center; margin-bottom: 10px; }
        .head img { height: 46px; margin-bottom: 4px; }
        .head h1 { font-size: 17px; margin: 2px 0; letter-spacing: 2px; }
        .head .geo { font-size: 11px; font-weight: bold; color: #0f172a; margin-top: 2px; }
        .head .sub { font-size: 9px; color: #475569; margin-top: 2px; }
        .legend { text-align: center; font-size: 8px; color: #334155; margin: 4px 0 14px; }
        .legend span { display: inline-block; margin: 0 5px; }
        /* Narrower, centred blocks so the tables don't stretch edge to edge.
           Widened from 84% and padding tightened to fit the Ditolak (C) /
           Tak Dimasukkan (D) / Jumlah Undian columns without wrapping the
           label column (Saluran / UNDI AWAL / UNDI POS / Jumlah Undi). */
        .block { width: 96%; margin: 0 auto 14px; page-break-inside: avoid; }
        .block .title { background: #0f172a; color: #fff; padding: 5px 10px; font-size: 9px; border-radius: 3px 3px 0 0; }
        .block .title .dm { font-weight: bold; }
        .block .title .pm { font-weight: normal; opacity: .85; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #cbd5e1; padding: 4px 6px; text-align: right; }
        th { background: #e2e8f0; font-size: 7px; text-transform: uppercase; letter-spacing: .2px; }
        td.l, th.l { text-align: left; white-space: nowrap; }
        tr.total td { background: #f1f5f9; font-weight: bold; }
        td.lead { background: #d1fae5; color: #065f46; font-weight: bold; }
        td.low { background: #ffe4e6; color: #9f1239; }
        .party-logo { height: 12px; vertical-align: middle; margin-right: 3px; }
        th .party-logo { height: 11px; }
        .special { width: 96%; margin: 16px auto 0; page-break-inside: avoid; }
        .special .title { background: #1e40af; color: #fff; padding: 5px 10px; font-size: 10px; border-radius: 3px 3px 0 0; }
        .foot { width: 96%; margin: 14px auto 0; text-align: right; font-size: 7px; color: #94a3b8; }

        /* --- muka ringkasan (muka 1) --- */
        .ringkas { width: 92%; margin: 0 auto; page-break-after: always; }
        .ringkas h2 { font-size: 13px; letter-spacing: 3px; margin: 14px 0 2px; text-align: center; color: #1e40af; }
        .ringkas .pemenang { border: 2px solid #065f46; background: #ecfdf5; border-radius: 4px;
            padding: 9px 14px; margin: 12px 0; text-align: center; }
        .ringkas .pemenang .lbl { font-size: 8px; letter-spacing: 2px; color: #047857; text-transform: uppercase; }
        .ringkas .pemenang .nm { font-size: 15px; font-weight: bold; color: #064e3b; margin-top: 3px; }
        .ringkas .pemenang .maj { font-size: 10px; color: #065f46; margin-top: 3px; }
        .ringkas .pemenang img { height: 16px; vertical-align: middle; margin-right: 4px; }
        .ringkas .kosong { border: 2px solid #cbd5e1; background: #f8fafc; border-radius: 4px;
            padding: 9px 14px; margin: 12px 0; text-align: center; font-size: 10px; color: #475569; }
        .ringkas table.kedudukan th, .ringkas table.kedudukan td { padding: 6px 8px; font-size: 10px; }
        .ringkas table.kedudukan th { font-size: 8px; }
        .ringkas table.kedudukan tr.juara td { background: #d1fae5; color: #065f46; font-weight: bold; }
        .ringkas .bar { display: inline-block; height: 7px; background: #1e40af; vertical-align: middle; }
        .ringkas .bar-wrap { width: 100%; background: #e2e8f0; height: 7px; }
        .stat { width: 100%; margin-top: 14px; border-collapse: collapse; }
        .stat td { border: 1px solid #cbd5e1; padding: 7px 8px; text-align: center; }
        .stat td .k { font-size: 7px; text-transform: uppercase; letter-spacing: .3px; color: #475569; display: block; }
        .stat td .v { font-size: 13px; font-weight: bold; color: #0f172a; display: block; margin-top: 2px; }
        .ringkas .nota { font-size: 7.5px; color: #64748b; margin-top: 10px; line-height: 1.5; }
    </style>
</head>
<body>
    {{--
        MUKA 1 — RINGKASAN KEPUTUSAN.

        Setiap angka di sini dijumlahkan daripada sel undi yang sama seperti
        jadual terperinci pada muka-muka berikutnya; tiada satu pun ditaip,
        dianggar atau dikarang. Apa yang tidak diketahui dipaparkan '—' dan
        BUKAN 0.
    --}}
    <div class="ringkas">
        <div class="head">
            @if ($logo)<img src="{{ $logo }}" alt="Logo">@endif
            <h1>BORANG 14</h1>
            <div class="geo">
                {{ $reference['negeri'] }} &middot; Parlimen {{ $reference['parlimen'] }}
                @if ($reference['dun'] ?? null)
                    &middot; DUN {{ $reference['dun'] }}
                @endif
            </div>
            <div class="sub">{{ $jenisPr ?? '' }} {{ $tahun ?? '' }} &mdash; {{ $penjuruLabel }}</div>
        </div>

        <h2>RINGKASAN KEPUTUSAN</h2>

        @if ($juara)
            @php $juaraLogo = $partyLogos[$juara['idx']] ?? null; @endphp
            <div class="pemenang">
                <div class="lbl">Undi Tertinggi</div>
                <div class="nm">
                    @if ($juaraLogo)<img src="{{ $juaraLogo }}" alt="">@endif
                    {{ $juara['calon'] ? $juara['calon'] . ' (' . $juara['nama'] . ')' : $juara['nama'] }}
                </div>
                <div class="maj">
                    {{ $nf($juara['undi']) }} undi &middot; {{ $pctf($juara['undi'], $sumUndian) }} daripada undi sah
                    @if ($majoriti !== null)
                        &middot; Majoriti <b>{{ $nf($majoriti) }}</b>
                    @endif
                </div>
            </div>
        @elseif ($seriTeratas)
            <div class="kosong">
                <b>Keputusan SERI di tempat teratas</b> &mdash; dua calon atau lebih memperoleh
                {{ $nf($ranking[0]['undi']) }} undi yang sama banyak. Tiada pemenang diisytiharkan.
            </div>
        @else
            <div class="kosong">
                Belum ada undi direkodkan untuk borang ini &mdash; ringkasan akan terisi
                sebaik sahaja undi saluran dimasukkan.
            </div>
        @endif

        <table class="kedudukan">
            <thead>
                <tr>
                    <th class="l" style="width:26px;">#</th>
                    <th class="l">Parti</th>
                    <th class="l">Calon</th>
                    <th style="width:80px;">Jumlah Undi</th>
                    <th style="width:64px;">% Undi Sah</th>
                    <th class="l" style="width:150px;">Agihan</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($ranking as $n => $r)
                    @php
                        // Bar mengekod NOMBOR YANG SAMA seperti lajur "% Undi Sah"
                        // di sebelahnya (bahagian daripada undi sah), bukan nisbah
                        // kepada pendahulu — dua ukuran berbeza dalam satu baris
                        // akan dibaca sebagai percanggahan.
                        $lebar = $adaUndi ? round($r['undi'] / $sumUndian * 100, 1) : 0;
                    @endphp
                    <tr class="{{ $juara && $r['slot'] === $juara['slot'] ? 'juara' : '' }}">
                        <td class="l">{{ $n + 1 }}</td>
                        <td class="l">
                            @if ($partyLogos[$r['idx']] ?? null)<img class="party-logo" src="{{ $partyLogos[$r['idx']] }}" alt="">@endif
                            {{ $r['nama'] }}
                        </td>
                        <td class="l">{{ $r['calon'] ?: '—' }}</td>
                        <td>{{ $nf($r['undi']) }}</td>
                        <td>{{ $pctf($r['undi'], $sumUndian) }}</td>
                        <td class="l">
                            <div class="bar-wrap"><div class="bar" style="width:{{ $lebar }}%;"></div></div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <table class="stat">
            <tr>
                <td><span class="k">Jumlah Undi Sah</span><span class="v">{{ $nf($sumUndian) }}</span></td>
                <td><span class="k">Undi Ditolak (C)</span><span class="v">{{ $nf($sumDitolak) }}</span></td>
                <td><span class="k">Tak Dimasukkan (D)</span><span class="v">{{ $nf($sumTidakMasuk) }}</span></td>
                <td><span class="k">Jumlah Keluar Mengundi</span><span class="v">{{ $nf($sumKeluar) }}</span></td>
            </tr>
            <tr>
                <td><span class="k">Pengundi Berdaftar</span><span class="v">{{ $sumBerdaftarNilai === null ? '—' : $nf($sumBerdaftarNilai) }}</span></td>
                <td><span class="k">% Keluar Mengundi</span><span class="v">{{ $pctf($sumKeluar, $sumBerdaftarNilai) }}</span></td>
                <td><span class="k">Tidak Keluar</span><span class="v">{{ $sumTakKeluar === null ? '—' : $nf($sumTakKeluar) }}</span></td>
                <td><span class="k">% Tidak Keluar</span><span class="v">{{ $sumTakKeluar === null ? '—' : $pctf($sumTakKeluar, $sumBerdaftarNilai) }}</span></td>
            </tr>
            <tr>
                <td><span class="k">Pusat Mengundi</span><span class="v">{{ $nf($bilPusat) }}</span></td>
                <td><span class="k">Saluran</span><span class="v">{{ $nf($bilSaluran) }}</span></td>
                <td><span class="k">Bilangan Calon</span><span class="v">{{ $nf($nParties) }}</span></td>
                <td><span class="k">Status Borang</span><span class="v" style="font-size:10px;">{{ ($statusBorang ?? null) === 'published' ? 'DITERBITKAN' : 'DRAF' }}</span></td>
            </tr>
        </table>

        <div class="nota">
            Jumlah Keluar Mengundi = undi sah + Ditolak (C) + Tak Dimasukkan (D), termasuk Undi Awal &amp; Undi Pos.
            Tanda &ldquo;&mdash;&rdquo; bermakna angka itu <b>tidak diketahui</b> bagi pilihan raya ini &mdash; bukan sifar.
            @if ($sumBerdaftarNilai === null)
                Tiada saluran melaporkan bilangan pengundi berdaftar, jadi peratus keluar mengundi tidak dapat dikira.
            @endif
            @if ($adaCalonDariScoreboard)
                Sebahagian nama calon diambil daripada papan markah (Scoreboard) kerusi ini kerana ia tidak dinamakan
                pada Borang 14 &mdash; undi kekal dikira mengikut slot, bukan mengikut nama.
            @endif
            @if (($reference['source'] ?? null) === 'dpt_estimate')
                Bilangan Berdaftar dianggarkan daripada data DPT (ikut Lokaliti), bukan pecahan Saluran rasmi gazet SPR.
            @endif
            @if ($inheritedFrom ?? null)
                Struktur saluran diwarisi daripada {{ strtoupper($inheritedFrom['jenis_pr']) }} {{ $inheritedFrom['tahun'] }}.
            @endif
            Pecahan penuh mengikut Daerah Mengundi, Pusat Mengundi dan Saluran ada pada muka berikutnya.
        </div>

        <div class="foot">Dijana pada {{ now()->format('d/m/Y H:i') }} &mdash; SISDA</div>
    </div>

    <div class="head">
        @if ($logo)<img src="{{ $logo }}" alt="Logo">@endif
        <h1>BORANG 14</h1>
        <div class="geo">
            {{ $reference['negeri'] }} &middot; Parlimen {{ $reference['parlimen'] }}
            @if ($reference['dun'] ?? null)
                &middot; DUN {{ $reference['dun'] }}
            @endif
        </div>
        <div class="sub">Penyata Undi Mengikut Saluran &mdash; {{ $penjuruLabel }}</div>
        {{--
            ASAL STRUKTUR mesti SENTIASA dinyatakan. PDF ialah artifak bercetak
            yang hidup lebih lama daripada skrin, jadi baris ini lebih penting di
            sini, bukan kurang: sesiapa yang memegang cetakan tidak boleh
            bertanya kepada sistem dari mana strukturnya datang.

            Maknanya terkandung sepenuhnya dalam PERKATAAN ("dianggarkan" lawan
            "angka sebenar") dan diperkuat dengan tebal — bukan pada warna
            sahaja, kerana cetakan hitam-putih dan fotokopi lazim di pusat
            penjumlahan.

            Ujian `source` di sini mesti SAMA PERSIS dengan
            resources/js/Pages/Pilihanraya/borang14/KeyinTab.jsx (~baris 821),
            yang memapar dua sepanduk yang sama pada skrin. Jika satu pihak
            berubah tanpa satu lagi, skrin dan cetakan akan menceritakan asal
            yang BERBEZA bagi borang yang sama.
        --}}
        @if (($reference['source'] ?? null) === 'dpt_estimate')
            <div class="sub" style="color:#b45309;">Pusat Mengundi &amp; Berdaftar dianggarkan daripada data DPT (ikut Lokaliti) &mdash; bukan pecahan Saluran rasmi gazet SPR.</div>
        @endif
        @if (($reference['source'] ?? null) === 'dpt_sebenar')
            <div class="sub" style="color:#065f46; font-weight:bold;">Struktur Daerah Mengundi, Pusat Mengundi &amp; Saluran diambil terus daripada fail DPPR/DPI yang dimuat naik &mdash; pecahan Saluran dan jumlah Berdaftar ialah angka sebenar, bukan anggaran.</div>
        @endif
        @if ($inheritedFrom ?? null)
            <div class="sub" style="color:#b45309;">Struktur saluran diwarisi daripada {{ strtoupper($inheritedFrom['jenis_pr']) }} {{ $inheritedFrom['tahun'] }} &mdash; bilangan pengundi berdaftar tidak diketahui untuk pilihan raya ini.</div>
        @endif
    </div>

    <div class="legend">
        @foreach ($partyNames as $i => $pn)
            <span><b>Parti {{ $i + 1 }}:</b> @if ($partyLogos[$i])<img class="party-logo" src="{{ $partyLogos[$i] }}" alt="">@endif {{ $pn }}</span>
        @endforeach
    </div>

    @foreach ($blocks as $b)
        @php
            $totSlots = array_fill(0, $nParties, 0);
            $totDitolak = 0; $totTidakMasuk = 0; $totUndian = 0; $totKeluar = 0;
            $totBerdaftar = 0; $totBerdaftarKnown = false;
        @endphp
        <div class="block">
            <div class="title"><span class="dm">DM: {{ $b['dm'] }}</span> &nbsp;|&nbsp; <span class="pm">Pusat Mengundi: {{ $b['pusat'] }}</span></div>
            {{-- class="saluran" menandakan jadual pecahan saluran (bukan jadual
                 ringkasan muka 1) — Borang14PdfTest memilihnya dengan nama ini
                 supaya menambah jadual baharu di mana-mana tidak memecahkan ujian. --}}
            <table class="saluran">
                <thead>
                    <tr>
                        <th class="l">Saluran</th>
                        @foreach ($partyNames as $i => $pn)<th>@if ($partyLogos[$i])<img class="party-logo" src="{{ $partyLogos[$i] }}" alt="">@endif{{ $pn }}</th>@endforeach
                        <th>Ditolak (C)</th>
                        <th>Tak Dimasukkan (D)</th>
                        <th>Jumlah Undian</th>
                        <th>Jumlah Keluar</th>
                        <th>Berdaftar</th>
                        <th>% Turnout</th>
                        <th>Tak Keluar</th>
                        <th>% Tak Keluar</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($b['saluran'] as $s)
                        @php
                            $slots = [];
                            $undian = 0;
                            for ($i = 0; $i < $nParties; $i++) {
                                $v = $vote($b['pusat'], (string) $s['no'], $i + 1);
                                $slots[$i] = $v; $undian += $v; $totSlots[$i] += $v;
                            }
                            $ditolak = $vote($b['pusat'], (string) $s['no'], 90);
                            $tidakMasuk = $vote($b['pusat'], (string) $s['no'], 91);
                            // Slots 90/91 are NEVER summed into party totals/lead —
                            // only into Jumlah Keluar, matching the screen's formula
                            // (Borang14Form.jsx: keluar = undian + ditolak + tidakMasuk).
                            $keluar = $undian + $ditolak + $tidakMasuk;
                            $berdaftarKnown = array_key_exists('berdaftar', $s) && $s['berdaftar'] !== null;
                            $berdaftar = $berdaftarKnown ? (int) $s['berdaftar'] : null;
                            $totDitolak += $ditolak; $totTidakMasuk += $tidakMasuk; $totUndian += $undian; $totKeluar += $keluar;
                            if ($berdaftarKnown) { $totBerdaftar += $berdaftar; $totBerdaftarKnown = true; }
                            $status = $leadStatus($slots);
                            $takKeluar = $berdaftarKnown ? max(0, $berdaftar - $keluar) : null;
                        @endphp
                        <tr>
                            <td class="l">Saluran {{ $s['no'] }}</td>
                            @foreach ($slots as $i => $v)<td class="{{ $cellClass($status[$i]) }}">{{ $nf($v) }}</td>@endforeach
                            <td>{{ $nf($ditolak) }}</td>
                            <td>{{ $nf($tidakMasuk) }}</td>
                            <td>{{ $nf($undian) }}</td>
                            <td>{{ $nf($keluar) }}</td>
                            <td>{{ $berdaftarKnown ? $nf($berdaftar) : '—' }}</td>
                            <td>{{ $pctf($keluar, $berdaftar) }}</td>
                            <td>{{ $takKeluar === null ? '—' : $nf($takKeluar) }}</td>
                            <td>{{ $takKeluar === null ? '—' : $pctf($takKeluar, $berdaftar) }}</td>
                        </tr>
                    @endforeach
                    @php
                        $totStatus = $leadStatus($totSlots);
                        $totTakKeluar = $totBerdaftarKnown ? max(0, $totBerdaftar - $totKeluar) : null;
                    @endphp
                    <tr class="total">
                        <td class="l">Jumlah Undi</td>
                        @foreach ($totSlots as $i => $v)<td class="{{ $cellClass($totStatus[$i]) }}">{{ $nf($v) }}</td>@endforeach
                        <td>{{ $nf($totDitolak) }}</td>
                        <td>{{ $nf($totTidakMasuk) }}</td>
                        <td>{{ $nf($totUndian) }}</td>
                        <td>{{ $nf($totKeluar) }}</td>
                        <td>{{ $totBerdaftarKnown ? $nf($totBerdaftar) : '—' }}</td>
                        <td>{{ $pctf($totKeluar, $totBerdaftarKnown ? $totBerdaftar : null) }}</td>
                        <td>{{ $totTakKeluar === null ? '—' : $nf($totTakKeluar) }}</td>
                        <td>{{ $totTakKeluar === null ? '—' : $pctf($totTakKeluar, $totBerdaftar) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    @endforeach

    <div class="special">
        <div class="title">Undi Awal &amp; Undi Pos</div>
        <table class="saluran">
            <thead>
                <tr>
                    <th class="l">Saluran</th>
                    @foreach ($partyNames as $i => $pn)<th>@if ($partyLogos[$i])<img class="party-logo" src="{{ $partyLogos[$i] }}" alt="">@endif{{ $pn }}</th>@endforeach
                    <th>Ditolak (C)</th>
                    <th>Tak Dimasukkan (D)</th>
                    <th>Jumlah Undian</th>
                    <th>Jumlah Keluar</th>
                    <th>Berdaftar</th>
                    <th>% Turnout</th>
                    <th>Tak Keluar</th>
                    <th>% Tak Keluar</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($awalPosRows as $row)
                    @php
                        $label = $row['label'];
                        $berdaftarKnown = $row['berdaftar'] !== null;
                        $berdaftar = $berdaftarKnown ? (int) $row['berdaftar'] : null;
                        $slots = []; $undian = 0;
                        for ($i = 0; $i < $nParties; $i++) { $v = $vote('', $label, $i + 1); $slots[$i] = $v; $undian += $v; }
                        $ditolak = $vote('', $label, 90);
                        $tidakMasuk = $vote('', $label, 91);
                        $keluar = $undian + $ditolak + $tidakMasuk;
                        $status = $leadStatus($slots);
                        $takKeluar = $berdaftarKnown ? max(0, $berdaftar - $keluar) : null;
                    @endphp
                    <tr>
                        <td class="l">{{ $label }}</td>
                        @foreach ($slots as $i => $v)<td class="{{ $cellClass($status[$i]) }}">{{ $nf($v) }}</td>@endforeach
                        <td>{{ $nf($ditolak) }}</td>
                        <td>{{ $nf($tidakMasuk) }}</td>
                        <td>{{ $nf($undian) }}</td>
                        <td>{{ $nf($keluar) }}</td>
                        <td>{{ $berdaftarKnown ? $nf($berdaftar) : '—' }}</td>
                        <td>{{ $pctf($keluar, $berdaftar) }}</td>
                        <td>{{ $takKeluar === null ? '—' : $nf($takKeluar) }}</td>
                        <td>{{ $takKeluar === null ? '—' : $pctf($takKeluar, $berdaftar) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="foot">Dijana pada {{ now()->format('d/m/Y H:i') }} &mdash; SISDA</div>
</body>
</html>
