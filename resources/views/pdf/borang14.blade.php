@php
    // --- helpers -------------------------------------------------------------
    $key = fn ($pusat, $saluran, $slot) => ($pusat ?? '') . '|' . $saluran . '|' . $slot;
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
    $pctf = fn ($num, $den) => $den > 0 ? number_format($num / $den * 100, 1) . '%' : '—';
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
        /* Narrower, centred blocks so the tables don't stretch edge to edge. */
        .block { width: 84%; margin: 0 auto 14px; page-break-inside: avoid; }
        .block .title { background: #0f172a; color: #fff; padding: 5px 10px; font-size: 9px; border-radius: 3px 3px 0 0; }
        .block .title .dm { font-weight: bold; }
        .block .title .pm { font-weight: normal; opacity: .85; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #cbd5e1; padding: 5px 10px; text-align: right; }
        th { background: #e2e8f0; font-size: 7.5px; text-transform: uppercase; letter-spacing: .3px; }
        td.l, th.l { text-align: left; }
        tr.total td { background: #f1f5f9; font-weight: bold; }
        .special { width: 84%; margin: 16px auto 0; page-break-inside: avoid; }
        .special .title { background: #1e40af; color: #fff; padding: 5px 10px; font-size: 10px; border-radius: 3px 3px 0 0; }
        .foot { width: 84%; margin: 14px auto 0; text-align: right; font-size: 7px; color: #94a3b8; }
    </style>
</head>
<body>
    <div class="head">
        @if ($logo)<img src="{{ $logo }}" alt="Logo">@endif
        <h1>BORANG 14</h1>
        <div class="geo">{{ $reference['negeri'] }} &middot; {{ $reference['parlimen'] }} &middot; DUN {{ $reference['dun'] }}</div>
        <div class="sub">Penyata Undi Mengikut Saluran &mdash; {{ $penjuruLabel }}</div>
    </div>

    <div class="legend">
        @foreach ($partyNames as $i => $pn)
            <span><b>Parti {{ $i + 1 }}:</b> {{ $pn }}</span>
        @endforeach
    </div>

    @foreach ($blocks as $b)
        @php
            $totSlots = array_fill(0, $nParties, 0);
            $totKeluar = 0; $totBerdaftar = 0;
        @endphp
        <div class="block">
            <div class="title"><span class="dm">DM: {{ $b['dm'] }}</span> &nbsp;|&nbsp; <span class="pm">Pusat Mengundi: {{ $b['pusat'] }}</span></div>
            <table>
                <thead>
                    <tr>
                        <th class="l">Saluran</th>
                        @foreach ($partyNames as $pn)<th>{{ $pn }}</th>@endforeach
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
                            $keluar = 0;
                            for ($i = 0; $i < $nParties; $i++) {
                                $v = $vote($b['pusat'], (string) $s['no'], $i + 1);
                                $slots[$i] = $v; $keluar += $v; $totSlots[$i] += $v;
                            }
                            $berdaftar = (int) ($s['berdaftar'] ?? 0);
                            $totKeluar += $keluar; $totBerdaftar += $berdaftar;
                        @endphp
                        <tr>
                            <td class="l">Saluran {{ $s['no'] }}</td>
                            @foreach ($slots as $v)<td>{{ $nf($v) }}</td>@endforeach
                            <td>{{ $nf($keluar) }}</td>
                            <td>{{ $nf($berdaftar) }}</td>
                            <td>{{ $pctf($keluar, $berdaftar) }}</td>
                            <td>{{ $nf($berdaftar - $keluar) }}</td>
                            <td>{{ $pctf($berdaftar - $keluar, $berdaftar) }}</td>
                        </tr>
                    @endforeach
                    <tr class="total">
                        <td class="l">Jumlah Undi</td>
                        @foreach ($totSlots as $v)<td>{{ $nf($v) }}</td>@endforeach
                        <td>{{ $nf($totKeluar) }}</td>
                        <td>{{ $nf($totBerdaftar) }}</td>
                        <td>{{ $pctf($totKeluar, $totBerdaftar) }}</td>
                        <td>{{ $nf($totBerdaftar - $totKeluar) }}</td>
                        <td>{{ $pctf($totBerdaftar - $totKeluar, $totBerdaftar) }}</td>
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
                    @foreach ($partyNames as $pn)<th>{{ $pn }}</th>@endforeach
                    <th>Jumlah Keluar</th>
                    <th>Berdaftar</th>
                    <th>% Turnout</th>
                    <th>Tak Keluar</th>
                    <th>% Tak Keluar</th>
                </tr>
            </thead>
            <tbody>
                @foreach (['UNDI AWAL', 'UNDI POS'] as $label)
                    @php $keluar = 0; $slots = [];
                        for ($i = 0; $i < $nParties; $i++) { $v = $vote('', $label, $i + 1); $slots[$i] = $v; $keluar += $v; }
                        $berdaftar = $vote('', $label, 0);
                    @endphp
                    <tr>
                        <td class="l">{{ $label }}</td>
                        @foreach ($slots as $v)<td>{{ $nf($v) }}</td>@endforeach
                        <td>{{ $nf($keluar) }}</td>
                        <td>{{ $nf($berdaftar) }}</td>
                        <td>{{ $pctf($keluar, $berdaftar) }}</td>
                        <td>{{ $nf(max(0, $berdaftar - $keluar)) }}</td>
                        <td>{{ $pctf(max(0, $berdaftar - $keluar), $berdaftar) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="foot">Dijana pada {{ now()->format('d/m/Y H:i') }} &mdash; SISDA</div>
</body>
</html>
