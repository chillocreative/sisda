import { Loader2, Check, AlertCircle } from 'lucide-react';
import { usePilihanrayaTheme } from './PilihanrayaShell';
import EditableCell from './EditableCell';
import DragScroll from '../analisa/DragScroll';

/* ------------------------------- helpers ------------------------------- */

export const fmt = (n) => (n == null || Number.isNaN(n) ? '0' : Number(n).toLocaleString('en-MY'));
// The scoresheet carries NO registered-voter (berdaftar) count — column (A) is
// ballots in the box, not registrations. null means "not in this reference",
// and must render as an honest '—', never a fabricated 0.
export const fmtOrDash = (n) => (n == null ? '—' : fmt(n));
export const pct = (num, den) => (den == null || den <= 0 ? '—' : `${((num / den) * 100).toFixed(1)}%`);
// Kunci sel grid kemasukan. `contest` ialah komponen PERTAMA dan WAJIB: pada
// borang serentak, jalur PRU dan jalur PRN berkongsi (pusat, saluran, slot)
// yang SAMA, jadi kunci tanpa contest akan membuat satu jalur menulis ganti
// satu lagi dalam votes/cellStatus/requestSeq — pepijat tulis-ganti senyap
// yang sama seperti key-drift dahulu, cuma satu lapisan lebih tinggi.
//
// BENTUK INI IALAH KONTRAK MERENTAS SEMPADAN — Borang14Controller::cellKey()
// di server mesti menghasilkan rentetan yang SERUPA BIT, kerana peta `votes`
// datang daripadanya. Jika ia menyimpang, setiap sel dipapar kosong.
export const cellKey = (contest, pusat, saluran, slot) => `${contest}|${pusat ?? ''}|${saluran}|${slot}`;

// Undi Awal & Undi Pos are combined into a single row only for DUN Buloh Kasap.
export const BULOH_KASAP_KADUN_ID = 41;

// Flatten reference into one block per Pusat Mengundi (a DM may have several).
// berdaftar stays null (never coerced to 0) when the source is a scoresheet —
// the sheet itself has no registered-voter column.
export function toBlocks(reference) {
    if (!reference) return [];
    return reference.daerah_mengundi.flatMap((dm) =>
        dm.pusat_mengundi.map((p) => {
            const known = p.saluran.some((x) => x.berdaftar != null) || p.jumlah_berdaftar != null;
            return {
                dm: dm.nama,
                pusat: p.nama,
                berdaftar: known
                    ? (p.jumlah_berdaftar ?? p.saluran.reduce((s, x) => s + (x.berdaftar || 0), 0))
                    : null,
                saluran: p.saluran,
            };
        }),
    );
}

/* --------------------------- lead highlighting ------------------------- */

// Classify each value in a row against the row max: the highest lead(s) win
// (green), the rest trail (red). All-zero rows stay neutral.
export function leadStatus(values) {
    const max = Math.max(0, ...values);
    if (max <= 0) return values.map(() => 'none');
    return values.map((v) => (v === max ? 'lead' : 'low'));
}

export function LeadSquare({ status }) {
    if (status === 'none') return null;
    return (
        <span
            className={`inline-block h-3 w-3 rounded-sm shrink-0 ${status === 'lead' ? 'bg-emerald-500' : 'bg-rose-500'}`}
            aria-hidden="true"
        />
    );
}

export const totalBgClass = (status, t) => (
    status === 'lead' ? 'bg-emerald-100 text-emerald-800'
        : status === 'low' ? 'bg-rose-100 text-rose-800'
            : t.text
);

/* --------------------------- save-status dot ---------------------------- */

