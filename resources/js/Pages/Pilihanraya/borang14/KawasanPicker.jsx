import { useMemo } from 'react';
import { CalendarDays, Landmark, ListFilter, Map, MapPin } from 'lucide-react';
import { usePilihanrayaTheme } from '../components/PilihanrayaShell';

// PRU/PRN/PRK — mesti dipilih, mempengaruhi bagaimana kawasan diselaraskan.
export const JENIS_PR_OPTIONS = [
    { value: 'pru', label: 'PRU — Pilihanraya Umum' },
    { value: 'prn', label: 'PRN — Pilihanraya Negeri' },
    { value: 'prk', label: 'PRK — Pilihanraya Kecil' },
];

// 1959 = pilihanraya umum pertama Malaysia; +1 tahun hadapan untuk PR akan
// datang. Mesti kekal <select> — taipan tahun boleh cipta rekod salah/pertindihan.
export const TAHUN_OPTIONS = (() => {
    const max = new Date().getFullYear() + 1;
    const list = [];
    for (let y = max; y >= 1959; y--) list.push(y);
    return list;
})();

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
 * Negeri -> Parlimen -> DUN (pilihan) + Jenis PR + Tahun, terkawal sepenuhnya
 * oleh `value`/`onChange` (bukan state dalaman) supaya KeyinTab boleh
 * menyegerakkan pilihan terus daripada prefill (hand-off Upload/Papar) tanpa
 * pertikaian antara dua sumber kebenaran.
 *
 * Semantik kawasan sama seperti tab Papar: Parlimen sahaja (DUN dibiar
 * "Seluruh Parlimen") -> kawasanType 'parlimen' guna id Parlimen itu sendiri;
 * + DUN dipilih -> kawasanType 'dun'. Ini membolehkan pengguna memilih terus
 * kerusi Parlimen tanpa mesti melalui DUN.
 */
export default function KawasanPicker({ value, onChange, negeriList, parlimenList, kadunList }) {
    const { t } = usePilihanrayaTheme();
    const { negeriId, jenisPr, parlimenId, kadunId, tahun } = value;

    const parlimenOptions = useMemo(
        () => (negeriId ? parlimenList.filter((p) => String(p.negeri_id) === String(negeriId)) : []),
        [parlimenList, negeriId],
    );
    const kadunOptions = useMemo(
        () => (parlimenId ? kadunList.filter((k) => String(k.bandar_id) === String(parlimenId)) : []),
        [kadunList, parlimenId],
    );

    const set = (patch) => onChange({ ...value, ...patch });

    return (
        <div className="grid grid-cols-1 sm:grid-cols-3 lg:grid-cols-5 gap-3">
            <Field label="Negeri" icon={Map}>
                <select
                    className={t.input}
                    value={negeriId}
                    onChange={(e) => set({ negeriId: e.target.value, parlimenId: '', kadunId: '', kawasanType: '' })}
                >
                    <option value="">Pilih Negeri</option>
                    {negeriList.map((n) => <option key={n.id} value={n.id}>{n.nama}</option>)}
                </select>
            </Field>

            <Field label="Parlimen" icon={Landmark}>
                <select
                    className={t.input}
                    value={parlimenId}
                    disabled={!negeriId}
                    onChange={(e) => set({ parlimenId: e.target.value, kadunId: '', kawasanType: 'parlimen' })}
                >
                    <option value="">Pilih Parlimen</option>
                    {parlimenOptions.map((p) => <option key={p.id} value={p.id}>{p.nama}</option>)}
                </select>
            </Field>

            <Field label="DUN (pilihan)" icon={MapPin}>
                <select
                    className={t.input}
                    value={kadunId}
                    disabled={!parlimenId}
                    onChange={(e) => {
                        const nextKadunId = e.target.value;
                        set({ kadunId: nextKadunId, kawasanType: nextKadunId ? 'dun' : 'parlimen' });
                    }}
                >
                    <option value="">Seluruh Parlimen</option>
                    {kadunOptions.map((k) => <option key={k.id} value={k.id}>{k.nama}</option>)}
                </select>
            </Field>

            <Field label="Jenis PR" icon={ListFilter}>
                <select className={t.input} value={jenisPr} onChange={(e) => set({ jenisPr: e.target.value })}>
                    <option value="">Pilih Jenis PR</option>
                    {JENIS_PR_OPTIONS.map((o) => <option key={o.value} value={o.value}>{o.label}</option>)}
                </select>
            </Field>

            <Field label="Tahun" icon={CalendarDays}>
                <select className={t.input} value={tahun} onChange={(e) => set({ tahun: e.target.value })}>
                    <option value="">Pilih Tahun</option>
                    {TAHUN_OPTIONS.map((y) => <option key={y} value={y}>{y}</option>)}
                </select>
            </Field>
        </div>
    );
}
