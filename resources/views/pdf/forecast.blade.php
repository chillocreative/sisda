<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="utf-8">
    <title>Analisis Strategik Pilihanraya — SISDA</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #0f172a; background: #fff; }

        .header { background: #0f172a; padding: 22px 28px 18px; }
        .header-logo { font-size: 11px; font-weight: bold; color: #10b981; letter-spacing: 0.12em; text-transform: uppercase; }
        .header-title { font-size: 22px; font-weight: bold; color: #ffffff; margin-top: 4px; }
        .header-sub { font-size: 10px; color: #94a3b8; margin-top: 4px; }
        .header-badge { display: inline-block; background: #dc2626; color: #fff; font-size: 8px; padding: 3px 10px; border-radius: 3px; font-weight: bold; margin-top: 8px; letter-spacing: 0.08em; }

        .accent-bar { height: 4px; background: linear-gradient(to right, #10b981, #3b82f6, #8b5cf6); }

        .section { padding: 16px 28px; }
        .section + .section { padding-top: 0; }

        .section-title {
            font-size: 9px; font-weight: bold; text-transform: uppercase;
            color: #64748b; letter-spacing: 0.08em;
            border-bottom: 1px solid #e2e8f0; padding-bottom: 5px; margin-bottom: 12px;
        }

        /* KPI grid via table (dompdf-safe) */
        .kpi-table { width: 100%; border-collapse: separate; border-spacing: 6px; margin: -6px; }
        .kpi-cell {
            background: #f8fafc; border: 1px solid #e2e8f0;
            border-radius: 6px; padding: 10px 12px; vertical-align: top; width: 33%;
        }
        .kpi-label { font-size: 8px; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em; }
        .kpi-value { font-size: 24px; font-weight: bold; margin-top: 4px; line-height: 1; }
        .kpi-bar-bg { height: 5px; background: #e2e8f0; border-radius: 3px; margin-top: 8px; overflow: hidden; }
        .kpi-bar-fill { height: 5px; border-radius: 3px; }

        /* Horizontal bar chart for seat projections */
        .chart-row { margin-bottom: 5px; }
        .chart-label { font-size: 9px; color: #334155; display: inline-block; width: 160px; vertical-align: middle; white-space: nowrap; overflow: hidden; }
        .chart-track { display: inline-block; vertical-align: middle; width: 200px; height: 12px; background: #f1f5f9; border-radius: 3px; overflow: hidden; }
        .chart-fill { height: 12px; border-radius: 3px; display: inline-block; }
        .chart-pct { display: inline-block; vertical-align: middle; font-size: 9px; font-weight: bold; margin-left: 6px; width: 36px; }
        .chart-cat { display: inline-block; vertical-align: middle; font-size: 8px; color: #64748b; }

        /* Narrative text */
        .narrative { line-height: 1.75; color: #1e293b; font-size: 10.5px; white-space: pre-line; }

        /* Seats table */
        table.seats { width: 100%; border-collapse: collapse; font-size: 10px; }
        table.seats th { background: #1e293b; color: #f1f5f9; padding: 6px 8px; font-size: 8px; text-align: left; text-transform: uppercase; letter-spacing: 0.05em; }
        table.seats td { padding: 5px 8px; border-bottom: 1px solid #f1f5f9; vertical-align: middle; }
        table.seats tr:nth-child(even) td { background: #f8fafc; }
        .prob-track { display: inline-block; width: 70px; height: 8px; background: #e2e8f0; border-radius: 3px; vertical-align: middle; overflow: hidden; }
        .prob-fill { height: 8px; border-radius: 3px; display: inline-block; }

        /* Status chip */
        .chip { display: inline-block; padding: 2px 7px; border-radius: 3px; font-size: 8px; font-weight: bold; }

        .divider { border: none; border-top: 1px solid #e2e8f0; margin: 0 28px; }

        .footer { background: #f8fafc; border-top: 1px solid #e2e8f0; padding: 10px 28px; margin-top: 16px; }
        .footer-text { font-size: 8.5px; color: #94a3b8; }

        .page-break { page-break-before: always; }
        .no-break { page-break-inside: avoid; }
    </style>
</head>
<body>

@php
$res = $result ?? [];
$phProb    = $res['ph_win_probability'] ?? 0;
$oppProb   = $res['opposition_win_probability'] ?? 0;
$swingProb = $res['swing_probability'] ?? 0;
$riskScore = $res['risk_score'] ?? 0;
$majority  = $res['expected_majority'] ?? 0;
$confidence = strtoupper($res['confidence'] ?? 'N/A');
$narrative = $res['narrative'] ?? '';
$seats     = $res['seat_projections'] ?? [];

$phColor   = '#10b981';
$oppColor  = '#ef4444';
$swingColor = '#f59e0b';
$riskColor = $riskScore >= 70 ? '#ef4444' : ($riskScore >= 40 ? '#f59e0b' : '#10b981');
$confColor = $confidence === 'TINGGI' ? '#10b981' : ($confidence === 'SEDERHANA' ? '#f59e0b' : '#ef4444');
$majColor  = $majority >= 0 ? '#10b981' : '#ef4444';
$statusLabel = ($status ?? '') === 'fallback' ? 'Unjuran Deterministik' : 'Analisis AI';
@endphp

{{-- HEADER --}}
<div class="header">
    <div class="header-logo">&#9632; SISDA — Digital War Room</div>
    <div class="header-title">Analisis Strategik Pilihanraya</div>
    <div class="header-sub">Skop: {{ $scope }} &nbsp;&middot;&nbsp; Dijana: {{ $generated_at }} &nbsp;&middot;&nbsp; {{ $statusLabel }}</div>
    <div class="header-badge">SULIT — DALAMAN SAHAJA</div>
</div>
<div class="accent-bar"></div>

{{-- RINGKASAN EKSEKUTIF --}}
<div class="section" style="padding-top:18px;">
    <div class="section-title">Ringkasan Eksekutif</div>
    <table class="kpi-table">
        <tr>
            <td class="kpi-cell">
                <div class="kpi-label">Kebarangkalian PH Menang</div>
                <div class="kpi-value" style="color:{{ $phColor }}">{{ $phProb }}%</div>
                <div class="kpi-bar-bg"><div class="kpi-bar-fill" style="width:{{ min(100,$phProb) }}%;background:{{ $phColor }};"></div></div>
            </td>
            <td class="kpi-cell">
                <div class="kpi-label">Kebarangkalian Pembangkang</div>
                <div class="kpi-value" style="color:{{ $oppColor }}">{{ $oppProb }}%</div>
                <div class="kpi-bar-bg"><div class="kpi-bar-fill" style="width:{{ min(100,$oppProb) }}%;background:{{ $oppColor }};"></div></div>
            </td>
            <td class="kpi-cell">
                <div class="kpi-label">Kebarangkalian Berayun</div>
                <div class="kpi-value" style="color:{{ $swingColor }}">{{ $swingProb }}%</div>
                <div class="kpi-bar-bg"><div class="kpi-bar-fill" style="width:{{ min(100,$swingProb) }}%;background:{{ $swingColor }};"></div></div>
            </td>
        </tr>
        <tr>
            <td class="kpi-cell">
                <div class="kpi-label">Skor Risiko</div>
                <div class="kpi-value" style="color:{{ $riskColor }}">{{ $riskScore }}%</div>
                <div class="kpi-bar-bg"><div class="kpi-bar-fill" style="width:{{ min(100,$riskScore) }}%;background:{{ $riskColor }};"></div></div>
            </td>
            <td class="kpi-cell">
                <div class="kpi-label">Unjuran Majoriti Kerusi</div>
                <div class="kpi-value" style="color:{{ $majColor }}; font-size:20px; margin-top:6px;">
                    {{ $majority >= 0 ? '+' : '' }}{{ number_format($majority) }}
                </div>
            </td>
            <td class="kpi-cell" style="background:{{ $confColor }}1a; border-color:{{ $confColor }}55;">
                <div class="kpi-label">Tahap Keyakinan</div>
                <div class="kpi-value" style="color:{{ $confColor }}; font-size:17px; margin-top:6px;">{{ $confidence }}</div>
            </td>
        </tr>
    </table>
</div>

{{-- CARTA KEBARANGKALIAN PH MENGIKUT KERUSI --}}
@if (!empty($seats))
<hr class="divider">
<div class="section" style="padding-top:14px;">
    <div class="section-title">Carta Kebarangkalian PH Mengikut Kerusi</div>
    @foreach ($seats as $seat)
    @php $prob = $seat['ph_probability'] ?? 50; $col = $prob >= 50 ? '#10b981' : '#ef4444'; @endphp
    <div class="chart-row">
        <span class="chart-label">{{ $seat['kerusi'] ?? '' }}</span>
        <span class="chart-track">
            <span class="chart-fill" style="width:{{ $prob }}%;background:{{ $col }};"></span>
        </span>
        <span class="chart-pct" style="color:{{ $col }};">{{ $prob }}%</span>
        <span class="chart-cat">{{ $seat['kategori'] ?? '' }}</span>
    </div>
    @endforeach
</div>
@endif

{{-- ANALISIS STRATEGIK --}}
@if ($narrative)
<hr class="divider">
<div class="section" style="padding-top:14px;">
    <div class="section-title">Analisis Strategik (AI)</div>
    <div class="narrative">{{ $narrative }}</div>
</div>
@endif

{{-- UNJURAN KERUSI UTAMA (table) --}}
@if (!empty($seats))
<div class="page-break">
<div style="background:#0f172a; padding:14px 28px;">
    <div style="font-size:9px; color:#10b981; font-weight:bold; letter-spacing:0.1em; text-transform:uppercase;">SISDA — Digital War Room</div>
    <div style="font-size:14px; color:#fff; font-weight:bold; margin-top:2px;">Unjuran Kerusi Utama</div>
</div>
<div class="accent-bar"></div>
<div class="section" style="padding-top:14px;">
    <table class="seats">
        <thead>
            <tr>
                <th style="width:160px;">Kerusi</th>
                <th style="width:140px;">Kebarangkalian PH</th>
                <th style="width:120px;">Kategori</th>
                <th>Catatan</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($seats as $seat)
            @php $prob = $seat['ph_probability'] ?? 50; $col = $prob >= 50 ? '#10b981' : '#ef4444'; @endphp
            <tr>
                <td style="font-weight:600;">{{ $seat['kerusi'] ?? '' }}</td>
                <td>
                    <span class="prob-track">
                        <span class="prob-fill" style="width:{{ $prob }}%;background:{{ $col }};"></span>
                    </span>
                    <span style="font-weight:bold; color:{{ $col }}; margin-left:5px;">{{ $prob }}%</span>
                </td>
                <td>
                    <span class="chip" style="background:{{ $col }}1a; color:{{ $col }}; border:1px solid {{ $col }}44;">
                        {{ $seat['kategori'] ?? '' }}
                    </span>
                </td>
                <td style="color:#475569;">{{ $seat['catatan'] ?? '' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    <p style="font-size:8px; color:#94a3b8; margin-top:10px;">
        Kebarangkalian dikira berdasarkan data culaan (hasil_culaan + data_pengundi). Kerusi bertanda * mempunyai data culaan nipis — kebarangkalian dikecilkan ke arah 50%.
    </p>
</div>
</div>
@endif

{{-- FOOTER --}}
<div class="footer">
    <table style="width:100%; border-collapse:collapse;">
        <tr>
            <td class="footer-text">Dijana oleh SISDA Digital War Room &middot; {{ $generated_at }}</td>
            <td class="footer-text" style="text-align:right;">Dokumen ini adalah SULIT — edaran terhad kepada pihak berkenaan sahaja</td>
        </tr>
    </table>
</div>

</body>
</html>