// Per-cell autosave feedback: blank when untouched, quiet spinner while the
// request is in flight, a quiet green tick that fades on success, and an
// unmissable, non-auto-dismissing red icon on failure (see cellStatus in
// Borang14.jsx — the failure must stay visible until the cell is re-saved).
export function SaveStatusDot({ status }) {
    if (!status) return <span className="inline-block w-3.5" aria-hidden="true" />;
    if (status === 'saving') return <Loader2 className="h-3.5 w-3.5 animate-spin text-slate-400" aria-label="Menyimpan…" />;
    if (status === 'saved') return <Check className="h-3.5 w-3.5 text-emerald-500" aria-label="Disimpan" />;
    return (
        <AlertCircle
            className="h-3.5 w-3.5 text-rose-500"
            aria-label="Gagal disimpan"
            title="Gagal disimpan — ubah nilai sel ini untuk cuba semula"
        />
    );
}

/* ------------------ jalur pertandingan (band) — dikongsi ---------------- */

// Nilai terbitan SATU jalur pertandingan bagi SATU baris saluran.
//
// Setiap bacaan undi melalui cellKey(contest, …). Tiada satu pun kunci tanpa
// contest terhasil di sini — pada borang serentak jalur PRU dan jalur PRN
// berkongsi (pusat, saluran, slot) yang SAMA, jadi kunci tanpa contest akan
// membuat satu jalur memapar angka jalur yang satu lagi.
export function bandRowValues(votes, contest, pusat, saluran, nParties) {
    const slots = Array.from({ length: nParties }, (_, i) =>
        votes[cellKey(contest, pusat, saluran, i + 1)] ?? 0);
    const ditolak = votes[cellKey(contest, pusat, saluran, 90)] ?? 0;
    const tidakMasuk = votes[cellKey(contest, pusat, saluran, 91)] ?? 0;
    const undian = slots.reduce((a, b) => a + b, 0);         // JUMLAH UNDIAN = Σ undi calon
    return {
        slots, ditolak, tidakMasuk, undian,
        keluar: undian + ditolak + tidakMasuk,               // (A) = Σ undi + (C) + (D)
        status: leadStatus(slots),
    };
}

// Jumlah setiap lajur satu jalur merentas semua baris saluran.
export function bandTotals(rows, nParties) {
    return {
        slots: Array.from({ length: nParties }, (_, i) => rows.reduce((a, r) => a + r.slots[i], 0)),
        ditolak: rows.reduce((a, r) => a + r.ditolak, 0),
        tidakMasuk: rows.reduce((a, r) => a + r.tidakMasuk, 0),
        undian: rows.reduce((a, r) => a + r.undian, 0),
        keluar: rows.reduce((a, r) => a + r.keluar, 0),
    };
}

// Sel BOLEH SUNTING bagi satu jalur: slot parti 1..n, diikuti Tolak (90) dan
// T.Msk (91). Dikongsi oleh jadual satu jalur DAN dua jalur supaya hanya ada
// SATU tempat di seluruh skrin yang menentukan pertandingan mana yang ditulis
// oleh sesuatu sel — `contest` yang sama menjadi kunci paparan dan muatan POST.
//
// `readOnly` (borang DIKUNCI) dikendalikan DI SINI dan bukan di setiap jadual:
// ini satu-satunya tempat sel boleh-taip dilahirkan, jadi tiada jadual yang
// boleh terlepas pandang dan tinggal boleh disunting.
function BandCells({ contest, pusat, saluran, row, cellStatus, onSave, maxFor, tint = '', readOnly = false }) {
    const tdClass = tint ? `px-2 py-1 ${tint}` : 'px-2 py-1';

    return [
        ...row.slots.map((v, i) => ({ slot: i + 1, v, lead: row.status[i] })),
        { slot: 90, v: row.ditolak, lead: null },
        { slot: 91, v: row.tidakMasuk, lead: null },
    ].map(({ slot, v, lead }) => {
        const key = cellKey(contest, pusat, saluran, slot);
        return (
            <td key={slot} className={tdClass}>
                <div className="flex items-center justify-end gap-1.5">
                    {lead && <LeadSquare status={lead} />}
                    <SaveStatusDot status={cellStatus[key]} />
                    <EditableCell
                        value={v}
                        invalid={cellStatus[key] === 'error'}
                        disabled={readOnly}
                        max={maxFor(v)}
                        onCommit={(undi) => onSave(pusat, saluran, slot, undi, contest)}
                    />
                </div>
            </td>
        );
    });
}

