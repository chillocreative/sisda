import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import SearchableSelect from '@/Components/SearchableSelect';
import { useState, useEffect, useRef } from 'react';
import axios from 'axios';

export default function Create({
    bangsaList,

    negeriList,
    bandarList,
    parlimenList,
    kadunList,
    daerahMengundiList,
    keahlianPartiList,
    kecenderunganPolitikList,
    lokalitiList,
    prefill
}) {
    const { data, setData, post, processing, errors } = useForm({
        nama: prefill?.nama || '',
        no_ic: prefill?.no_ic || '',
        umur: '',
        no_tel: '',
        bangsa: prefill?.bangsa || '',

        alamat: '',
        poskod: '',
        negeri: prefill?.negeri || '',
        bandar: '',
        parlimen: prefill?.parlimen || '',
        kadun: prefill?.kadun || '',
        mpkk: '',
        daerah_mengundi: prefill?.daerah_mengundi || '',
        lokaliti: prefill?.lokaliti || '',
        keahlian_parti: '',
        kecenderungan_politik: '',
    });

    const [parlimenOptions, setParlimenOptions] = useState([]);
    const [loadingParlimen, setLoadingParlimen] = useState(false);
    const [kadunOptions, setKadunOptions] = useState([]);
    const [loadingKadun, setLoadingKadun] = useState(false);
    const [daerahMengundiOptions, setDaerahMengundiOptions] = useState([]);
    const [loadingDaerahMengundi, setLoadingDaerahMengundi] = useState(false);
    const [mpkkOptions, setMpkkOptions] = useState([]);
    const [loadingMpkk, setLoadingMpkk] = useState(false);
    const [icSuggestions, setIcSuggestions] = useState([]);
    const [showSuggestions, setShowSuggestions] = useState(false);
    const icDebounceRef = useRef(null);
    const icWrapperRef = useRef(null);

    // Fetch Parlimen options when Negeri changes
    useEffect(() => {
        const fetchParlimen = async () => {
            if (!data.negeri) {
                setParlimenOptions([]);
                return;
            }

            setLoadingParlimen(true);
            try {
                const response = await axios.get(route('api.parlimen.by-negeri'), {
                    params: { negeri: data.negeri }
                });
                setParlimenOptions(response.data);
            } catch (error) {
                console.error('Error fetching Parlimen:', error);
                setParlimenOptions([]);
            } finally {
                setLoadingParlimen(false);
            }
        };

        fetchParlimen();
    }, [data.negeri]);

    // Fetch KADUN options when Parlimen changes
    useEffect(() => {
        const fetchKadun = async () => {
            if (!data.parlimen) {
                setKadunOptions([]);
                return;
            }

            setLoadingKadun(true);
            try {
                const response = await axios.get(route('api.kadun.by-bandar'), {
                    params: { bandar: data.parlimen }
                });
                setKadunOptions(response.data);
            } catch (error) {
                console.error('Error fetching KADUN:', error);
                setKadunOptions([]);
            } finally {
                setLoadingKadun(false);
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

    // Fetch Daerah Mengundi options when Parlimen changes
    useEffect(() => {
        const fetchDaerahMengundi = async () => {
            if (!data.parlimen) {
                setDaerahMengundiOptions([]);
                return;
            }

            setLoadingDaerahMengundi(true);
            try {
                const response = await axios.get(route('api.daerah-mengundi.by-bandar'), {
                    params: { bandar: data.parlimen }
                });
                setDaerahMengundiOptions(response.data);
            } catch (error) {
                console.error('Error fetching Daerah Mengundi:', error);
                setDaerahMengundiOptions([]);
            } finally {
                setLoadingDaerahMengundi(false);
            }
        };

        fetchDaerahMengundi();
    }, [data.parlimen]);

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
                            parlimen: postcodeData.bandar_nama || '',
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

    useEffect(() => {
        if (prefill?.no_ic && prefill.no_ic.length === 12) {
            const ic = prefill.no_ic;
            const fullYear = parseInt(ic.substring(0, 2)) <= 25
                ? 2000 + parseInt(ic.substring(0, 2))
                : 1900 + parseInt(ic.substring(0, 2));
            const birth = new Date(fullYear, parseInt(ic.substring(2, 4)) - 1, parseInt(ic.substring(4, 6)));
            const today = new Date();
            let age = today.getFullYear() - birth.getFullYear();
            if (today.getMonth() < birth.getMonth() ||
                (today.getMonth() === birth.getMonth() && today.getDate() < birth.getDate())) age--;
            if (age >= 0 && age <= 150) setData('umur', age.toString());
        }
    }, []);

    const handleSuggestionClick = (voter) => {
        setData({
            ...data,
            no_ic: voter.no_ic,
            nama: voter.nama || data.nama,
            lokaliti: voter.lokaliti || data.lokaliti,
            daerah_mengundi: voter.daerah_mengundi || data.daerah_mengundi,
            kadun: voter.kadun || data.kadun,
            parlimen: voter.parlimen || data.parlimen,
            negeri: voter.negeri || data.negeri,
            bangsa: voter.bangsa || data.bangsa,
        });
        setShowSuggestions(false);
        setIcSuggestions([]);
    };

    const handlePostcodeChange = (e) => {
        const value = e.target.value.replace(/\D/g, '');
        if (value.length <= 5) {
            setData('poskod', value);
        }
    };

    const handleSubmit = (e) => {
        e.preventDefault();
        post(route('reports.data-pengundi.store'));
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

                {/* Form */}
                <form onSubmit={handleSubmit} className="space-y-6">
                    {/* Personal Information */}
                    <div className="bg-white rounded-xl border border-slate-200 p-6">
                        <h2 className="text-lg font-semibold text-slate-900 mb-4">Maklumat Peribadi</h2>
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
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
                                    className="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400"
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
                                    onChange={handleTelChange}
                                    placeholder="0123456789"
                                    className="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400"
                                    required
                                />
                                {errors.no_tel && <p className="text-sm text-rose-600 mt-1">{errors.no_tel}</p>}
                                <p className="text-xs text-slate-500 mt-1">Hanya angka sahaja</p>
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
                                    {bangsaList.map((item) => (
                                        <option key={item.id} value={item.nama}>
                                            {item.nama}
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
                                    onChange={(e) => handleTextChange('alamat', e.target.value)}
                                    rows="3"
                                    className="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 uppercase"
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

                                <div>
                                    <label className="block text-sm font-medium text-slate-700 mb-1">
                                        Parlimen <span className="text-rose-500">*</span>
                                    </label>
                                    <select
                                        value={data.parlimen}
                                        onChange={(e) => setData('parlimen', e.target.value)}
                                        className="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400"
                                        required
                                    >
                                        <option value="">{loadingParlimen ? "Memuat..." : "Pilih Parlimen"}</option>
                                        {parlimenOptions.map((item) => (
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
                                        onChange={(e) => setData('kadun', e.target.value)}
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
                                        onChange={(e) => setData('daerah_mengundi', e.target.value)}
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
                                        <option value="">Pilih Lokaliti</option>
                                        {lokalitiList && lokalitiList.map((item) => (
                                            <option key={item.id} value={item.nama}>{item.nama}</option>
                                        ))}
                                    </select>
                                    {errors.lokaliti && <p className="text-sm text-rose-600 mt-1">{errors.lokaliti}</p>}
                                </div>
                            </div>
                        </div>
                    </div>

                    {/* Political Information */}
                    <div className="bg-white rounded-xl border border-slate-200 p-6">
                        <h2 className="text-lg font-semibold text-slate-900 mb-4">Maklumat Politik</h2>
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label className="block text-sm font-medium text-slate-700 mb-1">
                                    Keahlian Parti
                                </label>
                                <select
                                    value={data.keahlian_parti}
                                    onChange={(e) => setData('keahlian_parti', e.target.value)}
                                    className="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400"
                                >
                                    <option value="">Pilih Keahlian Parti</option>
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
                                    Kecenderungan Politik
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
                </form>
            </div>
        </AuthenticatedLayout>
    );
}
