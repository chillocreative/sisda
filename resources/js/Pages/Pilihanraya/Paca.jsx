import { useEffect, useState } from 'react';
import { Head } from '@inertiajs/react';
import axios from 'axios';
import { Check, Copy, History, Loader2, Save, Users } from 'lucide-react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PilihanrayaShell, { usePilihanrayaTheme } from './components/PilihanrayaShell';
import SeatPicker from './paca/SeatPicker';
import PusatCard from './paca/PusatCard';
import SejarahDrawer from './paca/SejarahDrawer';


/**
 * Gabungkan pokok BAHARU daripada server (selepas Tambah Saluran/Tambah PA)
 * ke dalam draf SEDIA ADA di skrin — kekalkan medan yang pengguna sedang
 * sunting (ketua_nama/tel, masa, petugas) bagi baris yang SUDAH wujud dalam
 * draf, dan hanya AMBIL baris baharu (Saluran/slot yang baru ditambah)
 * daripada server. Tanpa ini, klik "Tambah PA" pada satu Saluran akan
 * menggantikan keseluruhan pokok dengan versi DB semasa dan membuang
 * sebarang suntingan lain yang belum ditekan Simpan.
 */
function mergeTree(draft, serverTree) {
    if (!draft) return serverTree;

    const oldPusatById = new Map(draft.pusat.map((p) => [p.id, p]));

    return {
        ...serverTree,
        pusat: serverTree.pusat.map((sp) => {
            const op = oldPusatById.get(sp.id);
            if (!op) return sp;

            const oldSaluranById = new Map(op.saluran.map((s) => [s.id, s]));

            return {
                ...sp,
                ketua_nama: op.ketua_nama,
                ketua_tel: op.ketua_tel,
                saluran: sp.saluran.map((ss) => {
                    const os = oldSaluranById.get(ss.id);
                    if (!os) return ss;

                    const oldSlotById = new Map(os.slot.map((sl) => [sl.id, sl]));

                    return {
                        ...ss,
                        slot: ss.slot.map((sslot) => {
                            const oslot = oldSlotById.get(sslot.id);
                            if (!oslot) return sslot;
                            return {
                                ...sslot,
                                masa_mula: oslot.masa_mula,
                                masa_tamat: oslot.masa_tamat,
                                petugas_nama: oslot.petugas_nama,
                                petugas_kp: oslot.petugas_kp,
                                petugas_tel: oslot.petugas_tel,
                                petugas_parti: oslot.petugas_parti,
                            };
                        }),
                    };
                }),
            };
        }),
    };
}

const kosongKeString = (v) => {
    const s = (v ?? '').toString().trim();
    return s === '' ? null : s;
};

const buildPayload = (draft) => ({
    paca_form_id: draft.id,
    pusat: draft.pusat.map((p) => ({
        id: p.id,
        ketua_nama: kosongKeString(p.ketua_nama),
        ketua_tel: kosongKeString(p.ketua_tel),
        saluran: p.saluran.map((s) => ({
            id: s.id,
            slot: s.slot.map((sl) => ({
                id: sl.id,
                masa_mula: kosongKeString(sl.masa_mula),
                masa_tamat: kosongKeString(sl.masa_tamat),
                petugas_nama: kosongKeString(sl.petugas_nama),
                petugas_kp: kosongKeString(sl.petugas_kp),
                petugas_tel: kosongKeString(sl.petugas_tel),
                petugas_parti: kosongKeString(sl.petugas_parti),
            })),
        })),
    })),
});

const extractError = (e, fallback) => {
    const data = e?.response?.data;
    if (!data) return fallback;
    const first = data.errors ? Object.values(data.errors)[0] : null;
    if (Array.isArray(first) && first[0]) return first[0];
    return data.message || fallback;
};