/* --------------------------- per-pusat table --------------------------- */

// `contest` menentukan ruang nama kunci sel jadual ini DAN pertandingan yang
// ditulis oleh autosimpan. Ia WAJIB — tiada nilai lalai, kerana lalai yang
// senyap pada skrin dua jalur bermakna undi PRU ditulis ke pertandingan PRN.
export function VoteTable({ block, partyNames, votes, onSave, anchorId, contest, cellStatus = {}, readOnly = false }) {
    const { t } = usePilihanrayaTheme();
    const nParties = partyNames.length;

    const rows = block.saluran.map((s) => ({
        no: s.no,
        berdaftar: s.berdaftar ?? null,                       // null → render '—', never 0
        ...bandRowValues(votes, contest, block.pusat, String(s.no), nParties),
    }));

    const totals = {
        ...bandTotals(rows, nParties),
        berdaftarKnown: rows.some((r) => r.berdaftar != null),
        berdaftar: rows.reduce((a, r) => a + (r.berdaftar || 0), 0),
    };
    const totalStatus = leadStatus(totals.slots);

    return (
        <div id={anchorId} className={`${t.card} p-4 scroll-mt-24`}>
            <div className="mb-3">
                <div className={`text-xs font-semibold uppercase tracking-wider ${t.subtext}`}>DM: {block.dm}</div>
                <div className={`text-sm font-bold ${t.text}`}>Pusat Mengundi: {block.pusat}</div>
            </div>
            <DragScroll>
                <table className="min-w-full border-collapse">
                    <thead>
                        <tr>
                            <th className={`${t.tableHead} whitespace-nowrap`}>Saluran</th>
                            {partyNames.map((p, i) => (
                                <th key={i} className={`${t.tableHead} whitespace-nowrap text-right`}>{p}</th>
                            ))}
                            <th className={`${t.tableHead} whitespace-nowrap text-right`}>Ditolak (C)</th>
                            <th className={`${t.tableHead} whitespace-nowrap text-right`}>Tak Dimasukkan (D)</th>
                            <th className={`${t.tableHead} whitespace-nowrap text-right`}>Jumlah Undian</th>
                            <th className={`${t.tableHead} whitespace-nowrap text-right`}>Jumlah Keluar</th>
                            <th className={`${t.tableHead} whitespace-nowrap text-right`}>Berdaftar</th>
                            <th className={`${t.tableHead} whitespace-nowrap text-right`}>% Turnout</th>
                            <th className={`${t.tableHead} whitespace-nowrap text-right`}>Tak Keluar</th>
                            <th className={`${t.tableHead} whitespace-nowrap text-right`}>% Tak Keluar</th>
                        </tr>
                    </thead>
                    <tbody>
                        {rows.map((r) => (
                            <tr key={r.no} className={t.tableRow}>
                                <td className={`${t.tableCell} font-medium whitespace-nowrap`}>Saluran {r.no}</td>
                                <BandCells
                                    contest={contest}
                                    pusat={block.pusat}
                                    saluran={String(r.no)}
                                    row={r}
                                    cellStatus={cellStatus}
                                    onSave={onSave}
                                    readOnly={readOnly}
                                    maxFor={(v) => (r.berdaftar != null ? Math.max(0, r.berdaftar - (r.keluar - v)) : null)}
                                />
                                <td className={`${t.tableCell} text-right`}>{fmt(r.undian)}</td>
                                <td className={`${t.tableCell} text-right font-semibold`}>{fmt(r.keluar)}</td>
                                <td className={`${t.tableCell} text-right`}>{fmtOrDash(r.berdaftar)}</td>
                                <td className={`${t.tableCell} text-right`}>{r.berdaftar == null ? '—' : pct(r.keluar, r.berdaftar)}</td>
                                <td className={`${t.tableCell} text-right`}>{r.berdaftar == null ? '—' : fmt(Math.max(0, r.berdaftar - r.keluar))}</td>
                                <td className={`${t.tableCell} text-right`}>{r.berdaftar == null ? '—' : pct(Math.max(0, r.berdaftar - r.keluar), r.berdaftar)}</td>
                            </tr>
                        ))}
                        <tr className={`border-t-2 ${t.border} font-bold`}>
                            <td className={`${t.tableCell} font-bold whitespace-nowrap`}>Jumlah Undi</td>
                            {totals.slots.map((v, i) => (
                                <td key={i} className={`px-3 py-2 text-sm text-right font-bold ${totalBgClass(totalStatus[i], t)}`}>{fmt(v)}</td>
                            ))}
                            <td className={`${t.tableCell} text-right font-bold`}>{fmt(totals.ditolak)}</td>
                            <td className={`${t.tableCell} text-right font-bold`}>{fmt(totals.tidakMasuk)}</td>
                            <td className={`${t.tableCell} text-right font-bold`}>{fmt(totals.undian)}</td>
                            <td className={`${t.tableCell} text-right font-bold`}>{fmt(totals.keluar)}</td>
                            <td className={`${t.tableCell} text-right font-bold`}>{totals.berdaftarKnown ? fmt(totals.berdaftar) : '—'}</td>
                            <td className={`${t.tableCell} text-right font-bold`}>{totals.berdaftarKnown ? pct(totals.keluar, totals.berdaftar) : '—'}</td>
                            <td className={`${t.tableCell} text-right font-bold`}>{totals.berdaftarKnown ? fmt(Math.max(0, totals.berdaftar - totals.keluar)) : '—'}</td>
                            <td className={`${t.tableCell} text-right font-bold`}>{totals.berdaftarKnown ? pct(Math.max(0, totals.berdaftar - totals.keluar), totals.berdaftar) : '—'}</td>
                        </tr>
                    </tbody>
                </table>
            </DragScroll>
        </div>
    );
}

