import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm } from '@inertiajs/react';
import { ArrowLeft, Upload, X, Loader2, Image as ImageIcon } from 'lucide-react';
import { useState, useEffect, useRef } from 'react';
import axios from 'axios';

import SearchableSelect from '@/Components/SearchableSelect';

const formatCurrency = (value) => {
    if (!value && value !== 0) return '';
    const str = value.toString().replace(/,/g, '');
    if (str === '' || str === '.') return str;
    const parts = str.split('.');
    parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    return parts.length > 1 ? parts[0] + '.' + parts[1] : parts[0];
};

export default function Create({
    bangsaList,
    negeriList,
    bandarList,
    parlimenList,
    kadunList,
    daerahMengundiList,
    jenisSumbanganList,
    tujuanSumbanganList,
    bantuanLainList,
    keahlianPartiList,
    kecenderunganPolitikList,
    lokalitiList,
    initialVoter = null,
    initialSourceId = null,
}) {
    const [uploadProgress, setUploadProgress] = useState(0);
    const [isUploading, setIsUploading] = useState(false);
    const [previewUrl, setPreviewUrl] = useState(null);
    const [kadunOptions, setKadunOptions] = useState([]);
    const [loadingKadun, setLoadingKadun] = useState(false);
    const [daerahMengundiOptions, setDaerahMengundiOptions] = useState([]);
    const [loadingDaerahMengundi, setLoadingDaerahMengundi] = useState(false);
    const [mpkkOptions, setMpkkOptions] = useState([]);
    const [loadingMpkk, setLoadingMpkk] = useState(false);
    const [lokalitiOptions, setLokalitiOptions] = useState([]);
    const [loadingLokaliti, setLoadingLokaliti] = useState(false);
    const [icSuggestions, setIcSuggestions] = useState([]);
    const [showSuggestions, setShowSuggestions] = useState(false);
    const [bantuanHistory, setBantuanHistory] = useState([]);
    const [bantuanHistoryLoaded, setBantuanHistoryLoaded] = useState(false);
    const icDebounceRef = useRef(null);
    const icWrapperRef = useRef(null);
    const pendingVoterData = useRef(null);
    const sumbanganCardRef = useRef(null);
    const sumbanganAnchorTop = useRef(null);

    const initialIc = typeof window !== 'undefined'
        ? new URLSearchParams(window.location.search).get('ic') || ''
        : '';

    // When arriving from the DataPengundi edit page via the Sumbangan
    // shortcut card, initialVoter / initialSourceId are passed from the
    // controller and seed the form state on first render.
    const { data, setData, post, processing, errors } = useForm({
        nama: initialVoter?.nama || '',
        no_ic: initialVoter?.no_ic || initialIc,
        umur: initialVoter?.umur != null && initialVoter?.umur !== '' ? String(initialVoter.umur) : '',
        no_tel: initialVoter?.no_tel || '',
        bangsa: initialVoter?.bangsa || '',
        alamat: initialVoter?.alamat || '',
        poskod: initialVoter?.poskod || '',
        negeri: initialVoter?.negeri || '',
        bandar: initialVoter?.bandar || '',
        parlimen: initialVoter?.parlimen || '',
        kadun: initialVoter?.kadun || '',
        mpkk: initialVoter?.mpkk || '',
        daerah_mengundi: initialVoter?.daerah_mengundi || '',
        lokaliti: initialVoter?.lokaliti || '',
        bil_isi_rumah: '',
        pendapatan_isi_rumah: '',
        pekerjaan: '',
        jenis_pekerjaan: '',
        jenis_pekerjaan_lain: '',
        pemilik_rumah: '',
        pemilik_rumah_lain: '',
        jenis_sumbangan: [],
        jenis_sumbangan_lain: '',
        tujuan_sumbangan: [],
        tujuan_sumbangan_lain: '',
        bantuan_lain: [],
        bantuan_lain_lain: '',
        perkeso_bantuan: [],
        perkeso_bantuan_lain: '',
        zpp_jenis_bantuan: [],
        isejahtera_program: '',
        bkb_program: '',
        jkm_program: '',
        jumlah_bantuan_tunai: '',
        jumlah_wang_tunai: '',
        keahlian_parti: initialVoter?.keahlian_parti || '',
        kecenderungan_politik: initialVoter?.kecenderungan_politik || '',
        status_pengundi: initialVoter?.status_pengundi || '',
        kad_pengenalan: null,
        nota: '',
        is_deceased: false,
        has_sumbangan: !!initialSourceId,
        update_status_pengundi: false,
        locked_source_id: initialSourceId || '',
    });

    const sensitiveLocked = !!data.locked_source_id;
    const MASK = '****';
    // Pendapatan Isi Rumah is only protected if the voter actually has a
    // prior bantuan record with a stored pendapatan. For a first-time
    // sumbangan — even when the voter row is otherwise locked by a
    // user-role submitter — the current user may fill this in. While the
    // history fetch is in flight we keep it locked to avoid flashing a
    // writable field that might get overwritten.
    const pendapatanIsLocked = sensitiveLocked && (!bantuanHistoryLoaded || bantuanHistory.length > 0);

    const clearSumbanganFields = () => {
        setData(prev => ({
            ...prev,
            bil_isi_rumah: '',
            pendapatan_isi_rumah: '',
            pekerjaan: '',
            jenis_pekerjaan: [],
            jenis_pekerjaan_lain: '',
            pemilik_rumah: '',
            pemilik_rumah_lain: '',
            jenis_sumbangan: [],
            jenis_sumbangan_lain: '',
            tujuan_sumbangan: [],
            tujuan_sumbangan_lain: '',
            bantuan_lain: [],
            bantuan_lain_lain: '',
            perkeso_bantuan: [],
            perkeso_bantuan_lain: '',
            zpp_jenis_bantuan: [],
            isejahtera_program: '',
            jkm_program: '',
            jumlah_wang_tunai: '',
        }));
    };

    const handleSumbanganToggle = (checked) => {
        // Pin the Sumbangan card in the viewport across the re-render.
        // Toggling injects/removes large flex items whose DOM order does not
        // match their visual order (the Isi Rumah / Bantuan sections live
        // above the toggle in JSX but below it visually via order-*). That
        // combo causes the browser's scroll anchor to jump. We record the
        // card's viewport-top before the state change and, after the commit,
        // re-adjust window scroll by the delta so the card stays put.
        if (sumbanganCardRef.current) {
            sumbanganAnchorTop.current = sumbanganCardRef.current.getBoundingClientRect().top;
        }
        setData('has_sumbangan', checked);
        if (!checked) {
            clearSumbanganFields();
        }
    };

    useEffect(() => {
        if (sumbanganAnchorTop.current == null || !sumbanganCardRef.current) return;
        const newTop = sumbanganCardRef.current.getBoundingClientRect().top;
        const delta = newTop - sumbanganAnchorTop.current;
        if (Math.abs(delta) > 0.5) {
            window.scrollBy(0, delta);
        }
        sumbanganAnchorTop.current = null;
    }, [data.has_sumbangan]);

    const handleStatusPengundiToggle = (checked) => {
        setData(prev => ({
            ...prev,
            update_status_pengundi: checked,
            status_pengundi: checked ? prev.status_pengundi : '',
        }));
    };

    // Fetch KADUN and Daerah Mengundi when Parlimen changes
    useEffect(() => {
        const fetchKadun = async () => {
            if (!data.parlimen) {
                setKadunOptions([]);
                setDaerahMengundiOptions([]);
                return;
            }

            setLoadingKadun(true);
            setLoadingDaerahMengundi(true);
            try {
                const [kadunRes, dmRes] = await Promise.all([
                    axios.get(route('api.kadun.by-bandar'), { params: { bandar: data.parlimen } }),
                    axios.get(route('api.daerah-mengundi.by-bandar'), { params: { bandar: data.parlimen } }),
                ]);
                setKadunOptions(kadunRes.data);
                setDaerahMengundiOptions(dmRes.data);

                // Apply pending voter data if available
                if (pendingVoterData.current) {
                    const pending = pendingVoterData.current;
                    const updates = {};
                    if (pending.kadun) {
                        const match = kadunRes.data.find(k => k.nama.toLowerCase() === pending.kadun.toLowerCase());
                        if (match) updates.kadun = match.nama;
                    }
                    if (pending.daerah_mengundi) {
                        const match = dmRes.data.find(d => d.nama.toLowerCase() === pending.daerah_mengundi.toLowerCase());
                        if (match) updates.daerah_mengundi = match.nama;
                    }
                    if (Object.keys(updates).length > 0) {
                        setData(prev => ({ ...prev, ...updates }));
                    }
                }
            } catch (error) {
                console.error('Error fetching KADUN/DM:', error);
                setKadunOptions([]);
                setDaerahMengundiOptions([]);
            } finally {
                setLoadingKadun(false);
                setLoadingDaerahMengundi(false);
            }
        };

        fetchKadun();
    }, [data.parlimen]);

    // Fetch MPKK options when KADUN changes
    useEffect(() => {
        const fetchMpkk = async () => {
            if (!data.kadun) {
                setMpkkOptions([]);
                return;
            }

            setLoadingMpkk(true);
            try {
                const response = await axios.get(route('api.mpkk.by-kadun'), {
                    params: { kadun: data.kadun }
                });
                setMpkkOptions(response.data);
            } catch (error) {
                console.error('Error fetching MPKK:', error);
                setMpkkOptions([]);
            } finally {
                setLoadingMpkk(false);
            }
        };

        fetchMpkk();
    }, [data.kadun]);

    // Fetch Lokaliti options when Daerah Mengundi changes
    useEffect(() => {
        const fetchLokaliti = async () => {
            if (!data.daerah_mengundi) {
                setLokalitiOptions([]);
                return;
            }

            setLoadingLokaliti(true);
            try {
                const response = await axios.get(route('api.lokaliti.by-daerah-mengundi'), {
                    params: { daerah_mengundi: data.daerah_mengundi }
                });
                setLokalitiOptions(response.data);

                // Apply pending voter lokaliti if available
                if (pendingVoterData.current?.lokaliti) {
                    const match = response.data.find(l => l.nama.toLowerCase() === pendingVoterData.current.lokaliti.toLowerCase());
                    if (match) {
                        setData(prev => ({ ...prev, lokaliti: match.nama }));
                    }
                    pendingVoterData.current = null;
                }
            } catch (error) {
                console.error('Error fetching Lokaliti:', error);
                setLokalitiOptions([]);
            } finally {
                setLoadingLokaliti(false);
            }
        };

        fetchLokaliti();
    }, [data.daerah_mengundi]);

    const handleFileUpload = (e) => {
        const file = e.target.files[0];
        if (!file) return;

        // Validate file type
        if (!file.type.startsWith('image/')) {
            alert('Sila pilih fail imej sahaja');
            return;
        }

        // Validate file size (max 5MB)
        if (file.size > 5 * 1024 * 1024) {
            alert('Saiz fail terlalu besar. Maksimum 5MB');
            return;
        }

        // Create preview
        const reader = new FileReader();
        reader.onloadend = () => {
            setPreviewUrl(reader.result);
        };
        reader.readAsDataURL(file);

        // Simulate upload progress
        setIsUploading(true);
        setUploadProgress(0);

        const interval = setInterval(() => {
            setUploadProgress(prev => {
                if (prev >= 100) {
                    clearInterval(interval);
                    setIsUploading(false);
                    setData('kad_pengenalan', file);
                    return 100;
                }
                return prev + 10;
            });
        }, 100);
    };

    const handleRemoveFile = () => {
        setData('kad_pengenalan', null);
        setPreviewUrl(null);
        setUploadProgress(0);
    };

    const calculateAgeFromIc = (ic) => {
        if (!ic || ic.length < 6) return '';
        const year = ic.substring(0, 2);
        const month = ic.substring(2, 4);
        const day = ic.substring(4, 6);
        const fullYear = parseInt(year) <= 25 ? 2000 + parseInt(year) : 1900 + parseInt(year);
        const birthDate = new Date(fullYear, parseInt(month) - 1, parseInt(day));
        const today = new Date();
        let age = today.getFullYear() - birthDate.getFullYear();
        const monthDiff = today.getMonth() - birthDate.getMonth();
        if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthDate.getDate())) {
            age--;
        }
        return age >= 0 && age <= 150 ? age.toString() : '';
    };

    const handleIcChange = (e) => {
        const value = e.target.value;

        // Only allow digits
        const digitsOnly = value.replace(/\D/g, '');

        // Limit to 12 digits
        if (digitsOnly.length > 12) return;

        // If the form was locked to a protected source, editing the IC
        // means the user is moving on — wipe the locked state and all
        // masked sensitive fields so they can start fresh.
        if (data.locked_source_id) {
            setData(prev => ({
                ...prev,
                no_ic: digitsOnly,
                locked_source_id: '',
                umur: calculateAgeFromIc(digitsOnly),
                no_tel: '',
                bangsa: '',
                alamat: '',
                poskod: '',
                negeri: '',
                bandar: '',
                pendapatan_isi_rumah: '',
                nota: '',
            }));
        } else {
            setData('no_ic', digitsOnly);
            setData('umur', calculateAgeFromIc(digitsOnly));
        }

        if (icDebounceRef.current) clearTimeout(icDebounceRef.current);
        if (digitsOnly.length >= 3) {
            icDebounceRef.current = setTimeout(() => {
                axios.get(route('api.voter.suggest-ic'), { params: { ic: digitsOnly } })
                    .then(res => {
                        setIcSuggestions(res.data || []);
                        setShowSuggestions((res.data || []).length > 0);
                    })
                    .catch(() => setIcSuggestions([]));
            }, 300);
        } else {
            setIcSuggestions([]);
            setShowSuggestions(false);
        }
    };

    useEffect(() => {
        if (initialIc && initialIc.length >= 6) {
            setData('umur', calculateAgeFromIc(initialIc));
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    const handleTelChange = (e) => {
        const value = e.target.value;
        // Only allow digits
        const digitsOnly = value.replace(/\D/g, '');
        setData('no_tel', digitsOnly);
    };

    const handleTextChange = (field, value) => {
        setData(field, value.toUpperCase());
    };

    useEffect(() => {
        const fetchPostcodeDetails = async () => {
            if (data.poskod.length === 5) {
                try {
                    const response = await axios.get(route('api.postcodes.search-details'), {
                        params: { query: data.poskod }
                    });

                    if (response.data && response.data.length > 0) {
                        const postcodeData = response.data[0];
                        setData({
                            ...data,
                            negeri: postcodeData.negeri_nama || '',
                            bandar: postcodeData.city || '',
                        });
                    }
                } catch (error) {
                    console.error('Error fetching postcode details:', error);
                }
            }
        };

        fetchPostcodeDetails();
    }, [data.poskod]);

    useEffect(() => {
        const handleClickOutside = (e) => {
            if (icWrapperRef.current && !icWrapperRef.current.contains(e.target)) {
                setShowSuggestions(false);
            }
        };
        document.addEventListener('mousedown', handleClickOutside);
        return () => document.removeEventListener('mousedown', handleClickOutside);
    }, []);

    const toTitleCase = (str) => str
        ? str.toLowerCase().replace(/\b\w/g, c => c.toUpperCase())
        : '';

    const handleSuggestionClick = (voter) => {
        pendingVoterData.current = {
            parlimen: voter.parlimen || null,
            kadun: voter.kadun || null,
            daerah_mengundi: voter.daerah_mengundi || null,
            lokaliti: voter.lokaliti || null,
        };
        const parlimenMatch = parlimenList.find(p => p.nama.toLowerCase() === (voter.parlimen || '').toLowerCase());

        if (voter.source === 'data_pengundi' && voter.is_locked) {
            // Locked record — populate non-sensitive fields normally and
            // fill sensitive fields with a '****' mask so the user can
            // see that saved data exists but can't read it. The server
            // swaps the masks back to real values at submit time via
            // locked_source_id. Never write voter.no_ic here — the
            // suggestion API already masks it, so writing it would corrupt
            // the form state to '****'.
            setData({
                ...data,
                nama: voter.nama || data.nama,
                umur: MASK,
                no_tel: MASK,
                bangsa: MASK,
                alamat: MASK,
                poskod: MASK,
                negeri: MASK,
                bandar: MASK,
                pendapatan_isi_rumah: MASK,
                nota: MASK,
                parlimen: parlimenMatch ? parlimenMatch.nama : data.parlimen,
                mpkk: voter.mpkk || data.mpkk,
                keahlian_parti: voter.keahlian_parti || data.keahlian_parti,
                kecenderungan_politik: voter.kecenderungan_politik || data.kecenderungan_politik,
                status_pengundi: voter.status_pengundi || data.status_pengundi,
                locked_source_id: voter.id || '',
            });
        } else if (voter.source === 'data_pengundi') {
            // Fully populate form with previously saved record
            setData({
                ...data,
                no_ic: voter.no_ic,
                nama: voter.nama || '',
                umur: voter.umur != null ? voter.umur.toString() : '',
                no_tel: voter.no_tel || '',
                bangsa: voter.bangsa || '',
                alamat: voter.alamat || '',
                poskod: voter.poskod || '',
                negeri: voter.negeri || '',
                bandar: voter.bandar || '',
                parlimen: parlimenMatch ? parlimenMatch.nama : (voter.parlimen || ''),
                mpkk: voter.mpkk || '',
                keahlian_parti: voter.keahlian_parti || '',
                kecenderungan_politik: voter.kecenderungan_politik || '',
                status_pengundi: voter.status_pengundi || '',
            });
        } else {
            // DPPR record: auto-fill basic fields only
            setData({
                ...data,
                no_ic: voter.no_ic,
                nama: voter.nama || data.nama,
                parlimen: parlimenMatch ? parlimenMatch.nama : data.parlimen,
                negeri: voter.negeri ? toTitleCase(voter.negeri) : data.negeri,
                bangsa: voter.bangsa || data.bangsa,
            });
        }
        setShowSuggestions(false);
        setIcSuggestions([]);
    };

    // Auto-lookup voter database when IC is 12 digits (exact match auto-fill)
    useEffect(() => {
        if (data.no_ic.length === 12) {
            axios.get(route('api.voter.search-ic'), { params: { ic: data.no_ic } })
                .then(res => {
                    if (res.data && !res.data.multiple) {
                        const voter = res.data;
                        pendingVoterData.current = {
                            parlimen: voter.parlimen || null,
                            kadun: voter.kadun || null,
                            daerah_mengundi: voter.daerah_mengundi || null,
                            lokaliti: voter.lokaliti || null,
                        };
                        const parlimenMatch = parlimenList.find(p => p.nama.toLowerCase() === (voter.parlimen || '').toLowerCase());
                        if (voter.is_locked && voter.id) {
                            // 12-digit IC matches a locked Data Pengundi record —
                            // populate masked sensitive fields so user sees there
                            // is protected data, set locked_source_id for submit.
                            setData(prev => ({
                                ...prev,
                                nama: voter.nama || prev.nama,
                                umur: MASK,
                                no_tel: MASK,
                                bangsa: MASK,
                                alamat: MASK,
                                poskod: MASK,
                                negeri: MASK,
                                bandar: MASK,
                                pendapatan_isi_rumah: MASK,
                                nota: MASK,
                                parlimen: parlimenMatch ? parlimenMatch.nama : prev.parlimen,
                                mpkk: voter.mpkk || prev.mpkk,
                                keahlian_parti: voter.keahlian_parti || prev.keahlian_parti,
                                kecenderungan_politik: voter.kecenderungan_politik || prev.kecenderungan_politik,
                                status_pengundi: voter.status_pengundi || prev.status_pengundi,
                                locked_source_id: voter.id,
                            }));
                        } else {
                            setData(prev => ({
                                ...prev,
                                nama: prev.nama || voter.nama || '',
                                bangsa: prev.bangsa || voter.bangsa || '',
                                negeri: voter.negeri ? toTitleCase(voter.negeri) : prev.negeri,
                                parlimen: parlimenMatch ? parlimenMatch.nama : prev.parlimen,
                            }));
                        }
                    }
                })
                .catch(() => {});

            // Check for existing bantuan records and auto-fill personal data
            setBantuanHistoryLoaded(false);
            axios.get(route('api.hasil-culaan.by-ic'), { params: { ic: data.no_ic } })
                .then(res => {
                    if (res.data && res.data.length > 0) {
                        setBantuanHistory(res.data);
                        const latest = res.data[0];
                        const locked = latest.is_locked;
                        setData(prev => ({
                            ...prev,
                            nama: prev.nama || latest.nama || '',
                            // Sensitive fields — skip autofill when the latest bantuan
                            // record is locked by the masker
                            bangsa: locked ? prev.bangsa : (prev.bangsa || latest.bangsa || ''),
                            no_tel: locked ? prev.no_tel : (prev.no_tel || latest.no_tel || ''),
                            alamat: locked ? prev.alamat : (prev.alamat || latest.alamat || ''),
                            poskod: locked ? prev.poskod : (prev.poskod || latest.poskod || ''),
                            negeri: locked ? prev.negeri : (prev.negeri || latest.negeri || ''),
                            bandar: locked ? prev.bandar : (prev.bandar || latest.bandar || ''),
                            pendapatan_isi_rumah: locked ? prev.pendapatan_isi_rumah : (prev.pendapatan_isi_rumah || latest.pendapatan_isi_rumah || ''),
                            // Non-sensitive — always autofill when empty
                            bil_isi_rumah: prev.bil_isi_rumah || latest.bil_isi_rumah || '',
                            pekerjaan: prev.pekerjaan || latest.pekerjaan || '',
                            pemilik_rumah: prev.pemilik_rumah || latest.pemilik_rumah || '',
                            keahlian_parti: prev.keahlian_parti || latest.keahlian_parti || '',
                            kecenderungan_politik: prev.kecenderungan_politik || latest.kecenderungan_politik || '',
                        }));
                    } else {
                        setBantuanHistory([]);
                    }
                })
                .catch(() => setBantuanHistory([]))
                .finally(() => setBantuanHistoryLoaded(true));
        } else {
            setBantuanHistory([]);
            setBantuanHistoryLoaded(false);
        }
    }, [data.no_ic]);

    // When a locked Data Pengundi suggestion is picked, the form's no_ic
    // stays masked ('****') so the length===12 effect above cannot fetch.
    // Fall back to the source_id path which resolves the real IC server
    // side without exposing it to the client.
    useEffect(() => {
        if (!data.locked_source_id) return;
        if (data.no_ic && data.no_ic.length === 12 && data.no_ic !== MASK) return;

        setBantuanHistoryLoaded(false);
        axios.get(route('api.hasil-culaan.by-ic'), { params: { source_id: data.locked_source_id } })
            .then(res => {
                if (res.data && res.data.length > 0) {
                    setBantuanHistory(res.data);
                } else {
                    setBantuanHistory([]);
                }
            })
            .catch(() => setBantuanHistory([]))
            .finally(() => setBantuanHistoryLoaded(true));
    }, [data.locked_source_id]);

    const handlePostcodeChange = (e) => {
        const value = e.target.value.replace(/\D/g, '');
        if (value.length <= 5) {
            setData('poskod', value);
        }
    };

    const handleCheckboxChange = (field, value) => {
        const currentValues = data[field] || [];
        const newValues = currentValues.includes(value)
            ? currentValues.filter(v => v !== value)
            : [...currentValues, value];
        setData(field, newValues);
    };

    const scrollToFirstError = () => {
        setTimeout(() => {
            const firstErrorMsg = document.querySelector('p.text-rose-600');
            if (!firstErrorMsg) return;
            const container = firstErrorMsg.closest('div');
            (container || firstErrorMsg).scrollIntoView({ behavior: 'smooth', block: 'center' });
            const input = container?.querySelector('input:not([disabled]):not([type="hidden"]), select:not([disabled]), textarea:not([disabled])');
            if (input) {
                setTimeout(() => input.focus({ preventScroll: true }), 350);
            }
        }, 50);
    };

    const handleSubmit = (e) => {
        e.preventDefault();
        post(route('reports.hasil-culaan.store'), {
            onError: () => scrollToFirstError(),
        });
    };

    return (
        <AuthenticatedLayout>
            <Head title="Borang Data Pengundi" />

            <div className="max-w-4xl mx-auto space-y-6">
                {/* Header */}
                <div className="flex items-center space-x-4">
                    <button
                        onClick={() => window.history.back()}
                        className="p-2 hover:bg-slate-100 rounded-lg transition-colors"
                    >
                        <ArrowLeft className="h-5 w-5 text-slate-600" />
                    </button>
                    <div>
                        <h1 className="text-2xl font-bold text-slate-900">Borang Data Pengundi</h1>
                        <p className="text-sm text-slate-600 mt-1">Isi semua maklumat yang diperlukan</p>
                    </div>
                </div>

                {/* Kematian Toggle */}
                <div className={`rounded-xl border p-4 flex items-center justify-between ${data.is_deceased ? 'border-rose-300 bg-rose-50' : 'border-slate-200 bg-[#FFCEE3]'}`}>
                    <div>
                        <span className={`text-sm font-medium ${data.is_deceased ? 'text-rose-700' : 'text-slate-700'}`}>
                            {data.is_deceased ? 'Ditandakan sebagai kematian — semua medan dikunci' : 'Tandakan sebagai kematian'}
                        </span>
                    </div>
                    <label className="flex items-center gap-2 cursor-pointer">
                        <span className={`text-sm font-medium ${data.is_deceased ? 'text-rose-600' : 'text-slate-500'}`}>Kematian</span>
                        <button
                            type="button"
                            onClick={() => setData('is_deceased', !data.is_deceased)}
                            className={`relative inline-flex h-6 w-11 items-center rounded-full transition-colors ${data.is_deceased ? 'bg-rose-500' : 'bg-slate-300'}`}
                        >
                            <span className={`inline-block h-4 w-4 rounded-full bg-white transition-transform ${data.is_deceased ? 'translate-x-6' : 'translate-x-1'}`} />
                        </button>
                    </label>
                </div>

                {/* Form */}
                <form onSubmit={handleSubmit} className={`flex flex-col space-y-6 ${data.is_deceased ? 'opacity-50 pointer-events-none select-none' : ''}`}>
                    {/* Validation Error Summary */}
                    {Object.keys(errors).length > 0 && (
                        <div className="order-first bg-rose-50 border border-rose-300 rounded-xl p-4">
                            <div className="flex items-start gap-3">
                                <svg className="w-5 h-5 flex-shrink-0 text-rose-600 mt-0.5" fill="none" viewBox="0 0 24 24" strokeWidth="2" stroke="currentColor">
                                    <path strokeLinecap="round" strokeLinejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" />
                                </svg>
                                <div className="flex-1">
                                    <p className="text-sm font-semibold text-rose-800">Sila lengkapkan medan yang diperlukan:</p>
                                    <ul className="mt-1 text-xs text-rose-700 list-disc list-inside space-y-0.5">
                                        {Object.entries(errors).slice(0, 8).map(([field, msg]) => (
                                            <li key={field}>{msg}</li>
                                        ))}
                                    </ul>
                                </div>
                            </div>
                        </div>
                    )}

                    {/* Personal Information */}
                    <div className="order-1 bg-white rounded-xl border border-slate-200 p-6">
                        <h2 className="text-lg font-semibold text-slate-900 mb-4">Maklumat Peribadi</h2>
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div ref={icWrapperRef} className="relative">
                                <label className="block text-sm font-medium text-slate-700 mb-1">
                                    No. IC <span className="text-rose-500">*</span>
                                </label>
                                <input
                                    type="text"
                                    value={data.no_ic}
                                    onChange={handleIcChange}
                                    placeholder="900101145678"
                                    maxLength="12"
                                    className="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 placeholder:text-slate-300"
                                    required
                                />
                                {errors.no_ic && <p className="text-sm text-rose-600 mt-1">{errors.no_ic}</p>}
                                <p className="text-xs text-slate-500 mt-1">Hanya angka sahaja (contoh: 900101145678)</p>
                                {showSuggestions && icSuggestions.length > 0 && (
                                    <div className="absolute z-10 w-full mt-1 bg-white border border-slate-200 rounded-lg shadow-lg max-h-64 overflow-y-auto">
                                        {icSuggestions.map((voter, idx) => (
                                            <button
                                                key={(voter.source || 'x') + '-' + (voter.id || voter.no_ic) + '-' + idx}
                                                type="button"
                                                onClick={() => handleSuggestionClick(voter)}
                                                className="w-full text-left px-4 py-2.5 hover:bg-slate-50 transition-colors border-b border-slate-100 last:border-0"
                                            >
                                                <div className="flex items-center gap-2 flex-wrap">
                                                    {voter.source === 'data_pengundi' ? (
                                                        <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-semibold bg-emerald-100 text-emerald-700 uppercase tracking-wide">
                                                            Data Pengundi
                                                        </span>
                                                    ) : (
                                                        <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-semibold bg-slate-100 text-slate-600 uppercase tracking-wide">
                                                            DPPR
                                                        </span>
                                                    )}
                                                    <span className="font-mono text-sm font-medium text-slate-900">{voter.no_ic}</span>
                                                    <span className="text-sm text-slate-500">{voter.nama}</span>
                                                    {voter.daerah_mengundi && <span className="text-xs text-slate-400">({voter.daerah_mengundi})</span>}
                                                </div>
                                            </button>
                                        ))}
                                    </div>
                                )}
                            </div>

                            <div>
                                <label className="block text-sm font-medium text-slate-700 mb-1">
                                    Nama <span className="text-rose-500">*</span>
                                </label>
                                <input
                                    type="text"
                                    value={data.nama}
                                    onChange={(e) => handleTextChange('nama', e.target.value)}
                                    className="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 uppercase"
                                    required
                                />
                                {errors.nama && <p className="text-sm text-rose-600 mt-1">{errors.nama}</p>}
                            </div>

                            <div>
                                <label className="block text-sm font-medium text-slate-700 mb-1">
                                    Umur <span className="text-rose-500">*</span>
                                    {sensitiveLocked && <span className="ml-1 text-xs text-slate-400">🔒 Dilindungi</span>}
                                </label>
                                <input
                                    type="text"
                                    value={data.umur}
                                    onChange={(e) => setData('umur', e.target.value)}
                                    readOnly
                                    disabled={sensitiveLocked}
                                    className="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 bg-slate-50 disabled:bg-slate-100 disabled:text-slate-500 disabled:cursor-not-allowed"
                                    required
                                />
                                {errors.umur && <p className="text-sm text-rose-600 mt-1">{errors.umur}</p>}
                                <p className="text-xs text-slate-500 mt-1">Dikira automatik dari No. IC</p>
                            </div>

                            <div>
                                <label className="block text-sm font-medium text-slate-700 mb-1">
                                    No. Telefon <span className="text-rose-500">*</span>
                                    {sensitiveLocked && <span className="ml-1 text-xs text-slate-400">🔒 Dilindungi</span>}
                                </label>
                                <input
                                    type="text"
                                    value={data.no_tel}
                                    onChange={handleTelChange}
                                    placeholder="0123456789"
                                    disabled={sensitiveLocked}
                                    className="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 placeholder:text-slate-300 disabled:bg-slate-100 disabled:text-slate-500 disabled:cursor-not-allowed"
                                    required
                                />
                                {errors.no_tel && <p className="text-sm text-rose-600 mt-1">{errors.no_tel}</p>}
                                <p className="text-xs text-slate-500 mt-1">Hanya angka sahaja</p>
                            </div>

                            <div>
                                <label className="block text-sm font-medium text-slate-700 mb-1">
                                    Bangsa <span className="text-rose-500">*</span>
                                    {sensitiveLocked && <span className="ml-1 text-xs text-slate-400">🔒 Dilindungi</span>}
                                </label>
                                {sensitiveLocked ? (
                                    <input
                                        type="text"
                                        value={data.bangsa}
                                        disabled
                                        className="w-full px-3 py-2 border border-slate-300 rounded-lg bg-slate-100 text-slate-500 cursor-not-allowed"
                                    />
                                ) : (
                                    <select
                                        value={data.bangsa}
                                        onChange={(e) => setData('bangsa', e.target.value)}
                                        className="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400"
                                        required
                                    >
                                        <option value="">Pilih Bangsa</option>
                                        {bangsaList.map((bangsa) => (
                                            <option key={bangsa.id} value={bangsa.nama}>
                                                {bangsa.nama}
                                            </option>
                                        ))}
                                    </select>
                                )}
                                {errors.bangsa && <p className="text-sm text-rose-600 mt-1">{errors.bangsa}</p>}
                            </div>
                        </div>
                    </div>

                    {/* Address Information */}
                    <div className="order-1 bg-white rounded-xl border border-slate-200 p-6">
                        <h2 className="text-lg font-semibold text-slate-900 mb-4">
                            Maklumat Alamat
                            {sensitiveLocked && <span className="ml-2 text-xs font-normal text-slate-400">🔒 Dilindungi</span>}
                        </h2>
                        <div className="grid grid-cols-1 gap-4">
                            <div>
                                <label className="block text-sm font-medium text-slate-700 mb-1">
                                    Alamat <span className="text-rose-500">*</span>
                                </label>
                                <textarea
                                    value={data.alamat}
                                    onChange={(e) => handleTextChange('alamat', e.target.value)}
                                    rows="3"
                                    disabled={sensitiveLocked}
                                    className="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 uppercase disabled:bg-slate-100 disabled:text-slate-500 disabled:cursor-not-allowed disabled:normal-case"
                                    required
                                />
                                {errors.alamat && <p className="text-sm text-rose-600 mt-1">{errors.alamat}</p>}
                            </div>

                            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label className="block text-sm font-medium text-slate-700 mb-1">
                                        Poskod <span className="text-rose-500">*</span>
                                    </label>
                                    <input
                                        type="text"
                                        value={data.poskod}
                                        onChange={handlePostcodeChange}
                                        placeholder="00000"
                                        maxLength="5"
                                        disabled={sensitiveLocked}
                                        className="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 disabled:bg-slate-100 disabled:text-slate-500 disabled:cursor-not-allowed"
                                        required
                                    />
                                    {errors.poskod && <p className="text-sm text-rose-600 mt-1">{errors.poskod}</p>}
                                </div>

                                <div>
                                    <label className="block text-sm font-medium text-slate-700 mb-1">
                                        Negeri <span className="text-rose-500">*</span>
                                    </label>
                                    {sensitiveLocked ? (
                                        <input
                                            type="text"
                                            value={data.negeri}
                                            disabled
                                            className="w-full px-3 py-2 border border-slate-300 rounded-lg bg-slate-100 text-slate-500 cursor-not-allowed"
                                        />
                                    ) : (
                                        <select
                                            value={data.negeri}
                                            onChange={(e) => setData('negeri', e.target.value)}
                                            className="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400"
                                            required
                                        >
                                            <option value="">Pilih Negeri</option>
                                            {negeriList.map((item) => (
                                                <option key={item.id} value={item.nama}>{item.nama}</option>
                                            ))}
                                        </select>
                                    )}
                                    {errors.negeri && <p className="text-sm text-rose-600 mt-1">{errors.negeri}</p>}
                                </div>

                                <div>
                                    <label className="block text-sm font-medium text-slate-700 mb-1">
                                        Bandar <span className="text-rose-500">*</span>
                                    </label>
                                    <input
                                        type="text"
                                        value={data.bandar}
                                        readOnly
                                        disabled={sensitiveLocked}
                                        className="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 bg-slate-50 disabled:bg-slate-100 disabled:text-slate-500 disabled:cursor-not-allowed"
                                        placeholder="Pilih Poskod terlebih dahulu"
                                    />
                                    {errors.bandar && <p className="text-sm text-rose-600 mt-1">{errors.bandar}</p>}
                                </div>
                            </div>
                        </div>
                    </div>

                    {/* Household Information */}
                    {data.has_sumbangan && (
                    <div className="order-6 bg-white rounded-xl border border-slate-200 p-6">
                        <h2 className="text-lg font-semibold text-slate-900 mb-4">Maklumat Isi Rumah</h2>
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label className="block text-sm font-medium text-slate-700 mb-1">
                                    Bilangan Isi Rumah <span className="text-rose-500">*</span>
                                </label>
                                <input
                                    type="number"
                                    value={data.bil_isi_rumah}
                                    onChange={(e) => setData('bil_isi_rumah', e.target.value)}
                                    min="1"
                                    className="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400"
                                    required
                                />
                                {errors.bil_isi_rumah && <p className="text-sm text-rose-600 mt-1">{errors.bil_isi_rumah}</p>}
                            </div>

                            <div>
                                <label className="block text-sm font-medium text-slate-700 mb-1">
                                    Pendapatan Isi Rumah (RM)
                                    {pendapatanIsLocked && <span className="ml-1 text-xs text-slate-400">🔒 Dilindungi</span>}
                                </label>
                                <input
                                    type="text"
                                    inputMode="decimal"
                                    value={
                                        pendapatanIsLocked
                                            ? data.pendapatan_isi_rumah
                                            : (data.pendapatan_isi_rumah === MASK ? '' : formatCurrency(data.pendapatan_isi_rumah))
                                    }
                                    onChange={(e) => {
                                        const raw = e.target.value.replace(/[^0-9.]/g, '');
                                        const parts = raw.split('.');
                                        setData('pendapatan_isi_rumah', parts.length > 2 ? parts[0] + '.' + parts.slice(1).join('') : raw);
                                    }}
                                    placeholder="0.00"
                                    disabled={pendapatanIsLocked}
                                    className="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 disabled:bg-slate-100 disabled:text-slate-500 disabled:cursor-not-allowed"
                                />
                                {errors.pendapatan_isi_rumah && <p className="text-sm text-rose-600 mt-1">{errors.pendapatan_isi_rumah}</p>}
                            </div>

                            <div>
                                <label className="block text-sm font-medium text-slate-700 mb-1">
                                    Kategori Pekerjaan <span className="text-rose-500">*</span>
                                </label>
                                <select
                                    value={data.pekerjaan}
                                    onChange={(e) => {
                                        const val = e.target.value;
                                        setData(data => ({
                                            ...data,
                                            pekerjaan: val,
                                            jenis_pekerjaan: [],
                                            jenis_pekerjaan_lain: '',
                                        }));
                                    }}
                                    className="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400"
                                    required
                                >
                                    <option value="">Pilih Kategori</option>
                                    <option value="Kerajaan">Kerajaan</option>
                                    <option value="Swasta">Swasta</option>
                                    <option value="Bekerja Sendiri">Bekerja Sendiri</option>
                                    <option value="Tidak Bekerja">Tidak Bekerja</option>
                                </select>
                                {errors.pekerjaan && <p className="text-sm text-rose-600 mt-1">{errors.pekerjaan}</p>}
                            </div>

                            {data.pekerjaan === 'Kerajaan' ? (
                                <div className="md:col-span-2">
                                    <label className="block text-sm font-medium text-slate-700 mb-2">
                                        Sektor Pekerjaan <span className="text-rose-500">*</span>
                                    </label>
                                    <div className="space-y-4">
                                        {[
                                            {
                                                category: 'Jenis Perkhidmatan',
                                                items: [
                                                    'Perkhidmatan Awam Persekutuan (Kementerian / Jabatan)',
                                                    'Perkhidmatan Awam Negeri',
                                                    'Pihak Berkuasa Tempatan (PBT)',
                                                ],
                                            },
                                            {
                                                category: 'Agensi & Badan',
                                                items: [
                                                    'Badan Berkanun (MARA, LHDN, KWSP, dll)',
                                                    'Syarikat Berkaitan Kerajaan (GLC)',
                                                ],
                                            },
                                            {
                                                category: 'Keselamatan & Penguatkuasaan',
                                                items: [
                                                    'Angkatan Tentera Malaysia (ATM)',
                                                    'Polis Diraja Malaysia (PDRM)',
                                                    'Agensi Penguatkuasaan (APMM, JPJ, Imigresen, dll)',
                                                ],
                                            },
                                            {
                                                category: 'Pendidikan & Kesihatan',
                                                items: [
                                                    'Pendidikan Awam (Guru Sekolah Kerajaan)',
                                                    'Pendidikan Tinggi Awam (Pensyarah IPTA)',
                                                    'Kesihatan Awam (Hospital / Klinik Kerajaan)',
                                                ],
                                            },
                                        ].map((group) => (
                                            <div key={group.category}>
                                                <p className="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-1">{group.category}</p>
                                                <div className="grid grid-cols-1 md:grid-cols-2 gap-1">
                                                    {group.items.map((item) => (
                                                        <label key={item} className="flex items-center space-x-2 cursor-pointer">
                                                            <input
                                                                type="checkbox"
                                                                checked={Array.isArray(data.jenis_pekerjaan) && data.jenis_pekerjaan.includes(item)}
                                                                onChange={() => handleCheckboxChange('jenis_pekerjaan', item)}
                                                                className="w-4 h-4 text-blue-600 border-slate-300 rounded focus:ring-blue-500"
                                                            />
                                                            <span className="text-sm text-slate-700">{item}</span>
                                                        </label>
                                                    ))}
                                                </div>
                                            </div>
                                        ))}
                                        <div>
                                            <p className="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-1">Lain-lain</p>
                                            <label className="flex items-center space-x-2 cursor-pointer">
                                                <input
                                                    type="checkbox"
                                                    checked={Array.isArray(data.jenis_pekerjaan) && data.jenis_pekerjaan.includes('Lain-lain')}
                                                    onChange={() => handleCheckboxChange('jenis_pekerjaan', 'Lain-lain')}
                                                    className="w-4 h-4 text-blue-600 border-slate-300 rounded focus:ring-blue-500"
                                                />
                                                <span className="text-sm text-slate-700">Lain-lain</span>
                                            </label>
                                            {Array.isArray(data.jenis_pekerjaan) && data.jenis_pekerjaan.includes('Lain-lain') && (
                                                <input
                                                    type="text"
                                                    value={data.jenis_pekerjaan_lain}
                                                    onChange={(e) => setData('jenis_pekerjaan_lain', e.target.value)}
                                                    placeholder="Sila nyatakan sektor pekerjaan"
                                                    className="mt-2 w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400"
                                                />
                                            )}
                                        </div>
                                    </div>
                                    {errors.jenis_pekerjaan && <p className="text-sm text-rose-600 mt-1">{errors.jenis_pekerjaan}</p>}
                                    {errors.jenis_pekerjaan_lain && <p className="text-sm text-rose-600 mt-1">{errors.jenis_pekerjaan_lain}</p>}
                                </div>
                            ) : data.pekerjaan === 'Swasta' ? (
                                <div className="md:col-span-2">
                                    <label className="block text-sm font-medium text-slate-700 mb-2">
                                        Sektor Pekerjaan <span className="text-rose-500">*</span>
                                    </label>
                                    <div className="space-y-4">
                                        {[
                                            {
                                                category: 'Korporat & Profesional',
                                                items: [
                                                    'Syarikat Korporat / Multinasional',
                                                    'Profesional (Jurutera, Akauntan, Arkitek, dll)',
                                                    'Eksekutif / Pengurusan',
                                                ],
                                            },
                                            {
                                                category: 'Perdagangan & Perkhidmatan',
                                                items: [
                                                    'Peruncitan / Jualan (Retail)',
                                                    'Perkhidmatan (Servis – bengkel, salon, dll)',
                                                    'Perhotelan & Pelancongan',
                                                ],
                                            },
                                            {
                                                category: 'Industri & Teknikal',
                                                items: [
                                                    'Perkilangan / Industri',
                                                    'Pembinaan / Kontraktor',
                                                    'Logistik & Pengangkutan',
                                                ],
                                            },
                                            {
                                                category: 'Sektor Moden',
                                                items: [
                                                    'Teknologi Maklumat / Digital',
                                                    'Kewangan / Perbankan / Insurans',
                                                ],
                                            },
                                            {
                                                category: 'Sosial & Lain-lain',
                                                items: [
                                                    'Pendidikan Swasta',
                                                    'Kesihatan Swasta',
                                                ],
                                            },
                                        ].map((group) => (
                                            <div key={group.category}>
                                                <p className="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-1">{group.category}</p>
                                                <div className="grid grid-cols-1 md:grid-cols-2 gap-1">
                                                    {group.items.map((item) => (
                                                        <label key={item} className="flex items-center space-x-2 cursor-pointer">
                                                            <input
                                                                type="checkbox"
                                                                checked={Array.isArray(data.jenis_pekerjaan) && data.jenis_pekerjaan.includes(item)}
                                                                onChange={() => handleCheckboxChange('jenis_pekerjaan', item)}
                                                                className="w-4 h-4 text-blue-600 border-slate-300 rounded focus:ring-blue-500"
                                                            />
                                                            <span className="text-sm text-slate-700">{item}</span>
                                                        </label>
                                                    ))}
                                                </div>
                                            </div>
                                        ))}
                                        <div>
                                            <p className="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-1">Lain-lain</p>
                                            <label className="flex items-center space-x-2 cursor-pointer">
                                                <input
                                                    type="checkbox"
                                                    checked={Array.isArray(data.jenis_pekerjaan) && data.jenis_pekerjaan.includes('Lain-lain')}
                                                    onChange={() => handleCheckboxChange('jenis_pekerjaan', 'Lain-lain')}
                                                    className="w-4 h-4 text-blue-600 border-slate-300 rounded focus:ring-blue-500"
                                                />
                                                <span className="text-sm text-slate-700">Lain-lain</span>
                                            </label>
                                            {Array.isArray(data.jenis_pekerjaan) && data.jenis_pekerjaan.includes('Lain-lain') && (
                                                <input
                                                    type="text"
                                                    value={data.jenis_pekerjaan_lain}
                                                    onChange={(e) => setData('jenis_pekerjaan_lain', e.target.value)}
                                                    placeholder="Sila nyatakan sektor pekerjaan"
                                                    className="mt-2 w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400"
                                                />
                                            )}
                                        </div>
                                    </div>
                                    {errors.jenis_pekerjaan && <p className="text-sm text-rose-600 mt-1">{errors.jenis_pekerjaan}</p>}
                                    {errors.jenis_pekerjaan_lain && <p className="text-sm text-rose-600 mt-1">{errors.jenis_pekerjaan_lain}</p>}
                                </div>
                            ) : data.pekerjaan === 'Bekerja Sendiri' ? (
                                <div className="md:col-span-2">
                                    <label className="block text-sm font-medium text-slate-700 mb-2">
                                        Sektor Pekerjaan <span className="text-rose-500">*</span>
                                    </label>
                                    <div className="space-y-4">
                                        {[
                                            {
                                                category: 'Perniagaan & Jualan',
                                                items: [
                                                    'Peniaga Kecil (gerai, pasar, online)',
                                                    'Usahawan / Pemilik Syarikat',
                                                    'E-dagang (Shopee, TikTok Shop, dll)',
                                                ],
                                            },
                                            {
                                                category: 'Perkhidmatan',
                                                items: [
                                                    'Freelance (design, IT, content creator, dll)',
                                                    'Servis (bengkel, tukang, plumbing, wiring, dll)',
                                                    'Ejen (insurans, hartanah, dll)',
                                                ],
                                            },
                                            {
                                                category: 'Pengangkutan & Gig Economy',
                                                items: [
                                                    'Pemandu e-hailing (Grab, dll)',
                                                    'Rider penghantaran (Foodpanda, GrabFood, dll)',
                                                    'Lori / Van persendirian',
                                                ],
                                            },
                                            {
                                                category: 'Sektor Asas',
                                                items: [
                                                    'Pertanian',
                                                    'Penternakan',
                                                    'Perikanan',
                                                ],
                                            },
                                        ].map((group) => (
                                            <div key={group.category}>
                                                <p className="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-1">{group.category}</p>
                                                <div className="grid grid-cols-1 md:grid-cols-2 gap-1">
                                                    {group.items.map((item) => (
                                                        <label key={item} className="flex items-center space-x-2 cursor-pointer">
                                                            <input
                                                                type="checkbox"
                                                                checked={Array.isArray(data.jenis_pekerjaan) && data.jenis_pekerjaan.includes(item)}
                                                                onChange={() => handleCheckboxChange('jenis_pekerjaan', item)}
                                                                className="w-4 h-4 text-blue-600 border-slate-300 rounded focus:ring-blue-500"
                                                            />
                                                            <span className="text-sm text-slate-700">{item}</span>
                                                        </label>
                                                    ))}
                                                </div>
                                            </div>
                                        ))}
                                        <div>
                                            <p className="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-1">Lain-lain</p>
                                            <label className="flex items-center space-x-2 cursor-pointer">
                                                <input
                                                    type="checkbox"
                                                    checked={Array.isArray(data.jenis_pekerjaan) && data.jenis_pekerjaan.includes('Lain-lain')}
                                                    onChange={() => handleCheckboxChange('jenis_pekerjaan', 'Lain-lain')}
                                                    className="w-4 h-4 text-blue-600 border-slate-300 rounded focus:ring-blue-500"
                                                />
                                                <span className="text-sm text-slate-700">Lain-lain</span>
                                            </label>
                                            {Array.isArray(data.jenis_pekerjaan) && data.jenis_pekerjaan.includes('Lain-lain') && (
                                                <input
                                                    type="text"
                                                    value={data.jenis_pekerjaan_lain}
                                                    onChange={(e) => setData('jenis_pekerjaan_lain', e.target.value)}
                                                    placeholder="Sila nyatakan sektor pekerjaan"
                                                    className="mt-2 w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400"
                                                />
                                            )}
                                        </div>
                                    </div>
                                    {errors.jenis_pekerjaan && <p className="text-sm text-rose-600 mt-1">{errors.jenis_pekerjaan}</p>}
                                    {errors.jenis_pekerjaan_lain && <p className="text-sm text-rose-600 mt-1">{errors.jenis_pekerjaan_lain}</p>}
                                </div>
                            ) : data.pekerjaan === 'Tidak Bekerja' ? (
                                <div className="md:col-span-2">
                                    <label className="block text-sm font-medium text-slate-700 mb-2">
                                        Sektor Pekerjaan <span className="text-rose-500">*</span>
                                    </label>
                                    <div className="space-y-4">
                                        {[
                                            {
                                                category: 'Status',
                                                items: [
                                                    'Pelajar Sekolah',
                                                    'Pelajar IPT (IPTA / IPTS)',
                                                    'Suri Rumah',
                                                    'Pesara Kerajaan',
                                                    'Pesara Swasta',
                                                    'Tidak Bekerja / Menganggur',
                                                ],
                                            },
                                        ].map((group) => (
                                            <div key={group.category}>
                                                <p className="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-1">{group.category}</p>
                                                <div className="grid grid-cols-1 md:grid-cols-2 gap-1">
                                                    {group.items.map((item) => (
                                                        <label key={item} className="flex items-center space-x-2 cursor-pointer">
                                                            <input
                                                                type="checkbox"
                                                                checked={Array.isArray(data.jenis_pekerjaan) && data.jenis_pekerjaan.includes(item)}
                                                                onChange={() => handleCheckboxChange('jenis_pekerjaan', item)}
                                                                className="w-4 h-4 text-blue-600 border-slate-300 rounded focus:ring-blue-500"
                                                            />
                                                            <span className="text-sm text-slate-700">{item}</span>
                                                        </label>
                                                    ))}
                                                </div>
                                            </div>
                                        ))}
                                        <div>
                                            <p className="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-1">Lain-lain</p>
                                            <label className="flex items-center space-x-2 cursor-pointer">
                                                <input
                                                    type="checkbox"
                                                    checked={Array.isArray(data.jenis_pekerjaan) && data.jenis_pekerjaan.includes('Lain-lain')}
                                                    onChange={() => handleCheckboxChange('jenis_pekerjaan', 'Lain-lain')}
                                                    className="w-4 h-4 text-blue-600 border-slate-300 rounded focus:ring-blue-500"
                                                />
                                                <span className="text-sm text-slate-700">Lain-lain</span>
                                            </label>
                                            {Array.isArray(data.jenis_pekerjaan) && data.jenis_pekerjaan.includes('Lain-lain') && (
                                                <input
                                                    type="text"
                                                    value={data.jenis_pekerjaan_lain}
                                                    onChange={(e) => setData('jenis_pekerjaan_lain', e.target.value)}
                                                    placeholder="Sila nyatakan sektor pekerjaan"
                                                    className="mt-2 w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400"
                                                />
                                            )}
                                        </div>
                                    </div>
                                    {errors.jenis_pekerjaan && <p className="text-sm text-rose-600 mt-1">{errors.jenis_pekerjaan}</p>}
                                    {errors.jenis_pekerjaan_lain && <p className="text-sm text-rose-600 mt-1">{errors.jenis_pekerjaan_lain}</p>}
                                </div>
                            ) : null}

                            <div>
                                <label className="block text-sm font-medium text-slate-700 mb-1">
                                    Pemilik Rumah <span className="text-rose-500">*</span>
                                </label>
                                <select
                                    value={data.pemilik_rumah}
                                    onChange={(e) => {
                                        const val = e.target.value;
                                        setData(data => ({
                                            ...data,
                                            pemilik_rumah: val,
                                            pemilik_rumah_lain: val === 'Lain-lain' ? data.pemilik_rumah_lain : '',
                                        }));
                                    }}
                                    className="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400"
                                    required
                                >
                                    <option value="">Pilih Status</option>
                                    <option value="Sendiri">Sendiri</option>
                                    <option value="Sewa">Sewa</option>
                                    <option value="Keluarga">Keluarga</option>
                                    <option value="Lain-lain">Lain-lain</option>
                                </select>
                                {errors.pemilik_rumah && <p className="text-sm text-rose-600 mt-1">{errors.pemilik_rumah}</p>}
                            </div>

                            {data.pemilik_rumah === 'Lain-lain' && (
                                <div>
                                    <label className="block text-sm font-medium text-slate-700 mb-1">
                                        Pemilik Rumah (Lain-lain) <span className="text-rose-500">*</span>
                                    </label>
                                    <input
                                        type="text"
                                        value={data.pemilik_rumah_lain}
                                        onChange={(e) => setData('pemilik_rumah_lain', e.target.value)}
                                        className="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400"
                                        placeholder="Sila nyatakan"
                                    />
                                    {errors.pemilik_rumah_lain && <p className="text-sm text-rose-600 mt-1">{errors.pemilik_rumah_lain}</p>}
                                </div>
                            )}
                        </div>
                    </div>
                    )}

                    {/* Assistance & Political Information */}
                    {data.has_sumbangan && (
                    <div className="order-7 bg-white rounded-xl border border-slate-200 p-6">
                        <h2 className="text-lg font-semibold text-slate-900 mb-4">Maklumat Bantuan</h2>
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div className="md:col-span-2">
                                <label className="block text-sm font-medium text-slate-700 mb-2">
                                    Jenis Sumbangan <span className="text-rose-500">*</span>
                                </label>
                                <div className="grid grid-cols-1 md:grid-cols-2 gap-2">
                                    {[
                                        'Barangan Keperluan Dapur',
                                        'Hamper / Sumbangan Perayaan',
                                        'Wang Tunai / Kewangan',
                                        'Bantuan Perumahan (baik pulih)',
                                        'Bantuan Perumahan (bina baharu)',
                                        'Bantuan Pendidikan (yuran / kelengkapan sekolah)',
                                        'Bantuan Perubatan / Kesihatan',
                                        'Bantuan Perniagaan / Ekonomi (modal / peralatan)',
                                        'Bantuan Bencana / Kecemasan',
                                        'Lain-lain',
                                    ].map((item) => (
                                        <label key={item} className="flex items-center space-x-2 cursor-pointer">
                                            <input
                                                type="checkbox"
                                                checked={data.jenis_sumbangan.includes(item)}
                                                onChange={() => handleCheckboxChange('jenis_sumbangan', item)}
                                                className="w-4 h-4 text-blue-600 border-slate-300 rounded focus:ring-blue-500"
                                            />
                                            <span className="text-sm text-slate-700">{item}</span>
                                        </label>
                                    ))}
                                </div>
                                {data.jenis_sumbangan.includes('Wang Tunai / Kewangan') && (
                                    <div className="mt-3">
                                        <label className="block text-sm font-medium text-slate-700 mb-1">
                                            Jumlah Bantuan
                                        </label>
                                        <div className="relative">
                                            <span className="absolute left-3 top-1/2 -translate-y-1/2 text-sm text-slate-500">RM</span>
                                            <input
                                                type="text"
                                                inputMode="decimal"
                                                value={formatCurrency(data.jumlah_wang_tunai)}
                                                onChange={(e) => {
                                                    const raw = e.target.value.replace(/[^0-9.]/g, '');
                                                    const parts = raw.split('.');
                                                    setData('jumlah_wang_tunai', parts.length > 2 ? parts[0] + '.' + parts.slice(1).join('') : raw);
                                                }}
                                                placeholder="0.00"
                                                className="w-full pl-10 pr-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400"
                                            />
                                        </div>
                                        {errors.jumlah_wang_tunai && <p className="text-sm text-rose-600 mt-1">{errors.jumlah_wang_tunai}</p>}
                                    </div>
                                )}
                                {data.jenis_sumbangan.includes('Lain-lain') && (
                                    <input
                                        type="text"
                                        value={data.jenis_sumbangan_lain}
                                        onChange={(e) => setData('jenis_sumbangan_lain', e.target.value)}
                                        placeholder="Nyatakan jenis sumbangan lain"
                                        className="mt-2 w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400"
                                    />
                                )}
                                {errors.jenis_sumbangan && <p className="text-sm text-rose-600 mt-1">{errors.jenis_sumbangan}</p>}
                            </div>

                            <div className="md:col-span-2">
                                <label className="block text-sm font-medium text-slate-700 mb-2">
                                    Tujuan Sumbangan <span className="text-rose-500">*</span>
                                </label>
                                <div className="grid grid-cols-1 md:grid-cols-2 gap-2">
                                    {tujuanSumbanganList.map((item) => (
                                        <label key={item.id} className="flex items-center space-x-2 cursor-pointer">
                                            <input
                                                type="checkbox"
                                                checked={data.tujuan_sumbangan.includes(item.nama)}
                                                onChange={() => handleCheckboxChange('tujuan_sumbangan', item.nama)}
                                                className="w-4 h-4 text-blue-600 border-slate-300 rounded focus:ring-blue-500"
                                            />
                                            <span className="text-sm text-slate-700">{item.nama}</span>
                                        </label>
                                    ))}
                                </div>
                                {data.tujuan_sumbangan.some(item => item.toLowerCase().includes('lain')) && (
                                    <input
                                        type="text"
                                        value={data.tujuan_sumbangan_lain}
                                        onChange={(e) => setData('tujuan_sumbangan_lain', e.target.value)}
                                        placeholder="Nyatakan tujuan sumbangan lain"
                                        className="mt-2 w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400"
                                    />
                                )}
                                {errors.tujuan_sumbangan && <p className="text-sm text-rose-600 mt-1">{errors.tujuan_sumbangan}</p>}
                            </div>

                            <div className="md:col-span-2">
                                <label className="block text-sm font-medium text-slate-700 mb-2">
                                    Bantuan Lain Yang Diterima <span className="text-rose-500">*</span>
                                </label>
                                <div className="grid grid-cols-1 md:grid-cols-2 gap-2">
                                    {[
                                        'Jabatan Kebajikan Masyarakat (JKM)',
                                        'i-Sejahtera',
                                        'Zakat Pulau Pinang (ZPP)',
                                        'PERKESO',
                                        'Tiada',
                                        'Lain-lain',
                                    ].map((item) => (
                                        <label key={item} className="flex items-center space-x-2 cursor-pointer">
                                            <input
                                                type="checkbox"
                                                checked={data.bantuan_lain.includes(item)}
                                                onChange={() => handleCheckboxChange('bantuan_lain', item)}
                                                className="w-4 h-4 text-blue-600 border-slate-300 rounded focus:ring-blue-500"
                                            />
                                            <span className="text-sm text-slate-700">{item}</span>
                                        </label>
                                    ))}
                                </div>
                                {data.bantuan_lain.includes('Lain-lain') && (
                                    <input
                                        type="text"
                                        value={data.bantuan_lain_lain}
                                        onChange={(e) => setData('bantuan_lain_lain', e.target.value)}
                                        placeholder="Nyatakan bantuan lain"
                                        className="mt-2 w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400"
                                    />
                                )}
                                {data.bantuan_lain.includes('PERKESO') && (
                                    <div className="mt-3 ml-6 p-3 bg-slate-50 rounded-lg border border-slate-200">
                                        <label className="block text-sm font-medium text-slate-700 mb-2">
                                            Jenis Bantuan PERKESO
                                        </label>
                                        <div className="grid grid-cols-1 md:grid-cols-2 gap-2">
                                            {[
                                                'Bantuan Perubatan',
                                                'Pampasan Hilang Upaya',
                                                'Faedah Kematian / Orang Tanggungan',
                                                'Elaun Hilang Pekerjaan (EIS)',
                                                'Bantuan Latihan / Penempatan Kerja',
                                                'Lain-lain',
                                            ].map((item) => (
                                                <label key={item} className="flex items-center space-x-2 cursor-pointer">
                                                    <input
                                                        type="checkbox"
                                                        checked={data.perkeso_bantuan.includes(item)}
                                                        onChange={() => handleCheckboxChange('perkeso_bantuan', item)}
                                                        className="w-4 h-4 text-blue-600 border-slate-300 rounded focus:ring-blue-500"
                                                    />
                                                    <span className="text-sm text-slate-700">{item}</span>
                                                </label>
                                            ))}
                                        </div>
                                        {data.perkeso_bantuan.includes('Lain-lain') && (
                                            <input
                                                type="text"
                                                value={data.perkeso_bantuan_lain}
                                                onChange={(e) => setData('perkeso_bantuan_lain', e.target.value)}
                                                placeholder="Nyatakan bantuan PERKESO lain"
                                                className="mt-2 w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400"
                                            />
                                        )}
                                    </div>
                                )}
                                {data.bantuan_lain.includes('Zakat Pulau Pinang (ZPP)') && (
                                    <div className="mt-3 p-3 bg-slate-50 rounded-lg">
                                        <label className="block text-sm font-medium text-slate-700 mb-2">
                                            Jenis Bantuan ZPP
                                        </label>
                                        <div className="grid grid-cols-1 md:grid-cols-2 gap-2">
                                            {[
                                                'Bantuan kewangan asnaf',
                                                'Bantuan makanan / sara hidup',
                                                'Bantuan perubatan',
                                                'Bantuan pendidikan',
                                                'Bantuan perumahan',
                                                'Modal perniagaan asnaf',
                                            ].map((item) => (
                                                <label key={item} className="flex items-center space-x-2 cursor-pointer">
                                                    <input
                                                        type="checkbox"
                                                        checked={data.zpp_jenis_bantuan.includes(item)}
                                                        onChange={() => handleCheckboxChange('zpp_jenis_bantuan', item)}
                                                        className="w-4 h-4 text-blue-600 border-slate-300 rounded focus:ring-blue-500"
                                                    />
                                                    <span className="text-sm text-slate-700">{item}</span>
                                                </label>
                                            ))}
                                        </div>
                                        {errors.zpp_jenis_bantuan && <p className="text-sm text-rose-600 mt-1">{errors.zpp_jenis_bantuan}</p>}
                                    </div>
                                )}
                                {data.bantuan_lain.some(item => item === 'i-Sejahtera') && (
                                    <div className="mt-3">
                                        <label className="block text-sm font-medium text-slate-700 mb-1">
                                            Program i-Sejahtera <span className="text-rose-500">*</span>
                                        </label>
                                        <select
                                            value={data.isejahtera_program}
                                            onChange={(e) => setData('isejahtera_program', e.target.value)}
                                            className="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400"
                                        >
                                            <option value="">Pilih Program i-Sejahtera</option>
                                            <option value="Program Penghargaan Warga Emas">Program Penghargaan Warga Emas</option>
                                            <option value="Program Bantuan Ibu Tunggal">Program Bantuan Ibu Tunggal</option>
                                            <option value="Program Bantuan Orang Kurang Upaya (OKU)">Program Bantuan Orang Kurang Upaya (OKU)</option>
                                            <option value="Program Suri Emas / Surirumah Emas">Program Suri Emas / Surirumah Emas</option>
                                        </select>
                                        {errors.isejahtera_program && <p className="text-sm text-rose-600 mt-1">{errors.isejahtera_program}</p>}
                                    </div>
                                )}
                                {data.bantuan_lain.includes('Jabatan Kebajikan Masyarakat (JKM)') && (
                                    <div className="mt-3">
                                        <label className="block text-sm font-medium text-slate-700 mb-1">
                                            Program JKM <span className="text-rose-500">*</span>
                                        </label>
                                        <select
                                            value={data.jkm_program}
                                            onChange={(e) => setData('jkm_program', e.target.value)}
                                            className="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400"
                                        >
                                            <option value="">Pilih Program</option>
                                            <option value="Bantuan Kanak-Kanak (BKK)">Bantuan Kanak-Kanak (BKK)</option>
                                            <option value="Bantuan Warga Emas (BWE)">Bantuan Warga Emas (BWE)</option>
                                            <option value="Elaun Pekerja Orang Kurang Upaya (EPOKU)">Elaun Pekerja Orang Kurang Upaya (EPOKU)</option>
                                            <option value="Bantuan OKU Tidak Berupaya Bekerja (BTB)">Bantuan OKU Tidak Berupaya Bekerja (BTB)</option>
                                            <option value="Bantuan Penjagaan OKU / Pesakit Terlantar (BPT)">Bantuan Penjagaan OKU / Pesakit Terlantar (BPT)</option>
                                            <option value="Bantuan Am Persekutuan (BA)">Bantuan Am Persekutuan (BA)</option>
                                        </select>
                                        {errors.jkm_program && <p className="text-sm text-rose-600 mt-1">{errors.jkm_program}</p>}
                                    </div>
                                )}
                                {errors.bantuan_lain && <p className="text-sm text-rose-600 mt-1">{errors.bantuan_lain}</p>}
                            </div>
                        </div>
                    </div>
                    )}

                    {/* Maklumat Kawasan Mengundi */}
                    <div className="order-2 bg-white rounded-xl border border-slate-200 p-6">
                        <h2 className="text-lg font-semibold text-slate-900 mb-4">Maklumat Kawasan Mengundi</h2>
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label className="block text-sm font-medium text-slate-700 mb-1">Parlimen <span className="text-rose-500">*</span></label>
                                    <select value={data.parlimen} onChange={(e) => setData({...data, parlimen: e.target.value, kadun: '', mpkk: '', daerah_mengundi: '', lokaliti: ''})} className="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400" required>
                                        <option value="">Pilih Parlimen</option>
                                        {parlimenList.map((item) => (<option key={item.id} value={item.nama}>{item.nama}</option>))}
                                    </select>
                                    {errors.parlimen && <p className="text-sm text-rose-600 mt-1">{errors.parlimen}</p>}
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-slate-700 mb-1">KADUN <span className="text-rose-500">*</span></label>
                                    <select value={data.kadun} onChange={(e) => setData({...data, kadun: e.target.value, mpkk: ''})} className="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400" required>
                                        <option value="">{loadingKadun ? "Memuat..." : "Pilih KADUN"}</option>
                                        {kadunOptions.map((item) => (<option key={item.id} value={item.nama}>{item.nama}</option>))}
                                    </select>
                                    {errors.kadun && <p className="text-sm text-rose-600 mt-1">{errors.kadun}</p>}
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-slate-700 mb-1">MPKK <span className="text-rose-500">*</span></label>
                                    <select value={data.mpkk} onChange={(e) => setData('mpkk', e.target.value)} className="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400" required>
                                        <option value="">{loadingMpkk ? "Memuat..." : "Pilih MPKK"}</option>
                                        {mpkkOptions.map((item) => (<option key={item.id} value={item.nama}>{item.nama}</option>))}
                                    </select>
                                    {errors.mpkk && <p className="text-sm text-rose-600 mt-1">{errors.mpkk}</p>}
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-slate-700 mb-1">Daerah Mengundi <span className="text-rose-500">*</span></label>
                                    <select value={data.daerah_mengundi} onChange={(e) => setData({...data, daerah_mengundi: e.target.value, lokaliti: ''})} className="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400" required>
                                        <option value="">{loadingDaerahMengundi ? "Memuat..." : "Pilih Daerah Mengundi"}</option>
                                        {daerahMengundiOptions.map((item) => (<option key={item.id} value={item.nama}>{item.nama}</option>))}
                                    </select>
                                    {errors.daerah_mengundi && <p className="text-sm text-rose-600 mt-1">{errors.daerah_mengundi}</p>}
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-slate-700 mb-1">Lokaliti</label>
                                    <select value={data.lokaliti} onChange={(e) => setData('lokaliti', e.target.value)} className="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400">
                                        <option value="">{loadingLokaliti ? "Memuat..." : "Pilih Lokaliti"}</option>
                                        {lokalitiOptions.map((item) => (<option key={item.id} value={item.nama}>{item.nama}</option>))}
                                    </select>
                                    {errors.lokaliti && <p className="text-sm text-rose-600 mt-1">{errors.lokaliti}</p>}
                                </div>
                        </div>
                    </div>

                    {/* Status Pengundi */}
                    <div className={`order-3 bg-white rounded-xl border border-slate-200 p-6 ${!data.update_status_pengundi ? 'bg-slate-50' : ''}`}>
                        <div className="flex items-center justify-between mb-4">
                            <h2 className={`text-lg font-semibold ${data.update_status_pengundi ? 'text-slate-900' : 'text-slate-400'}`}>Status Pengundi</h2>
                            <label className="flex items-center space-x-2 cursor-pointer">
                                <input
                                    type="checkbox"
                                    checked={data.update_status_pengundi}
                                    onChange={(e) => handleStatusPengundiToggle(e.target.checked)}
                                    className="w-4 h-4 text-blue-600 border-slate-300 rounded focus:ring-blue-500"
                                />
                                <span className="text-sm font-medium text-slate-700">Perlu Dikemaskini</span>
                            </label>
                        </div>
                        <div className={`grid grid-cols-1 md:grid-cols-2 gap-2 ${!data.update_status_pengundi ? 'opacity-50 pointer-events-none select-none' : ''}`}>
                            {[
                                'Pemilih Bertukar Alamat (Keluar)',
                                'Hilang Layak Pengundi Awam',
                                'Pertukaran Kepada Lokaliti Awam',
                            ].map((item) => (
                                <label key={item} className="flex items-center space-x-2 cursor-pointer">
                                    <input
                                        type="checkbox"
                                        disabled={!data.update_status_pengundi}
                                        checked={(data.status_pengundi || '').split(', ').includes(item)}
                                        onChange={() => {
                                            const current = data.status_pengundi ? data.status_pengundi.split(', ').filter(Boolean) : [];
                                            const updated = current.includes(item) ? current.filter(v => v !== item) : [...current, item];
                                            setData('status_pengundi', updated.join(', '));
                                        }}
                                        className="w-4 h-4 text-blue-600 border-slate-300 rounded focus:ring-blue-500"
                                    />
                                    <span className="text-sm text-slate-700">{item}</span>
                                </label>
                            ))}
                        </div>
                        {errors.status_pengundi && <p className="text-sm text-rose-600 mt-1">{errors.status_pengundi}</p>}
                    </div>

                    {/* Political Information */}
                    <div className="order-4 bg-white rounded-xl border border-slate-200 p-6">
                        <h2 className="text-lg font-semibold text-slate-900 mb-4">Maklumat Politik</h2>
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label className="block text-sm font-medium text-slate-700 mb-1">
                                    Keanggotaan Parti {data.has_sumbangan && <span className="text-rose-500">*</span>}
                                </label>
                                <select
                                    value={data.keahlian_parti}
                                    onChange={(e) => setData('keahlian_parti', e.target.value)}
                                    className="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400"
                                >
                                    <option value="">Pilih Keanggotaan Parti</option>
                                    {keahlianPartiList.map((item) => (
                                        <option key={item.id} value={item.nama}>
                                            {item.nama}
                                        </option>
                                    ))}
                                </select>
                                {errors.keahlian_parti && <p className="text-sm text-rose-600 mt-1">{errors.keahlian_parti}</p>}
                            </div>

                            <div>
                                <label className="block text-sm font-medium text-slate-700 mb-1">
                                    Kecenderungan Politik {data.has_sumbangan && <span className="text-rose-500">*</span>}
                                </label>
                                <select
                                    value={data.kecenderungan_politik}
                                    onChange={(e) => setData('kecenderungan_politik', e.target.value)}
                                    className="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400"
                                >
                                    <option value="">Pilih Kecenderungan Politik</option>
                                    {kecenderunganPolitikList.map((item) => (
                                        <option key={item.id} value={item.nama}>
                                            {item.nama}
                                        </option>
                                    ))}
                                </select>
                                {errors.kecenderungan_politik && <p className="text-sm text-rose-600 mt-1">{errors.kecenderungan_politik}</p>}
                            </div>
                        </div>
                    </div>

                    {/* Sumbangan Toggle */}
                    <div ref={sumbanganCardRef} className="order-5 bg-[#D5E7B5] rounded-xl border border-slate-200 p-6">
                        <label className="flex items-center space-x-3 cursor-pointer">
                            <input
                                type="checkbox"
                                checked={data.has_sumbangan}
                                onChange={(e) => handleSumbanganToggle(e.target.checked)}
                                className="w-5 h-5 text-blue-600 border-slate-300 rounded focus:ring-blue-500"
                            />
                            <span className="text-lg font-semibold text-slate-900">Sumbangan</span>
                        </label>
                        <p className="text-sm text-slate-500 mt-2 ml-8">Tandakan untuk mengisi Maklumat Isi Rumah dan Maklumat Bantuan.</p>
                    </div>

                    {/* Documents & Notes */}
                    <div className="order-8 bg-white rounded-xl border border-slate-200 p-6">
                        <h2 className="text-lg font-semibold text-slate-900 mb-4">
                            Dokumen & Nota
                            {sensitiveLocked && <span className="ml-2 text-xs font-normal text-slate-400">🔒 Dilindungi</span>}
                        </h2>
                        {sensitiveLocked ? (
                            <div className="rounded-lg bg-slate-50 border border-slate-200 p-6 text-center text-sm text-slate-500">
                                Dokumen & nota sedia ada dilindungi.
                            </div>
                        ) : (
                        <div className="grid grid-cols-1 gap-4">
                            <div>
                                <label className="block text-sm font-medium text-slate-700 mb-2">
                                    Kad Pengenalan
                                </label>

                                {!previewUrl ? (
                                    <div className="relative">
                                        <input
                                            type="file"
                                            accept="image/*"
                                            onChange={handleFileUpload}
                                            className="hidden"
                                            id="kad-pengenalan-upload"
                                            disabled={isUploading}
                                        />
                                        <label
                                            htmlFor="kad-pengenalan-upload"
                                            className="flex flex-col items-center justify-center w-full h-32 border-2 border-dashed border-slate-300 rounded-lg cursor-pointer hover:border-slate-400 hover:bg-slate-50 transition-colors"
                                        >
                                            <Upload className="h-8 w-8 text-slate-400 mb-2" />
                                            <span className="text-sm text-slate-600">Klik untuk muat naik imej</span>
                                            <span className="text-xs text-slate-500 mt-1">PNG, JPG, JPEG (Maks. 5MB)</span>
                                        </label>
                                    </div>
                                ) : (
                                    <div className="relative">
                                        <div className="relative w-full h-48 bg-slate-100 rounded-lg overflow-hidden">
                                            <img
                                                src={previewUrl}
                                                alt="Preview Kad Pengenalan"
                                                className="w-full h-full object-contain"
                                                onError={(e) => {
                                                    e.target.onerror = null;
                                                    e.target.src = 'https://placehold.co/600x400?text=Imej+Tidak+Dijumpai';
                                                    e.target.className = "w-full h-full object-contain opacity-50";
                                                }}
                                            />
                                            {isUploading && (
                                                <div className="absolute inset-0 bg-black/50 flex flex-col items-center justify-center">
                                                    <Loader2 className="h-8 w-8 text-white animate-spin mb-2" />
                                                    <span className="text-white text-sm font-medium">{uploadProgress}%</span>
                                                </div>
                                            )}
                                        </div>
                                        {!isUploading && (
                                            <button
                                                type="button"
                                                onClick={handleRemoveFile}
                                                className="absolute top-2 right-2 p-1.5 bg-rose-600 text-white rounded-lg hover:bg-rose-700 transition-colors"
                                            >
                                                <X className="h-4 w-4" />
                                            </button>
                                        )}
                                        {data.kad_pengenalan && (
                                            <p className="text-sm text-slate-600 mt-2 flex items-center">
                                                <ImageIcon className="h-4 w-4 mr-1" />
                                                {data.kad_pengenalan.name}
                                            </p>
                                        )}
                                    </div>
                                )}
                                {errors.kad_pengenalan && <p className="text-sm text-rose-600 mt-1">{errors.kad_pengenalan}</p>}
                            </div>

                            <div>
                                <label className="block text-sm font-medium text-slate-700 mb-2">
                                    Nota
                                </label>
                                <div className="flex items-center space-x-4 mb-2">
                                    <label className="flex items-center space-x-2 cursor-pointer">
                                        <input
                                            type="radio"
                                            name="nota_toggle"
                                            checked={!data.nota}
                                            onChange={() => setData('nota', '')}
                                            className="w-4 h-4 text-blue-600 border-slate-300 focus:ring-blue-500"
                                        />
                                        <span className="text-sm text-slate-700">Tiada</span>
                                    </label>
                                    <label className="flex items-center space-x-2 cursor-pointer">
                                        <input
                                            type="radio"
                                            name="nota_toggle"
                                            checked={!!data.nota}
                                            onChange={() => setData('nota', data.nota || ' ')}
                                            className="w-4 h-4 text-blue-600 border-slate-300 focus:ring-blue-500"
                                        />
                                        <span className="text-sm text-slate-700">Ada</span>
                                    </label>
                                </div>
                                {!!data.nota && (
                                    <textarea
                                        value={data.nota.trim() === '' ? '' : data.nota}
                                        onChange={(e) => setData('nota', e.target.value || ' ')}
                                        rows="4"
                                        placeholder="Catatan tambahan..."
                                        className="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400"
                                    />
                                )}
                                {errors.nota && <p className="text-sm text-rose-600 mt-1">{errors.nota}</p>}
                            </div>
                        </div>
                        )}
                    </div>

                    {/* Sejarah Bantuan - previous sumbangan records for this IC */}
                    {((data.no_ic && data.no_ic.length === 12 && data.no_ic !== MASK) || data.locked_source_id) && (
                        <div className="order-[9] bg-white rounded-xl border-2 border-blue-200 p-6">
                            <div className="flex items-start gap-3 mb-4">
                                <div className="flex-shrink-0 mt-0.5">
                                    <svg className="w-6 h-6 text-blue-600" fill="none" viewBox="0 0 24 24" strokeWidth="1.5" stroke="currentColor">
                                        <path strokeLinecap="round" strokeLinejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                    </svg>
                                </div>
                                <div className="flex-1">
                                    <h2 className="text-lg font-semibold text-slate-900">Sejarah Bantuan Terdahulu</h2>
                                    <p className="text-xs text-slate-500 mt-0.5">
                                        {bantuanHistory.length > 0
                                            ? `${bantuanHistory.length} rekod bantuan untuk ${bantuanHistory[0].nama}`
                                            : 'Tiada sejarah bantuan ditemui untuk pengundi ini.'}
                                    </p>
                                </div>
                            </div>
                            {bantuanHistory.length > 0 && (
                            <div className="space-y-3">
                                {bantuanHistory.map((record) => (
                                    <div
                                        key={record.id}
                                        className="rounded-lg border border-slate-200 bg-slate-50 p-4"
                                    >
                                        <div className="flex items-start justify-between mb-2">
                                            <div>
                                                <p className="text-sm font-semibold text-slate-900">
                                                    {new Date(record.created_at).toLocaleDateString('ms-MY', { year: 'numeric', month: 'long', day: 'numeric' })}
                                                    <span className="ml-2 text-xs font-normal text-slate-500">
                                                        {new Date(record.created_at).toLocaleTimeString('ms-MY', { hour: '2-digit', minute: '2-digit' })}
                                                    </span>
                                                </p>
                                                {record.submitted_by?.name && (
                                                    <p className="text-xs text-slate-500 mt-0.5">Dihantar oleh: {record.submitted_by.name}</p>
                                                )}
                                            </div>
                                            {record.jumlah_wang_tunai && (
                                                <span className="text-sm font-semibold text-blue-700">
                                                    RM {Number(record.jumlah_wang_tunai).toLocaleString('en-MY', { minimumFractionDigits: 2 })}
                                                </span>
                                            )}
                                        </div>
                                        <div className="grid grid-cols-1 md:grid-cols-2 gap-x-4 gap-y-1 text-xs text-slate-600">
                                            {record.jenis_sumbangan && (
                                                <div><span className="font-medium text-slate-700">Jenis Sumbangan:</span> {record.jenis_sumbangan}</div>
                                            )}
                                            {record.tujuan_sumbangan && (
                                                <div><span className="font-medium text-slate-700">Tujuan:</span> {record.tujuan_sumbangan}</div>
                                            )}
                                            {record.bantuan_lain && (
                                                <div><span className="font-medium text-slate-700">Bantuan Lain:</span> {record.bantuan_lain}</div>
                                            )}
                                            {record.pekerjaan && (
                                                <div><span className="font-medium text-slate-700">Pekerjaan:</span> {record.pekerjaan}</div>
                                            )}
                                            {record.bil_isi_rumah && (
                                                <div><span className="font-medium text-slate-700">Bil. Isi Rumah:</span> {record.bil_isi_rumah}</div>
                                            )}
                                            {record.pendapatan_isi_rumah && (
                                                <div><span className="font-medium text-slate-700">Pendapatan:</span> RM {Number(record.pendapatan_isi_rumah).toLocaleString('en-MY')}</div>
                                            )}
                                            {record.lokaliti && (
                                                <div><span className="font-medium text-slate-700">Lokaliti:</span> {record.lokaliti}</div>
                                            )}
                                            {record.kadun && (
                                                <div><span className="font-medium text-slate-700">KADUN:</span> {record.kadun}</div>
                                            )}
                                        </div>
                                    </div>
                                ))}
                            </div>
                            )}
                        </div>
                    )}

                    {/* Form Actions */}
                    <div className="order-[10] flex items-center justify-end space-x-3 pb-6">
                        <button
                            type="button"
                            onClick={() => window.history.back()}
                            className="px-6 py-2 border border-slate-300 text-slate-700 rounded-lg hover:bg-slate-50 transition-colors"
                        >
                            Batal
                        </button>
                        <button
                            type="submit"
                            disabled={processing}
                            className="px-6 py-2 bg-slate-900 text-white rounded-lg hover:bg-slate-800 transition-colors disabled:opacity-50"
                        >
                            {processing ? 'Menyimpan...' : 'Simpan'}
                        </button>
                    </div>
                </form>
            </div>
        </AuthenticatedLayout>
    );
}
