<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="utf-8">
    <title>Simulasi Pilihanraya — SISDA</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #0f172a; background: #fff; }

        .header { background: #0f172a; padding: 22px 28px 18px; }
        .header-logo { font-size: 11px; font-weight: bold; color: #10b981; letter-spacing: 0.12em; text-transform: uppercase; }
        .header-title { font-size: 19px; font-weight: bold; color: #ffffff; margin-top: 4px; line-height: 1.25; }
        .header-sub { font-size: 10px; color: #94a3b8; margin-top: 5px; }
        .header-badge { display: inline-block; background: #dc2626; color: #fff; font-size: 8px; padding: 3px 10px; border-radius: 3px; font-weight: bold; margin-top: 8px; letter-spacing: 0.08em; }

        .accent-bar { height: 4px; background: linear-gradient(to right, #10b981, #3b82f6, #8b5cf6); }

        .section { padding: 14px 28px; }
        .section-title {
            font-size: 9px; font-weight: bold; text-transform: uppercase;
            color: #64748b; letter-spacing: 0.08em;
            border-bottom: 1px solid #e2e8f0; padding-bottom: 5px; margin-bottom: 12px;
        }

        .winner-banner {
            border-radius: 8px; padding: 12px 16px; margin-bottom: 4px;
            border: 1px solid; overflow: hidden;
        }
        .winner-status { font-size: 18px; font-weight: bold; }
        .winner-sub { font-size: 9px; color: #64748b; margin-top: 2px; }
        .winner-maj { font-size: 9px; color: #64748b; }
        .winner-maj b { font-size: 15px; }

        .kpi-table { width: 100%; border-collapse: separate; border-spacing: 6px; margin: -6px; }
        .kpi-cell {
            background: #f8fafc; border: 1px solid #e2e8f0;
            border-radius: 6px; padding: 10px 12px; vertical-align: top; width: 25%;
        }
        .kpi-label { font-size: 8px; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em; }
        .kpi-value { font-size: 21px; font-weight: bold; margin-top: 4px; line-height: 1; color: #0f172a; }
        .kpi-sub { font-size: 8px; color: #94a3b8; margin-top: 4px; }

        table.grid { width: 100%; border-collapse: collapse; font-size: 10px; }
        table.grid th { background: #1e293b; color: #f1f5f9; padding: 6px 8px; font-size: 9px; text-align: right; white-space: nowrap; }
        table.grid th.lbl { text-align: left; }
        table.grid td { padding: 5px 8px; border-bottom: 1px solid #f1f5f9; text-align: right; vertical-align: middle; }
        table.grid td.lbl { text-align: left; color: #475569; font-weight: 600; }
        table.grid tr:nth-child(even) td { background: #f8fafc; }
        table.grid tfoot td { font-weight: bold; color: #0f172a; border-top: 2px solid #cbd5e1; background: #f1f5f9 !important; }

        .chip { display: inline-block; width: 9px; height: 9px; border-radius: 2px; vertical-align: middle; margin-right: 4px; }

        .footer { background: #f8fafc; border-top: 1px solid #e2e8f0; padding: 10px 28px; margin-top: 8px; }
        .footer-text { font-size: 8.5px; color: #94a3b8; }
        .disclaimer { font-size: 8.5px; color: #b45309; background: #fffbeb; border: 1px solid #fde68a; border-radius: 5px; padding: 7px 10px; margin-top: 10px; }
        .no-break { page-break-inside: avoid; }
    </style>
</head>
<body>

@php
    $n = fn ($v) => number_format((float) $v);
    $p1 = fn ($v) => number_format((float) $v * 100, 1) . '%';
    $winner = $totals['winner'] ?? null;
    $wcolor = '#64748b';
    foreach ($parties as $p) {
        if ($winner && $p['kod'] === $winner['kod']) { $wcolor = $p['color']; break; }
    }
    $last = $parties[count($parties) - 1] ?? null;
@endphp

<div class="header">
    <div class="header-logo">&#9632; SISDA — Simulasi Pilihanraya</div>
    <div class="header-title">{{ $title }}</div>
    <div class="header-sub">{{ $kawasan }} &nbsp;&middot;&nbsp; Dijana: {{ $genAt }} &nbsp;&middot;&nbsp; Model deterministik (kalibrasi PRN 2022)</div>
    <div class="header-badge">SULIT — DALAMAN SAHAJA</div>
</div>
<div class="accent-bar"></div>

{{-- WINNER BANNER --}}
<div class="section" style="padding-top:16px; padding-bottom:6px;">
    <div class="winner-banner no-break" style="border-color: {{ $wcolor }}66; background: {{ $wcolor }}12;">
        <table style="width:100%;"><tr>
            <td style="text-align:left; vertical-align:middle;">
                <div class="winner-status" style="color: {{ $wcolor }};">{{ $totals['status'] }}</div>
                <div class="winner-sub">
                    @foreach ($parties as $i => $p){{ $i ? ' vs ' : '' }}{{ $p['nama'] }}@endforeach
                </div>
            </td>
            <td style="text-align:right; vertical-align:middle;">
                <div class="winner-maj">Majoriti</div>
                <div class="winner-maj"><b style="color: {{ $wcolor }};">{{ $totals['majoriti'] >= 0 ? '+' : '' }}{{ $n($totals['majoriti']) }}</b></div>
            </td>
        </tr></table>
    </div>
</div>

{{-- KPI ROW --}}
<div class="section" style="padding-top:8px;">
    <div class="section-title">Ringkasan Keputusan</div>
    <table class="kpi-table no-break">
        <tr>
            <td class="kpi-cell">
                <div class="kpi-label">Undi Keluar</div>
                <div class="kpi-value">{{ $n($totals['keluar']) }}</div>
                <div class="kpi-sub">{{ $p1($totals['turnout_all']) }} turnout keseluruhan</div>
            </td>
            <td class="kpi-cell">
                <div class="kpi-label">Undi Diperlukan (50%+1)</div>
                <div class="kpi-value">{{ $n($totals['perlu']) }}</div>
                <div class="kpi-sub">Jumlah pengundi {{ $n($totals['pengundi']) }}</div>
            </td>
            <td class="kpi-cell">
                <div class="kpi-label">Undi {{ $winner['kod'] ?? '—' }}</div>
                <div class="kpi-value" style="color: {{ $wcolor }};">
                    @php $wi = 0; foreach ($parties as $i => $p) { if ($winner && $p['kod'] === $winner['kod']) { $wi = $i; break; } } @endphp
                    {{ $n($totals['undi'][$wi] ?? 0) }}
                </div>
                <div class="kpi-sub">Parti mendahului</div>
            </td>
            <td class="kpi-cell">
                <div class="kpi-label">Majoriti</div>
                <div class="kpi-value" style="color: {{ $wcolor }};">{{ $totals['majoriti'] >= 0 ? '+' : '' }}{{ $n($totals['majoriti']) }}</div>
                <div class="kpi-sub">{{ $totals['status'] }}</div>
            </td>
        </tr>
    </table>
</div>

{{-- PENGUNDI --}}
<div class="section no-break" style="padding-top:4px;">
    <div class="section-title">Pengundi (DPPR)</div>
    <table class="grid">
        <thead><tr><th class="lbl">Kaum</th><th>Bilangan Pengundi</th></tr></thead>
        <tbody>
            @foreach (['melayu'=>'Melayu','cina'=>'Cina','india'=>'India','lain'=>'Lain-lain'] as $k => $label)
                <tr><td class="lbl">{{ $label }}</td><td>{{ $n($pengundi[$k] ?? 0) }}</td></tr>
            @endforeach
        </tbody>
        <tfoot><tr><td class="lbl">JUMLAH</td><td>{{ $n($totals['pengundi']) }}</td></tr></tfoot>
    </table>
</div>

{{-- ANDAIAN SENARIO --}}
<div class="section no-break" style="padding-top:4px;">
    <div class="section-title">Andaian Senario (baki % = {{ $last['kod'] ?? '' }})</div>
    <table class="grid">
        <thead>
            <tr>
                <th class="lbl">Kaum</th>
                <th>% Turnout</th>
                @for ($i = 0; $i < count($parties) - 1; $i++)
                    <th>% Sokongan {{ $parties[$i]['kod'] }}</th>
                @endfor
                <th>{{ $last['kod'] ?? '' }} (baki)</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($andaian as $row)
                @php
                    $explicitSum = 0;
                    foreach ($row['sokongan'] as $s) { $explicitSum += (float) $s; }
                    $baki = max(0, 1 - $explicitSum);
                @endphp
                <tr>
                    <td class="lbl">{{ $row['kaum'] }}</td>
                    <td>{{ $p1($row['turnout']) }}</td>
                    @for ($i = 0; $i < count($parties) - 1; $i++)
                        <td>{{ $p1($row['sokongan'][$i] ?? 0) }}</td>
                    @endfor
                    <td>{{ $p1($baki) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>

{{-- KEPUTUSAN SIMULASI --}}
<div class="section no-break" style="padding-top:4px;">
    <div class="section-title">Keputusan Simulasi</div>
    <table class="grid">
        <thead>
            <tr>
                <th class="lbl">Kaum</th>
                <th>Undi Keluar</th>
                @foreach ($parties as $p)
                    <th><span class="chip" style="background: {{ $p['color'] }};"></span>Undi {{ $p['kod'] }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach ($keputusan as $row)
                <tr>
                    <td class="lbl">{{ $row['kaum'] }}</td>
                    <td>{{ $n($row['keluar']) }}</td>
                    @foreach ($parties as $i => $p)
                        <td style="color: {{ $p['color'] }};">{{ $n($row['undi'][$i] ?? 0) }}</td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td class="lbl">JUMLAH</td>
                <td>{{ $n($totals['keluar']) }}</td>
                @foreach ($parties as $i => $p)
                    <td style="color: {{ $p['color'] }};">{{ $n($totals['undi'][$i] ?? 0) }}</td>
                @endforeach
            </tr>
        </tfoot>
    </table>
</div>

<div class="section" style="padding-top:0;">
    <div class="disclaimer">
        Simulasi ini adalah unjuran deterministik berdasarkan andaian turnout dan sokongan yang dimasukkan pengguna,
        dikalibrasi kepada keputusan PRN 2022. Ia bukan ramalan rasmi dan hendaklah digunakan untuk perancangan strategik dalaman sahaja.
    </div>
</div>

<div class="footer">
    <div class="footer-text">SISDA — Sistem Data Pengundi &nbsp;&middot;&nbsp; Simulasi Pilihanraya &nbsp;&middot;&nbsp; Dijana {{ $genAt }} &nbsp;&middot;&nbsp; SULIT</div>
</div>

</body>
</html>
