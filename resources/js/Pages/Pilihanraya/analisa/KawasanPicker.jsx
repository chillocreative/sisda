import { useEffect, useMemo, useState } from 'react';
import { usePage } from '@inertiajs/react';
import { Landmark, Map, MapPin } from 'lucide-react';
import { usePilihanrayaTheme } from '../components/PilihanrayaShell';

function Field({ label, icon: Icon, children }) {
    const { t } = usePilihanrayaTheme();
    return (
        <div className="min-w-[210px]">
            <label className={t.label}>
                <span className="inline-flex items-center gap-1">{Icon && <Icon className="h-3.5 w-3.5" />} {label}</span>
            </label>
            {children}
        </div>
    );
}

/**
 * Negeri → Parlimen → DUN (optional) cascade for the whole of Malaysia.
 * Emits a scope object; DUN chosen → level 'dun', otherwise 'parlimen'.
 */
export default function KawasanPicker({ geo, onChange }) {
    const { t } = usePilihanrayaTheme();
    const { negeriList = [], parlimenList = [], kadunList = [] } = geo || {};

    const { rememberedFilters } = usePage().props;
    const [negeriId, setNegeriId] = useState(rememberedFilters?.negeri_id ?? '');
    const [bandarId, setBandarId] = useState(rememberedFilters?.bandar_id ?? '');
    const [kadunId, setKadunId] = useState(rememberedFilters?.kadun_id ?? '');

    const parlimens = useMemo(
        () => parlimenList.filter((p) => String(p.negeri_id) === String(negeriId)),
        [parlimenList, negeriId],
    );
    const kaduns = useMemo(
        () => kadunList.filter((k) => String(k.bandar_id) === String(bandarId)),
        [kadunList, bandarId],
    );

    useEffect(() => {
        if (!bandarId) {
            onChange(null);
            return;
        }
        const negeri = negeriList.find((n) => String(n.id) === String(negeriId));
        const bandar = parlimenList.find((p) => String(p.id) === String(bandarId));
        const kadun = kadunList.find((k) => String(k.id) === String(kadunId));
        const level = kadunId ? 'dun' : 'parlimen';
        onChange({
            level,
            negeri_id: negeriId,
            negeri: negeri?.nama,
            bandar_id: Number(bandarId),
            parlimen: bandar?.nama,
            kadun_id: kadunId ? Number(kadunId) : null,
            dun: kadun?.nama || null,
            label: level === 'dun' ? kadun?.nama : `${bandar?.nama} (seluruh parlimen)`,
        });
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [negeriId, bandarId, kadunId]);

    return (
        <div className={`${t.cardTight} mb-6`}>
            <div className="flex flex-wrap items-end gap-4">
                <Field label="Negeri" icon={Map}>
                    <select
                        className={t.input}
                        value={negeriId}
                        onChange={(e) => { setNegeriId(e.target.value); setBandarId(''); setKadunId(''); }}
                    >
                        <option value="">— Pilih Negeri —</option>
                        {negeriList.map((n) => <option key={n.id} value={n.id}>{n.nama}</option>)}
                    </select>
                </Field>

                <Field label="Parlimen" icon={Landmark}>
                    <select
                        className={t.input}
                        value={bandarId}
                        disabled={!negeriId}
                        onChange={(e) => { setBandarId(e.target.value); setKadunId(''); }}
                    >
                        <option value="">— Pilih Parlimen —</option>
                        {parlimens.map((p) => <option key={p.id} value={p.id}>{p.nama}</option>)}
                    </select>
                </Field>

                <Field label="DUN (pilihan)" icon={MapPin}>
                    <select
                        className={t.input}
                        value={kadunId}
                        disabled={!bandarId}
                        onChange={(e) => setKadunId(e.target.value)}
                    >
                        <option value="">Seluruh Parlimen</option>
                        {kaduns.map((k) => <option key={k.id} value={k.id}>{k.nama}</option>)}
                    </select>
                </Field>

                {bandarId && (
                    <div className="text-sm">
                        <span className="block text-xs opacity-60 mb-1">Skop analisa</span>
                        <span className="font-semibold">
                            {kadunId ? kaduns.find((k) => String(k.id) === String(kadunId))?.nama : 'Seluruh Parlimen'}
                        </span>
                    </div>
                )}
            </div>
        </div>
    );
}
