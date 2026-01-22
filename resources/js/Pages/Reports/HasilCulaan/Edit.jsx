import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm } from '@inertiajs/react';
import { ArrowLeft, Upload, X, Loader2, Image as ImageIcon } from 'lucide-react';
import { useState, useEffect } from 'react';
import axios from 'axios';
import SearchableSelect from '@/Components/SearchableSelect';

export default function Edit({
    hasilCulaan,
    bangsaList,
    negeriList,
    bandarList,
    kadunList,
    daerahMengundiList,
    jenisSumbanganList,
    tujuanSumbanganList,
    bantuanLainList,
    keahlianPartiList,
    kecenderunganPolitikList
}) {
    const [uploadProgress, setUploadProgress] = useState(0);
    const [isUploading, setIsUploading] = useState(false);
    const [previewUrl, setPreviewUrl] = useState(
        hasilCulaan.kad_pengenalan ? `/storage/${hasilCulaan.kad_pengenalan}` : null
    );
    const [newFile, setNewFile] = useState(null);
    const [parlimenOptions, setParlimenOptions] = useState([]);
    const [loadingParlimen, setLoadingParlimen] = useState(false);
    const [kadunOptions, setKadunOptions] = useState([]);
    const [loadingKadun, setLoadingKadun] = useState(false);
    const [daerahMengundiOptions, setDaerahMengundiOptions] = useState([]);
    const [loadingDaerahMengundi, setLoadingDaerahMengundi] = useState(false);
    const [mpkkOptions, setMpkkOptions] = useState([]);
    const [loadingMpkk, setLoadingMpkk] = useState(false);

    const { data, setData, post, processing, errors } = useForm({
        _method: 'PUT',
        nama: hasilCulaan.nama || '',
        no_ic: hasilCulaan.no_ic || '',
        umur: hasilCulaan.umur || '',
        no_tel: hasilCulaan.no_tel || '',
        bangsa: hasilCulaan.bangsa || '',
        alamat: hasilCulaan.alamat || '',
        poskod: hasilCulaan.poskod || '',
        negeri: hasilCulaan.negeri || '',
        bandar: hasilCulaan.bandar || '',
        parlimen: hasilCulaan.parlimen || '',
        kadun: hasilCulaan.kadun || '',
        mpkk: hasilCulaan.mpkk || '',
        daerah_mengundi: hasilCulaan.daerah_mengundi || '',
        bil_isi_rumah: hasilCulaan.bil_isi_rumah || '',
        pendapatan_isi_rumah: hasilCulaan.pendapatan_isi_rumah || '',
        pekerjaan: hasilCulaan.pekerjaan || '',
        pemilik_rumah: hasilCulaan.pemilik_rumah || '',
        jenis_sumbangan: hasilCulaan.jenis_sumbangan || '',
        tujuan_sumbangan: hasilCulaan.tujuan_sumbangan || '',
        bantuan_lain: hasilCulaan.bantuan_lain || '',
        keahlian_parti: hasilCulaan.keahlian_parti || '',
        kecenderungan_politik: hasilCulaan.kecenderungan_politik || '',
        kad_pengenalan: null,
        nota: hasilCulaan.nota || '',
    });

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
                    setNewFile(file);
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
        setNewFile(null);
    };

    const handleSubmit = (e) => {
        e.preventDefault();
        post(route('reports.hasil-culaan.update', hasilCulaan.id));
    };

    return (
        <AuthenticatedLayout>
            <Head title="Edit Hasil Culaan" />

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
                        <h1 className="text-2xl font-bold text-slate-900">Edit Hasil Culaan</h1>
                        <p className="text-sm text-slate-600 mt-1">Kemaskini maklumat yang diperlukan</p>
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
                                    onChange={(e) => setData('nama', e.target.value)}
                                    className="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400"
                                    required
                                />
                                {errors.nama && <p className="text-sm text-rose-600 mt-1">{errors.nama}</p>}
                            </div>

                            <div>
                                <label className="block text-sm font-medium text-slate-700 mb-1">
                                    No. IC <span className="text-rose-500">*</span>
                                </label>
                                <input
                                    type="text"
                                    value={data.no_ic}
                                    onChange={handleIcChange}
                                    placeholder="000000000000"
                                    className="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400"
                                    required
                                />
                                {errors.no_ic && <p className="text-sm text-rose-600 mt-1">{errors.no_ic}</p>}
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
                                    className="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400"
                                    required
                                />
                                {errors.umur && <p className="text-sm text-rose-600 mt-1">{errors.umur}</p>}
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
                                    className="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400"
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
                            </div>
                        </div>
                    </div>

                    {/* Household Information */}
                    <div className="bg-white rounded-xl border border-slate-200 p-6">
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
                                </label>
                                <input
                                    type="number"
                                    value={data.pendapatan_isi_rumah}
                                    onChange={(e) => setData('pendapatan_isi_rumah', e.target.value)}
                                    min="0"
                                    step="0.01"
                                    className="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400"
                                />
                                {errors.pendapatan_isi_rumah && <p className="text-sm text-rose-600 mt-1">{errors.pendapatan_isi_rumah}</p>}
                            </div>

                            <div>
                                <label className="block text-sm font-medium text-slate-700 mb-1">
                                    Pekerjaan <span className="text-rose-500">*</span>
                                </label>
                                <input
                                    type="text"
                                    value={data.pekerjaan}
                                    onChange={(e) => setData('pekerjaan', e.target.value)}
                                    className="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400"
                                    required
                                />
                                {errors.pekerjaan && <p className="text-sm text-rose-600 mt-1">{errors.pekerjaan}</p>}
                            </div>

                            <div>
                                <label className="block text-sm font-medium text-slate-700 mb-1">
                                    Pemilik Rumah <span className="text-rose-500">*</span>
                                </label>
                                <select
                                    value={data.pemilik_rumah}
                                    onChange={(e) => setData('pemilik_rumah', e.target.value)}
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
                        </div>
                    </div>

                    {/* Assistance & Political Information */}
                    <div className="bg-white rounded-xl border border-slate-200 p-6">
                        <h2 className="text-lg font-semibold text-slate-900 mb-4">Maklumat Bantuan & Politik</h2>
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label className="block text-sm font-medium text-slate-700 mb-1">
                                    Jenis Sumbangan
                                </label>
                                <select
                                    value={data.jenis_sumbangan}
                                    onChange={(e) => setData('jenis_sumbangan', e.target.value)}
                                    className="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400"
                                >
                                    <option value="">Pilih Jenis Sumbangan</option>
                                    {jenisSumbanganList.map((item) => (
                                        <option key={item.id} value={item.nama}>
                                            {item.nama}
                                        </option>
                                    ))}
                                </select>
                                {errors.jenis_sumbangan && <p className="text-sm text-rose-600 mt-1">{errors.jenis_sumbangan}</p>}
                            </div>

                            <div>
                                <label className="block text-sm font-medium text-slate-700 mb-1">
                                    Tujuan Sumbangan
                                </label>
                                <select
                                    value={data.tujuan_sumbangan}
                                    onChange={(e) => setData('tujuan_sumbangan', e.target.value)}
                                    className="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400"
                                >
                                    <option value="">Pilih Tujuan Sumbangan</option>
                                    {tujuanSumbanganList.map((item) => (
                                        <option key={item.id} value={item.nama}>
                                            {item.nama}
                                        </option>
                                    ))}
                                </select>
                                {errors.tujuan_sumbangan && <p className="text-sm text-rose-600 mt-1">{errors.tujuan_sumbangan}</p>}
                            </div>

                            <div>
                                <label className="block text-sm font-medium text-slate-700 mb-1">
                                    Bantuan Lain
                                </label>
                                <select
                                    value={data.bantuan_lain}
                                    onChange={(e) => setData('bantuan_lain', e.target.value)}
                                    className="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400"
                                >
                                    <option value="">Pilih Bantuan Lain</option>
                                    {bantuanLainList.map((item) => (
                                        <option key={item.id} value={item.nama}>
                                            {item.nama}
                                        </option>
                                    ))}
                                </select>
                                {errors.bantuan_lain && <p className="text-sm text-rose-600 mt-1">{errors.bantuan_lain}</p>}
                            </div>

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

                    {/* Documents & Notes */}
                    <div className="bg-white rounded-xl border border-slate-200 p-6">
                        <h2 className="text-lg font-semibold text-slate-900 mb-4">Dokumen & Nota</h2>
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
                                            <>
                                                <button
                                                    type="button"
                                                    onClick={handleRemoveFile}
                                                    className="absolute top-2 right-2 p-1.5 bg-rose-600 text-white rounded-lg hover:bg-rose-700 transition-colors"
                                                >
                                                    <X className="h-4 w-4" />
                                                </button>
                                                <div className="mt-2 flex items-center justify-between">
                                                    {newFile ? (
                                                        <p className="text-sm text-slate-600 flex items-center">
                                                            <ImageIcon className="h-4 w-4 mr-1" />
                                                            {newFile.name}
                                                        </p>
                                                    ) : (
                                                        <p className="text-sm text-slate-600 flex items-center">
                                                            <ImageIcon className="h-4 w-4 mr-1" />
                                                            Imej sedia ada
                                                        </p>
                                                    )}
                                                    <label
                                                        htmlFor="kad-pengenalan-upload"
                                                        className="text-sm text-sky-600 hover:text-sky-700 cursor-pointer underline"
                                                    >
                                                        Tukar imej
                                                    </label>
                                                    <input
                                                        type="file"
                                                        accept="image/*"
                                                        onChange={handleFileUpload}
                                                        className="hidden"
                                                        id="kad-pengenalan-upload"
                                                        disabled={isUploading}
                                                    />
                                                </div>
                                            </>
                                        )}
                                    </div>
                                )}
                                {errors.kad_pengenalan && <p className="text-sm text-rose-600 mt-1">{errors.kad_pengenalan}</p>}
                            </div>

                            <div>
                                <label className="block text-sm font-medium text-slate-700 mb-1">
                                    Nota
                                </label>
                                <textarea
                                    value={data.nota}
                                    onChange={(e) => setData('nota', e.target.value)}
                                    rows="4"
                                    placeholder="Catatan tambahan..."
                                    className="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400"
                                />
                                {errors.nota && <p className="text-sm text-rose-600 mt-1">{errors.nota}</p>}
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
                            {processing ? 'Mengemaskini...' : 'Kemaskini'}
                        </button>
                    </div>
                </form>
            </div >
        </AuthenticatedLayout >
    );
}
