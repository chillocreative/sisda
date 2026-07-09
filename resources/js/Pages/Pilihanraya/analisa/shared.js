// Shared palette + formatters for the Pilihanraya → Analisa pages.
// Colours are chosen to read as a coherent PH (KEADILAN) command-centre system
// and to stay legible against both the light and dark module themes.

export const PARTY = {
    PH: '#e11d48',      // Pakatan Harapan — rose/red
    BN: '#1d4ed8',      // Barisan Nasional — blue
    PN: '#0d9488',      // Perikatan Nasional — teal
    PEJUANG: '#f59e0b', // amber
    ditolak: '#94a3b8', // slate
};

export const PARTY_LABEL = {
    PH: 'PH',
    BN: 'BN',
    PN: 'PN',
    PEJUANG: 'PEJUANG',
    ditolak: 'Ditolak',
};

export const KAUM = {
    melayu: '#16a34a', // green
    cina: '#dc2626',   // red
    india: '#f59e0b',  // amber
    lain: '#94a3b8',   // slate
};

export const KAUM_LABEL = {
    melayu: 'Melayu',
    cina: 'Cina',
    india: 'India',
    lain: 'Lain-lain',
};

export const STATUS_STYLES = {
    'PH MENANG': 'bg-emerald-500/15 text-emerald-600 border border-emerald-500/40',
    'BN MENANG': 'bg-blue-500/15 text-blue-600 border border-blue-500/40',
    'MUDAH': 'bg-emerald-500/15 text-emerald-600 border border-emerald-500/40',
    'BOLEH DICAPAI': 'bg-emerald-500/15 text-emerald-600 border border-emerald-500/40',
    'SUKAR': 'bg-amber-500/15 text-amber-600 border border-amber-500/40',
    'SANGAT SUKAR': 'bg-orange-500/15 text-orange-600 border border-orange-500/40',
    'TIDAK REALISTIK': 'bg-red-500/15 text-red-600 border border-red-500/40',
};

export const fmt = (n, digits = 0) => {
    if (n === null || n === undefined || n === '' || Number.isNaN(Number(n))) return '—';
    return Number(n).toLocaleString('en-MY', { minimumFractionDigits: digits, maximumFractionDigits: digits });
};

export const pct = (n, digits = 1) => {
    if (n === null || n === undefined || n === '' || Number.isNaN(Number(n))) return '—';
    return `${(Number(n) * 100).toFixed(digits)}%`;
};

export const safeDiv = (a, b) => (b ? a / b : 0);

// Re-derive Keputusan totals from an arbitrary set of rows (used after the
// Daerah Mengundi filter narrows the visible rows).
export function computeKeputusanTotals(rows) {
    const sum = (k) => rows.reduce((s, r) => s + (Number(r[k]) || 0), 0);
    const ph = sum('ph');
    const bn = sum('bn');
    const pn = sum('pn');
    return {
        pemilih: sum('pemilih'),
        keluar: sum('keluar'),
        ph,
        pejuang: sum('pejuang'),
        pn,
        bn,
        ditolak: sum('ditolak'),
        majoriti_bn: Math.abs(bn - ph),
        undi_pn: pn,
    };
}

export function computeKaumTotals(rows) {
    const sum = (k) => rows.reduce((s, r) => s + (Number(r[k]) || 0), 0);
    return {
        melayu: sum('melayu'),
        cina: sum('cina'),
        india: sum('india'),
        lain: sum('lain'),
        jumlah: sum('jumlah'),
    };
}
