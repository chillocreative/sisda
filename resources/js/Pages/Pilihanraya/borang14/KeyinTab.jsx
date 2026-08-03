import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { usePage } from '@inertiajs/react';
import axios from 'axios';
import { Download, Info, Lock, LockOpen, MapPin, Loader2, Save } from 'lucide-react';
import { usePilihanrayaTheme } from '../components/PilihanrayaShell';
import KawasanPicker from './KawasanPicker';
import StrukturPanel from './StrukturPanel';
import {
    VoteTable, UndiAwalPosTable, GrandSummary,
    VoteTableSerentak, UndiAwalPosTableSerentak,
    toBlocks, cellKey, BULOH_KASAP_KADUN_ID,
} from '../components/Borang14Form';

const JENIS_LABEL = { pru: 'PRU', prn: 'PRN', prk: 'PRK' };

// Skop pertandingan borang. Skop tunggal DINAMAKAN mengikut pertandingannya
// sendiri ('parlimen' / 'dun'), jadi hanya nilai ketiga perlukan pemalar —
// sama seperti Borang14Controller::SKOP_KEDUA di server.
const SKOP_KEDUA = 'kedua';

// Warna baris calon dalam mod serentak mengikut scoresheet asal: BIRU untuk
// pertandingan Parlimen (PRU), MERAH untuk pertandingan DUN (PRN), supaya
// pengendali yang memegang cetakan itu nampak pemetaannya serta-merta. Sama
// dengan warna jalur pada jadual undi di bawah.
//
// Baris yang BELUM ditanda kekal kelabu bergaris putus. Belum ditanda bukan
// bermakna "DUN secara lalai" — meneka di sini bermakna calon boleh berakhir
// pada pertandingan yang salah, dan itu senyap sepenuhnya.
const WARNA_KONTES = {
    parlimen: 'border-sky-300 bg-sky-50',
    dun: 'border-rose-300 bg-rose-50',
};
const warnaKontes = (kontes) => WARNA_KONTES[kontes] ?? 'border-slate-300 border-dashed bg-slate-50';

const LABEL_KONTES = { parlimen: 'Parlimen (PRU)', dun: 'DUN (PRN)' };

// Laravel 422 membawa mesej generik ("The given data was invalid.") pada
// `message`, dengan sebab sebenar pada `errors.<medan>[]`. Mesej server bagi
// skop pertandingan menamakan masalah SEBENAR (pertandingan tinggal satu
// calon, undi sudah wujud jadi penetapan dikunci, skop kedua-dua pada borang
// Parlimen) — menggantikannya dengan mesej generik membuang seluruh nilainya.
//
// SEMUA mesej digabung, bukan yang pertama sahaja: `skop` dan `parties` boleh
// gagal dalam permintaan yang sama.
const ralatServer = (e, fallback) => {
    // 403 datang daripada konvensyen kebenaran projek, yang mengabort dengan
    // teks Inggeris tetap ('Unauthorized action.'). Teks itu dikongsi seluruh
    // sistem dan tidak sepatutnya ditukar di sini — jadi ia digantikan pada
    // paparan supaya pengendali tidak pernah melihat bahasa Inggeris.
    if (e?.response?.status === 403) {
        return 'Anda tiada kebenaran untuk kerusi ini. Hubungi Super Admin jika anda sepatutnya boleh menyuntingnya.';
    }

    const data = e?.response?.data;
    if (!data) return fallback;
    const semua = Object.values(data.errors || {}).flat().filter(Boolean);
    return semua.length > 0 ? semua.join(' ') : (data.message || fallback);
};

// Skop yang SEDANG tersimpan pada borang, dibaca semula daripada CARA senarai
// calon dipisahkan antara borang DUN dan borang takrifan Parlimen — cerminan
// tepat Borang14Controller::skopSemasa(). Tiada lajur "skop" wujud dalam
// pangkalan data dan tiada medan baharu pada data(); pemisahan itu sendiri
// ialah satu-satunya rekod, jadi ini satu-satunya cara yang betul.
//
// Pautan SEMATA-MATA (togol serentak pada panel Struktur, calon PRU belum
// dinamakan) BUKAN skop dua pertandingan — takrifan yang masih kosong tidak
// menetapkan sesiapa kepada mana-mana pertandingan.
const kiraSkopSemasa = (data, sendiri) => {
    const calonParlimen = data.kontes_parlimen?.parties;
    if (!Array.isArray(calonParlimen) || calonParlimen.length === 0) return sendiri;

    return data.parties?.length > 0 ? SKOP_KEDUA : 'parlimen';
};

// Satu peta undi bagi kedua-dua jalur. Kunci sel dinamakan ruang mengikut
// contest ('dun|…' lawan 'parlimen|…'), jadi undi PRN dan PRU boleh duduk
// dalam SATU objek tanpa satu menulis ganti satu lagi — itulah sebabnya
// contest wajib menjadi komponen pertama cellKey().
//
// Kedua-dua peta bersiri sebagai [] (array), BUKAN {}, apabila kosong; sebaran
// objek mengendalikan kedua-dua bentuk dengan betul kerana {...[]} === {}.
const mergeVotes = (data) => ({ ...(data.votes || {}), ...(data.kontes_parlimen?.votes || {}) });

