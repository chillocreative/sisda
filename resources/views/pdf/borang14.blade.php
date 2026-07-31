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
    </style>
</head>
<body>
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
        @if (($reference['source'] ?? null) === 'dpt_estimate')
            <div class="sub" style="color:#b45309;">Pusat Mengundi &amp; Berdaftar dianggarkan daripada data DPT (ikut Lokaliti) &mdash; bukan pecahan Saluran rasmi gazet SPR.</div>
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
            <table>
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
        <table>
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
