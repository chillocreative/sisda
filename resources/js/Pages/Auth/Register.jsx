import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { useState, useEffect } from 'react';
import { Info } from 'lucide-react';

export default function Register({ negeriList = [], bandarList = [], kadunList = [] }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        name: '',
        telephone: '',
        email: '',
        password: '',
        password_confirmation: '',
        role: 'user',
        negeri_id: '',
        bandar_id: '',
        kadun_id: '',
    });

    const [filteredBandar, setFilteredBandar] = useState([]);
    const [filteredKadun, setFilteredKadun] = useState([]);

    // Filter Bandar when Negeri changes
    useEffect(() => {
        if (data.negeri_id) {
            const filtered = bandarList.filter(b => b.negeri_id == data.negeri_id);
            setFilteredBandar(filtered);
            // Reset bandar and kadun if negeri changes
            if (data.bandar_id) {
                const bandarStillValid = filtered.some(b => b.id == data.bandar_id);
                if (!bandarStillValid) {
                    setData(prev => ({ ...prev, bandar_id: '', kadun_id: '' }));
                }
            }
        } else {
            setFilteredBandar([]);
            setData(prev => ({ ...prev, bandar_id: '', kadun_id: '' }));
        }
    }, [data.negeri_id]);

    // Filter KADUN when Bandar changes
    useEffect(() => {
        if (data.bandar_id) {
            const filtered = kadunList.filter(k => k.bandar_id == data.bandar_id);
            setFilteredKadun(filtered);
            // Reset kadun if bandar changes
            if (data.kadun_id) {
                const kadunStillValid = filtered.some(k => k.id == data.kadun_id);
                if (!kadunStillValid) {
                    setData(prev => ({ ...prev, kadun_id: '' }));
                }
            }
        } else {
            setFilteredKadun([]);
            setData(prev => ({ ...prev, kadun_id: '' }));
        }
    }, [data.bandar_id]);

    const handleNameChange = (e) => {
        setData('name', e.target.value.toUpperCase());
    };

    const handleTelephoneChange = (e) => {
        const value = e.target.value;
        // Only allow digits
        const digitsOnly = value.replace(/\D/g, '');
        setData('telephone', digitsOnly);
    };

    const submit = (e) => {
        e.preventDefault();
        post(route('register'), {
            onFinish: () => reset('password', 'password_confirmation'),
        });
    };

    return (
        <GuestLayout>
            <Head title="Daftar" />

            <div className="mb-6 text-center">
                <h2 className="text-2xl font-bold text-slate-900">Cipta Akaun</h2>
                <p className="text-sm text-slate-600">Masukkan butiran anda untuk bermula.</p>
            </div>

            {/* Info Alert */}
            <div className="mb-6 p-4 bg-sky-50 border border-sky-200 rounded-lg flex items-start space-x-3">
                <Info className="h-5 w-5 text-sky-600 mt-0.5 flex-shrink-0" />
                <div className="text-sm text-sky-800">
                    <p className="font-medium">Proses Kelulusan Diperlukan</p>
                    <p className="mt-1">Akaun anda akan menunggu kelulusan daripada pentadbir sebelum anda boleh log masuk.</p>
                </div>
            </div>

            <form onSubmit={submit} className="space-y-4">
                {/* Name */}
                <div>
                    <InputLabel htmlFor="name" value="Nama" className="text-slate-700" />
                    <TextInput
                        id="name"
                        name="name"
                        value={data.name}
                        className="mt-1 block w-full border-slate-300 focus:border-slate-400 focus:ring-slate-400 rounded-lg uppercase"
                        autoComplete="name"
                        isFocused={true}
                        onChange={handleNameChange}
                        required
                    />
                    <InputError message={errors.name} className="mt-2" />
                </div>

                {/* Telephone */}
                <div>
                    <InputLabel htmlFor="telephone" value="Nombor Telefon" className="text-slate-700" />
                    <TextInput
                        id="telephone"
                        type="tel"
                        name="telephone"
                        value={data.telephone}
                        className="mt-1 block w-full border-slate-300 focus:border-slate-400 focus:ring-slate-400 rounded-lg"
                        autoComplete="tel"
                        onChange={handleTelephoneChange}
                        placeholder="0123456789"
                        required
                    />
                    <p className="mt-1 text-xs text-slate-500">Hanya angka sahaja</p>
                    <InputError message={errors.telephone} className="mt-2" />
                </div>

                {/* Email */}
                <div>
                    <InputLabel htmlFor="email" value="Emel (Pilihan)" className="text-slate-700" />
                    <TextInput
                        id="email"
                        type="email"
                        name="email"
                        value={data.email}
                        className="mt-1 block w-full border-slate-300 focus:border-slate-400 focus:ring-slate-400 rounded-lg"
                        autoComplete="username"
                        onChange={(e) => setData('email', e.target.value)}
                    />
                    <InputError message={errors.email} className="mt-2" />
                </div>

                {/* Territory Section */}
                <div className="pt-4 border-t border-slate-200">
                    <h3 className="text-sm font-semibold text-slate-900 mb-3">Kawasan Anda</h3>
                    {/* Negeri */}
                    <div className="mb-4">
                        <InputLabel htmlFor="negeri_id" value="Negeri" className="text-slate-700" />
                        <select
                            id="negeri_id"
                            value={data.negeri_id}
                            onChange={(e) => setData('negeri_id', e.target.value)}
                            className="mt-1 block w-full border-slate-300 focus:border-slate-400 focus:ring-slate-400 rounded-lg shadow-sm"
                            required
                        >
                            <option value="">Pilih Negeri</option>
                            {negeriList.map((negeri) => (
                                <option key={negeri.id} value={negeri.id}>{negeri.nama}</option>
                            ))}
                        </select>
                        <InputError message={errors.negeri_id} className="mt-2" />
                    </div>

                    {/* Bandar */}
                    <div className="mb-4">
                        <InputLabel htmlFor="bandar_id" value="Bandar / Parlimen" className="text-slate-700" />
                        <select
                            id="bandar_id"
                            value={data.bandar_id}
                            onChange={(e) => setData('bandar_id', e.target.value)}
                            className="mt-1 block w-full border-slate-300 focus:border-slate-400 focus:ring-slate-400 rounded-lg shadow-sm"
                            required
                            disabled={!data.negeri_id}
                        >
                            <option value="">Pilih Bandar / Parlimen</option>
                            {filteredBandar.map((bandar) => (
                                <option key={bandar.id} value={bandar.id}>{bandar.nama}</option>
                            ))}
                        </select>
                        <InputError message={errors.bandar_id} className="mt-2" />
                    </div>

                    {/* KADUN */}
                    <div>
                        <InputLabel htmlFor="kadun_id" value="KADUN" className="text-slate-700" />
                        <select
                            id="kadun_id"
                            value={data.kadun_id}
                            onChange={(e) => setData('kadun_id', e.target.value)}
                            className="mt-1 block w-full border-slate-300 focus:border-slate-400 focus:ring-slate-400 rounded-lg shadow-sm"
                            required
                            disabled={!data.bandar_id}
                        >
                            <option value="">Pilih KADUN</option>
                            {filteredKadun.map((kadun) => (
                                <option key={kadun.id} value={kadun.id}>{kadun.nama}</option>
                            ))}
                        </select>
                        <InputError message={errors.kadun_id} className="mt-2" />
                    </div>
                </div>

                {/* Password */}
                <div className="pt-4 border-t border-slate-200">
                    <InputLabel htmlFor="password" value="Kata Laluan" className="text-slate-700" />
                    <TextInput
                        id="password"
                        type="password"
                        name="password"
                        value={data.password}
                        className="mt-1 block w-full border-slate-300 focus:border-slate-400 focus:ring-slate-400 rounded-lg"
                        autoComplete="new-password"
                        onChange={(e) => setData('password', e.target.value)}
                        required
                    />
                    <InputError message={errors.password} className="mt-2" />
                </div>

                {/* Password Confirmation */}
                <div>
                    <InputLabel
                        htmlFor="password_confirmation"
                        value="Sahkan Kata Laluan"
                        className="text-slate-700"
                    />
                    <TextInput
                        id="password_confirmation"
                        type="password"
                        name="password_confirmation"
                        value={data.password_confirmation}
                        className="mt-1 block w-full border-slate-300 focus:border-slate-400 focus:ring-slate-400 rounded-lg"
                        autoComplete="new-password"
                        onChange={(e) =>
                            setData('password_confirmation', e.target.value)
                        }
                        required
                    />
                    <InputError
                        message={errors.password_confirmation}
                        className="mt-2"
                    />
                </div>

                {/* Submit */}
                <div className="pt-6 flex items-center justify-between">
                    <Link
                        href={route('login')}
                        className="text-sm text-slate-600 hover:text-slate-900 underline decoration-slate-300 underline-offset-4"
                    >
                        Sudah mendaftar?
                    </Link>

                    <PrimaryButton
                        className="ms-4 bg-slate-900 hover:bg-slate-800 focus:bg-slate-800 active:bg-slate-900 rounded-lg px-6"
                        disabled={processing}
                    >
                        {processing ? 'Mendaftar...' : 'Daftar'}
                    </PrimaryButton>
                </div>
            </form>
        </GuestLayout>
    );
}
