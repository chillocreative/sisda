@php
    // Semua angka diterbitkan di sini daripada $tree (keadaan tersimpan DB).
    // Slot dikira DIDAFTARKAN apabila mempunyai Nama; Pusat LENGKAP apabila
    // semua slotnya bernama. Nilai kosong dipapar '—' (bukan 0/andaian).
    $pusatList = $tree['pusat'] ?? [];
    $dash = '—';

    $totalSlot = 0;
    $totalTerisi = 0;
    $pusatLengkap = 0;
    foreach ($pusatList as $p) {
        $pSlot = 0; $pTerisi = 0;
        foreach ($p['saluran'] as $s) {
            foreach ($s['slot'] as $sl) {
                $pSlot++;
                if (trim((string) ($sl['petugas_nama'] ?? '')) !== '') { $pTerisi++; }
            }
        }
        $totalSlot += $pSlot;
        $totalTerisi += $pTerisi;
        if ($pSlot > 0 && $pTerisi === $pSlot) { $pusatLengkap++; }
    }
    $pusatBelum = count($pusatList) - $pusatLengkap;
    $peratus = $totalSlot > 0 ? round($totalTerisi / $totalSlot * 100) : 0;

    $masaLabel = function ($mula, $tamat) use ($dash) {
        if (empty($mula)) { return $dash; }
        return $mula.' - '.(empty($tamat) ? 'selesai' : $tamat);
    };
    $atau = fn ($v) => trim((string) ($v ?? '')) === '' ? $dash : $v;
