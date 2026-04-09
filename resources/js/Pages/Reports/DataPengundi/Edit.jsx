import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm, usePage, router } from '@inertiajs/react';
import { ArrowLeft, Trash2 } from 'lucide-react';

import { useState, useEffect, useRef } from 'react';
import axios from 'axios';
import SearchableSelect from '@/Components/SearchableSelect';

export default function Edit({
    dataPengundi,
    bangsaList,

    negeriList,
    bandarList,
    parlimenList,
    kadunList,
    daerahMengundiList,
    keahlianPartiList,
    kecenderunganPolitikList,
    lokalitiList,
    editHistories = []
}) {
    const { auth } = usePage().props;
    const [isDeceased, setIsDeceased] = useState(dataPengundi.is_deceased || false);
    const [togglingDeceased, setTogglingDeceased] = useState(false);
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
    const icDebounceRef = useRef(null);
    const icWrapperRef = useRef(null);
    const pendingVoterData = useRef(null);
    const { data, setData, put, processing, errors } = useForm({
        nama: dataPengundi.nama || '',
        no_ic: dataPengundi.no_ic || '',
        umur: dataPengundi.umur || '',
        no_tel: dataPengundi.no_tel || '',
        bangsa: dataPengundi.bangsa || '',

        alamat: dataPengundi.alamat || '',
        poskod: dataPengundi.poskod || '',
        negeri: dataPengundi.negeri || '',
        bandar: dataPengundi.bandar || '',
        parlimen: dataPengundi.parlimen || '',
        kadun: dataPengundi.kadun || '',
        mpkk: dataPengundi.mpkk || '',
        daerah_mengundi: dataPengundi.daerah_mengundi || '',
        lokaliti: dataPengundi.lokaliti || '',
        keahlian_parti: dataPengundi.keahlian_parti || '',
        kecenderungan_politik: dataPengundi.kecenderungan_politik || '',
        status_pengundi: dataPengundi.status_pengundi || '',
    });

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
        setData({
            ...data,
            no_ic: voter.no_ic,
            nama: voter.nama || data.nama,
            bangsa: voter.bangsa || data.bangsa,
            negeri: voter.negeri ? toTitleCase(voter.negeri) : data.negeri,
            parlimen: parlimenMatch ? parlimenMatch.nama : data.parlimen,
        });
        setShowSuggestions(false);
        setIcSuggestions([]);
    };

    // Auto-lookup voter database when IC is 12 digits - populate Maklumat Peribadi & Kawasan Mengundi
    useEffect(() => {
        if (data.no_ic.length === 12 && data.no_ic !== dataPengundi.no_ic) {
            axios.get(route('api.voter.search-ic'), { params: { ic: data.no_ic } })
                .then(res => {
                    if (res.data) {
                        pendingVoterData.current = {
                            parlimen: res.data.parlimen || null,
                            kadun: res.data.kadun || null,
                            daerah_mengundi: res.data.daerah_mengundi || null,
                            lokaliti: res.data.lokaliti || null,
                        };
                        const parlimenMatch = parlimenList.find(p => p.nama.toLowerCase() === (res.data.parlimen || '').toLowerCase());
                        setData(prev => ({
                            ...prev,
                            nama: res.data.nama || prev.nama,
                            bangsa: res.data.bangsa || prev.bangsa,
                            negeri: res.data.negeri ? toTitleCase(res.data.negeri) : prev.negeri,
                            parlimen: parlimenMatch ? parlimenMatch.nama : prev.parlimen,
                        }));
                    }
                })
                .catch(() => {});
        }
    }, [data.no_ic]);

    const handlePostcodeChange = (e) => {
        const value = e.target.value.replace(/\D/g, '');
        if (value.length <= 5) {
            setData('poskod', value);
        }
    };

    const handleIcChange = (e) => {
        const value = e.target.value;

        // Only allow digits
        const digitsOnly = value.replace(/\D/g, '');

        // Limit to 12 digits
        if (digitsOnly.length > 12) return;

        setData('no_ic', digitsOnly);

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

        // Clear age if IC is empty or too short
        if (digitsOnly.length < 6) {
            setData('umur', '');
            return;
        }

        // Auto-calculate age if IC has at least 6 digits (YYMMDD)
        const year = digitsOnly.substring(0, 2);
        const month = digitsOnly.substring(2, 4);
        const day = digitsOnly.substring(4, 6);

        // Determine century (00-25 = 2000s, 26-99 = 1900s)
        const fullYear = parseInt(year) <= 25 ? 2000 + parseInt(year) : 1900 + parseInt(year);

        // Calculate age
        const birthDate = new Date(fullYear, parseInt(month) - 1, parseInt(day));
        const today = new Date();
        let age = today.getFullYear() - birthDate.getFullYear();
        const monthDiff = today.getMonth() - birthDate.getMonth();

        if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthDate.getDate())) {
            age--;
        }

        // Set age if valid
        if (age >= 0 && age <= 150) {
            setData('umur', age.toString());
        }
    };

    const handleSubmit = (e) => {
        e.preventDefault();
        put(route('reports.data-pengundi.update', dataPengundi.id));
    };

    return (
        <AuthenticatedLayout>
            <Head title="Edit Data Pengundi" />

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
                        <h1 className="text-2xl font-bold text-slate-900">Edit Data Pengundi</h1>
                        <p className="text-sm text-slate-600 mt-1">Kemaskini maklumat pengundi</p>
                    </div>
                </div>

                {/* Kematian Toggle */}
                <div className={`rounded-xl border p-4 flex items-center justify-between ${isDeceased ? 'border-rose-300 bg-rose-50' : 'border-slate-200 bg-white'}`}>
                    <div>
                        <span className={`text-sm font-medium ${isDeceased ? 'text-rose-700' : 'text-slate-700'}`}>
                            {isDeceased ? 'Ditandakan sebagai kematian — semua medan dikunci' : 'Tandakan sebagai kematian'}
                        </span>
                    </div>
                    <label className="flex items-center gap-2 cursor-pointer">
                        <span className={`text-sm font-medium ${isDeceased ? 'text-rose-600' : 'text-slate-500'}`}>Kematian</span>
                        <button
                            type="button"
                            disabled={togglingDeceased}
                            onClick={() => {
                                setTogglingDeceased(true);
                                axios.post(route('reports.data-pengundi.toggle-deceased', dataPengundi.id))
                                    .then(res => setIsDeceased(res.data.is_deceased))
                                    .finally(() => setTogglingDeceased(false));
                            }}
                            className={`relative inline-flex h-6 w-11 items-center rounded-full transition-colors ${isDeceased ? 'bg-rose-500' : 'bg-slate-300'} ${togglingDeceased ? 'opacity-50' : ''}`}
                        >
                            <span className={`inline-block h-4 w-4 rounded-full bg-white transition-transform ${isDeceased ? 'translate-x-6' : 'translate-x-1'}`} />
                        </button>
                    </label>
                </div>

                {/* Form */}
                <form onSubmit={handleSubmit} className={`space-y-6 ${isDeceased ? 'opacity-50 pointer-events-none select-none' : ''}`}>
                    {/* Personal Information */}
                    <div className="bg-white rounded-xl border border-slate-200 p-6">
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
                                    <div className="absolute z-10 w-full mt-1 bg-white border border-slate-200 rounded-lg shadow-lg max-h-48 overflow-y-auto">
                                        {icSuggestions.map((voter) => (
                                            <button
                                                key={voter.no_ic}
                                                type="button"
                                                onClick={() => handleSuggestionClick(voter)}
                                                className="w-full text-left px-4 py-2.5 hover:bg-slate-50 transition-colors border-b border-slate-100 last:border-0"
                                            >
                                                <span className="font-mono text-sm font-medium text-slate-900">{voter.no_ic}</span>
                                                <span className="ml-2 text-sm text-slate-500">{voter.nama}</span>
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
                                    onChange={(e) => setData('nama', e.target.value)}
                                    className="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400"
                                    required
                                />
                                {errors.nama && <p className="text-sm text-rose-600 mt-1">{errors.nama}</p>}
                            </div>

                            <div>
                                <label className="block text-sm font-medium text-slate-700 mb-1">
                                    Umur <span className="text-rose-500">*</span>
                                </label>
                                <input
                                    type="number"
                                    value={data.umur}
                                    onChange={(e) => setData('umur', e.target.value)}
                                    min="1"
                                    max="150"
                                    readOnly
                                    className="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 bg-slate-50"
                                    required
                                />
                                {errors.umur && <p className="text-sm text-rose-600 mt-1">{errors.umur}</p>}
                                <p className="text-xs text-slate-500 mt-1">Dikira automatik dari No. IC</p>
                            </div>

                            <div>
                                <label className="block text-sm font-medium text-slate-700 mb-1">
                                    No. Telefon <span className="text-rose-500">*</span>
                                </label>
                                <input
                                    type="tel"
                                    value={data.no_tel}
                                    onChange={(e) => setData('no_tel', e.target.value)}
                                    placeholder="0123456789"
                                    className="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 placeholder:text-slate-300"
                                    required
                                />
                                {errors.no_tel && <p className="text-sm text-rose-600 mt-1">{errors.no_tel}</p>}
                            </div>

                            <div>
                                <label className="block text-sm font-medium text-slate-700 mb-1">
                                    Bangsa <span className="text-rose-500">*</span>
                                </label>
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
                                {errors.bangsa && <p className="text-sm text-rose-600 mt-1">{errors.bangsa}</p>}
                            </div>


                        </div>
                    </div>

                    {/* Address Information */}
                    <div className="bg-white rounded-xl border border-slate-200 p-6">
                        <h2 className="text-lg font-semibold text-slate-900 mb-4">Maklumat Alamat</h2>
                        <div className="grid grid-cols-1 gap-4">
                            <div>
                                <label className="block text-sm font-medium text-slate-700 mb-1">
                                    Alamat <span className="text-rose-500">*</span>
                                </label>
                                <textarea
                                    value={data.alamat}
                                    onChange={(e) => setData('alamat', e.target.value)}
                                    rows="3"
                                    className="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400"
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
                                        className="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400"
                                        required
                                    />
                                    {errors.poskod && <p className="text-sm text-rose-600 mt-1">{errors.poskod}</p>}
                                </div>

                                <div>
                                    <label className="block text-sm font-medium text-slate-700 mb-1">
                                        Negeri <span className="text-rose-500">*</span>
                                    </label>
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
                                        className="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 bg-slate-50"
                                        placeholder="Pilih Poskod terlebih dahulu"
                                    />
                                    {errors.bandar && <p className="text-sm text-rose-600 mt-1">{errors.bandar}</p>}
                                </div>
                            </div>
                        </div>
                    </div>

                    {/* Maklumat Kawasan Mengundi */}
                    <div className="bg-white rounded-xl border border-slate-200 p-6">
                        <h2 className="text-lg font-semibold text-slate-900 mb-4">Maklumat Kawasan Mengundi</h2>
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label className="block text-sm font-medium text-slate-700 mb-1">
                                        Parlimen <span className="text-rose-500">*</span>
                                    </label>
                                    <select
                                        value={data.parlimen}
                                        onChange={(e) => setData({...data, parlimen: e.target.value, kadun: '', mpkk: '', daerah_mengundi: '', lokaliti: ''})}
                                        className="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400"
                                        required
                                    >
                                        <option value="">Pilih Parlimen</option>
                                        {parlimenList.map((item) => (
                                            <option key={item.id} value={item.nama}>{item.nama}</option>
                                        ))}
                                    </select>
                                    {errors.parlimen && <p className="text-sm text-rose-600 mt-1">{errors.parlimen}</p>}
                                </div>

                                <div>
                                    <label className="block text-sm font-medium text-slate-700 mb-1">
                                        KADUN <span className="text-rose-500">*</span>
                                    </label>
                                    <select
                                        value={data.kadun}
                                        onChange={(e) => setData({...data, kadun: e.target.value, mpkk: ''})}
                                        className="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400"
                                        required
                                    >
                                        <option value="">{loadingKadun ? "Memuat..." : "Pilih KADUN"}</option>
                                        {kadunOptions.map((item) => (
                                            <option key={item.id} value={item.nama}>{item.nama}</option>
                                        ))}
                                    </select>
                                    {errors.kadun && <p className="text-sm text-rose-600 mt-1">{errors.kadun}</p>}
                                </div>

                                <div>
                                    <label className="block text-sm font-medium text-slate-700 mb-1">
                                        MPKK <span className="text-rose-500">*</span>
                                    </label>
                                    <select
                                        value={data.mpkk}
                                        onChange={(e) => setData('mpkk', e.target.value)}
                                        className="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400"
                                        required
                                    >
                                        <option value="">{loadingMpkk ? "Memuat..." : "Pilih MPKK"}</option>
                                        {mpkkOptions.map((item) => (
                                            <option key={item.id} value={item.nama}>{item.nama}</option>
                                        ))}
                                    </select>
                                    {errors.mpkk && <p className="text-sm text-rose-600 mt-1">{errors.mpkk}</p>}
                                </div>

                                <div>
                                    <label className="block text-sm font-medium text-slate-700 mb-1">
                                        Daerah Mengundi <span className="text-rose-500">*</span>
                                    </label>
                                    <select
                                        value={data.daerah_mengundi}
                                        onChange={(e) => setData({...data, daerah_mengundi: e.target.value, lokaliti: ''})}
                                        className="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400"
                                        required
                                    >
                                        <option value="">{loadingDaerahMengundi ? "Memuat..." : "Pilih Daerah Mengundi"}</option>
                                        {daerahMengundiOptions.map((item) => (
                                            <option key={item.id} value={item.nama}>{item.nama}</option>
                                        ))}
                                    </select>
                                    {errors.daerah_mengundi && <p className="text-sm text-rose-600 mt-1">{errors.daerah_mengundi}</p>}
                                </div>

                                <div>
                                    <label className="block text-sm font-medium text-slate-700 mb-1">
                                        Lokaliti
                                    </label>
                                    <select
                                        value={data.lokaliti}
                                        onChange={(e) => setData('lokaliti', e.target.value)}
                                        className="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400"
                                    >
                                        <option value="">{loadingLokaliti ? "Memuat..." : "Pilih Lokaliti"}</option>
                                        {lokalitiOptions.map((item) => (
                                            <option key={item.id} value={item.nama}>{item.nama}</option>
                                        ))}
                                    </select>
                                    {errors.lokaliti && <p className="text-sm text-rose-600 mt-1">{errors.lokaliti}</p>}
                                </div>
                        </div>
                    </div>

                    {/* Status Pengundi */}
                    <div className="bg-white rounded-xl border border-slate-200 p-6">
                        <h2 className="text-lg font-semibold text-slate-900 mb-4">Status Pengundi</h2>
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-2">
                            {[
                                'Pemilih Bertukar Alamat (Keluar)',
                                'Hilang Layak Pengundi Awam',
                                'Pertukaran Kepada Lokaliti Awam',
                            ].map((item) => (
                                <label key={item} className="flex items-center space-x-2 cursor-pointer">
                                    <input
                                        type="checkbox"
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
                    <div className="bg-white rounded-xl border border-slate-200 p-6">
                        <h2 className="text-lg font-semibold text-slate-900 mb-4">Maklumat Politik</h2>
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label className="block text-sm font-medium text-slate-700 mb-1">
                                    Keanggotaan Parti <span className="text-rose-500">*</span>
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
                                    Kecenderungan Politik <span className="text-rose-500">*</span>
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

                    {/* Form Actions */}
                    <div className="flex items-center justify-end space-x-3 pb-6">
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
                </form >

                {/* Edit History */}
                {editHistories.length > 0 && (
                    <div className="bg-white rounded-xl border border-slate-200 p-6 mt-6">
                        <h2 className="text-lg font-semibold text-slate-900 mb-4">Sejarah Pengemaskinian</h2>
                        <div className="space-y-3">
                            {editHistories.map((history) => (
                                <div key={history.id} className="flex items-start justify-between border-b border-slate-100 pb-3 last:border-0 last:pb-0">
                                    <div>
                                        <p className="text-sm font-medium text-slate-700">
                                            {history.action === 'created' ? 'Dicipta' : 'Dikemaskini'}
                                            <span className="ml-2 font-normal text-slate-500">oleh {history.user?.name || '-'}</span>
                                        </p>
                                        <p className="text-xs text-slate-400 mt-0.5">
                                            {new Date(history.created_at).toLocaleDateString('ms-MY', { day: 'numeric', month: 'long', year: 'numeric' })}
                                            {' '}
                                            {new Date(history.created_at).toLocaleTimeString('ms-MY', { hour: '2-digit', minute: '2-digit' })}
                                        </p>
                                        {history.changes && (
                                            <div className="mt-1">
                                                {Object.entries(history.changes).slice(0, 3).map(([field, vals]) => (
                                                    <p key={field} className="text-xs text-slate-500">
                                                        <span className="font-medium">{field}</span>: {vals.old || '-'} → {vals.new || '-'}
                                                    </p>
                                                ))}
                                                {Object.keys(history.changes).length > 3 && (
                                                    <p className="text-xs text-slate-400">+{Object.keys(history.changes).length - 3} lagi perubahan</p>
                                                )}
                                            </div>
                                        )}
                                    </div>
                                    {auth.user.role === 'super_admin' && (
                                        <button
                                            onClick={() => router.delete(route('edit-history.destroy', history.id), { preserveScroll: true })}
                                            className="p-1 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded"
                                            title="Padam sejarah"
                                        >
                                            <Trash2 className="h-3.5 w-3.5" />
                                        </button>
                                    )}
                                </div>
                            ))}
                        </div>
                    </div>
                )}
            </div >
        </AuthenticatedLayout >
    );
}
