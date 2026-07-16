import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import axios from 'axios';
import { Download, Info, Landmark, MapPin, Vote, Loader2 } from 'lucide-react';
import { usePilihanrayaTheme } from '../components/PilihanrayaShell';
import {
    VoteTable, UndiAwalPosTable, GrandSummary,
    toBlocks, cellKey, BULOH_KASAP_KADUN_ID,
} from '../components/Borang14Form';

export default function KeyinTab({ negeriList, parlimenList, kadunList, partiList, penjuruOptions, prefill = null }) {
    const { t } = usePilihanrayaTheme();

    const [negeriId, setNegeriId] = useState('');
    const [parlimenId, setParlimenId] = useState('');
    const [kadunId, setKadunId] = useState('');
    const [penjuru, setPenjuru] = useState('');
    const [parties, setParties] = useState([]); // [{slot, keahlian_parti_id, nama}]
    const [reference, setReference] = useState(null);
    const [hasData, setHasData] = useState(true);
    const [votes, setVotes] = useState({});
    const [loading, setLoading] = useState(false);
    const [selectedPusat, setSelectedPusat] = useState('');
    const [cellStatus, setCellStatus] = useState({});
    const statusTimers = useRef({});
    // Per-cellKey request sequence — guards against out-of-order POST
    // resolutions (see Task 8 review finding: resolution order is not send
    // order, so a stale response must never overwrite a newer one).
    const requestSeq = useRef({});
    useEffect(() => () => Object.values(statusTimers.current).forEach(clearTimeout), []);

    // Applied when the Upload/Papar tabs hand off a specific geography via
    // openKeyin(prefill); nonce forces re-apply even if the same DUN is sent
    // twice in a row. Only geography is consumable until Task 12 wires the
    // remaining prefill fields (kawasanType/jenisPr/tahun/formId) into the
    // API payload.
    useEffect(() => {
        if (!prefill) return;
        setNegeriId(String(prefill.negeriId ?? ''));
        setParlimenId(String(prefill.parlimenId ?? ''));
        setKadunId(String(prefill.kadunId ?? ''));
    }, [prefill?.nonce]); // eslint-disable-line react-hooks/exhaustive-deps

    const parlimenOptions = negeriId
        ? parlimenList.filter((p) => String(p.negeri_id) === String(negeriId))
        : [];
    const kadunOptions = parlimenId
        ? kadunList.filter((k) => String(k.bandar_id) === String(parlimenId))
        : [];

    const geographyComplete = negeriId && parlimenId && kadunId;

    // Fetch reference + saved data whenever the DUN or penjuru changes.
    useEffect(() => {
        if (!kadunId) { setReference(null); setHasData(true); setVotes({}); return; }
        let cancelled = false;
        setLoading(true);
        setSelectedPusat('');
        axios.get(route('pilihanraya.borang-14.data'), { params: { kadun_id: kadunId, penjuru: penjuru || undefined } })
            .then(({ data }) => {
                if (cancelled) return;
                setReference(data.reference);
                setHasData(data.hasData);
                setVotes(data.votes || {});
                if (data.parties && data.parties.length) setParties(data.parties);
            })
            .finally(() => { if (!cancelled) setLoading(false); });
        return () => { cancelled = true; };
    }, [kadunId, penjuru]);

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

    const isBulohKasap = Number(kadunId) === BULOH_KASAP_KADUN_ID;

    // Undi Awal & Undi Pos rows for the current DUN: one combined row for
    // Buloh Kasap, two separate rows (each with its own Berdaftar) elsewhere.
    const undiAwalPosRows = useMemo(() => {
        const awal = reference?.undi_awal?.berdaftar ?? 0;
        const pos = reference?.undi_pos?.berdaftar ?? 0;
        return isBulohKasap
            ? [{ label: 'UNDI AWAL & POS', berdaftar: awal + pos }]
            : [{ label: 'UNDI AWAL', berdaftar: awal }, { label: 'UNDI POS', berdaftar: pos }];
    }, [reference, isBulohKasap]);

    // Grand rollup across every saluran + undi awal & pos for the bottom summary.
    const summary = useMemo(() => {
        const nParties = partyNames.length;
        const partyTotals = Array.from({ length: nParties }, () => 0);
        let berdaftar = 0;
        blocks.forEach((b) => {
            b.saluran.forEach((s) => {
                berdaftar += s.berdaftar || 0;
                for (let i = 0; i < nParties; i++) {
                    partyTotals[i] += votes[cellKey(b.pusat, String(s.no), i + 1)] ?? 0;
                }
            });
        });
        undiAwalPosRows.forEach(({ label, berdaftar: rowBerdaftar }) => {
            berdaftar += rowBerdaftar;
            for (let i = 0; i < nParties; i++) {
                partyTotals[i] += votes[cellKey('', label, i + 1)] ?? 0;
            }
        });
        const keluar = partyTotals.reduce((a, b) => a + b, 0);
        return { partyTotals, keluar, berdaftar };
    }, [blocks, votes, partyNames, undiAwalPosRows]);

    const persistParties = useCallback((next) => {
        if (!kadunId || !penjuru) return;
        axios.post(route('pilihanraya.borang-14.parties'), {
            kadun_id: kadunId, penjuru: Number(penjuru), parties: next,
        }).catch(() => {});
    }, [kadunId, penjuru]);

    const onPickParty = (index, partiId) => {
        const parti = partiList.find((p) => String(p.id) === String(partiId));
        const next = parties.map((p, i) => (i === index
            ? { slot: i + 1, keahlian_parti_id: parti ? parti.id : '', nama: parti ? parti.nama : '' }
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
            kadun_id: kadunId, penjuru: Number(penjuru), pusat, saluran, slot, undi,
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
    }, [kadunId, penjuru]);

    const downloadPdf = () => {
        const url = route('pilihanraya.borang-14.pdf', {
            kadun_id: kadunId,
            penjuru: Number(penjuru),
            parti: partyNames, // headers follow the on-screen dropdown selection
        });
        window.open(url, '_blank');
    };

    const canShowTables = geographyComplete && hasData && penjuru && blocks.length > 0;

    return (
        <>
            {/* Filters */}
            <div className={`${t.cardTight} mb-4`}>
                {/* Row 1 — geography */}
                <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div>
                        <label className={t.label}><span className="inline-flex items-center gap-1"><MapPin className="h-3.5 w-3.5" /> Negeri</span></label>
                        <select
                            value={negeriId}
                            onChange={(e) => { setNegeriId(e.target.value); setParlimenId(''); setKadunId(''); }}
                            className={t.input}
                        >
                            <option value="">Pilih Negeri</option>
                            {negeriList.map((n) => <option key={n.id} value={n.id}>{n.nama}</option>)}
                        </select>
                    </div>
                    <div>
                        <label className={t.label}><span className="inline-flex items-center gap-1"><Landmark className="h-3.5 w-3.5" /> Parlimen</span></label>
                        <select
                            value={parlimenId}
                            onChange={(e) => { setParlimenId(e.target.value); setKadunId(''); }}
                            className={t.input}
                            disabled={!negeriId}
                        >
                            <option value="">Pilih Parlimen</option>
                            {parlimenOptions.map((p) => <option key={p.id} value={p.id}>{p.nama}</option>)}
                        </select>
                    </div>
                    <div>
                        <label className={t.label}><span className="inline-flex items-center gap-1"><Vote className="h-3.5 w-3.5" /> DUN</span></label>
                        <select
                            value={kadunId}
                            onChange={(e) => setKadunId(e.target.value)}
                            className={t.input}
                            disabled={!parlimenId}
                        >
                            <option value="">Pilih DUN</option>
                            {kadunOptions.map((k) => <option key={k.id} value={k.id}>{k.nama}</option>)}
                        </select>
                    </div>
                </div>

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
                            </div>
                        ))}
                    </div>
                )}
            </div>

            {/* Note when geography incomplete */}
            {!geographyComplete && (
                <div className={`${t.banner} flex items-center gap-2`}>
                    <Info className="h-4 w-4 shrink-0" />
                    <span>Pilih Negeri &gt; Parlimen &gt; DUN untuk di isi.</span>
                </div>
            )}

            {/* No reference data for chosen DUN */}
            {geographyComplete && !hasData && !loading && (
                <div className={`${t.banner} flex items-center gap-2`}>
                    <Info className="h-4 w-4 shrink-0" />
                    <span>Data Borang 14 (saluran & pengundi berdaftar) belum tersedia untuk DUN ini.</span>
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
                            {reference.negeri} · {reference.parlimen} · <span className={`font-semibold ${t.text}`}>DUN {reference.dun}</span>
                        </div>
                        <button type="button" onClick={downloadPdf} className={t.buttonPrimary}>
                            <Download className="h-4 w-4" /> Muat Turun PDF
                        </button>
                    </div>

                    {reference.source === 'dpt_estimate' && (
                        <div className={`${t.banner} flex items-center gap-2 mb-4`}>
                            <Info className="h-4 w-4 shrink-0" />
                            <span>Pusat Mengundi &amp; Berdaftar dianggarkan daripada data DPT yang dimuat naik (dikumpul ikut Lokaliti, satu Saluran setiap Pusat Mengundi) — bukan pecahan Saluran rasmi gazet SPR.</span>
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

                    <div className="mt-4">
                        <UndiAwalPosTable
                            partyNames={partyNames}
                            votes={votes}
                            onSave={saveVote}
                            rows={undiAwalPosRows}
                            cellStatus={cellStatus}
                        />
                    </div>
                </>
            )}
        </>
    );
}