/* ----------------------- undi awal / undi pos -------------------------- */

export function UndiAwalPosTable({ partyNames, votes, onSave, rows, contest, cellStatus = {}, readOnly = false }) {
    const { t } = usePilihanrayaTheme();
    const nParties = partyNames.length;

    return (
        <div className={`${t.card} p-4`}>
            <div className={`text-sm font-bold ${t.text} mb-3`}>Undi Awal & Undi Pos</div>
            <DragScroll>
                <table className="min-w-full border-collapse">
                    <thead>
                        <tr>
                            <th className={`${t.tableHead} whitespace-nowrap`}>Saluran</th>
                            {partyNames.map((p, i) => (
                                <th key={i} className={`${t.tableHead} whitespace-nowrap text-right`}>{p}</th>
                            ))}
                            <th className={`${t.tableHead} whitespace-nowrap text-right`}>Ditolak (C)</th>
                            <th className={`${t.tableHead} whitespace-nowrap text-right`}>Tak Dimasukkan (D)</th>
                            <th className={`${t.tableHead} whitespace-nowrap text-right`}>Jumlah Undian</th>
                            <th className={`${t.tableHead} whitespace-nowrap text-right`}>Jumlah Keluar</th>
                            <th className={`${t.tableHead} whitespace-nowrap text-right`}>Berdaftar</th>
                            <th className={`${t.tableHead} whitespace-nowrap text-right`}>% Turnout</th>
                            <th className={`${t.tableHead} whitespace-nowrap text-right`}>Tak Keluar</th>
                            <th className={`${t.tableHead} whitespace-nowrap text-right`}>% Tak Keluar</th>
                        </tr>
                    </thead>
                    <tbody>
                        {rows.map(({ label, berdaftar }) => {
                            const r = bandRowValues(votes, contest, '', label, nParties);
                            const { undian, keluar } = r;
                            const berdaftarKnown = berdaftar != null;
                            return (
                                <tr key={label} className={t.tableRow}>
                                    <td className={`${t.tableCell} font-medium whitespace-nowrap`}>{label}</td>
                                    <BandCells
                                        contest={contest}
                                        pusat=""
                                        saluran={label}
                                        row={r}
                                        cellStatus={cellStatus}
                                        onSave={onSave}
                                        readOnly={readOnly}
                                        maxFor={() => null}
                                    />
                                    <td className={`${t.tableCell} text-right`}>{fmt(undian)}</td>
                                    <td className={`${t.tableCell} text-right font-semibold`}>{fmt(keluar)}</td>
                                    <td className={`${t.tableCell} text-right`}>{fmtOrDash(berdaftar)}</td>
                                    <td className={`${t.tableCell} text-right`}>{berdaftarKnown ? pct(keluar, berdaftar) : '—'}</td>
                                    <td className={`${t.tableCell} text-right`}>{berdaftarKnown ? fmt(Math.max(0, berdaftar - keluar)) : '—'}</td>
                                    <td className={`${t.tableCell} text-right`}>{berdaftarKnown ? pct(Math.max(0, berdaftar - keluar), berdaftar) : '—'}</td>
                                </tr>
                            );
                        })}
                    </tbody>
                </table>
            </DragScroll>
        </div>
    );
}

