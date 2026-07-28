import { useEffect, useMemo, useState } from 'react';
import { Head } from '@inertiajs/react';
import axios from 'axios';
import { AlertTriangle, Check, Copy, FileDown, History, Loader2, RefreshCw, Save, Send, Users, X } from 'lucide-react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PilihanrayaShell, { usePilihanrayaTheme } from './components/PilihanrayaShell';
import SeatPicker from './paca/SeatPicker';
import PusatCard from './paca/PusatCard';
import RingkasanPaca from './paca/RingkasanPaca';
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

// `bolehUrusStruktur` false bagi Ketua PACA DUN — Sejarah dan Bina Semula
// Roster memadam/menulis ganti keseluruhan roster, jadi kedua-duanya
// disembunyikan. Pengawal turut menolak kedua-dua endpoint itu dengan 403;
// ini semata-mata supaya butang yang pasti gagal tidak dipaparkan.
function PacaEditor({ seat, parti, bolehUrusStruktur = true }) {
    const { t } = usePilihanrayaTheme();
    const [draft, setDraft] = useState(null);
    const [loading, setLoading] = useState(false);
    const [loadError, setLoadError] = useState('');
    const [saving, setSaving] = useState(false);
    const [saveError, setSaveError] = useState('');
    const [savedOk, setSavedOk] = useState(false);
    const [sejarahOpen, setSejarahOpen] = useState(false);
    const [copied, setCopied] = useState(false);
    const [waOpen, setWaOpen] = useState(false);
    const [waPhone, setWaPhone] = useState('');
    const [waBusy, setWaBusy] = useState(false);
    const [waMsg, setWaMsg] = useState(null); // { ok: boolean, text: string }
    const [rebuildOpen, setRebuildOpen] = useState(false);
    const [rebuildBusy, setRebuildBusy] = useState(false);
    const [rebuildErr, setRebuildErr] = useState('');

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
        if (!draft) return false;
        setSaving(true);
        setSaveError('');
        setSavedOk(false);
        try {
            const { data } = await axios.post(route('pilihanraya.paca.simpan'), buildPayload(draft));
            setDraft(data.paca);
            setSavedOk(true);
            setTimeout(() => setSavedOk(false), 3000);
            return true;
        } catch (e) {
            setSaveError(extractError(e, 'Gagal menyimpan roster PACA.'));
            return false;
        } finally {
            setSaving(false);
        }
    };

    // Simpan draf semasa DAHULU supaya PDF sepadan dengan skrin, kemudian
    // muat turun. Endpoint PDF menjana daripada keadaan DB (tersimpan).
    const muatTurunPdf = async () => {
        if (!seat) return;
        const ok = await simpan();
        if (!ok) return;
        window.location.href = route('pilihanraya.paca.pdf', {
            kawasan_type: seat.kawasan_type,
            kawasan_id: seat.kawasan_id,
            jenis_pr: seat.jenis_pr,
            tahun: seat.tahun,
        });
    };

    // Simpan draf dahulu (PDF sepadan skrin), kemudian jana PDF di pelayan dan
    // hantar sebagai lampiran WhatsApp (Sendora send-file) ke nombor dimasukkan.
    const hantarWhatsapp = async () => {
        if (!seat || !waPhone.trim()) return;
        setWaBusy(true);
        setWaMsg(null);
        const ok = await simpan();
        if (!ok) {
            setWaBusy(false);
            setWaMsg({ ok: false, text: 'Gagal menyimpan roster sebelum hantar. Cuba lagi.' });
            return;
        }
        try {
            const { data } = await axios.post(route('pilihanraya.paca.whatsapp'), {
                kawasan_type: seat.kawasan_type,
                kawasan_id: seat.kawasan_id,
                jenis_pr: seat.jenis_pr,
                tahun: seat.tahun,
                telefon: waPhone.trim(),
            });
            setWaMsg({ ok: true, text: data.message || 'Berjaya dihantar.' });
            setTimeout(() => { setWaOpen(false); setWaMsg(null); setWaPhone(''); }, 1800);
        } catch (e) {
            setWaMsg({ ok: false, text: extractError(e, 'Gagal menghantar ke WhatsApp.') });
        } finally {
            setWaBusy(false);
        }
    };

    // Tatal ke kad Pusat tertentu apabila baris ringkasan diklik. scroll-mt
    // pada pembalut memberi ruang untuk bar tindakan lekat (sticky) di atas.
    const lompatKePusat = (pusatId) => {
        document.getElementById(`pusat-${pusatId}`)?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    };

    // Buang roster sedia ada dan semai semula daripada struktur Borang 14
    // SEMASA. Untuk roster yang tersemai daripada struktur yang salah (mis.
    // anggaran DPT yang menyenaraikan lokaliti sebagai Pusat Mengundi) —
    // membetulkan struktur sahaja tidak menyentuh roster yang sudah wujud.
    // Pelayan mengambil snapshot dahulu dan menolak permintaan ini sebaik
    // sahaja ada petugas atau Ketua PACA direkod.
    const binaSemula = async () => {
        if (!seat) return;
        setRebuildBusy(true);
        setRebuildErr('');
        try {
            const { data } = await axios.post(route('pilihanraya.paca.bina-semula'), {
                kawasan_type: seat.kawasan_type,
                kawasan_id: seat.kawasan_id,
                jenis_pr: seat.jenis_pr,
                tahun: seat.tahun,
            });
            setDraft(data.paca);
            setRebuildOpen(false);
            setSaveError('');
            setSavedOk(false);
        } catch (e) {
            setRebuildErr(extractError(e, 'Gagal membina semula roster.'));
        } finally {
            setRebuildBusy(false);
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
                            {bolehUrusStruktur && (
                                <>
                                    <button type="button" onClick={() => setSejarahOpen(true)} className={t.buttonSecondary}>
                                        <History className="h-4 w-4" /> Sejarah
                                    </button>
                                    <button type="button" onClick={() => { setRebuildErr(''); setRebuildOpen(true); }} disabled={saving} className={t.buttonSecondary}>
                                        <RefreshCw className="h-4 w-4" /> Bina Semula Roster
                                    </button>
                                </>
                            )}
                            <button type="button" onClick={muatTurunPdf} disabled={saving} className={t.buttonSecondary}>
                                {saving ? <Loader2 className="h-4 w-4 animate-spin" /> : <FileDown className="h-4 w-4" />}
                                Muat Turun PDF
                            </button>
                            <button type="button" onClick={() => { setWaMsg(null); setWaOpen(true); }} disabled={saving} className={t.buttonSecondary}>
                                <Send className="h-4 w-4" /> Hantar WhatsApp
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

                    <RingkasanPaca pusatList={draft.pusat} onLompat={lompatKePusat} />

                    <div className="space-y-5">
                        {draft.pusat.map((pusat) => (
                            <div key={pusat.id} id={`pusat-${pusat.id}`} className="scroll-mt-24">
                                <PusatCard
                                    pusat={pusat}
                                    saving={saving}
                                    parti={parti}
                                    onChangePusat={changePusat}
                                    onChangeSlot={changeSlot}
                                    onTambahSaluran={tambahSaluran}
                                    onTambahSlot={tambahSlot}
                                    onBuangSlot={buangSlot}
                                />
                            </div>
                        ))}
                    </div>

                    {bolehUrusStruktur && (
                        <SejarahDrawer
                            open={sejarahOpen}
                            pacaFormId={draft.id}
                            onClose={() => setSejarahOpen(false)}
                            onPulih={pulih}
                        />
                    )}

                    {rebuildOpen && (
                        <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4" onClick={() => !rebuildBusy && setRebuildOpen(false)}>
                            <div className="w-full max-w-md rounded-2xl bg-white shadow-xl p-5" onClick={(e) => e.stopPropagation()}>
                                <div className="flex items-center justify-between mb-1">
                                    <h3 className="text-base font-semibold text-slate-900">Bina Semula Roster</h3>
                                    <button type="button" onClick={() => !rebuildBusy && setRebuildOpen(false)} className="text-slate-400 hover:text-slate-600 p-1 -m-1" aria-label="Tutup">
                                        <X className="h-4 w-4" />
                                    </button>
                                </div>
                                <div className="mt-3 text-sm rounded-lg px-3 py-2.5 bg-amber-50 border border-amber-200 text-amber-900 flex gap-2">
                                    <AlertTriangle className="h-4 w-4 shrink-0 mt-0.5" />
                                    <span>
                                        Kesemua {draft.pusat.length} Pusat Mengundi, Saluran dan slot roster ini akan
                                        <strong> dibuang</strong> dan disemai semula daripada struktur Borang 14 semasa.
                                    </span>
                                </div>
                                <ul className="mt-3 text-xs text-slate-600 space-y-1.5 list-disc pl-5">
                                    <li>Snapshot roster semasa disimpan dahulu — boleh dilihat dalam Sejarah.</li>
                                    <li>Pautan awam kerusi ini kekal sah (tidak bertukar).</li>
                                    <li>Permintaan ini ditolak jika ada petugas atau Ketua PACA yang sudah direkod.</li>
                                    <li>Betulkan struktur di Borang 14 &rsaquo; Struktur dahulu, kemudian bina semula di sini.</li>
                                </ul>
                                {rebuildErr && (
                                    <p className="mt-3 text-sm rounded-lg px-3 py-2 text-rose-800 bg-rose-50 border border-rose-300">{rebuildErr}</p>
                                )}
                                <div className="flex items-center justify-end gap-2 mt-5">
                                    <button type="button" onClick={() => setRebuildOpen(false)} disabled={rebuildBusy} className={t.buttonSecondary}>Batal</button>
                                    <button type="button" onClick={binaSemula} disabled={rebuildBusy} className={t.buttonPrimary}>
                                        {rebuildBusy ? <Loader2 className="h-4 w-4 animate-spin" /> : <RefreshCw className="h-4 w-4" />}
                                        Bina Semula
                                    </button>
                                </div>
                            </div>
                        </div>
                    )}

                    {waOpen && (
                        <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4" onClick={() => !waBusy && setWaOpen(false)}>
                            <div className="w-full max-w-md rounded-2xl bg-white shadow-xl p-5" onClick={(e) => e.stopPropagation()}>
                                <div className="flex items-center justify-between mb-1">
                                    <h3 className="text-base font-semibold text-slate-900">Hantar Roster ke WhatsApp</h3>
                                    <button type="button" onClick={() => !waBusy && setWaOpen(false)} className="text-slate-400 hover:text-slate-600 p-1 -m-1" aria-label="Tutup">
                                        <X className="h-4 w-4" />
                                    </button>
                                </div>
                                <p className="text-xs text-slate-500 mb-4">
                                    PDF roster akan disimpan &amp; dihantar sebagai lampiran WhatsApp melalui Sendora ke nombor di bawah.
                                </p>
                                <label className={t.label}>No Telefon Penerima</label>
                                <input
                                    type="tel"
                                    className={t.input}
                                    value={waPhone}
                                    disabled={waBusy}
                                    onChange={(e) => setWaPhone(e.target.value)}
                                    onKeyDown={(e) => { if (e.key === 'Enter' && waPhone.trim() && !waBusy) hantarWhatsapp(); }}
                                    placeholder="012-3456789"
                                    autoFocus
                                />
                                {waMsg && (
                                    <p className={`mt-3 text-sm rounded-lg px-3 py-2 ${waMsg.ok ? 'text-emerald-700 bg-emerald-50 border border-emerald-200' : 'text-rose-800 bg-rose-50 border border-rose-300'}`}>
                                        {waMsg.text}
                                    </p>
                                )}
                                <div className="flex items-center justify-end gap-2 mt-5">
                                    <button type="button" onClick={() => setWaOpen(false)} disabled={waBusy} className={t.buttonSecondary}>Batal</button>
                                    <button type="button" onClick={hantarWhatsapp} disabled={waBusy || !waPhone.trim()} className={t.buttonPrimary}>
                                        {waBusy ? <Loader2 className="h-4 w-4 animate-spin" /> : <Send className="h-4 w-4" />}
                                        Hantar
                                    </button>
                                </div>
                            </div>
                        </div>
                    )}
                </>
            )}
        </div>
    );
}

