import { useEffect, useState } from 'react';
import axios from 'axios';
import { Loader2, RotateCcw, X } from 'lucide-react';
import Modal from '@/Components/Modal';
import { usePilihanrayaTheme } from '../components/PilihanrayaShell';
import ConfirmDialog from '../borang14/ConfirmDialog';

const REASON_LABEL = {
    before_edit: 'Sebelum disunting',
};

const formatWhen = (iso) => {
    try {
        return new Date(iso).toLocaleString('ms-MY', { dateStyle: 'medium', timeStyle: 'short' });
    } catch {
        return iso;
    }
};

/**
 * Panel sejarah (snapshot) bagi satu PacaForm — dimuatkan hanya apabila
 * dibuka (`open`), bukan dibawa bersama pokok utama, kerana sejarah boleh
 * membesar tanpa pengguna pernah membukanya. "Pulih" mengembalikan Pusat/
 * Saluran/slot SEDIA ADA kepada nilai snapshot (lihat PacaController::pulih)
 * — baris yang ditambah selepas snapshot diambil tidak dibuang.
 */
export default function SejarahDrawer({ open, pacaFormId, onClose, onPulih }) {
    const { t } = usePilihanrayaTheme();
    const [sejarah, setSejarah] = useState([]);
    const [loading, setLoading] = useState(false);
    const [ralat, setRalat] = useState('');
    const [pulihTarget, setPulihTarget] = useState(null); // { id, created_at }
    const [pulihBusy, setPulihBusy] = useState(false);

    useEffect(() => {
        if (!open || !pacaFormId) return;
        setLoading(true);
        setRalat('');
        axios.get(route('pilihanraya.paca.sejarah'), { params: { paca_form_id: pacaFormId } })
            .then(({ data }) => setSejarah(data.sejarah ?? []))
            .catch(() => setRalat('Gagal memuatkan sejarah.'))
            .finally(() => setLoading(false));
    }, [open, pacaFormId]);

    const sahkanPulih = async () => {
        if (!pulihTarget) return;
        setPulihBusy(true);
        try {
            await onPulih(pulihTarget.id);
            setPulihTarget(null);
            onClose();
        } catch {
            setRalat('Gagal memulihkan snapshot ini.');
            setPulihTarget(null);
        } finally {
            setPulihBusy(false);
        }
    };

    return (
        <>
            <Modal show={open} onClose={onClose} maxWidth="lg">
                <div className="p-6">
                    <div className="flex items-center justify-between mb-4">
                        <h2 className="text-lg font-medium text-slate-900">Sejarah Perubahan</h2>
                        <button type="button" onClick={onClose} className="text-slate-400 hover:text-slate-600">
                            <X className="h-5 w-5" />
                        </button>
                    </div>

                    {loading && (
                        <div className="flex items-center gap-2 text-sm text-slate-500 py-6 justify-center">
                            <Loader2 className="h-4 w-4 animate-spin" /> Memuatkan sejarah...
                        </div>
                    )}

                    {!loading && ralat && (
                        <div className="text-sm bg-rose-50 border border-rose-300 text-rose-800 rounded-lg px-4 py-3 mb-3">{ralat}</div>
                    )}

                    {!loading && !ralat && sejarah.length === 0 && (
                        <p className={`${t.subtext} text-sm`}>Belum ada sejarah perubahan bagi kerusi ini.</p>
                    )}

                    {!loading && sejarah.length > 0 && (
                        <ul className="divide-y divide-slate-200 max-h-96 overflow-y-auto">
                            {sejarah.map((s) => (
                                <li key={s.id} className="py-3 flex items-center justify-between gap-3">
                                    <div>
                                        <p className="text-sm font-medium text-slate-800">{formatWhen(s.created_at)}</p>
                                        <p className={`${t.subtext} text-xs`}>{REASON_LABEL[s.reason] ?? s.reason}</p>
                                    </div>
                                    <button
                                        type="button"
                                        onClick={() => setPulihTarget({ id: s.id, created_at: s.created_at })}
                                        className={t.buttonSecondary}
                                    >
                                        <RotateCcw className="h-4 w-4" /> Pulih
                                    </button>
                                </li>
                            ))}
                        </ul>
                    )}
                </div>
            </Modal>

            <ConfirmDialog
                open={!!pulihTarget}
                title="Pulihkan snapshot ini?"
                confirmLabel="Ya, pulihkan"
                busy={pulihBusy}
                onClose={() => setPulihTarget(null)}
                onConfirm={sahkanPulih}
            >
                {pulihTarget && (
                    <p>
                        Roster akan dikembalikan kepada keadaan pada <strong>{formatWhen(pulihTarget.created_at)}</strong>.
                        Sebarang suntingan yang belum disimpan pada skrin ini akan hilang. Tindakan ini tidak boleh dibuat asal. Teruskan?
                    </p>
                )}
            </ConfirmDialog>
        </>
    );
}