/* ------------------------ jadual dua jalur (serentak) ------------------- */

// Warna jalur: PRN merah, PRU biru. Warna yang SAMA diulang pada kepala, badan
// dan baris jumlah supaya mata mengikut satu jalur lurus ke bawah tanpa tersasar
// ke jalur sebelah — dua kertas undi yang bentuknya hampir serupa ialah punca
// silap masuk pada pukul 11 malam.
const BAND_STYLE = {
    dun: { head: 'bg-rose-100 text-rose-900', cell: 'bg-rose-50' },
    parlimen: { head: 'bg-sky-100 text-sky-900', cell: 'bg-sky-50' },
};
const bandStyle = (contest) => BAND_STYLE[contest] ?? BAND_STYLE.dun;

// Lebar satu jalur: slot parti + Tolak + T.Msk + Jum. Undian + Jum. Keluar + % Keluar.
const bandSpan = (band) => band.partyNames.length + 5;

// Kepala dua baris: baris pertama menamakan pertandingan DAN kerusinya, baris
// kedua lajur sebenar. `Saluran` dan `Berdaftar` merentang kedua-dua baris
// kerana ia DI LUAR mana-mana jalur — Berdaftar dikongsi oleh kedua-dua
// pertandingan, dan kedudukannya itulah yang membawa makna tersebut.
function BandHead({ bands, t }) {
    return (
        <>
            <tr>
                <th rowSpan={2} className={`${t.tableHead} whitespace-nowrap align-bottom`}>Saluran</th>
                {bands.map((band) => (
                    <th
                        key={band.contest}
                        colSpan={bandSpan(band)}
                        className={`px-3 py-2 text-center text-xs font-bold uppercase tracking-wider whitespace-nowrap border-x-2 border-white ${bandStyle(band.contest).head}`}
                    >
                        {band.tajuk}
                    </th>
                ))}
                <th rowSpan={2} className={`${t.tableHead} whitespace-nowrap text-right align-bottom`}>Berdaftar</th>
            </tr>
            <tr>
                {bands.flatMap((band) => {
                    const { head } = bandStyle(band.contest);
                    return [
                        ...band.partyNames.map((p, i) => (
                            <th key={`${band.contest}-p${i}`} className={`${t.tableHead} whitespace-nowrap text-right ${head}`}>{p}</th>
                        )),
                        <th key={`${band.contest}-90`} className={`${t.tableHead} whitespace-nowrap text-right ${head}`}>Tolak (C)</th>,
                        <th key={`${band.contest}-91`} className={`${t.tableHead} whitespace-nowrap text-right ${head}`}>T.Msk (D)</th>,
                        <th key={`${band.contest}-undian`} className={`${t.tableHead} whitespace-nowrap text-right ${head}`}>Jum. Undian</th>,
                        <th key={`${band.contest}-keluar`} className={`${t.tableHead} whitespace-nowrap text-right ${head}`}>Jum. Keluar</th>,
                        <th key={`${band.contest}-pct`} className={`${t.tableHead} whitespace-nowrap text-right ${head}`}>% Keluar</th>,
                    ];
                })}
            </tr>
        </>
    );
}