@endphp
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="utf-8">
    <title>Roster PACA — {{ $kerusi ?? 'SISDA' }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #0f172a; background: #fff; }

        .header { background: #0f172a; padding: 20px 26px 16px; }
        .header-logo { font-size: 10px; font-weight: bold; color: #10b981; letter-spacing: 0.12em; text-transform: uppercase; }
        .header-title { font-size: 18px; font-weight: bold; color: #ffffff; margin-top: 4px; line-height: 1.25; }
        .header-sub { font-size: 9px; color: #94a3b8; margin-top: 5px; }
        .accent-bar { height: 4px; background: #10b981; }

        .section { padding: 12px 26px; }

        .summary-table { width: 100%; border-collapse: separate; border-spacing: 6px; margin: -6px 0 4px; }
        .summary-cell { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 9px 12px; vertical-align: top; width: 25%; }
        .summary-label { font-size: 8px; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em; }
        .summary-value { font-size: 19px; font-weight: bold; margin-top: 3px; line-height: 1; color: #0f172a; }

        .pusat { margin-top: 14px; page-break-inside: avoid; }
        .pusat-head { border-bottom: 1.5px solid #cbd5e1; padding-bottom: 5px; margin-bottom: 8px; }
        .pusat-name { font-size: 13px; font-weight: bold; color: #0f172a; }
        .pusat-dm { font-size: 8.5px; color: #94a3b8; margin-top: 1px; }
        .pusat-meta { font-size: 9px; color: #475569; margin-top: 4px; }
        .badge { display: inline-block; font-size: 8px; font-weight: bold; padding: 2px 8px; border-radius: 3px; letter-spacing: 0.04em; }
        .badge-ok { background: #dcfce7; color: #15803d; }
        .badge-no { background: #fef3c7; color: #b45309; }

        .saluran-title { font-size: 9.5px; font-weight: bold; color: #334155; margin: 8px 0 4px; }

        table.grid { width: 100%; border-collapse: collapse; font-size: 9px; }
        table.grid th { background: #1e293b; color: #f1f5f9; padding: 5px 7px; font-size: 8px; text-align: left; white-space: nowrap; }
        table.grid td { padding: 4px 7px; border-bottom: 1px solid #f1f5f9; text-align: left; vertical-align: middle; }
        table.grid tr:nth-child(even) td { background: #f8fafc; }
        .jawatan { font-weight: bold; color: #0f172a; }
        .kosong { color: #cbd5e1; }

        .footer { background: #f8fafc; border-top: 1px solid #e2e8f0; padding: 9px 26px; margin-top: 12px; }
        .footer-text { font-size: 8px; color: #94a3b8; }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-logo">SISDA · Sistem Data Pengundi</div>
        <div class="header-title">Senarai Petugas PACA{{ $kerusi ? ' — '.$kerusi : '' }}</div>
        <div class="header-sub">Roster Petugas Pengundian Awal (PA) &amp; Ketua PACA · Dijana {{ $dijana }}</div>
    </div>
    <div class="accent-bar"></div>

    <div class="section">
        <table class="summary-table">
            <tr>
                <td class="summary-cell">
                    <div class="summary-label">Pusat Mengundi</div>
                    <div class="summary-value">{{ count($pusatList) }}</div>
                </td>
                <td class="summary-cell">
                    <div class="summary-label">Pusat Lengkap</div>
                    <div class="summary-value" style="color:#15803d">{{ $pusatLengkap }}</div>
                </td>
                <td class="summary-cell">
                    <div class="summary-label">Belum Lengkap</div>
                    <div class="summary-value" style="color:#b45309">{{ $pusatBelum }}</div>
                </td>
                <td class="summary-cell">
                    <div class="summary-label">Petugas Didaftarkan</div>
                    <div class="summary-value">{{ $totalTerisi }}/{{ $totalSlot }} <span style="font-size:10px;color:#94a3b8">({{ $peratus }}%)</span></div>
                </td>
            </tr>
        </table>

        @forelse ($pusatList as $p)
            @php
                $pSlot = 0; $pTerisi = 0;
                foreach ($p['saluran'] as $s) { foreach ($s['slot'] as $sl) { $pSlot++; if (trim((string) ($sl['petugas_nama'] ?? '')) !== '') $pTerisi++; } }
                $lengkap = $pSlot > 0 && $pTerisi === $pSlot;
            @endphp
            <div class="pusat">
                <div class="pusat-head">
                    <span class="badge {{ $lengkap ? 'badge-ok' : 'badge-no' }}" style="float:right">{{ $lengkap ? 'LENGKAP' : 'BELUM LENGKAP' }} · {{ $pTerisi }}/{{ $pSlot }}</span>
                    <div class="pusat-name">{{ $p['pusat'] }}</div>
                    @if (!empty($p['dm']))<div class="pusat-dm">{{ $p['dm'] }}</div>@endif
                    <div class="pusat-meta">Ketua PACA: <b>{{ $atau($p['ketua_nama']) }}</b> &nbsp;·&nbsp; Tel: {{ $atau($p['ketua_tel']) }}</div>
                </div>

                @foreach ($p['saluran'] as $s)
                    <div class="saluran-title">Saluran {{ $s['label'] }}</div>
                    <table class="grid">
                        <thead>
                            <tr>
                                <th style="width:9%">Jawatan</th>
                                <th style="width:26%">Nama</th>
                                <th style="width:17%">No K/P</th>
                                <th style="width:15%">No Tel</th>
                                <th style="width:16%">Parti</th>
                                <th style="width:17%">Masa Bertugas</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($s['slot'] as $sl)
                                @php $terisi = trim((string) ($sl['petugas_nama'] ?? '')) !== ''; @endphp
                                <tr>
                                    <td class="jawatan">{{ $sl['jawatan_papar'] ?? $sl['jawatan'] }}</td>
                                    <td class="{{ $terisi ? '' : 'kosong' }}">{{ $terisi ? $sl['petugas_nama'] : $dash }}</td>
                                    <td class="{{ $atau($sl['petugas_kp']) === $dash ? 'kosong' : '' }}">{{ $atau($sl['petugas_kp']) }}</td>
                                    <td class="{{ $atau($sl['petugas_tel']) === $dash ? 'kosong' : '' }}">{{ $atau($sl['petugas_tel']) }}</td>
                                    <td class="{{ $atau($sl['petugas_parti']) === $dash ? 'kosong' : '' }}">{{ $atau($sl['petugas_parti']) }}</td>
                                    <td>{{ $masaLabel($sl['masa_mula'] ?? null, $sl['masa_tamat'] ?? null) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endforeach
            </div>
        @empty
            <p style="color:#64748b;margin-top:12px">Tiada Pusat Mengundi dalam struktur kerusi ini.</p>
        @endforelse
    </div>

    <div class="footer">
        <div class="footer-text">Dijana oleh SISDA · Sistem Data Pengundi — {{ $dijana }}. Dokumen dalaman; mengandungi maklumat peribadi petugas.</div>
    </div>
</body>
</html>
