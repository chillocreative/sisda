<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="utf-8">
    <title>Perbandingan Senario Pilihanraya — SISDA</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #0f172a; background: #fff; }

        .header { background: #0f172a; padding: 22px 28px 18px; }
        .header-logo { font-size: 11px; font-weight: bold; color: #10b981; letter-spacing: 0.12em; text-transform: uppercase; }
        .header-title { font-size: 21px; font-weight: bold; color: #ffffff; margin-top: 4px; }
        .header-sub { font-size: 10px; color: #94a3b8; margin-top: 4px; }
        .header-badge { display: inline-block; background: #dc2626; color: #fff; font-size: 8px; padding: 3px 10px; border-radius: 3px; font-weight: bold; margin-top: 8px; letter-spacing: 0.08em; }

        .accent-bar { height: 4px; background: linear-gradient(to right, #10b981, #3b82f6, #8b5cf6); }

        .section { padding: 14px 28px; }
        .section-title {
            font-size: 9px; font-weight: bold; text-transform: uppercase;
            color: #64748b; letter-spacing: 0.08em;
            border-bottom: 1px solid #e2e8f0; padding-bottom: 5px; margin-bottom: 12px;
        }

        .kpi-table { width: 100%; border-collapse: separate; border-spacing: 6px; margin: -6px; }
        .kpi-cell {
            background: #f8fafc; border: 1px solid #e2e8f0;
            border-radius: 6px; padding: 10px 12px; vertical-align: top; width: 25%;
        }
        .kpi-label { font-size: 8px; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em; }
        .kpi-value { font-size: 22px; font-weight: bold; margin-top: 4px; line-height: 1; color: #0f172a; }
        .kpi-sub { font-size: 8px; color: #94a3b8; margin-top: 4px; }

        table.cmp { width: 100%; border-collapse: collapse; font-size: 10px; }
        table.cmp th { background: #1e293b; color: #f1f5f9; padding: 6px 8px; font-size: 9px; text-align: right; }
        table.cmp th.lbl { text-align: left; }
        table.cmp td { padding: 5px 8px; border-bottom: 1px solid #f1f5f9; text-align: right; vertical-align: middle; }
        table.cmp td.lbl { text-align: left; color: #475569; font-weight: 600; }
        table.cmp tr:nth-child(even) td { background: #f8fafc; }

        .bar-row { margin-bottom: 6px; }
        .bar-logo { width: 16px; height: 16px; vertical-align: middle; }
        .bar-name { display: inline-block; width: 135px; font-size: 9px; color: #334155; vertical-align: middle; }
        .bar-track { display: inline-block; vertical-align: middle; width: 170px; height: 11px; background: #f1f5f9; border-radius: 3px; overflow: hidden; }
        .bar-fill { height: 11px; border-radius: 3px; display: inline-block; }
        .bar-pct { display: inline-block; vertical-align: middle; font-size: 9px; font-weight: bold; margin-left: 6px; }

        .narrative { line-height: 1.7; color: #1e293b; font-size: 10.5px; }
        .bullets { margin: 8px 0 0 16px; }
        .bullets li { margin-bottom: 4px; line-height: 1.5; color: #334155; font-size: 10px; }

        .factor { border-left: 3px solid #10b981; padding: 4px 0 4px 12px; margin-bottom: 12px; }
        .factor-title { font-weight: bold; font-size: 11px; color: #0f172a; }
        .factor-body { font-size: 10px; color: #334155; line-height: 1.6; margin-top: 3px; }
        .factor-src { font-size: 8.5px; color: #94a3b8; font-style: italic; margin-top: 3px; }

        .ref { font-size: 9px; color: #475569; margin-bottom: 4px; word-break: break-all; }

        .divider { border: none; border-top: 1px solid #e2e8f0; margin: 0 28px; }
        .footer { background: #f8fafc; border-top: 1px solid #e2e8f0; padding: 10px 28px; margin-top: 14px; }
        .footer-text { font-size: 8.5px; color: #94a3b8; }
        .disclaimer { font-size: 8.5px; color: #b45309; background: #fffbeb; border: 1px solid #fde68a; border-radius: 5px; padding: 7px 10px; margin-top: 10px; }
        .no-break { page-break-inside: avoid; }
    </style>
</head>
<body>

@php
    use App\Support\PartyLogo;
    $r = $result ?? [];
    $f = $facts ?? [];
    $senario = $f['senario'] ?? [];
    $roll = $f['roll_semasa'] ?? [];
    $saluran = $f['saluran_semasa'] ?? [];

    $first = $senario[0] ?? null;
    $last = $senario[count($senario) - 1] ?? null;
    $growth = ($first && $last) ? ($last['pemilih_berdaftar'] - $first['pemilih_berdaftar']) : 0;
    $growthPct = ($first && ($first['pemilih_berdaftar'] ?? 0) > 0)
        ? round($growth / $first['pemilih_berdaftar'] * 100, 1) : 0;

    $n = fn ($v) => number_format((float) $v);

    // Party colours (party-agnostic — detected from the sheets).
    $pmap = [
        'PH'=>'#e11d48','PAKATAN HARAPAN'=>'#e11d48','HARAPAN'=>'#e11d48','PKR'=>'#e11d48','KEADILAN'=>'#e11d48','DAP'=>'#e11d48','AMANAH'=>'#e11d48',
        'BN'=>'#1d4ed8','BARISAN NASIONAL'=>'#1d4ed8','UMNO'=>'#1d4ed8','MCA'=>'#1d4ed8','MIC'=>'#1d4ed8',
        'PN'=>'#0d9488','PERIKATAN NASIONAL'=>'#0d9488','BERSATU'=>'#0d9488','PAS'=>'#0d9488','GERAKAN'=>'#0d9488',
        'PEJUANG'=>'#f59e0b','MUDA'=>'#8b5cf6','GPS'=>'#16a34a','GRS'=>'#16a34a',
    ];
    $palette = ['#e11d48','#1d4ed8','#0d9488','#f59e0b','#8b5cf6','#16a34a','#db2777','#0891b2'];
    $pcolor = function ($name, $i) use ($pmap, $palette) {
        $k = mb_strtoupper(trim((string) $name));
        return $pmap[$k] ?? $palette[$i % count($palette)];
    };
    $allParties = [];
    foreach ($senario as $s) {
        foreach (($s['parti'] ?? array_keys($s['undi'] ?? [])) as $p) {
            if (! in_array($p, $allParties, true)) $allParties[] = $p;
        }
    }

    $statusLabel = ($comparison->ai_status ?? '') === 'fallback' ? 'Laporan Deterministik' : 'Analisis AI';
    $isEstimate = ($saluran['sumber'] ?? '') === 'dpt_estimate';
    $genAt = optional($comparison->ai_generated_at)->translatedFormat('d F Y, g:i A') ?? now()->translatedFormat('d F Y');
@endphp

<div class="header">
    <div class="header-logo">&#9632; SISDA — Analisa Keputusan</div>
    <div class="header-title">{{ $r['tajuk'] ?? 'Perbandingan Senario Pilihanraya' }}</div>
    <div class="header-sub">{{ $comparison->dun }} &middot; {{ $comparison->parlimen }} &nbsp;&middot;&nbsp; Dijana: {{ $genAt }} &nbsp;&middot;&nbsp; {{ $statusLabel }}</div>
    <div class="header-badge">SULIT — DALAMAN SAHAJA</div>
</div>
<div class="accent-bar"></div>

{{-- KPI ROW --}}
<div class="section" style="padding-top:16px;">
    <div class="section-title">Ringkasan Angka</div>
    <table class="kpi-table">
        <tr>
            <td class="kpi-cell">
                <div class="kpi-label">Bilangan Senario</div>
                <div class="kpi-value">{{ count($senario) }}</div>
            </td>
            <td class="kpi-cell">
                <div class="kpi-label">Pertumbuhan Pemilih</div>
                <div class="kpi-value" style="color:{{ $growth >= 0 ? '#10b981' : '#ef4444' }}; font-size:19px;">{{ $growth >= 0 ? '+' : '' }}{{ $n($growth) }}</div>
                <div class="kpi-sub">{{ $growth >= 0 ? '+' : '' }}{{ $growthPct }}% sejak senario terawal</div>
            </td>
            <td class="kpi-cell">
                <div class="kpi-label">% Pengundi Muda (18–29)</div>
                <div class="kpi-value" style="color:#3b82f6;">{{ $roll['pct_muda'] ?? 0 }}%</div>
                <div class="kpi-sub">{{ $n($roll['muda_18_29'] ?? 0) }} pengundi semasa</div>
            </td>
            <td class="kpi-cell">
                <div class="kpi-label">% Pengundi Baru</div>
                <div class="kpi-value" style="color:#8b5cf6;">{{ $roll['pct_baru'] ?? 0 }}%</div>
                <div class="kpi-sub">{{ $n($roll['baru'] ?? 0) }} daripada {{ $n($roll['jumlah'] ?? 0) }}</div>
            </td>
        </tr>
    </table>
</div>

{{-- SCENARIO COMPARISON TABLE --}}
@if (!empty($senario))
<hr class="divider">
<div class="section no-break">
    <div class="section-title">Perbandingan Keputusan Mengikut Senario</div>
    <table class="cmp">
        <thead>
            <tr>
                <th class="lbl">Metrik</th>
                @foreach ($senario as $s)
                    <th>{{ $s['label'] }}<br><span style="font-weight:normal; color:#94a3b8;">{{ $s['tahun'] }}</span></th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            <tr><td class="lbl">Pemilih Berdaftar</td>@foreach ($senario as $s)<td>{{ $n($s['pemilih_berdaftar']) }}</td>@endforeach</tr>
            <tr><td class="lbl">Undi Keluar</td>@foreach ($senario as $s)<td>{{ $n($s['undi_keluar']) }}</td>@endforeach</tr>
            <tr><td class="lbl">% Keluar</td>@foreach ($senario as $s)<td>{{ $s['peratus_keluar'] !== null ? $s['peratus_keluar'].'%' : '—' }}</td>@endforeach</tr>
            @foreach ($allParties as $pi => $party)
            <tr>
                <td class="lbl"><span style="display:inline-block;width:8px;height:8px;background:{{ $pcolor($party,$pi) }};"></span> {{ $party }}</td>
                @foreach ($senario as $s)
                    <td style="color:{{ $pcolor($party,$pi) }}; font-weight:600;">{{ isset($s['undi'][$party]) ? $n($s['undi'][$party]).' ('.($s['peratus_undi'][$party] ?? 0).'%)' : '—' }}</td>
                @endforeach
            </tr>
            @endforeach
            <tr><td class="lbl">Pemenang</td>@foreach ($senario as $s)<td style="font-weight:bold;">{{ $s['pemenang'] ?? '—' }}</td>@endforeach</tr>
            <tr><td class="lbl">Majoriti</td>@foreach ($senario as $s)<td>{{ $n($s['majoriti']) }}</td>@endforeach</tr>
        </tbody>
    </table>

    <div style="margin-top:14px;">
        @foreach ($senario as $s)
            <div style="font-size:9px; color:#64748b; margin:8px 0 4px;">{{ $s['label'] }} — % undi mengikut parti</div>
            @foreach (($s['parti'] ?? array_keys($s['undi'] ?? [])) as $pi => $party)
                @php $pv = $s['peratus_undi'][$party] ?? 0; $col = $pcolor($party, $pi); $logo = PartyLogo::dataUri($party); @endphp
                <div class="bar-row">
                    @if ($logo)<img src="{{ $logo }}" class="bar-logo">@endif
                    <span class="bar-name">{{ $party }}</span>
                    <span class="bar-track"><span class="bar-fill" style="width:{{ min(100, $pv) }}%; background:{{ $col }};"></span></span>
                    <span class="bar-pct" style="color:{{ $col }};">{{ $pv }}%</span>
                </div>
            @endforeach
        @endforeach
    </div>
</div>
@endif

{{-- EXECUTIVE SUMMARY --}}
@if (!empty($r['ringkasan_eksekutif']))
<hr class="divider">
<div class="section">
    <div class="section-title">Ringkasan Eksekutif</div>
    <div class="narrative">{{ $r['ringkasan_eksekutif'] }}</div>
</div>
@endif

{{-- THREE METRIC SECTIONS --}}
@php
    $sections = [
        ['Pengundi Baru vs Pengundi Lama', $r['pengundi_baru_lama'] ?? null],
        ['Pengundi Muda', $r['pengundi_muda'] ?? null],
        ['Pecahan Mengikut Saluran', $r['saluran'] ?? null],
    ];
@endphp
@foreach ($sections as [$title, $sec])
    @if ($sec && (!empty($sec['analisis']) || !empty($sec['bullet_points'])))
    <hr class="divider">
    <div class="section no-break">
        <div class="section-title">{{ $title }}</div>
        @if (!empty($sec['analisis']))<div class="narrative">{{ $sec['analisis'] }}</div>@endif
        @if (!empty($sec['bullet_points']))
            <ul class="bullets">
                @foreach ($sec['bullet_points'] as $bp)<li>{{ $bp }}</li>@endforeach
            </ul>
        @endif
    </div>
    @endif
@endforeach

{{-- WHY THE CHANGE --}}
@if (!empty($r['faktor_perubahan']))
<hr class="divider">
<div class="section">
    <div class="section-title">Faktor Perubahan — Analisis &amp; Hujah</div>
    @foreach ($r['faktor_perubahan'] as $i => $fp)
    <div class="factor no-break">
        <div class="factor-title">{{ $i + 1 }}. {{ $fp['tajuk'] }}</div>
        <div class="factor-body">{{ $fp['hujah'] }}</div>
        @if (!empty($fp['sumber']))<div class="factor-src">Sumber: {{ $fp['sumber'] }}</div>@endif
    </div>
    @endforeach
</div>
@endif

{{-- CONCLUSION --}}
@if (!empty($r['kesimpulan']))
<hr class="divider">
<div class="section no-break">
    <div class="section-title">Kesimpulan</div>
    <div class="narrative">{{ $r['kesimpulan'] }}</div>
</div>
@endif

{{-- REFERENCES --}}
@if (!empty($r['rujukan']))
<hr class="divider">
<div class="section">
    <div class="section-title">Rujukan (Carian Web)</div>
    @foreach ($r['rujukan'] as $ref)
        <div class="ref">&bull; {{ $ref['tajuk'] }} — {{ $ref['url'] }}</div>
    @endforeach
</div>
@endif

@if ($isEstimate)
<div class="section" style="padding-top:0;">
    <div class="disclaimer">
        Nota: Pecahan saluran adalah anggaran daripada pangkalan data DPT (setiap lokaliti dikira sebagai satu saluran)
        kerana pecahan saluran rasmi SPR belum tersedia untuk kawasan ini.
    </div>
</div>
@endif

{{-- FOOTER --}}
<div class="footer">
    <table style="width:100%; border-collapse:collapse;">
        <tr>
            <td class="footer-text">Dijana oleh SISDA &middot; {{ $statusLabel }}{{ $comparison->ai_model ? ' ('.$comparison->ai_model.')' : '' }} &middot; {{ $genAt }}</td>
            <td class="footer-text" style="text-align:right;">SULIT — edaran terhad kepada pihak berkenaan sahaja</td>
        </tr>
    </table>
</div>

</body>
</html>
