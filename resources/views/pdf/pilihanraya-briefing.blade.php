<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="utf-8">
    <title>{{ $briefing['tajuk'] ?? 'Taklimat Eksekutif Pilihanraya' }}</title>
    <style>
        /* dompdf-safe: tables and basic CSS only, always print-light */
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #0f172a; margin: 24px; }
        h1 { font-size: 18px; margin: 0 0 2px 0; }
        h2 { font-size: 13px; margin: 18px 0 6px 0; border-bottom: 1px solid #cbd5e1; padding-bottom: 3px; }
        p { margin: 4px 0; line-height: 1.5; }
        ul { margin: 4px 0 4px 16px; padding: 0; }
        li { margin: 2px 0; line-height: 1.4; }
        .meta { color: #64748b; font-size: 10px; margin-bottom: 16px; }
        .urgent { background: #fef3c7; border: 1px solid #f59e0b; padding: 8px 12px; margin-top: 16px; }
        .urgent strong { display: block; margin-bottom: 4px; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th { background: #0f172a; color: #ffffff; text-align: left; padding: 5px 6px; font-size: 9px; text-transform: uppercase; }
        td { border-bottom: 1px solid #e2e8f0; padding: 4px 6px; font-size: 10px; }
        .footer { margin-top: 24px; color: #94a3b8; font-size: 9px; border-top: 1px solid #e2e8f0; padding-top: 6px; }
    </style>
</head>
<body>
    <h1>{{ $briefing['tajuk'] ?? 'Taklimat Eksekutif Pilihanraya' }}</h1>
    <div class="meta">SISDA — Digital War Room &middot; {{ $briefing['tarikh'] ?? now()->format('d/m/Y') }} &middot; SULIT</div>

    @foreach ($briefing['seksyen'] ?? [] as $i => $seksyen)
        <h2>{{ $i + 1 }}. {{ $seksyen['tajuk'] ?? '' }}</h2>
        <p>{{ $seksyen['kandungan'] ?? '' }}</p>
        @if (!empty($seksyen['bullet_points']))
            <ul>
                @foreach ($seksyen['bullet_points'] as $point)
                    <li>{{ $point }}</li>
                @endforeach
            </ul>
        @endif
    @endforeach

    @if (!empty($briefing['kesimpulan']))
        <h2>Kesimpulan</h2>
        <p>{{ $briefing['kesimpulan'] }}</p>
    @endif

    @if (!empty($briefing['tindakan_segera']))
        <div class="urgent">
            <strong>Tindakan Segera</strong>
            <ul>
                @foreach ($briefing['tindakan_segera'] as $tindakan)
                    <li>{{ $tindakan }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if (!empty($seatScores))
        <h2>Skor Kesihatan Kerusi</h2>
        <table>
            <thead>
                <tr>
                    <th>Kerusi</th>
                    <th>Daftar</th>
                    <th>Diculaan</th>
                    <th>Liputan %</th>
                    <th>Putih</th>
                    <th>Hitam</th>
                    <th>Kelabu</th>
                    <th>Skor</th>
                    <th>Kategori</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($seatScores as $seat)
                    <tr>
                        <td>{{ $seat['kerusi'] ?? '' }}</td>
                        <td>{{ number_format($seat['daftar'] ?? 0) }}</td>
                        <td>{{ number_format($seat['culaan'] ?? 0) }}</td>
                        <td>{{ $seat['liputan'] ?? 0 }}</td>
                        <td>{{ number_format($seat['putih'] ?? 0) }}</td>
                        <td>{{ number_format($seat['hitam'] ?? 0) }}</td>
                        <td>{{ number_format($seat['kelabu'] ?? 0) }}</td>
                        <td>{{ $seat['skor'] ?? 0 }}</td>
                        <td>{{ $seat['kategori'] ?? '' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <p style="color:#64748b; font-size:9px;">Skor: &ge;75 Selamat &middot; 65-74 Cenderung Kuat &middot; 55-64 Cenderung &middot; 45-54 Berayun &middot; 35-44 Kritikal &middot; &lt;35 Risiko Kalah</p>
    @endif

    <div class="footer">
        Dijana oleh SISDA Digital War Room pada {{ now()->format('d/m/Y H:i') }}. Dokumen ini mengandungi analisis strategik dalaman — edaran terhad.
    </div>
</body>
</html>
