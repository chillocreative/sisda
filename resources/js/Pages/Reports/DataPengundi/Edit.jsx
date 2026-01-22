import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';

import { useState, useEffect } from 'react';
import axios from 'axios';
import AsyncSearchableSelectWithCallback from '@/Components/AsyncSearchableSelectWithCallback';

export default function Edit({
    dataPengundi,
    bangsaList,

    negeriList,
    bandarList,
    parlimenList,
    kadunList,
    daerahMengundiList,
    keahlianPartiList,
    kecenderunganPolitikList
}) {
    const [kadunOptions, setKadunOptions] = useState([]);
    const [loadingKadun, setLoadingKadun] = useState(false);
    const [daerahMengundiOptions, setDaerahMengundiOptions] = useState([]);
    const [loadingDaerahMengundi, setLoadingDaerahMengundi] = useState(false);
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
        daerah_mengundi: dataPengundi.daerah_mengundi || '',
        keahlian_parti: dataPengundi.keahlian_parti || '',
        kecenderungan_politik: dataPengundi.kecenderungan_politik || '',
    });

    // Fetch KADUN options when Bandar changes
    useEffect(() => {
        const fetchKadun = async () => {
            if (!data.bandar) {
                setKadunOptions([]);
                return;
            }

            setLoadingKadun(true);
            try {
                const response = await axios.get(route('api.kadun.by-bandar'), {
                    params: { bandar: data.bandar }
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
    }, [data.bandar]);

    // Fetch Daerah Mengundi options when Bandar changes
    useEffect(() => {
        const fetchDaerahMengundi = async () => {
            if (!data.bandar) {
                setDaerahMengundiOptions([]);
                return;
            }

            setLoadingDaerahMengundi(true);
            try {
                const response = await axios.get(route('api.daerah-mengundi.by-bandar'), {
                    params: { bandar: data.bandar }
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
    }, [data.bandar]);

    const handlePostcodeSelect = (postcodeData) => {
        if (postcodeData) {
            setData(prevData => ({
                ...prevData,
                poskod: postcodeData.postcode,
                negeri: postcodeData.negeri_nama || '',
                bandar: postcodeData.bandar_nama || '',
            }));
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
                                    placeholder="900101145678"
                                    maxLength="12"
                                    className="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400"
                                    required
                                />
                                {errors.no_ic && <p className="text-sm text-rose-600 mt-1">{errors.no_ic}</p>}
                                <p className="text-xs text-slate-500 mt-1">Hanya angka sahaja (contoh: 900101145678)</p>
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
                                    <AsyncSearchableSelectWithCallback
                                        value={data.poskod}
                                        onChange={(val) => setData('poskod', val)}
                                        onSelect={handlePostcodeSelect}
                                        routeName="api.postcodes.search-details"
                                        placeholder="00000"
                                        displayField="postcode"
                                    />
                                    {errors.poskod && <p className="text-sm text-rose-600 mt-1">{errors.poskod}</p>}
                                </div>

                                <div>
                                    <label className="block text-sm font-medium text-slate-700 mb-1">
                                        Negeri <span className="text-rose-500">*</span>
                                    </label>
                                    <input
                                        type="text"
                                        value={data.negeri}
                                        readOnly
                                        className="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 bg-slate-50"
                                        placeholder="Pilih Poskod terlebih dahulu"
                                    />
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
                                    <SearchableSelect
                                        value={data.parlimen}
                                        onChange={(val) => setData('parlimen', val)}
                                        options={parlimenList}
                                        placeholder="Pilih Parlimen"
                                    />
                                    {errors.parlimen && <p className="text-sm text-rose-600 mt-1">{errors.parlimen}</p>}
                                </div>

                                <div>
                                    <label className="block text-sm font-medium text-slate-700 mb-1">
                                        KADUN <span className="text-rose-500">*</span>
                                    </label>
                                    <SearchableSelect
                                        value={data.kadun}
                                        onChange={(val) => setData('kadun', val)}
                                        options={kadunList}
                                        placeholder="Pilih KADUN"
                                    />
                                    {errors.kadun && <p className="text-sm text-rose-600 mt-1">{errors.kadun}</p>}
                                </div>

                                <div>
                                    <label className="block text-sm font-medium text-slate-700 mb-1">
                                        Daerah Mengundi <span className="text-rose-500">*</span>
                                    </label>
                                    <SearchableSelect
                                        value={data.daerah_mengundi}
                                        onChange={(val) => setData('daerah_mengundi', val)}
                                        options={daerahMengundiList}
                                        placeholder="Pilih Daerah Mengundi"
                                    />
                                    {errors.daerah_mengundi && <p className="text-sm text-rose-600 mt-1">{errors.daerah_mengundi}</p>}
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
                </form >
            </div >
        </AuthenticatedLayout >
    );
}