// Satu baris saluran merentas semua jalur. `berdaftar` dihantar masuk kerana ia
// dikongsi: ia mengehadkan sel kedua-dua jalur dan menjadi penyebut kedua-dua
// peratusan, tetapi ia bukan milik mana-mana jalur.
function BandRow({ bands, votes, pusat, saluran, berdaftar, cellStatus, onSave, readOnly = false }) {
    const { t } = usePilihanrayaTheme();

    return (
        <>
            {bands.flatMap((band) => {
                const r = bandRowValues(votes, band.contest, pusat, saluran, band.partyNames.length);
                const { cell } = bandStyle(band.contest);
                return [
                    <BandCells
                        key={`${band.contest}-sel`}
                        contest={band.contest}
                        pusat={pusat}
                        saluran={saluran}
                        row={r}
                        cellStatus={cellStatus}
                        onSave={onSave}
                        readOnly={readOnly}
                        tint={cell}
                        maxFor={(v) => (berdaftar != null ? Math.max(0, berdaftar - (r.keluar - v)) : null)}
                    />,
                    <td key={`${band.contest}-undian`} className={`${t.tableCell} text-right ${cell}`}>{fmt(r.undian)}</td>,
                    <td key={`${band.contest}-keluar`} className={`${t.tableCell} text-right font-semibold ${cell}`}>{fmt(r.keluar)}</td>,
                    // Penyebut dikongsi, jadi '—' apabila Berdaftar tidak diketahui —
                    // JANGAN sekali-kali papar 0% daripada berdaftar yang tiada.
                    <td key={`${band.contest}-pct`} className={`${t.tableCell} text-right ${cell}`}>
                        {berdaftar == null ? '—' : pct(r.keluar, berdaftar)}
                    </td>,
                ];
            })}
        </>
    );
}

// Baris jumlah bagi semua jalur.
function BandTotalRow({ bands, rows, votes, t }) {
    const berdaftarKnown = rows.some((r) => r.berdaftar != null);
    const berdaftar = rows.reduce((a, r) => a + (r.berdaftar || 0), 0);

    return (
        <tr className={`border-t-2 ${t.border} font-bold`}>
            <td className={`${t.tableCell} font-bold whitespace-nowrap`}>Jumlah Undi</td>
            {bands.flatMap((band) => {
                const n = band.partyNames.length;
                const perRow = rows.map((r) => bandRowValues(votes, band.contest, r.pusat, r.saluran, n));
                const totals = bandTotals(perRow, n);
                const status = leadStatus(totals.slots);
                const { cell } = bandStyle(band.contest);
                return [
                    ...totals.slots.map((v, i) => (
                        <td key={`${band.contest}-p${i}`} className={`px-3 py-2 text-sm text-right font-bold ${totalBgClass(status[i], t)}`}>{fmt(v)}</td>
                    )),
                    <td key={`${band.contest}-90`} className={`${t.tableCell} text-right font-bold ${cell}`}>{fmt(totals.ditolak)}</td>,
                    <td key={`${band.contest}-91`} className={`${t.tableCell} text-right font-bold ${cell}`}>{fmt(totals.tidakMasuk)}</td>,
                    <td key={`${band.contest}-undian`} className={`${t.tableCell} text-right font-bold ${cell}`}>{fmt(totals.undian)}</td>,
                    <td key={`${band.contest}-keluar`} className={`${t.tableCell} text-right font-bold ${cell}`}>{fmt(totals.keluar)}</td>,
                    <td key={`${band.contest}-pct`} className={`${t.tableCell} text-right font-bold ${cell}`}>
                        {berdaftarKnown ? pct(totals.keluar, berdaftar) : '—'}
                    </td>,
                ];
            })}
            <td className={`${t.tableCell} text-right font-bold`}>{berdaftarKnown ? fmt(berdaftar) : '—'}</td>
        </tr>
    );
}

