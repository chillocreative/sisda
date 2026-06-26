<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        /* dompdf-safe: tables for layout, no flexbox. */
        @page { margin: 118px 26px 64px 26px; }
        * { box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #0f172a; }

        /* Fixed running header */
        header { position: fixed; top: -98px; left: 0; right: 0; height: 90px; }
        .hbar { width: 100%; border-collapse: collapse; }
        .brand { font-size: 20px; font-weight: bold; letter-spacing: 0.5px; color: #0f172a; }
        .brand .accent { color: #e11d48; }
        .brand small { display: block; font-size: 8px; font-weight: normal; color: #64748b; letter-spacing: 1px; }
        .doc-title { font-size: 14px; font-weight: bold; color: #0f172a; margin: 10px 0 0 0; }
        .confid { display: inline-block; background: #fef2f2; border: 1px solid #fecaca; color: #b91c1c;
                  font-size: 8px; font-weight: bold; letter-spacing: 1px; padding: 2px 8px; border-radius: 3px; }
        .gen { font-size: 8px; color: #94a3b8; margin-top: 4px; }
        .rule { height: 3px; background: #0f172a; margin-top: 8px; }

        /* Filter chips + count */
        .filters { margin-top: 6px; }
        .chip { display: inline-block; background: #f1f5f9; border: 1px solid #e2e8f0; color: #334155;
                font-size: 8px; padding: 2px 7px; border-radius: 10px; margin: 0 3px 3px 0; }
        .chip b { color: #0f172a; }
        .count { float: right; font-size: 9px; color: #475569; }
        .count b { color: #0f172a; font-size: 11px; }

        /* Table */
        table.data { width: 100%; border-collapse: collapse; margin-top: 4px; }
        table.data thead th { background: #0f172a; color: #ffffff; text-align: left; padding: 5px 5px;
                              font-size: 8px; text-transform: uppercase; letter-spacing: 0.3px; }
        table.data tbody td { border-bottom: 1px solid #eef2f7; padding: 4px 5px; font-size: 8.5px; vertical-align: top; }
        table.data tbody tr:nth-child(even) td { background: #f8fafc; }
        .muted { color: #94a3b8; }
        .badge { display: inline-block; color: #ffffff; font-size: 7.5px; font-weight: bold;
                 padding: 1px 6px; border-radius: 8px; margin: 0 2px 1px 0; }

        /* Fixed running footer */
        footer { position: fixed; bottom: -48px; left: 0; right: 0; height: 38px;
                 border-top: 1px solid #e2e8f0; padding-top: 5px; color: #94a3b8; font-size: 8px; }
        .pageno { float: right; }
        .pageno:after { content: "Muka " counter(page) " / " counter(pages); }
    </style>
</head>
<body>
    <header>
        <table class="hbar">
            <tr>
                <td style="vertical-align: top;">
                    <div class="brand">SIS<span class="accent">DA</span><small>SISTEM DATA PENGUNDI</small></div>
                </td>
                <td style="vertical-align: top; text-align: right;">
                    <span class="confid">SULIT</span>
                    <div class="gen">Dijana: {{ $generatedAt }}</div>
                </td>
            </tr>
        </table>
        <div class="doc-title">{{ $title }}</div>
        <div class="rule"></div>
        <div class="filters">
            @forelse ($filters as $f)
                <span class="chip">{{ $f['label'] }}: <b>{{ $f['value'] }}</b></span>
            @empty
                <span class="chip">Tiada tapisan — semua rekod</span>
            @endforelse
            <span class="count">Jumlah: <b>{{ number_format($total) }}</b> rekod</span>
        </div>
    </header>

    <footer>
        SISDA — Sistem Data Pengundi &middot; Dokumen sulit untuk kegunaan dalaman sahaja
        <span class="pageno"></span>
    </footer>

    <main>
        <table class="data">
            <thead>
                <tr>
                    @foreach ($columns as $col)
                        <th>{{ $col }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                    <tr>
                        @foreach ($row as $cell)
                            <td>
                                @if (is_array($cell))
                                    @forelse ($cell as $b)
                                        <span class="badge" style="background: {{ $b['color'] }};">{{ $b['text'] }}</span>
                                    @empty
                                        <span class="muted">-</span>
                                    @endforelse
                                @else
                                    {{ ($cell === null || $cell === '') ? '' : $cell }}@if ($cell === null || $cell === '')<span class="muted">-</span>@endif
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @empty
                    <tr><td colspan="{{ count($columns) }}" style="text-align:center; padding: 24px; color:#94a3b8;">Tiada rekod sepadan dengan tapisan.</td></tr>
                @endforelse
            </tbody>
        </table>
    </main>
</body>
</html>