export default function KeyinTab({ negeriList, parlimenList, kadunList, partiList, penjuruOptions, prefill = null }) {
    const { t } = usePilihanrayaTheme();

    // Geography + jenis PR/tahun in one controlled object — kawasan can be a
    // Parlimen OR a DUN (kawasanType), never implied only via a DUN path.
    // Nilai awal disemai daripada rememberedFilters (skop 'borang14') — selamat
    // kerana ia berlaku SEBELUM render pertama; jangan tambah tulisan kedua
    // kepada `picker` atau tunda/alih kesan sedia ada di bawah.
    const { rememberedFilters } = usePage().props;
    const [picker, setPicker] = useState(() => ({
        negeriId: rememberedFilters?.negeri_id ?? '',
        kawasanType: rememberedFilters?.kawasan_type ?? '',
        parlimenId: rememberedFilters?.parlimen_id ?? '',
        kadunId: rememberedFilters?.kadun_id ?? '',
        jenisPr: rememberedFilters?.jenis_pr ?? '',
        tahun: rememberedFilters?.tahun ?? '',
    }));
    const { negeriId, jenisPr, kawasanType, parlimenId, kadunId, tahun } = picker;
    const kawasanId = kawasanType === 'parlimen' ? parlimenId : kadunId;
    const geographyComplete = Boolean(negeriId && jenisPr && kawasanType && kawasanId && tahun);

    // Pertandingan borang ini SENDIRI — cerminan tepat `Borang14Form::contestSendiri()`
    // di server: kawasan Parlimen merekod undi PRU, kawasan DUN merekod undi PRN.
    // Nilai `Borang14Vote::CONTEST_*` memang sama dengan `kawasan_type`, jadi tiada
    // pemetaan tambahan di sini — satu pemetaan lagi hanya akan hanyut.
    const contestSendiri = kawasanType === 'parlimen' ? 'parlimen' : 'dun';

    const [penjuru, setPenjuru] = useState('');
    // Skop yang DIPILIH pengguna, dan skop yang benar-benar TERSIMPAN pada
    // server. Kedua-duanya bermula pada skop tunggal borang ini sendiri —
    // itulah tingkah laku hari ini, kes yang paling biasa, dan laluan yang
    // sudah berada di produksi; ia mesti kekal serupa-bit.
    const [skop, setSkop] = useState(contestSendiri);
    const [skopSemasa, setSkopSemasa] = useState(contestSendiri);
    const [ralatParti, setRalatParti] = useState('');
    const [menyimpanSkop, setMenyimpanSkop] = useState(false);
    // [{slot, keahlian_parti_id, nama, calon?, kontes?}] — `kontes` ialah
    // keadaan UI SEMENTARA sahaja. Server membuangnya sebelum menyimpan dan
    // tidak pernah memulangkannya, kerana selepas pemisahan borang mana calon
    // itu duduk sudah menyatakan segalanya.
    const [parties, setParties] = useState([]);
    const [reference, setReference] = useState(null);
    const [hasData, setHasData] = useState(true);
    // { tahun, jenis_pr } when the current structure was inherited from another
    // election of the same seat (no scoresheet uploaded yet this round); null
    // whenever the reference came from curated JSON/DPT or this election's own
    // structure. Must never be silent — see the banner below.
    const [inheritedFrom, setInheritedFrom] = useState(null);
    // Keadaan panel Sunting Struktur. `struktur` sentiasa datang dari server —
    // jangan sekali-kali kira semula di client, kerana peraturan row_id yang
    // hanyut bermakna undi dipadam sebagai ganti dipindahkan.
    const [struktur, setStruktur] = useState({ pusat: [], undi_awal: false, undi_pos: false });
    const [bolehSuntingStruktur, setBolehSuntingStruktur] = useState(false);
    const [suntingStruktur, setSuntingStruktur] = useState(false);
    const [reloadNonce, setReloadNonce] = useState(0);
    const [votes, setVotes] = useState({});
    const [form, setForm] = useState(null); // { id, status, source, needs_review, crosscheck_issues, penjuru }
    // Pertandingan Parlimen yang dipaut kepada borang DUN ini — { id, penjuru,
    // parties, kawasan_nama, votes }. Server MENINGGALKAN kunci ini sepenuhnya
    // apabila tiada pautan, jadi `null` di sini bermakna borang satu
    // pertandingan: kes yang paling biasa, dan yang mesti kekal dipapar
    // serupa-bit dengan hari ini.
    const [kontesParlimen, setKontesParlimen] = useState(null);
    const [loading, setLoading] = useState(false);
    const [selectedPusat, setSelectedPusat] = useState('');
    const [cellStatus, setCellStatus] = useState({});
    const [publishing, setPublishing] = useState(false);
    const [publishedOk, setPublishedOk] = useState(false);
    // Bolehkah pengguna INI mengunci/membuka kunci borang yang sedang dipapar.
    // Datang dari server pada setiap muatan — peraturan peranan+kerusi yang
    // ditulis semula di sini pasti menyimpang daripada pengawal.
    const [bolehKunci, setBolehKunci] = useState(false);
    const [kunciBusy, setKunciBusy] = useState(false);
    const [ralatKunci, setRalatKunci] = useState('');
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
                    setStruktur(data.struktur || { pusat: [], undi_awal: false, undi_pos: false });
                    setBolehSuntingStruktur(Boolean(data.boleh_sunting_struktur));
                    setInheritedFrom(data.inherited_from || null);
                    setVotes(mergeVotes(data));
                    setKontesParlimen(data.kontes_parlimen || null);
                    setForm(data.form || null);
                    setBolehKunci(Boolean(data.boleh_kunci));
                    setRalatKunci('');
                    setPublishedOk(false);
                    // Skop disemai daripada borang yang DIMUAT, bukan daripada
                    // `contestSendiri` semasa: picker baru sahaja ditulis di
                    // atas dan belum dirender, jadi kawasan_type yang betul
                    // hanya wujud di sini, dalam `resolved`.
                    const skopDimuat = kiraSkopSemasa(data, r.kawasan_type === 'parlimen' ? 'parlimen' : 'dun');
                    setSkopSemasa(skopDimuat);
                    setSkop(skopDimuat);
                    setRalatParti('');
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
        if (!geographyComplete) {
            setReference(null); setHasData(true); setInheritedFrom(null); setVotes({}); setForm(null);
            // Kunci milik borang kerusi yang tadi dipilih — biarkan hidup dan
            // butang "Buka Kunci" akan menyasarkan borang yang salah.
            setBolehKunci(false);
            setRalatKunci('');
            // Pautan kontes milik kerusi yang tadi dipilih — biarkan ia hidup
            // dan skrin akan memapar jalur PRU kerusi LAMA di atas grid kerusi
            // baharu, iaitu tepat salah tafsir yang jalur berwarna ini wujud
            // untuk halang.
            setKontesParlimen(null);
            // Struktur/bolehSuntingStruktur belong to whichever seat was
            // previously selected — never let them survive a picker change
            // (complete or not), or "Sunting Struktur" can open the panel
            // with the old seat's rows against the newly chosen geography.
            setStruktur({ pusat: [], undi_awal: false, undi_pos: false });
            setBolehSuntingStruktur(false);
            // Tutup panel Sunting Struktur juga — pengguna sengaja beralih
            // dari kerusi yang sedang disunting, jadi suntingan separuh siap
            // bagi kerusi lama dianggap dibatalkan, bukan disasarkan semula
            // secara senyap kepada kerusi/pilihan raya baharu.
            setSuntingStruktur(false);
            // Skop milik kerusi yang tadi dipilih. Kembalikan kepada skop
            // TUNGGAL borang semasa — membiarkan "kedua-duanya" hidup merentas
            // pertukaran kerusi bermakna permintaan simpan seterusnya membawa
            // skop kerusi lama.
            setSkop(contestSendiri);
            setSkopSemasa(contestSendiri);
            setRalatParti('');
            return undefined;
        }
        let cancelled = false;
        setLoading(true);
        setSelectedPusat('');
        setPublishedOk(false);
        // Atas sebab yang sama, dan SEBELUM permintaan dihantar: skop kembali
        // kepada lalai tunggal sehingga server memberitahu sebaliknya.
        setSkop(contestSendiri);
        setSkopSemasa(contestSendiri);
        setRalatParti('');
        setRalatKunci('');
        setStruktur({ pusat: [], undi_awal: false, undi_pos: false });
        setBolehSuntingStruktur(false);
        setBolehKunci(false);
        // Atas sebab yang sama: kosongkan pautan kontes SEBELUM permintaan
        // dihantar, supaya jalur PRU kerusi lama tidak kekal terpapar sepanjang
        // muatan kerusi baharu.
        setKontesParlimen(null);
        // Sama seperti di atas — pertukaran kerusi/pilihan raya menutup panel
        // sunting struktur supaya ia tidak menyimpan `struktur` kerusi lama
        // sambil membaca `picker` kerusi baharu semasa Simpan.
        setSuntingStruktur(false);
        axios.get(route('pilihanraya.borang-14.data'), {
            params: { kawasan_type: kawasanType, kawasan_id: kawasanId, jenis_pr: jenisPr, tahun, penjuru: penjuru || undefined },
        })
            .then(({ data }) => {
                if (cancelled) return;
                setReference(data.reference);
                setHasData(data.hasData);
                setStruktur(data.struktur || { pusat: [], undi_awal: false, undi_pos: false });
                setBolehSuntingStruktur(Boolean(data.boleh_sunting_struktur));
                setInheritedFrom(data.inherited_from || null);
                setVotes(mergeVotes(data));
                setKontesParlimen(data.kontes_parlimen || null);
                setForm(data.form || null);
                setBolehKunci(Boolean(data.boleh_kunci));
                // Skop dibaca semula daripada server pada setiap muatan —
                // termasuk muatan semula selepas satu pemisahan. `penjuru`
                // kedua-dua borang ditulis semula oleh pemisahan itu (borang
                // DUN mendapat kiraan DUN, borang takrifan mendapat kiraan
                // Parlimen), jadi apa-apa yang dikira di client akan salah.
                const skopDimuat = kiraSkopSemasa(data, contestSendiri);
                setSkopSemasa(skopDimuat);
                setSkop(skopDimuat);
                if (data.parties?.length) {
                    setParties(data.parties);
                    setPenjuru(String(data.form?.penjuru ?? data.parties.length));
                }
            })
            .finally(() => { if (!cancelled) setLoading(false); });
        return () => { cancelled = true; };
    }, [geographyComplete, kawasanType, kawasanId, jenisPr, tahun, reloadNonce]); // eslint-disable-line react-hooks/exhaustive-deps

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

    // Nama parti jalur PRU. Saiznya datang daripada `penjuru` borang PARLIMEN
    // yang dipaut — BUKAN penjuru borang DUN. Pertandingan Parlimen mempunyai
    // bilangan calonnya sendiri (cth. 3 penjuru di Parlimen berbanding 2 di
    // DUN yang sama), jadi membaca penjuru yang salah akan menggugurkan sel
    // calon secara senyap atau menghantar slot yang ditolak server.
    //
    // Panjang senarai ditentukan oleh `penjuru`, bukan oleh `parties.length`:
    // parti yang belum dipetakan meninggalkan lubang dalam `parties`, dan
    // memendekkan jalur mengikutnya akan menyembunyikan sel yang sah.
    const partyNamesPru = useMemo(() => {
        const n = kontesParlimen?.penjuru;
        if (n == null) return [];
        const senarai = kontesParlimen.parties || [];
        return Array.from({ length: n }, (_, i) => senarai[i]?.nama || `Parti ${i + 1}`);
    }, [kontesParlimen]);

    // Dua jalur hanya apabila kontes Parlimen dipaut DAN penjurunya diketahui.
    // `penjuru` null bermakna "belum ditetapkan", bukan sifar calon — dan
    // Number(null) ialah 0, jadi tanpa semakan `!= null` yang eksplisit ini
    // jalur PRU akan dipapar dengan SIFAR lajur calon dan kelihatan seperti
    // pertandingan yang tiada calon. Bila ragu, kembali kepada satu jalur.
    const serentak = kontesParlimen != null && kontesParlimen.penjuru != null && partyNamesPru.length > 0;

    // Tajuk jalur menamakan pertandingan DAN kerusinya. Nama kerusi yang tiada
    // dieja sebagai teks yang jelas, bukan '—': tajuk "PRU · Parlimen —" pada
    // pukul 11 malam tidak memberitahu PACA kertas undi mana yang dipegangnya.
    const namaParlimen = kontesParlimen?.kawasan_nama;
    const bands = useMemo(() => (serentak ? [
        {
            contest: contestSendiri,
            tajuk: `${JENIS_LABEL[jenisPr] ?? jenisPr} · ${reference?.dun ? `DUN ${reference.dun}` : 'DUN (nama kerusi tidak diketahui)'}`,
            partyNames,
        },
        {
            contest: 'parlimen',
            tajuk: namaParlimen && namaParlimen !== '—'
                ? `PRU · Parlimen ${namaParlimen}`
                : 'PRU · Parlimen (nama kerusi tidak diketahui)',
            partyNames: partyNamesPru,
        },
    ] : []), [serentak, contestSendiri, jenisPr, reference?.dun, namaParlimen, partyNames, partyNamesPru]);

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
        // Key off the REAL saluran string carried in reference.undi_awal/undi_pos.label
        // (set by referenceFromStructure() for scoresheet-sourced forms) —
        // votes are stored under that exact string, so a hardcoded literal
        // here would silently show 0 votes whenever the AI reads anything
        // other than the exact 'UNDI AWAL'/'UNDI POS' text. Curated/DPT
        // references carry no such label — fall back to the same literal the
        // frontend has always used for those (unaffected by this fix).
        const rows = [];
        if (awal) rows.push({ label: awal.label || 'UNDI AWAL', berdaftar: awal.berdaftar ?? null });
        if (pos) rows.push({ label: pos.label || 'UNDI POS', berdaftar: pos.berdaftar ?? null });
        return rows;
    }, [reference, isBulohKasap]);

    // Grand rollup across every saluran + undi awal & pos for the bottom summary.
    //
    // Diambil kira bagi SATU pertandingan pada satu masa supaya skrin dua jalur
    // boleh memanggilnya sekali bagi setiap kertas undi. `berdaftar` sengaja
    // sama pada kedua-dua panggilan — ia memang dikongsi oleh kedua-dua
    // pertandingan, dan itu bukan pendua.
    const buatSummary = useCallback((contest, nParties) => {
        const partyTotals = Array.from({ length: nParties }, () => 0);
        let berdaftar = 0;
        let berdaftarKnown = false;
        let ditolak = 0;
        let tidakDimasukkan = 0;

        const accumulate = (pusat, saluran, rowBerdaftar) => {
            if (rowBerdaftar != null) { berdaftarKnown = true; berdaftar += rowBerdaftar; }
            for (let i = 0; i < nParties; i++) {
                partyTotals[i] += votes[cellKey(contest, pusat, saluran, i + 1)] ?? 0;
            }
            ditolak += votes[cellKey(contest, pusat, saluran, 90)] ?? 0;
            tidakDimasukkan += votes[cellKey(contest, pusat, saluran, 91)] ?? 0;
        };

        blocks.forEach((b) => {
            b.saluran.forEach((s) => accumulate(b.pusat, String(s.no), s.berdaftar ?? null));
        });
        undiAwalPosRows.forEach(({ label, berdaftar: rowBerdaftar }) => accumulate('', label, rowBerdaftar));

        const keluar = partyTotals.reduce((a, b) => a + b, 0) + ditolak + tidakDimasukkan;
        return { partyTotals, ditolak, tidakDimasukkan, keluar, berdaftar, berdaftarKnown };
    }, [blocks, votes, undiAwalPosRows]);

    const summary = useMemo(
        () => buatSummary(contestSendiri, partyNames.length),
        [buatSummary, contestSendiri, partyNames.length],
    );
    const summaryPru = useMemo(
        () => (serentak ? buatSummary('parlimen', partyNamesPru.length) : null),
        [buatSummary, serentak, partyNamesPru.length],
    );

    // Slot dihantar RATA (1..n seperti diekstrak). Server yang menomborkan
    // semula setiap pertandingan kepada 1..n semasa memisahkan senarai —
    // menomborkan semula di sini bermakna dua pelaksanaan peraturan yang sama,
    // dan hanyut di antaranya memindahkan undi kepada calon yang salah.
    const persistParties = useCallback((next, skopHantar) => {
        if (!kawasanType || !kawasanId || !jenisPr || !tahun || !penjuru) return Promise.resolve(null);
        setRalatParti('');

        return axios.post(route('pilihanraya.borang-14.parties'), {
            kawasan_type: kawasanType, kawasan_id: kawasanId, jenis_pr: jenisPr, tahun: Number(tahun),
            penjuru: Number(penjuru),
            skop: skopHantar,
            // `kontes` dihantar HANYA dalam skop kedua-duanya — di situ ia
            // wajib pada setiap calon. Dalam skop tunggal server mengabaikannya
            // sepenuhnya, jadi ia digugurkan supaya muatan kekal serupa-bit
            // dengan sebelum ciri ini.
            parties: next.map((p) => {
                if (skopHantar === SKOP_KEDUA) return p;
                const salinan = { ...p };
                delete salinan.kontes;

                return salinan;
            }),
        }).catch((e) => {
            // Mesej server dipapar APA ADANYA — ia menamakan masalah sebenar.
            setRalatParti(ralatServer(e, 'Gagal menyimpan pemetaan parti.'));
            throw e;
        });
    }, [kawasanType, kawasanId, jenisPr, tahun, penjuru]);

    // Pengguna memilih skop yang BERBEZA daripada apa yang tersimpan → satu
    // penstrukturan semula sedang menunggu. Ia tidak pernah disimpan secara
    // automatik semasa menaip: pemisahan menomborkan semula slot calon dan
    // mencipta borang takrifan Parlimen, jadi ia mesti satu tindakan yang
    // disengajakan, bukan kesan sampingan memilih parti.
    const menungguPemisahan = skop !== skopSemasa;

    // Pemilih pertandingan hanya berguna semasa senarai RATA masih utuh.
    // Sesudah dipisahkan, calon Parlimen tinggal pada borang takrifan dan
    // baris di skrin ini ialah calon DUN semata-mata — menawarkan pemilih di
    // situ hanya membawa pengguna ke jalan mati (0 calon Parlimen → 422).
    const pilihKontes = skop === SKOP_KEDUA && menungguPemisahan;

    // Preserves extra fields (calon) so the scoresheet's candidate name keeps
    // showing under the dropdown after a party is picked.
    const onPickParty = (index, partiId) => {
        const parti = partiList.find((p) => String(p.id) === String(partiId));
        const next = parties.map((p, i) => (i === index
            ? { ...p, slot: i + 1, keahlian_parti_id: parti ? parti.id : '', nama: parti ? parti.nama : (p.calon ?? '') }
            : p));
        setParties(next);
        // Autosimpan sentiasa menghantar skop TUNGGAL borang ini sendiri —
        // muatan yang serupa-bit dengan klien sebelum ciri ini (yang tidak
        // menghantar `skop` langsung, lalu server melalaikannya kepada nilai
        // yang sama). Menghantar `skop` pilihan pengguna di sini akan menukar
        // pemetaan parti biasa menjadi penstrukturan semula.
        if (!menungguPemisahan) persistParties(next, contestSendiri).catch(() => {}); // ralat sudah dipapar
    };

    // Tanda pertandingan bagi satu calon. Semata-mata keadaan tempatan —
    // tiada apa dihantar sehingga pengguna menekan Simpan Skop.
    const onPickKontes = (index, kontes) => {
        setParties((prev) => prev.map((p, i) => (i === index ? { ...p, slot: i + 1, kontes: kontes || undefined } : p)));
    };

    // Kiraan calon setiap pertandingan mengikut tanda pengguna SENDIRI —
    // bukan tekaan, dan bukan angka yang datang dari mana-mana model.
    const kiraKontes = useMemo(() => ({
        parlimen: parties.filter((p) => p.kontes === 'parlimen').length,
        dun: parties.filter((p) => p.kontes === 'dun').length,
        belum: parties.filter((p) => !p.kontes).length,
    }), [parties]);

    const simpanSkop = async () => {
        setMenyimpanSkop(true);
        try {
            // null = permintaan langsung tidak dihantar kerana medan tidak
            // lengkap. Jangan buang senarai calon pengguna dalam kes itu.
            if (!await persistParties(parties, skop)) return;
            // Pemisahan menulis semula `penjuru` KEDUA-DUA borang: borang DUN
            // mendapat kiraan DUN, borang takrifan Parlimen mendapat kiraan
            // Parlimen. Buang senarai rata tempatan DAHULU supaya muatan semula
            // tidak boleh mengekalkan baris/penjuru lama, kemudian baca semula
            // kedua-duanya terus daripada server.
            setParties([]);
            setPenjuru('');
            setReloadNonce((n) => n + 1);
        } catch {
            // Mesej sebenar sudah dipapar oleh persistParties.
        } finally {
            setMenyimpanSkop(false);
        }
    };

    // `contest` WAJIB pada setiap POST undi (server 422 tanpanya) supaya undi
    // tidak pernah senyap-senyap ditulis ke pertandingan yang salah pada borang
    // serentak. Ia melalaikan kepada pertandingan borang ini sendiri — satu-satunya
    // pertandingan yang boleh dipapar buat masa ini; pemanggil dua jalur kelak
    // menghantarnya secara eksplisit.
    const saveVote = useCallback((pusat, saluran, slot, undi, contest = contestSendiri) => {
        const key = cellKey(contest, pusat, saluran, slot);
        // Claim this request as the latest for the cell *before* the await —
        // a later re-edit of the same cell will bump this again, and only
        // the resolution whose seq still matches the ref may write status.
        const mySeq = (requestSeq.current[key] || 0) + 1;
        requestSeq.current[key] = mySeq;
        setVotes((prev) => ({ ...prev, [key]: undi }));
        setCellStatus((prev) => ({ ...prev, [key]: 'saving' }));
        axios.post(route('pilihanraya.borang-14.vote'), {
            kawasan_type: kawasanType, kawasan_id: kawasanId, jenis_pr: jenisPr, tahun: Number(tahun),
            penjuru: Number(penjuru), contest, pusat, saluran, slot, undi,
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
    }, [kawasanType, kawasanId, jenisPr, tahun, penjuru, contestSendiri]);

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

    // Borang DIKUNCI — dibekukan sepenuhnya. Ini keadaan SERVER (form.locked),
    // bukan kebenaran: setiap laluan tulis menolaknya dengan 422, jadi
    // mengelabukan skrin di sini semata-mata menjimatkan pengendali daripada
    // menaip angka yang pasti ditolak.
    const dikunci = Boolean(form?.locked);

    const tukarKunci = async (mahuKunci) => {
        if (!form?.id) return;
        setKunciBusy(true);
        setRalatKunci('');
        try {
            await axios.post(
                route(mahuKunci ? 'pilihanraya.borang-14.kunci' : 'pilihanraya.borang-14.buka-kunci'),
                { form_id: form.id },
            );
            // Muat semula PENUH dan bukan sekadar setForm(): kunci turut
            // menentukan boleh_sunting_struktur dan boleh_kunci, yang dikira
            // di server. Menekanya di client bermakna dua pelaksanaan.
            setReloadNonce((n) => n + 1);
        } catch (e) {
            setRalatKunci(ralatServer(e, mahuKunci ? 'Gagal mengunci Borang 14.' : 'Gagal membuka kunci Borang 14.'));
        } finally {
            setKunciBusy(false);
        }
    };

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

    const canShowTables = geographyComplete && hasData && penjuru && blocks.length > 0 && !suntingStruktur;

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
                        {/* Skop hanya boleh dipilih pada borang DUN: pautan
                            wujud dari borang DUN ke borang Parlimen sahaja,
                            tidak pernah sebaliknya, kerana borang Parlimen ITU
                            SENDIRI ialah takrifan calon PRU. Pada borang
                            Parlimen kawalan ini digugurkan sepenuhnya supaya
                            skrin kekal sama seperti hari ini. */}
                        {kawasanType === 'dun' && (
                            <div>
                                <label className={t.label}>Skop Borang 14</label>
                                <select value={skop} onChange={(e) => setSkop(e.target.value)} disabled={dikunci} className={t.input}>
                                    <option value="dun">DUN sahaja (PRN)</option>
                                    <option value="parlimen">Parlimen sahaja (PRU)</option>
                                    <option value={SKOP_KEDUA}>Kedua-duanya (serentak)</option>
                                </select>
                                <div className={`text-xs ${t.subtext} mt-0.5`}>
                                    {skopSemasa === SKOP_KEDUA
                                        ? 'Borang ini sudah dipisahkan — senarai di bawah ialah calon DUN sahaja; calon PRU disimpan pada borang takrifan Parlimen yang dipaut.'
                                        : 'Pilih “Kedua-duanya” jika scoresheet ini mengandungi DUA pertandingan bersebelahan (calon PRU dan calon PRN).'}
                                </div>
                            </div>
                        )}
                        <div>
                            <label className={t.label}>Bilangan Penjuru</label>
                            <select value={penjuru} onChange={(e) => setPenjuru(e.target.value)} disabled={dikunci} className={t.input}>
                                <option value="">Pilih Penjuru</option>
                                {penjuruOptions.map((o) => <option key={o.value} value={o.value}>{o.label}</option>)}
                            </select>
                            {pilihKontes && (
                                <div className={`text-xs ${t.subtext} mt-0.5`}>
                                    Jumlah calon KEDUA-DUA pertandingan seperti pada scoresheet.
                                </div>
                            )}
                        </div>
                        {parties.map((p, i) => (
                            <div
                                key={i}
                                className={pilihKontes ? `rounded-lg border px-2 py-2 ${warnaKontes(p.kontes)}` : undefined}
                            >
                                <label className={t.label}>Parti {i + 1}</label>
                                <select
                                    value={p.keahlian_parti_id || ''}
                                    onChange={(e) => onPickParty(i, e.target.value)}
                                    disabled={dikunci}
                                    className={t.input}
                                >
                                    <option value="">Pilih Parti</option>
                                    {partiList.map((pt) => <option key={pt.id} value={pt.id}>{pt.nama}</option>)}
                                </select>
                                {pilihKontes && (
                                    <select
                                        value={p.kontes || ''}
                                        onChange={(e) => onPickKontes(i, e.target.value)}
                                        disabled={dikunci}
                                        className={`${t.input} mt-1`}
                                    >
                                        <option value="">Pertandingan mana?</option>
                                        <option value="parlimen">{LABEL_KONTES.parlimen}</option>
                                        <option value="dun">{LABEL_KONTES.dun}</option>
                                    </select>
                                )}
                                {p.calon && (
                                    <div className={`text-xs ${t.subtext} mt-0.5`}>Calon: {p.calon}{!p.keahlian_parti_id && ' — belum dipetakan'}</div>
                                )}
                            </div>
                        ))}
                    </div>
                )}

                {/* Pemisahan pertandingan — tindakan yang DISENGAJAKAN. Ia
                    mencipta/mengemas kini borang takrifan Parlimen dan
                    menomborkan semula slot calon, jadi ia tidak pernah berlaku
                    sebagai kesan sampingan memilih parti seperti skop tunggal. */}
                {geographyComplete && hasData && menungguPemisahan && (
                    <div className="mt-3 pt-3 border-t border-dashed flex flex-wrap items-center justify-between gap-2">
                        <div className={`text-sm ${t.subtext}`}>
                            {pilihKontes && (
                                <>
                                    Tandakan setiap calon: <strong className="text-sky-700">{kiraKontes.parlimen}</strong> calon
                                    Parlimen (biru) · <strong className="text-rose-700">{kiraKontes.dun}</strong> calon DUN (merah)
                                    {kiraKontes.belum > 0 && <> · <strong>{kiraKontes.belum}</strong> belum ditanda</>}.
                                </>
                            )}
                            {skop !== SKOP_KEDUA && (
                                <>
                                    Borang ini akan merekod pertandingan <strong>{LABEL_KONTES[skop]}</strong> sahaja
                                    {' '}— {parties.length} calon.
                                </>
                            )}
                        </div>
                        <button
                            type="button"
                            onClick={simpanSkop}
                            disabled={dikunci || menyimpanSkop || parties.length === 0 || (pilihKontes && kiraKontes.belum > 0)}
                            title={pilihKontes && kiraKontes.belum > 0 ? 'Tandakan pertandingan bagi setiap calon dahulu' : undefined}
                            className={t.buttonPrimary}
                        >
                            {menyimpanSkop ? <Loader2 className="h-4 w-4 animate-spin" /> : <Save className="h-4 w-4" />} Simpan Skop &amp; Pertandingan
                        </button>
                    </div>
                )}

                {/* Borang DUN yang merekod pertandingan Parlimen SAHAJA: tiada
                    calon DUN wujud, jadi jadual undi DUN memang tidak boleh
                    dipaparkan. Dieja dengan jelas — skrin yang kosong tanpa
                    sebab akan disangka data hilang. */}
                {geographyComplete && hasData && skopSemasa === 'parlimen' && kawasanType === 'dun' && (
                    <div className={`${t.banner} flex items-start gap-2 mt-3`}>
                        <Info className="h-4 w-4 shrink-0 mt-0.5" />
                        <span>
                            Borang DUN ini merekod pertandingan <strong>Parlimen (PRU) sahaja</strong> — tiada calon
                            DUN ditetapkan, jadi tiada jadual undi DUN untuk dipaparkan. Tukar Skop kepada
                            &ldquo;Kedua-duanya&rdquo; dan tandakan calon DUN jika kerusi ini turut bertanding.
                        </span>
                    </div>
                )}

                {ralatParti && (
                    <div className="bg-rose-50 border border-rose-300 text-rose-800 rounded-lg px-4 py-3 text-sm mt-3">
                        {ralatParti}
                    </div>
                )}
            </div>

            {geographyComplete && hasData && bolehSuntingStruktur && !suntingStruktur && !loading && (
                <div className="flex justify-end mb-3">
                    <button type="button" onClick={() => setSuntingStruktur(true)} className={t.buttonSecondary}>
                        Sunting Struktur
                    </button>
                </div>
            )}

            {suntingStruktur && (
                <StrukturPanel
                    // Pertahanan lapis kedua: jika panel ini pernah dipaparkan
                    // merentasi pertukaran kerusi (sepatutnya sudah dielak oleh
                    // reset di atas), `key` yang berbeza memaksa React remount
                    // panel — bukan guna semula state `struktur` kerusi lama.
                    key={`${kawasanType}-${kawasanId}-${jenisPr}-${tahun}`}
                    picker={picker}
                    struktur={struktur}
                    // Kontes Parlimen sudah dimuat daripada data() — samakan
                    // keadaan awal togol dengannya supaya membuka panel dan
                    // menekan Simpan tanpa menyentuh togol TIDAK menyahpaut
                    // pertandingan yang sudah dipaut secara senyap.
                    initialSerentak={kontesParlimen != null}
                    parlimenNama={parlimenList.find((p) => String(p.id) === String(parlimenId))?.nama}
                    onCancel={() => setSuntingStruktur(false)}
                    onSaved={() => { setSuntingStruktur(false); setReloadNonce((n) => n + 1); }}
                />
            )}

            {/* Note when geography incomplete */}
            {!geographyComplete && (
                <div className={`${t.banner} flex items-center gap-2`}>
                    <Info className="h-4 w-4 shrink-0" />
                    <span>Pilih Negeri, Parlimen (atau DUN), Jenis PR dan Tahun untuk mula isi.</span>
                </div>
            )}

            {/* No reference data for chosen kawasan */}
            {geographyComplete && !hasData && !loading && !suntingStruktur && (
                <div className={`${t.banner} flex flex-wrap items-center gap-2`}>
                    <Info className="h-4 w-4 shrink-0" />
                    <span>
                        Tiada struktur Borang 14 untuk kawasan ini — tiada data DPT dan tiada
                        scoresheet tahun lepas untuk diwarisi.
                    </span>
                    {bolehSuntingStruktur && (
                        <button type="button" onClick={() => setSuntingStruktur(true)} className={t.buttonPrimary}>
                            Cipta Borang 14 kosong
                        </button>
                    )}
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
                            {dikunci && <span className={`${t.badge} bg-slate-200 text-slate-700`}>DIKUNCI</span>}
                            <button type="button" onClick={downloadPdf} className={t.buttonSecondary}>
                                <Download className="h-4 w-4" /> Muat Turun PDF
                            </button>
                            {/* Kunci ialah kawalan penyeliaan — butangnya hanya
                                wujud bagi Super Admin/Admin pemilik kerusi, dan
                                kelayakan itu dikira di server (boleh_kunci). */}
                            {bolehKunci && form?.id && (
                                <button
                                    type="button"
                                    onClick={() => tukarKunci(!dikunci)}
                                    disabled={kunciBusy}
                                    title={dikunci
                                        ? 'Buka kunci supaya borang ini boleh disunting semula'
                                        : 'Kunci borang ini supaya undi dan calon tidak boleh diubah lagi'}
                                    className={t.buttonSecondary}
                                >
                                    {kunciBusy
                                        ? <Loader2 className="h-4 w-4 animate-spin" />
                                        : (dikunci ? <LockOpen className="h-4 w-4" /> : <Lock className="h-4 w-4" />)}
                                    {dikunci ? 'Buka Kunci' : 'Kunci'}
                                </button>
                            )}
                            <button
                                type="button"
                                onClick={publish}
                                disabled={dikunci || !form?.id || !allPartiesMapped || anySaving || publishing || form?.status === 'published'}
                                title={dikunci ? 'Borang ini dikunci — buka kunci dahulu' : !allPartiesMapped ? 'Petakan setiap calon kepada parti dahulu' : anySaving ? 'Tunggu autosave selesai / betulkan sel merah' : undefined}
                                className={t.buttonPrimary}
                            >
                                {publishing ? <Loader2 className="h-4 w-4 animate-spin" /> : <Save className="h-4 w-4" />} Save &amp; Terbit
                            </button>
                        </div>
                    </div>

                    {/* Sebab skrin kelabu mesti DIEJA. Grid yang senyap-senyap
                        menolak taipan akan disangka pepijat, dan pengendali
                        akan menaip semula angka yang sama berulang kali pada
                        malam mengira undi. */}
                    {dikunci && (
                        <div className="bg-slate-100 border border-slate-300 text-slate-700 rounded-lg px-4 py-3 text-sm mb-4 flex items-start gap-2">
                            <Lock className="h-4 w-4 shrink-0 mt-0.5" />
                            <span>
                                Borang 14 ini <strong>DIKUNCI</strong>
                                {form?.locked_at && <> pada {new Date(form.locked_at).toLocaleString('ms-MY')}</>}
                                {form?.locked_by_nama && <> oleh <strong>{form.locked_by_nama}</strong></>}
                                {' '}— undi, nama calon dan struktur saluran tidak boleh diubah lagi.
                                {bolehKunci
                                    ? ' Tekan “Buka Kunci” di atas jika pindaan benar-benar perlu.'
                                    : ' Hubungi Super Admin atau Admin jika pindaan benar-benar perlu.'}
                            </span>
                        </div>
                    )}

                    {ralatKunci && (
                        <div className="bg-rose-50 border border-rose-300 text-rose-800 rounded-lg px-4 py-3 text-sm mb-4">
                            {ralatKunci}
                        </div>
                    )}

                    {/*
                        ASAL STRUKTUR mesti SENTIASA dinyatakan. Rujukan terbitan
                        DPT datang dalam DUA rasa dan pengendali perlu tahu yang
                        mana satu sedang dipandang — struktur sebenar yang senyap
                        lebih buruk daripada amaran yang sentiasa ada, kerana
                        ketiadaan amaran jadi tidak bermakna.

                        Ujian `source` di sini mesti SAMA PERSIS dengan
                        resources/views/pdf/borang14.blade.php (~baris 100), yang
                        mencetak dua baris yang sama pada PDF. Jika satu pihak
                        berubah tanpa satu lagi, skrin dan cetakan akan
                        menceritakan asal yang BERBEZA bagi borang yang sama.
                    */}
                    {reference.source === 'dpt_estimate' && (
                        <div className={`${t.banner} flex items-center gap-2 mb-4`}>
                            <Info className="h-4 w-4 shrink-0" />
                            <span>Pusat Mengundi &amp; Berdaftar dianggarkan daripada data DPT yang dimuat naik (dikumpul ikut Lokaliti, satu Saluran setiap Pusat Mengundi) — bukan pecahan Saluran rasmi gazet SPR.</span>
                        </div>
                    )}

                    {reference.source === 'dpt_sebenar' && (
                        <div className={`${t.bannerOk} flex items-center gap-2 mb-4`}>
                            <Info className="h-4 w-4 shrink-0" />
                            <span>Struktur Daerah Mengundi, Pusat Mengundi &amp; Saluran diambil terus daripada fail DPPR/DPI yang dimuat naik — pecahan Saluran dan jumlah Berdaftar ini adalah angka sebenar, bukan anggaran.</span>
                        </div>
                    )}

                    {reference.source === 'scoresheet' && !inheritedFrom && (
                        <div className={`${t.banner} flex items-center gap-2 mb-4`}>
                            <Info className="h-4 w-4 shrink-0" />
                            <span>Struktur Pusat Mengundi &amp; Saluran diambil terus daripada scoresheet yang dimuat naik — kawasan ini belum ada rujukan SPR rasmi/data DPT, jadi Berdaftar tidak diketahui.</span>
                        </div>
                    )}

                    {inheritedFrom && (
                        <div className={`${t.banner} flex items-center gap-2 mb-4`}>
                            <Info className="h-4 w-4 shrink-0" />
                            <span>
                                Struktur Pusat Mengundi &amp; Saluran diwarisi daripada {JENIS_LABEL[inheritedFrom.jenis_pr] ?? inheritedFrom.jenis_pr} {inheritedFrom.tahun}
                                {' '}kerana kawasan ini belum ada scoresheet/rujukan SPR untuk pilihan raya semasa. Sahkan Pusat Mengundi masih sama sebelum isi undi — Berdaftar tidak diketahui.
                            </span>
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
                            {/* Tajuk umum: pada borang serentak senarai ini turut
                                membawa isu pariti antara jalur PRN dan PRU, bukan
                                hanya baki (A) satu jalur. Setiap mesej mengeja
                                sendiri jalur dan peraturan yang gagal. */}
                            <div className="font-semibold mb-1">Silang-semak tidak seimbang pada baris berikut:</div>
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

                    {serentak && (
                        <div className={`${t.banner} flex items-start gap-2 mb-4`}>
                            <Info className="h-4 w-4 shrink-0 mt-0.5" />
                            <span>
                                Pilihan raya serentak — setiap saluran mempunyai DUA kertas undi.
                                Jalur merah <strong>{bands[0].tajuk}</strong> dan jalur biru{' '}
                                <strong>{bands[1].tajuk}</strong> disimpan berasingan; angka satu jalur
                                tidak pernah menyentuh jalur yang satu lagi. Calon jalur PRU ditetapkan
                                pada borang Parlimen yang dipaut, bukan di skrin ini.
                            </span>
                        </div>
                    )}

                    {/* Satu ringkasan bagi setiap kertas undi. Pada borang serentak
                        setiap satu dinamakan — ringkasan tanpa nama di bawah grid
                        dua jalur akan disangka meliputi kedua-dua pertandingan. */}
                    <GrandSummary
                        partyNames={partyNames}
                        totals={summary}
                        tajuk={serentak ? `Ringkasan ${bands[0].tajuk}` : undefined}
                    />
                    {serentak && (
                        <GrandSummary
                            partyNames={partyNamesPru}
                            totals={summaryPru}
                            tajuk={`Ringkasan ${bands[1].tajuk}`}
                        />
                    )}

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
                        {blocks.map((b, i) => (serentak ? (
                            <VoteTableSerentak
                                key={`${b.dm}-${b.pusat}-${i}`}
                                block={b}
                                bands={bands}
                                votes={votes}
                                onSave={saveVote}
                                anchorId={pusatAnchors[i]?.anchorId}
                                cellStatus={cellStatus}
                                readOnly={dikunci}
                            />
                        ) : (
                            <VoteTable
                                key={`${b.dm}-${b.pusat}-${i}`}
                                block={b}
                                partyNames={partyNames}
                                votes={votes}
                                onSave={saveVote}
                                anchorId={pusatAnchors[i]?.anchorId}
                                contest={contestSendiri}
                                cellStatus={cellStatus}
                                readOnly={dikunci}
                            />
                        )))}
                    </div>

                    {undiAwalPosRows.length > 0 && (
                        <div className="mt-4">
                            {serentak ? (
                                <UndiAwalPosTableSerentak
                                    bands={bands}
                                    votes={votes}
                                    onSave={saveVote}
                                    rows={undiAwalPosRows}
                                    cellStatus={cellStatus}
                                    readOnly={dikunci}
                                />
                            ) : (
                                <UndiAwalPosTable
                                    partyNames={partyNames}
                                    votes={votes}
                                    onSave={saveVote}
                                    rows={undiAwalPosRows}
                                    contest={contestSendiri}
                                    cellStatus={cellStatus}
                                    readOnly={dikunci}
                                />
                            )}
                        </div>
                    )}
                </>
            )}
        </>
    );
}
