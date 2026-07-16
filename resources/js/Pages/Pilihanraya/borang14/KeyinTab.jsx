import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import axios from 'axios';
import { Download, Info, MapPin, Loader2, Save } from 'lucide-react';
import { usePilihanrayaTheme } from '../components/PilihanrayaShell';
import KawasanPicker from './KawasanPicker';
import {
    VoteTable, UndiAwalPosTable, GrandSummary,
    toBlocks, cellKey, BULOH_KASAP_KADUN_ID,
} from '../components/Borang14Form';

export default function KeyinTab({ negeriList, parlimenList, kadunList, partiList, penjuruOptions, prefill = null }) {
    const { t } = usePilihanrayaTheme();

    // Geography + jenis PR/tahun in one controlled object — kawasan can be a
    // Parlimen OR a DUN (kawasanType), never implied only via a DUN path.
    const [picker, setPicker] = useState({ negeriId: '', jenisPr: '', kawasanType: '', parlimenId: '', kadunId: '', tahun: '' });
    const { negeriId, jenisPr, kawasanType, parlimenId, kadunId, tahun } = picker;
    const kawasanId = kawasanType === 'parlimen' ? parlimenId : kadunId;
    const geographyComplete = Boolean(negeriId && jenisPr && kawasanType && kawasanId && tahun);

    const [penjuru, setPenjuru] = useState('');
    const [parties, setParties] = useState([]); // [{slot, keahlian_parti_id, nama, calon?}]
    const [reference, setReference] = useState(null);
    const [hasData, setHasData] = useState(true);
    const [votes, setVotes] = useState({});
    const [form, setForm] = useState(null); // { id, status, source, needs_review, crosscheck_issues, penjuru }
    const [loading, setLoading] = useState(false);
    const [selectedPusat, setSelectedPusat] = useState('');
    const [cellStatus, setCellStatus] = useState({});
    const [publishing, setPublishing] = useState(false);
    const [publishedOk, setPublishedOk] = useState(false);
    const statusTimers = useRef({});
    // Per-cellKey request sequence — guards against out-of-order POST
    // resolutions (see Task 8 review finding: resolution order is not send
    // order, so a stale response must never overwrite a newer one).
    const requestSeq = useRef({});
    useEffect(() => () => Object.values(statusTimers.current).forEach(clearTimeout), []);

    // Applied when the Upload/Papar tabs hand off via openKeyin(prefill); nonce
    // forces re-apply even if the same form is sent twice in a row. Upload's
    // hand-off only ever knows a formId (never geography), so whenever one is
    // present we resolve the FULL picker + data straight from the server
    // instead of trusting client-side geography fields that may not exist.
    useEffect(() => {
        if (!prefill) return undefined;

        if (prefill.formId) {
            let cancelled = false;
            setLoading(true);
            axios.get(route('pilihanraya.borang-14.data'), { params: { form_id: prefill.formId } })
                .then(({ data }) => {
                    if (cancelled) return;
                    const r = data.resolved || {};
                    setPicker({
                        negeriId: String(r.negeri_id ?? ''),
                        jenisPr: r.jenis_pr ?? '',
                        kawasanType: r.kawasan_type ?? 'dun',
                        parlimenId: String(r.bandar_id ?? ''),
                        kadunId: r.kawasan_type === 'dun' ? String(r.kawasan_id ?? '') : '',
                        tahun: String(r.tahun ?? ''),
                    });
                    setReference(data.reference);
                    setHasData(data.hasData);
                    setVotes(data.votes || {});
                    setForm(data.form || null);
                    setPublishedOk(false);
                    if (data.parties?.length) {
                        setParties(data.parties);
                        setPenjuru(String(data.form?.penjuru ?? data.parties.length));
                    }
                })
                .finally(() => { if (!cancelled) setLoading(false); });
            return () => { cancelled = true; };
        }

        setPicker({
            negeriId: String(prefill.negeriId ?? ''),
            jenisPr: prefill.jenisPr ?? '',
            kawasanType: prefill.kawasanType ?? 'dun',
            parlimenId: String(prefill.parlimenId ?? ''),
            kadunId: String(prefill.kadunId ?? ''),
            tahun: String(prefill.tahun ?? ''),
        });
        return undefined;
    }, [prefill?.nonce]); // eslint-disable-line react-hooks/exhaustive-deps

    // Fetch reference + saved data whenever the chosen kawasan/jenis/tahun changes.
    // Also syncs penjuru from a loaded draft so a scoresheet upload lands ready-to-edit.
    useEffect(() => {
        if (!geographyComplete) { setReference(null); setHasData(true); setVotes({}); setForm(null); return undefined; }
        let cancelled = false;
        setLoading(true);
        setSelectedPusat('');
        setPublishedOk(false);
        axios.get(route('pilihanraya.borang-14.data'), {
            params: { kawasan_type: kawasanType, kawasan_id: kawasanId, jenis_pr: jenisPr, tahun, penjuru: penjuru || undefined },
        })
            .then(({ data }) => {
                if (cancelled) return;
                setReference(data.reference);
                setHasData(data.hasData);
                setVotes(data.votes || {});
                setForm(data.form || null);
                if (data.parties?.length) {
                    setParties(data.parties);
                    setPenjuru(String(data.form?.penjuru ?? data.parties.length));
                }
            })
            .finally(() => { if (!cancelled) setLoading(false); });
        return () => { cancelled = true; };
    }, [geographyComplete, kawasanType, kawasanId, jenisPr, tahun]); // eslint-disable-line react-hooks/exhaustive-deps

    // Keep the party-slot array sized to the chosen penjuru.
    useEffect(() => {
        if (!penjuru) { setParties([]); return; }
        setParties((prev) => {
            const n = Number(penjuru);
            return Array.from({ length: n }, (_, i) => prev[i] || { slot: i + 1, keahlian_parti_id: '', nama: '' });
        });
    }, [penjuru]);

    const partyNames = useMemo(
        () => parties.map((p, i) => (p?.nama ? p.nama : `Parti ${i + 1}`)),
        [parties],
    );

    const blocks = useMemo(() => toBlocks(reference), [reference]);

    // One anchor per Pusat Mengundi (each block is already one PM) — lets the
    // dropdown jump straight to the card the user wants to fill.
    const pusatAnchors = useMemo(
        () => blocks.map((b, i) => ({ anchorId: `pm-${i}`, dm: b.dm, pusat: b.pusat })),
        [blocks],
    );

    const goToPusat = () => {
        if (!selectedPusat) return;
        document.getElementById(selectedPusat)?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    };

    // Buloh Kasap's Undi Awal/Pos merge is a DUN-only exception — a Parlimen
    // that happens to share id 41 must never trigger it.
    const isBulohKasap = kawasanType === 'dun' && Number(kadunId) === BULOH_KASAP_KADUN_ID;

    // Undi Awal & Undi Pos rows: never fabricate a row absent from the sheet —
    // the reference sheet may have Undi Pos but no Undi Awal, or vice versa.
    const undiAwalPosRows = useMemo(() => {
        const awal = reference?.undi_awal;
        const pos = reference?.undi_pos;
        if (isBulohKasap) {
            return [{ label: 'UNDI AWAL & POS', berdaftar: awal?.berdaftar != null || pos?.berdaftar != null ? (awal?.berdaftar ?? 0) + (pos?.berdaftar ?? 0) : null }];
        }
        const rows = [];
        if (awal) rows.push({ label: 'UNDI AWAL', berdaftar: awal.berdaftar ?? null });
        if (pos) rows.push({ label: 'UNDI POS', berdaftar: pos.berdaftar ?? null });
        return rows;
    }, [reference, isBulohKasap]);

    // Grand rollup across every saluran + undi awal & pos for the bottom summary.
    const summary = useMemo(() => {
        const nParties = partyNames.length;
        const partyTotals = Array.from({ length: nParties }, () => 0);
        let berdaftar = 0;
        let berdaftarKnown = false;
        let ditolak = 0;
        let tidakDimasukkan = 0;

        const accumulate = (pusat, saluran, rowBerdaftar) => {
            if (rowBerdaftar != null) { berdaftarKnown = true; berdaftar += rowBerdaftar; }
            for (let i = 0; i < nParties; i++) {
                partyTotals[i] += votes[cellKey(pusat, saluran, i + 1)] ?? 0;
            }
            ditolak += votes[cellKey(pusat, saluran, 90)] ?? 0;
            tidakDimasukkan += votes[cellKey(pusat, saluran, 91)] ?? 0;
        };

        blocks.forEach((b) => {
            b.saluran.forEach((s) => accumulate(b.pusat, String(s.no), s.berdaftar ?? null));
        });
        undiAwalPosRows.forEach(({ label, berdaftar: rowBerdaftar }) => accumulate('', label, rowBerdaftar));

        const keluar = partyTotals.reduce((a, b) => a + b, 0) + ditolak + tidakDimasukkan;
        return { partyTotals, ditolak, tidakDimasukkan, keluar, berdaftar, berdaftarKnown };
    }, [blocks, votes, partyNames, undiAwalPosRows]);

    const persistParties = useCallback((next) => {
        if (!kawasanType || !kawasanId || !jenisPr || !tahun || !penjuru) return;
        axios.post(route('pilihanraya.borang-14.parties'), {
            kawasan_type: kawasanType, kawasan_id: kawasanId, jenis_pr: jenisPr, tahun: Number(tahun),
            penjuru: Number(penjuru), parties: next,
        }).catch(() => {});
    }, [kawasanType, kawasanId, jenisPr, tahun, penjuru]);

    // Preserves extra fields (calon) so the scoresheet's candidate name keeps
    // showing under the dropdown after a party is picked.
    const onPickParty = (index, partiId) => {
        const parti = partiList.find((p) => String(p.id) === String(partiId));
        const next = parties.map((p, i) => (i === index
            ? { ...p, slot: i + 1, keahlian_parti_id: parti ? parti.id : '', nama: parti ? parti.nama : (p.calon ?? '') }
            : p));
        setParties(next);
        persistParties(next);
    };

    const saveVote = useCallback((pusat, saluran, slot, undi) => {
        const key = cellKey(pusat, saluran, slot);
        // Claim this request as the latest for the cell *before* the await —
        // a later re-edit of the same cell will bump this again, and only
        // the resolution whose seq still matches the ref may write status.
        const mySeq = (requestSeq.current[key] || 0) + 1;
        requestSeq.current[key] = mySeq;
        setVotes((prev) => ({ ...prev, [key]: undi }));
        setCellStatus((prev) => ({ ...prev, [key]: 'saving' }));
        axios.post(route('pilihanraya.borang-14.vote'), {
            kawasan_type: kawasanType, kawasan_id: kawasanId, jenis_pr: jenisPr, tahun: Number(tahun),
            penjuru: Number(penjuru), pusat, saluran, slot, undi,
        })
            .then(() => {
                if (requestSeq.current[key] !== mySeq) return; // superseded — a newer request owns this cell now
                setCellStatus((prev) => ({ ...prev, [key]: 'saved' }));
                clearTimeout(statusTimers.current[key]);
                statusTimers.current[key] = setTimeout(() => {
                    if (requestSeq.current[key] !== mySeq) return; // don't clear a status set by a newer request
                    setCellStatus((prev) => { const next = { ...prev }; delete next[key]; return next; });
                }, 2000);
            })
            .catch(() => {
                if (requestSeq.current[key] !== mySeq) return; // stale failure — a newer request already succeeded
                clearTimeout(statusTimers.current[key]);
                setCellStatus((prev) => ({ ...prev, [key]: 'error' })); // visible & persistent — see Task 8 brief
            });
    }, [kawasanType, kawasanId, jenisPr, tahun, penjuru]);

    const downloadPdf = () => {
        const url = route('pilihanraya.borang-14.pdf', {
            kawasan_type: kawasanType,
            kawasan_id: kawasanId,
            jenis_pr: jenisPr,
            tahun,
            penjuru: Number(penjuru),
            parti: partyNames, // headers follow the on-screen dropdown selection
        });
        window.open(url, '_blank');
    };

    const allPartiesMapped = parties.length > 0 && parties.every((p) => p.keahlian_parti_id);
    const anySaving = Object.values(cellStatus).some((s) => s === 'saving' || s === 'error');

    const publish = async () => {
        if (!form?.id) return;
        setPublishing(true);
        try {
            await axios.post(route('pilihanraya.borang-14.publish'), { form_id: form.id });
            setForm((f) => ({ ...f, status: 'published' }));
            setPublishedOk(true);
        } catch (e) {
            alert(e.response?.data?.message || 'Gagal menerbitkan Borang 14.');
        } finally {
            setPublishing(false);
        }
    };

    const canShowTables = geographyComplete && hasData && penjuru && blocks.length > 0;

    return (
        <>
            {/* Filters */}
            <div className={`${t.cardTight} mb-4`}>
                <KawasanPicker
                    value={picker}
                    onChange={setPicker}
                    negeriList={negeriList}
                    parlimenList={parlimenList}
                    kadunList={kadunList}
                />

                {/* Row 2 — penjuru + party pickers (only once geography chosen & data exists) */}
                {geographyComplete && hasData && (
                    <div className="mt-3 pt-3 border-t border-dashed grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                        <div>
                            <label className={t.label}>Bilangan Penjuru</label>
                            <select value={penjuru} onChange={(e) => setPenjuru(e.target.value)} className={t.input}>
                                <option value="">Pilih Penjuru</option>
                                {penjuruOptions.map((o) => <option key={o.value} value={o.value}>{o.label}</option>)}
                            </select>
                        </div>
                        {parties.map((p, i) => (
                            <div key={i}>
                                <label className={t.label}>Parti {i + 1}</label>
                                <select
                                    value={p.keahlian_parti_id || ''}
                                    onChange={(e) => onPickParty(i, e.target.value)}
                                    className={t.input}
                                >
                                    <option value="">Pilih Parti</option>
                                    {partiList.map((pt) => <option key={pt.id} value={pt.id}>{pt.nama}</option>)}
                                </select>
                                {p.calon && (
                                    <div className={`text-xs ${t.subtext} mt-0.5`}>Calon: {p.calon}{!p.keahlian_parti_id && ' — belum dipetakan'}</div>
                                )}
                            </div>
                        ))}
                    </div>
                )}
            </div>

            {/* Note when geography incomplete */}
            {!geographyComplete && (
                <div className={`${t.banner} flex items-center gap-2`}>
                    <Info className="h-4 w-4 shrink-0" />
                    <span>Pilih Negeri, Parlimen (atau DUN), Jenis PR dan Tahun untuk mula isi.</span>
                </div>
            )}

            {/* No reference data for chosen kawasan */}
            {geographyComplete && !hasData && !loading && (
                <div className={`${t.banner} flex items-center gap-2`}>
                    <Info className="h-4 w-4 shrink-0" />
                    <span>Data Borang 14 (saluran &amp; pengundi berdaftar) belum tersedia untuk kawasan ini.</span>
                </div>
            )}

            {/* Prompt to pick penjuru */}
            {geographyComplete && hasData && !penjuru && !loading && (
                <div className={`${t.banner} flex items-center gap-2`}>
                    <Info className="h-4 w-4 shrink-0" />
                    <span>Pilih bilangan penjuru dan parti untuk memaparkan jadual.</span>
                </div>
            )}

            {loading && (
                <div className={`flex items-center gap-2 ${t.subtext} py-8 justify-center`}>
                    <Loader2 className="h-5 w-5 animate-spin" /> Memuatkan…
                </div>
            )}

            {/* Tables */}
            {canShowTables && !loading && (
                <>
                    <div className="flex items-center justify-between mb-4">
                        <div className={`text-sm ${t.subtext}`}>
                            {reference.negeri} · {reference.parlimen}
                            {reference.dun && <> · <span className={`font-semibold ${t.text}`}>DUN {reference.dun}</span></>}
                        </div>
                        <div className="flex items-center gap-2">
                            {form?.status === 'published' && <span className={`${t.badge} bg-emerald-100 text-emerald-800`}>DITERBITKAN</span>}
                            {form?.status === 'draft' && <span className={`${t.badge} bg-amber-100 text-amber-800`}>DRAF</span>}
                            <button type="button" onClick={downloadPdf} className={t.buttonSecondary}>
                                <Download className="h-4 w-4" /> Muat Turun PDF
                            </button>
                            <button
                                type="button"
                                onClick={publish}
                                disabled={!form?.id || !allPartiesMapped || anySaving || publishing || form?.status === 'published'}
                                title={!allPartiesMapped ? 'Petakan setiap calon kepada parti dahulu' : anySaving ? 'Tunggu autosave selesai / betulkan sel merah' : undefined}
                                className={t.buttonPrimary}
                            >
                                {publishing ? <Loader2 className="h-4 w-4 animate-spin" /> : <Save className="h-4 w-4" />} Save &amp; Terbit
                            </button>
                        </div>
                    </div>

                    {reference.source === 'dpt_estimate' && (
                        <div className={`${t.banner} flex items-center gap-2 mb-4`}>
                            <Info className="h-4 w-4 shrink-0" />
                            <span>Pusat Mengundi &amp; Berdaftar dianggarkan daripada data DPT yang dimuat naik (dikumpul ikut Lokaliti, satu Saluran setiap Pusat Mengundi) — bukan pecahan Saluran rasmi gazet SPR.</span>
                        </div>
                    )}

                    {reference.source === 'scoresheet' && (
                        <div className={`${t.banner} flex items-center gap-2 mb-4`}>
                            <Info className="h-4 w-4 shrink-0" />
                            <span>Struktur Pusat Mengundi &amp; Saluran diambil terus daripada scoresheet yang dimuat naik — kawasan ini belum ada rujukan SPR rasmi/data DPT, jadi Berdaftar tidak diketahui.</span>
                        </div>
                    )}

                    {form?.needs_review && (
                        <div className={`${t.banner} flex items-center gap-2 mb-4`}>
                            <Info className="h-4 w-4 shrink-0" />
                            <span>Scoresheet ini perlu semakan: sahkan pemetaan parti bagi setiap calon (dan saluran teragregat, jika ada) sebelum publish.</span>
                        </div>
                    )}

                    {form?.crosscheck_issues?.length > 0 && (
                        <div className="bg-rose-50 border border-rose-300 text-rose-800 rounded-lg px-4 py-3 text-sm mb-4">
                            <div className="font-semibold mb-1">Silang-semak tidak seimbang — (A) ≠ Σ undi + (C) + (D) pada baris berikut:</div>
                            <ul className="list-disc pl-5 space-y-0.5">
                                {form.crosscheck_issues.map((msg, i) => <li key={i}>{msg}</li>)}
                            </ul>
                        </div>
                    )}

                    {publishedOk && (
                        <div className="bg-emerald-50 border border-emerald-300 text-emerald-800 rounded-lg px-4 py-3 text-sm mb-4">
                            Borang 14 diterbitkan — rekod kini kelihatan dalam tab Papar.
                        </div>
                    )}

                    {Object.values(cellStatus).includes('error') && (
                        <div className="bg-rose-50 border border-rose-300 text-rose-800 rounded-lg px-4 py-3 text-sm mb-4">
                            Sesetengah sel gagal disimpan (bertanda merah). Ubah semula nilai sel itu untuk cuba simpan sekali lagi.
                        </div>
                    )}

                    <GrandSummary partyNames={partyNames} totals={summary} />

                    {/* Jump-to-Pusat-Mengundi — scroll straight to the card the user wants to fill. */}
                    {pusatAnchors.length > 1 && (
                        <div className={`${t.cardTight} mb-4`}>
                            <div className="flex flex-wrap items-center gap-2">
                                <span className={`text-xs font-semibold uppercase tracking-wider ${t.subtext} mr-1 inline-flex items-center gap-1`}>
                                    <MapPin className="h-3.5 w-3.5" /> Pusat Mengundi
                                </span>
                                <select
                                    value={selectedPusat}
                                    onChange={(e) => setSelectedPusat(e.target.value)}
                                    className={`${t.input} max-w-md`}
                                >
                                    <option value="">Pilih Pusat Mengundi</option>
                                    {pusatAnchors.map(({ anchorId, dm, pusat }) => (
                                        <option key={anchorId} value={anchorId}>{pusat} — DM: {dm}</option>
                                    ))}
                                </select>
                                <button
                                    type="button"
                                    onClick={goToPusat}
                                    disabled={!selectedPusat}
                                    className="inline-flex items-center gap-1.5 px-4 py-1.5 rounded-lg bg-slate-900 text-white text-sm font-medium disabled:opacity-40 disabled:cursor-not-allowed hover:bg-slate-800"
                                >
                                    Go
                                </button>
                            </div>
                        </div>
                    )}

                    <div className="grid grid-cols-1 gap-4">
                        {blocks.map((b, i) => (
                            <VoteTable
                                key={`${b.dm}-${b.pusat}-${i}`}
                                block={b}
                                partyNames={partyNames}
                                votes={votes}
                                onSave={saveVote}
                                anchorId={pusatAnchors[i]?.anchorId}
                                cellStatus={cellStatus}
                            />
                        ))}
                    </div>

                    {undiAwalPosRows.length > 0 && (
                        <div className="mt-4">
                            <UndiAwalPosTable
                                partyNames={partyNames}
                                votes={votes}
                                onSave={saveVote}
                                rows={undiAwalPosRows}
                                cellStatus={cellStatus}
                            />
                        </div>
                    )}
                </>
            )}
        </>
    );
}