// Jadual satu Pusat Mengundi untuk pilihan raya SERENTAK: satu jalur berjalur
// warna bagi setiap pertandingan, Berdaftar dikongsi di luar kedua-duanya.
//
// Bilangan sel parti setiap jalur datang daripada `band.partyNames`, yang
// dibina oleh pemanggil daripada `penjuru` PERTANDINGAN ITU SENDIRI — penjuru
// borang DUN bagi jalur PRN, penjuru borang Parlimen yang dipaut bagi jalur PRU.
export function VoteTableSerentak({ block, bands, votes, onSave, anchorId, cellStatus = {}, readOnly = false }) {
    const { t } = usePilihanrayaTheme();
    const rows = block.saluran.map((s) => ({
        pusat: block.pusat,
        saluran: String(s.no),
        berdaftar: s.berdaftar ?? null,                       // null → '—', bukan 0
    }));

    return (
        <div id={anchorId} className={`${t.card} p-4 scroll-mt-24`}>
            <div className="mb-3">
                <div className={`text-xs font-semibold uppercase tracking-wider ${t.subtext}`}>DM: {block.dm}</div>
                <div className={`text-sm font-bold ${t.text}`}>Pusat Mengundi: {block.pusat}</div>
            </div>
            <DragScroll>
                <table className="min-w-full border-collapse">
                    <thead><BandHead bands={bands} t={t} /></thead>
                    <tbody>
                        {rows.map((r) => (
                            <tr key={r.saluran} className={t.tableRow}>
                                <td className={`${t.tableCell} font-medium whitespace-nowrap`}>Saluran {r.saluran}</td>
                                <BandRow
                                    bands={bands}
                                    votes={votes}
                                    pusat={r.pusat}
                                    saluran={r.saluran}
                                    berdaftar={r.berdaftar}
                                    cellStatus={cellStatus}
                                    onSave={onSave}
                                    readOnly={readOnly}
                                />
                                <td className={`${t.tableCell} text-right`}>{fmtOrDash(r.berdaftar)}</td>
                            </tr>
                        ))}
                        <BandTotalRow bands={bands} rows={rows} votes={votes} t={t} />
                    </tbody>
                </table>
            </DragScroll>
        </div>
    );
}

