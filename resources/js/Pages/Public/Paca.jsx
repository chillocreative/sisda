import { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import axios from 'axios';
import { MapPin, Users, CheckCircle2, Loader2, X } from 'lucide-react';

/* ------------------------------- helpers ------------------------------- */

// Senarai parti biasa untuk datalist — petugas boleh taip nilai lain juga.
const PARTI_SENARAI = ['KEADILAN', 'DAP', 'AMANAH', 'PKR', 'UMNO', 'PAS', 'BERSATU'];

// Petunjuk ringan sahaja di sisi klien — pelayan adalah sumber kebenaran.
// Terima format berdash (NNNNNN-NN-NNNN) atau 12 digit berturutan.
const icKelihatanSah = (kp) => /^\d{6}-\d{2}-\d{4}$/.test(kp) || /^\d{12}$/.test(kp);

/* ------------------------------ borang slot ----------------------------- */

function BorangSlot({ slot, token, onBerjaya, onBatal }) {
    const [nama, setNama] = useState('');
    const [kp, setKp] = useState('');
    const [tel, setTel] = useState('');
    const [parti, setParti] = useState('');
    const [menghantar, setMenghantar] = useState(false);
    const [ralat, setRalat] = useState(null);

    const icDisentuh = kp.length > 0;
    const icNampakTidakSah = icDisentuh && !icKelihatanSah(kp);

    const hantar = (e) => {
        e.preventDefault();
        setMenghantar(true);
        setRalat(null);

        axios.post(route('paca.public.hantar', token), {
            paca_slot_id: slot.id,
            petugas_nama: nama,
            petugas_kp: kp,
            petugas_tel: tel,
            petugas_parti: parti || null,
        })
            .then(() => {
                onBerjaya();
            })
            .catch((err) => {
                const data = err?.response?.data;
                const mesej = data?.errors
                    ? Object.values(data.errors).flat().join(' ')
                    : (data?.message || 'Gagal menghantar. Sila cuba lagi.');
                setRalat(mesej);
            })
            .finally(() => setMenghantar(false));
    };

    return (
        <form onSubmit={hantar} className="mt-3 rounded-xl border border-slate-200 bg-slate-50 p-4 space-y-3">
            <div className="flex items-center justify-between">
                <span className="text-sm font-semibold text-slate-700">Isi Slot {slot.jawatan}</span>
                <button type="button" onClick={onBatal} className="text-slate-400 hover:text-slate-600 p-1 -m-1" aria-label="Batal">
                    <X className="h-4 w-4" />
                </button>
            </div>

            <label className="block">
                <span className="text-xs font-medium text-slate-600 mb-1 block">Nama Penuh</span>
                <input
                    type="text" required value={nama} onChange={(e) => setNama(e.target.value)}
                    className="w-full px-3 py-2.5 bg-white border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-slate-400 focus:outline-none"
                    placeholder="Nama seperti dalam kad pengenalan"
                />
            </label>

            <label className="block">
                <span className="text-xs font-medium text-slate-600 mb-1 block">No Kad Pengenalan</span>
                <input
                    type="text" required value={kp} onChange={(e) => setKp(e.target.value)}
                    className="w-full px-3 py-2.5 bg-white border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-slate-400 focus:outline-none"
                    placeholder="680623-07-5749"
                    inputMode="numeric"
                />
                {icNampakTidakSah && (
                    <span className="text-xs text-amber-600 mt-1 block">Format biasanya NNNNNN-NN-NNNN atau 12 digit — sila semak.</span>
                )}
            </label>

            <label className="block">
                <span className="text-xs font-medium text-slate-600 mb-1 block">No Telefon</span>
                <input
                    type="tel" required value={tel} onChange={(e) => setTel(e.target.value)}
                    className="w-full px-3 py-2.5 bg-white border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-slate-400 focus:outline-none"
                    placeholder="012-3456789"
                />
            </label>

            <label className="block">
                <span className="text-xs font-medium text-slate-600 mb-1 block">Parti</span>
                <input
                    type="text" value={parti} onChange={(e) => setParti(e.target.value)}
                    list="paca-senarai-parti"
                    className="w-full px-3 py-2.5 bg-white border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-slate-400 focus:outline-none"
                    placeholder="Contoh: KEADILAN"
                />
                <datalist id="paca-senarai-parti">
                    {PARTI_SENARAI.map((p) => <option key={p} value={p} />)}
                </datalist>
            </label>

            {ralat && (
                <p className="text-sm text-red-600 bg-red-50 border border-red-200 rounded-lg px-3 py-2">{ralat}</p>
            )}

            <button
                type="submit" disabled={menghantar}
                className="w-full inline-flex items-center justify-center gap-2 px-4 py-3 rounded-lg bg-slate-900 text-white text-sm font-semibold disabled:opacity-60 active:scale-[0.99] transition"
            >
                {menghantar ? <Loader2 className="h-4 w-4 animate-spin" /> : null}
                {menghantar ? 'Menghantar…' : 'Hantar'}
            </button>
        </form>
    );
}

/* ------------------------------ baris slot ------------------------------ */

function BarisSlot({ slot, token, aktif, onBuka, onTutup, onBerjaya }) {
    return (
        <li className="py-3 first:pt-0 last:pb-0">
            <div className="flex items-center justify-between gap-3">
                <div className="min-w-0">
                    <div className="text-sm font-semibold text-slate-900">{slot.jawatan}</div>
                    {slot.masa && <div className="text-xs text-slate-500">{slot.masa}</div>}
                </div>

                {slot.terisi ? (
                    <span className="inline-flex items-center gap-1.5 shrink-0 px-3 py-1.5 rounded-full bg-emerald-50 text-emerald-700 text-xs font-semibold border border-emerald-200">
                        <CheckCircle2 className="h-3.5 w-3.5" />
                        Terisi{slot.parti ? ` · ${slot.parti}` : ''}
                    </span>
                ) : aktif ? (
                    <button
                        type="button" onClick={onTutup}
                        className="shrink-0 px-3 py-1.5 rounded-full bg-slate-100 text-slate-600 text-xs font-semibold border border-slate-300"
                    >
                        Batal
                    </button>
                ) : (
                    <button
                        type="button" onClick={onBuka}
                        className="shrink-0 px-3 py-1.5 rounded-full bg-slate-900 text-white text-xs font-semibold active:scale-95 transition"
                    >
                        Isi slot ini
                    </button>
                )}
            </div>

            {aktif && !slot.terisi && (
                <BorangSlot slot={slot} token={token} onBerjaya={onBerjaya} onBatal={onTutup} />
            )}
        </li>
    );
}

/* -------------------------------- saluran -------------------------------- */

function BlokSaluran({ saluran, token, slotAktif, setSlotAktif, onBerjaya }) {
    return (
        <div className="rounded-2xl bg-white border border-slate-200 shadow-sm p-4 sm:p-5">
            <h2 className="text-sm font-bold uppercase tracking-wide text-slate-500 mb-2">
                Saluran {saluran.label}
            </h2>
            <ul className="divide-y divide-slate-100">
                {saluran.slot.map((slot) => (
                    <BarisSlot
                        key={slot.id}
                        slot={slot}
                        token={token}
                        aktif={slotAktif === slot.id}
                        onBuka={() => setSlotAktif(slot.id)}
                        onTutup={() => setSlotAktif(null)}
                        onBerjaya={() => { setSlotAktif(null); onBerjaya(); }}
                    />
                ))}
            </ul>
        </div>
    );
}

/* -------------------------------- halaman -------------------------------- */

export default function PublicPaca({ token, pusat, saluran = [] }) {
    const [slotAktif, setSlotAktif] = useState(null);
    const [tunjukTerimaKasih, setTunjukTerimaKasih] = useState(false);

    const segarkan = () => {
        setTunjukTerimaKasih(true);
        router.reload();
    };

    return (
        <>
            <Head title={`${pusat?.pusat || 'PACA'} — Senarai Petugas`} />
            <div className="min-h-screen bg-[#f5f6f8]">
                <header className="border-b border-slate-200 bg-white">
                    <div className="max-w-lg mx-auto px-4 py-5">
                        <div className="flex items-start gap-3">
                            <div className="h-10 w-10 shrink-0 rounded-full bg-slate-900 text-white flex items-center justify-center">
                                <Users className="h-5 w-5" />
                            </div>
                            <div className="min-w-0">
                                <h1 className="text-lg font-black text-slate-900 leading-tight">{pusat?.pusat || '—'}</h1>
                                <p className="text-xs text-slate-500 inline-flex items-center gap-1 mt-0.5">
                                    <MapPin className="h-3.5 w-3.5" /> DM {pusat?.dm || '—'}
                                </p>
                            </div>
                        </div>
                        <p className="text-sm text-slate-600 mt-3">
                            Senarai Petugas PACABA — isi slot yang masih kosong.
                        </p>
                    </div>
                </header>

                <main className="max-w-lg mx-auto px-4 py-6 space-y-4">
                    {tunjukTerimaKasih && (
                        <div className="rounded-2xl bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 text-sm font-medium flex items-center gap-2">
                            <CheckCircle2 className="h-4 w-4 shrink-0" />
                            Terima kasih, slot anda telah didaftarkan.
                        </div>
                    )}

                    {saluran.length === 0 ? (
                        <div className="rounded-2xl bg-white border border-slate-200 p-8 text-center text-slate-500 text-sm">
                            Tiada saluran didaftarkan untuk Pusat ini.
                        </div>
                    ) : (
                        saluran.map((s) => (
                            <BlokSaluran
                                key={s.id}
                                saluran={s}
                                token={token}
                                slotAktif={slotAktif}
                                setSlotAktif={setSlotAktif}
                                onBerjaya={segarkan}
                            />
                        ))
                    )}
                </main>

                <footer className="max-w-lg mx-auto px-4 pb-10 text-center">
                    <p className="text-xs text-slate-400">Dikuasakan oleh <span className="font-semibold text-slate-500">SISDA</span></p>
                </footer>
            </div>
        </>
    );
}