function PacaEditor({ seat, parti }) {
    const { t } = usePilihanrayaTheme();
    const [draft, setDraft] = useState(null);
    const [loading, setLoading] = useState(false);
    const [loadError, setLoadError] = useState('');
    const [saving, setSaving] = useState(false);
    const [saveError, setSaveError] = useState('');
    const [savedOk, setSavedOk] = useState(false);
    const [sejarahOpen, setSejarahOpen] = useState(false);
    const [copied, setCopied] = useState(false);

    const salinPautan = async () => {
        if (!draft?.public_url) return;
        try {
            await navigator.clipboard.writeText(draft.public_url);
            setCopied(true);
            setTimeout(() => setCopied(false), 2000);
        } catch {
            // Papan klip tidak tersedia (bukan konteks selamat) — pautan masih
            // boleh disalin manual daripada URL yang dipaparkan.
        }
    };

    // Muatkan (atau bina, idempoten) pokok bagi kerusi yang dipilih. `seat`
    // sudah membawa kawasan_type/kawasan_id/jenis_pr/tahun terus daripada
    // `seats` — tiada pengesahan tambahan diperlukan di sini.
    useEffect(() => {
        if (!seat) return;
        let cancelled = false;
        setLoading(true);
        setLoadError('');
        setSavedOk(false);
        setSaveError('');
        axios.get(route('pilihanraya.paca.data'), {
            params: {
                kawasan_type: seat.kawasan_type,
                kawasan_id: seat.kawasan_id,
                jenis_pr: seat.jenis_pr,
                tahun: seat.tahun,
            },
        })
            .then(({ data }) => { if (!cancelled) setDraft(data.paca); })
            .catch((e) => { if (!cancelled) setLoadError(extractError(e, 'Gagal memuatkan roster PACA bagi kerusi ini.')); })
            .finally(() => { if (!cancelled) setLoading(false); });
        return () => { cancelled = true; };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [seat?.kawasan_type, seat?.kawasan_id, seat?.jenis_pr, seat?.tahun]);

    const changePusat = (pusatId, patch) => {
        setSavedOk(false);
        setDraft((prev) => ({
            ...prev,
            pusat: prev.pusat.map((p) => (p.id === pusatId ? { ...p, ...patch } : p)),
        }));
    };

    const changeSlot = (pusatId, saluranId, slotId, patch) => {
        setSavedOk(false);
        setDraft((prev) => ({
            ...prev,
            pusat: prev.pusat.map((p) => {
                if (p.id !== pusatId) return p;
                return {
                    ...p,
                    saluran: p.saluran.map((s) => {
                        if (s.id !== saluranId) return s;
                        return { ...s, slot: s.slot.map((sl) => (sl.id === slotId ? { ...sl, ...patch } : sl)) };
                    }),
                };
            }),
        }));
    };

    const tambahSaluran = async (pusatId) => {
        setSaveError('');
        try {
            const { data } = await axios.post(route('pilihanraya.paca.saluran.tambah'), { paca_pusat_id: pusatId });
            setDraft((prev) => mergeTree(prev, data.paca));
        } catch (e) {
            // Tanpa ini, kegagalan (skop 403, throttle 429, rangkaian) hilang
            // senyap — spinner berhenti dan tiada apa muncul.
            setSaveError(extractError(e, 'Gagal menambah saluran.'));
        }
    };

    const tambahSlot = async (saluranId) => {
        setSaveError('');
        try {
            const { data } = await axios.post(route('pilihanraya.paca.slot.tambah'), { paca_saluran_id: saluranId });
            setDraft((prev) => mergeTree(prev, data.paca));
        } catch (e) {
            setSaveError(extractError(e, 'Gagal menambah PA.'));
        }
    };

    const buangSlot = async (slotId) => {
        setSaveError('');
        try {
            // Buang mengubah struktur saluran (relabel PA); gabung dengan respons
            // server supaya jawatan/urutan sentiasa sepadan DB.
            const { data } = await axios.post(route('pilihanraya.paca.slot.buang'), { paca_slot_id: slotId });
            setDraft((prev) => mergeTree(prev, data.paca));
        } catch (e) {
            setSaveError(extractError(e, 'Gagal membuang slot.'));
        }
    };

    const simpan = async () => {
        if (!draft) return;
        setSaving(true);
        setSaveError('');
        setSavedOk(false);
        try {
            const { data } = await axios.post(route('pilihanraya.paca.simpan'), buildPayload(draft));
            setDraft(data.paca);
            setSavedOk(true);
            setTimeout(() => setSavedOk(false), 3000);
        } catch (e) {
            setSaveError(extractError(e, 'Gagal menyimpan roster PACA.'));
        } finally {
            setSaving(false);
        }
    };

    const pulih = async (snapshotId) => {
        const { data } = await axios.post(route('pilihanraya.paca.pulih'), { snapshot_id: snapshotId });
        setDraft(data.paca);
        setSavedOk(false);
        setSaveError('');
    };

    return (
        <div>

            {loading && (
                <div className="flex items-center gap-2 text-sm text-slate-500 py-8 justify-center">
                    <Loader2 className="h-4 w-4 animate-spin" /> Memuatkan roster PACA...
                </div>
            )}

            {!loading && loadError && (
                <div className="text-sm bg-rose-50 border border-rose-300 text-rose-800 rounded-lg px-4 py-3">{loadError}</div>
            )}

            {!loading && !loadError && draft && (
                <>
                    <div className="sticky top-0 z-10 -mx-4 sm:-mx-6 px-4 sm:px-6 py-3 mb-4 bg-slate-50/95 backdrop-blur border-b border-slate-200 flex flex-wrap items-center justify-between gap-3">
                        <div>
                            {saveError && (
                                <p className="text-sm bg-rose-50 border border-rose-300 text-rose-800 rounded-lg px-3 py-2">{saveError}</p>
                            )}
                            {!saveError && savedOk && (
                                <p className="text-sm text-emerald-700 flex items-center gap-1.5">
                                    <Check className="h-4 w-4" /> Roster PACA berjaya disimpan.
                                </p>
                            )}
                        </div>
                        <div className="flex items-center gap-2">
                            {draft?.public_url && (
                                <button type="button" onClick={salinPautan} className={t.buttonSecondary}>
                                    {copied ? <Check className="h-4 w-4 text-emerald-600" /> : <Copy className="h-4 w-4" />}
                                    {copied ? 'Disalin!' : 'Salin Pautan Awam'}
                                </button>
                            )}
                            <button type="button" onClick={() => setSejarahOpen(true)} className={t.buttonSecondary}>
                                <History className="h-4 w-4" /> Sejarah
                            </button>
                            <button type="button" onClick={simpan} disabled={saving} className={t.buttonPrimary}>
                                {saving ? <Loader2 className="h-4 w-4 animate-spin" /> : <Save className="h-4 w-4" />}
                                Simpan
                            </button>
                        </div>
                    </div>

                    {draft.pusat.length === 0 && (
                        <div className={`${t.card} ${t.subtext} text-sm`}>
                            Tiada Pusat Mengundi dalam struktur kerusi ini.
                        </div>
                    )}

                    <div className="space-y-5">
                        {draft.pusat.map((pusat) => (
                            <PusatCard
                                key={pusat.id}
                                pusat={pusat}
                                saving={saving}
                                parti={parti}
                                onChangePusat={changePusat}
                                onChangeSlot={changeSlot}
                                onTambahSaluran={tambahSaluran}
                                onTambahSlot={tambahSlot}
                                onBuangSlot={buangSlot}
                            />
                        ))}
                    </div>

                    <SejarahDrawer
                        open={sejarahOpen}
                        pacaFormId={draft.id}
                        onClose={() => setSejarahOpen(false)}
                        onPulih={pulih}
                    />
                </>
            )}
        </div>
    );
}

export default function Paca({ seats, parti = [] }) {
    const [seat, setSeat] = useState(null);

    return (
        <AuthenticatedLayout>
            <Head title="PACA" />
            <PilihanrayaShell
                title="PACA"
                subtitle="Susun roster Petugas Pengundian Awal (PA) dan Ketua PACA mengikut Pusat Mengundi dan Saluran"
            >
                <div className="bg-white rounded-xl border border-slate-200 p-6 shadow-sm mb-5">
                    <h3 className="text-lg font-semibold text-slate-900 mb-4">Pilih Kerusi</h3>
                    {seats.length === 0 ? (
                        <p className="text-sm text-slate-500 flex items-center gap-2">
                            <Users className="h-4 w-4" /> Tiada kerusi berscoresheet ditemui. PACA hanya boleh disediakan untuk
                            kerusi yang telah mempunyai Borang 14 (scoresheet).
                        </p>
                    ) : (
                        <SeatPicker seats={seats} onSelect={setSeat} />
                    )}
                </div>

                {seat && <PacaEditor key={`${seat.kawasan_type}-${seat.kawasan_id}-${seat.jenis_pr}-${seat.tahun}`} seat={seat} parti={parti} />}
            </PilihanrayaShell>
        </AuthenticatedLayout>
    );
}