export default function Paca({ seats, parti = [], rememberedFilters = {}, kerusiTerkunci = null, bolehUrusStruktur = true }) {
    // Pulihkan kerusi terakhir dipilih daripada `rememberedFilters` — prop
    // sejagat yang disemai oleh RememberFilters (sesi, dibersihkan pada logout,
    // skop 'paca'). Cari padanan dalam `seats` semasa; abaikan jika tiada
    // padanan (mis. scoresheet dibuang atau skop admin bertukar). Sama seperti
    // War Room/Analisa/Borang 14 yang turut menyemai daripada rememberedFilters.
    const restored = useMemo(() => {
        const rf = rememberedFilters || {};
        if (!rf.kawasan_type || !rf.kawasan_id) return null;
        return seats.find((s) =>
            s.kawasan_type === rf.kawasan_type
            && String(s.kawasan_id) === String(rf.kawasan_id)
            && s.jenis_pr === rf.jenis_pr
            && String(s.tahun) === String(rf.tahun),
        ) ?? null;
    }, [seats, rememberedFilters]);

    // Ketua PACA DUN dikunci pada satu kerusi — pengawal menghantarnya sebagai
    // `kerusiTerkunci`. Pemilih kerusi disembunyikan sepenuhnya: tiada apa-apa
    // untuk dipilih, dan dropdown Negeri/Parlimen akan menyenaraikan kawasan
    // yang mereka tiada kebenaran ke atasnya.
    const [seat, setSeat] = useState(kerusiTerkunci ?? restored);

    return (
        <AuthenticatedLayout>
            <Head title="PACA" />
            <PilihanrayaShell
                title="PACA"
                subtitle="Susun roster Petugas Pengundian Awal (PA) dan Ketua PACA mengikut Pusat Mengundi dan Saluran"
            >
                <div className="bg-white rounded-xl border border-slate-200 p-6 shadow-sm mb-5">
                    {kerusiTerkunci ? (
                        <div className="flex items-center gap-3">
                            <div className="rounded-lg bg-violet-50 p-2">
                                <Users className="h-5 w-5 text-violet-700" />
                            </div>
                            <div>
                                <p className="text-xs uppercase tracking-wide text-slate-500">Kerusi Anda</p>
                                <p className="text-lg font-semibold text-slate-900">
                                    DUN {kerusiTerkunci.dun ?? kerusiTerkunci.nama}
                                </p>
                                <p className="text-sm text-slate-500">
                                    {kerusiTerkunci.negeri} · {kerusiTerkunci.parlimen} · {String(kerusiTerkunci.jenis_pr).toUpperCase()} {kerusiTerkunci.tahun}
                                </p>
                            </div>
                        </div>
                    ) : (
                        <>
                            <h3 className="text-lg font-semibold text-slate-900 mb-4">Pilih Kerusi</h3>
                            {seats.length === 0 ? (
                                <p className="text-sm text-slate-500 flex items-center gap-2">
                                    <Users className="h-4 w-4" /> Tiada kerusi berscoresheet ditemui. PACA hanya boleh disediakan untuk
                                    kerusi yang telah mempunyai Borang 14 (scoresheet).
                                </p>
                            ) : (
                                <SeatPicker seats={seats} initial={restored} onSelect={setSeat} />
                            )}
                        </>
                    )}
                </div>

                {seat && <PacaEditor key={`${seat.kawasan_type}-${seat.kawasan_id}-${seat.jenis_pr}-${seat.tahun}`} seat={seat} parti={parti} bolehUrusStruktur={bolehUrusStruktur} />}
            </PilihanrayaShell>
        </AuthenticatedLayout>
    );
}
