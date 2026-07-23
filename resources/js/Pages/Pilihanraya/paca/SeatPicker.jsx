import { useMemo, useState } from 'react';
import { CalendarDays, Landmark, ListFilter, Map, MapPin } from 'lucide-react';
import { usePilihanrayaTheme } from '../components/PilihanrayaShell';

const JENIS_PR_LABEL = { pru: 'PRU', prn: 'PRN', prk: 'PRK' };

const kawasanKeyOf = (s) => `${s.kawasan_type}:${s.kawasan_id}`;
const prKeyOf = (s) => `${s.jenis_pr}:${s.tahun}`;
const uniqSorted = (arr) => [...new Set(arr)].sort((a, b) => a.localeCompare(b, 'ms'));

function Field({ label, icon: Icon, children }) {
    const { t } = usePilihanrayaTheme();
    return (
        <div>
            <label className={t.label}><span className="inline-flex items-center gap-1">{Icon && <Icon className="h-3.5 w-3.5" />} {label}</span></label>
            {children}
        </div>
    );
}

/**
 * Negeri -> Parlimen -> DUN -> Pilihan Raya (bila lebih daripada satu),
 * dibina terus daripada `seats` (senarai kerusi BERSCORESHEET sahaja,
 * dibekalkan oleh PacaController::index) — tiada senarai geografi berasingan
 * dimuatkan, kerana kerusi tanpa scoresheet tidak boleh mempunyai PACA.
 *
 * Nama negeri/parlimen dipadan sebagai RENTETAN (selari dengan cara `seats`
 * dihasilkan oleh PacaBuilderService::seatsWithScoresheet — lihat nota
 * "Geography is string-matched" dalam CLAUDE.md). Pemilihan DUN guna
 * kekunci `kawasan_type:kawasan_id`, bukan nama, supaya "Seluruh Parlimen"
 * (kawasan_type=parlimen) tidak bertembung dengan id Kadun yang berlainan
 * ruang nombor.
 */
export default function SeatPicker({ seats, onSelect }) {
    const { t } = usePilihanrayaTheme();

    const [negeri, setNegeri] = useState('');
    const [parlimen, setParlimen] = useState('');
    const [kawasanKey, setKawasanKey] = useState('');
    const [prKey, setPrKey] = useState('');

    const negeriOptions = useMemo(() => uniqSorted(seats.map((s) => s.negeri)), [seats]);

    const parlimenOptions = useMemo(
        () => uniqSorted(seats.filter((s) => s.negeri === negeri).map((s) => s.parlimen)),
        [seats, negeri],
    );

    const kawasanOptions = useMemo(() => {
        if (!parlimen) return [];
        const seen = new Map();
        seats
            .filter((s) => s.negeri === negeri && s.parlimen === parlimen)
            .forEach((s) => {
                const key = kawasanKeyOf(s);
                if (!seen.has(key)) {
                    seen.set(key, { key, label: s.kawasan_type === 'parlimen' ? 'Seluruh Parlimen' : s.dun });
                }
            });
        return [...seen.values()].sort((a, b) => (a.label || '').localeCompare(b.label || '', 'ms'));
    }, [seats, negeri, parlimen]);

    const candidates = useMemo(
        () => (kawasanKey ? seats.filter((s) => kawasanKeyOf(s) === kawasanKey) : []),
        [seats, kawasanKey],
    );

    const prOptions = useMemo(
        () => candidates.map((s) => ({ key: prKeyOf(s), label: `${JENIS_PR_LABEL[s.jenis_pr] ?? s.jenis_pr.toUpperCase()} ${s.tahun}` })),
        [candidates],
    );

    const needsPrChoice = candidates.length > 1;

    const finalSeat = useMemo(() => {
        if (candidates.length === 1) return candidates[0];
        if (candidates.length > 1 && prKey) return candidates.find((s) => prKeyOf(s) === prKey) ?? null;
        return null;
    }, [candidates, prKey]);

    const emit = (seat) => { if (seat) onSelect(seat); };

    return (
        <div className={`grid grid-cols-1 sm:grid-cols-2 ${needsPrChoice ? 'lg:grid-cols-4' : 'lg:grid-cols-3'} gap-3`}>
            <Field label="Negeri" icon={Map}>
                <select
                    className={t.input}
                    value={negeri}
                    onChange={(e) => {
                        setNegeri(e.target.value);
                        setParlimen('');
                        setKawasanKey('');
                        setPrKey('');
                    }}
                >
                    <option value="">Pilih Negeri</option>
                    {negeriOptions.map((n) => <option key={n} value={n}>{n}</option>)}
                </select>
            </Field>

            <Field label="Parlimen" icon={Landmark}>
                <select
                    className={t.input}
                    value={parlimen}
                    disabled={!negeri}
                    onChange={(e) => {
                        setParlimen(e.target.value);
                        setKawasanKey('');
                        setPrKey('');
                    }}
                >
                    <option value="">Pilih Parlimen</option>
                    {parlimenOptions.map((p) => <option key={p} value={p}>{p}</option>)}
                </select>
            </Field>

            <Field label="DUN" icon={MapPin}>
                <select
                    className={t.input}
                    value={kawasanKey}
                    disabled={!parlimen}
                    onChange={(e) => {
                        const key = e.target.value;
                        setKawasanKey(key);
                        setPrKey('');
                        const matches = seats.filter((s) => kawasanKeyOf(s) === key);
                        if (matches.length === 1) emit(matches[0]);
                    }}
                >
                    <option value="">Pilih Kawasan</option>
                    {kawasanOptions.map((k) => <option key={k.key} value={k.key}>{k.label}</option>)}
                </select>
            </Field>

            {needsPrChoice && (
                <Field label="Pilihan Raya" icon={CalendarDays}>
                    <select
                        className={t.input}
                        value={prKey}
                        onChange={(e) => {
                            setPrKey(e.target.value);
                            const seat = candidates.find((s) => prKeyOf(s) === e.target.value);
                            emit(seat);
                        }}
                    >
                        <option value="">Pilih Pilihan Raya</option>
                        {prOptions.map((o) => <option key={o.key} value={o.key}>{o.label}</option>)}
                    </select>
                </Field>
            )}

            {finalSeat && !needsPrChoice && (
                <div className={`sm:col-span-2 lg:col-span-3 ${t.subtext} text-xs flex items-center gap-1`}>
                    <ListFilter className="h-3.5 w-3.5" /> {JENIS_PR_LABEL[finalSeat.jenis_pr] ?? finalSeat.jenis_pr.toUpperCase()} {finalSeat.tahun}
                </div>
            )}
        </div>
    );
}