// Undi Awal & Undi Pos versi dua jalur — struktur sama, cuma barisnya berlabel
// dan tiada had `max` kerana baris ini tiada Berdaftar sendiri.
export function UndiAwalPosTableSerentak({ bands, votes, onSave, rows, cellStatus = {}, readOnly = false }) {
    const { t } = usePilihanrayaTheme();
    const baris = rows.map(({ label, berdaftar }) => ({ pusat: '', saluran: label, berdaftar: berdaftar ?? null }));

    return (
        <div className={`${t.card} p-4`}>
            <div className={`text-sm font-bold ${t.text} mb-3`}>Undi Awal &amp; Undi Pos</div>
            <DragScroll>
                <table className="min-w-full border-collapse">
                    <thead><BandHead bands={bands} t={t} /></thead>
                    <tbody>
                        {baris.map((r) => (
                            <tr key={r.saluran} className={t.tableRow}>
                                <td className={`${t.tableCell} font-medium whitespace-nowrap`}>{r.saluran}</td>
                                <BandRow
                                    bands={bands}
                                    votes={votes}
                                    pusat=""
                                    saluran={r.saluran}
                                    berdaftar={r.berdaftar}
                                    cellStatus={cellStatus}
                                    onSave={onSave}
                                    readOnly={readOnly}
                                />
                                <td className={`${t.tableCell} text-right`}>{fmtOrDash(r.berdaftar)}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </DragScroll>
        </div>
    );
}

/* --------------------------- grand summary ----------------------------- */

// Bottom-of-page rollup across every pusat mengundi + undi awal & pos:
// per-party grand totals, Ditolak/Tak Dimasukkan, total turnout (sum of
// parties + C + D), and overall % — honest '—' when berdaftar is unknown.
// `tajuk` melalaikan kepada teks satu pertandingan yang sedia ada — skrin dua
// jalur menghantarnya secara eksplisit ("Ringkasan PRN · DUN GEMAS") supaya
// tiada ringkasan tanpa nama yang boleh disangka meliputi kedua-dua kertas undi.
export function GrandSummary({ partyNames, totals, tajuk = 'Ringkasan Keseluruhan' }) {
    const { t } = usePilihanrayaTheme();
    const status = leadStatus(totals.partyTotals);

    const tileTone = (s) => (
        s === 'lead' ? 'border-emerald-300 bg-emerald-50'
            : s === 'low' ? 'border-rose-300 bg-rose-50'
                : `${t.border} bg-slate-50`
    );
    const valueTone = (s) => (
        s === 'lead' ? 'text-emerald-700'
            : s === 'low' ? 'text-rose-700'
                : t.text
    );

    return (
        <div className={`${t.card} mt-4 mb-4`}>
            <div className="mb-4">
                <div className={`text-sm font-bold ${t.text}`}>{tajuk}</div>
                <div className={`text-xs ${t.subtext}`}>Semua pusat mengundi termasuk undi awal &amp; undi pos</div>
            </div>
            <div className="grid grid-cols-2 lg:grid-cols-4 gap-3">
                {partyNames.map((name, i) => (
                    <div key={i} className={`rounded-xl border p-4 ${tileTone(status[i])}`}>
                        <div className={`text-xs font-semibold uppercase tracking-wider ${t.subtext} flex items-center gap-1.5`}>
                            <LeadSquare status={status[i]} /> {name}
                        </div>
                        <div className={`text-2xl font-bold mt-1 ${valueTone(status[i])}`}>{fmt(totals.partyTotals[i])}</div>
                        <div className={`text-xs ${t.subtext} mt-0.5`}>Jumlah undi</div>
                    </div>
                ))}
                <div className={`rounded-xl border ${t.border} bg-slate-50 p-4`}>
                    <div className={`text-xs font-semibold uppercase tracking-wider ${t.subtext}`}>Ditolak (C) / Tak Dimasukkan (D)</div>
                    <div className={`text-2xl font-bold mt-1 ${t.text}`}>{fmt(totals.ditolak)} / {fmt(totals.tidakDimasukkan)}</div>
                    <div className={`text-xs ${t.subtext} mt-0.5`}>Kertas undi bermasalah</div>
                </div>
                <div className={`rounded-xl border ${t.border} bg-slate-50 p-4`}>
                    <div className={`text-xs font-semibold uppercase tracking-wider ${t.subtext}`}>Jumlah Keluar Mengundi</div>
                    <div className={`text-2xl font-bold mt-1 ${t.text}`}>{fmt(totals.keluar)}</div>
                    <div className={`text-xs ${t.subtext} mt-0.5`}>{partyNames.join(' + ') || 'Semua parti'} + C + D</div>
                </div>
                <div className="rounded-xl border border-sky-300 bg-sky-50 p-4">
                    <div className="text-xs font-semibold uppercase tracking-wider text-sky-700">Peratusan Keluar Mengundi</div>
                    <div className="text-2xl font-bold mt-1 text-sky-800">
                        {totals.berdaftarKnown ? pct(totals.keluar, totals.berdaftar) : '—'}
                    </div>
                    <div className="text-xs text-sky-700/80 mt-0.5">
                        {totals.berdaftarKnown ? `${fmt(totals.keluar)} / ${fmt(totals.berdaftar)} berdaftar` : 'Berdaftar tiada dalam scoresheet — perlukan rujukan SPR'}
                    </div>
                </div>
            </div>
        </div>
    );
}
