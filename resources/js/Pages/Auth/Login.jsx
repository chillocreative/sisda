import Checkbox from '@/Components/Checkbox';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, Link, useForm } from '@inertiajs/react';

export default function Login({ status, canResetPassword }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        telephone: '',
        password: '',
        remember: false,
    });

    const submit = (e) => {
        e.preventDefault();

        post(route('login'), {
            onFinish: () => reset('password'),
        });
    };

    return (
        <GuestLayout>
            <Head title="Log Masuk" />

            <div className="mb-6 text-center">
                <h2 className="text-2xl font-bold text-slate-900">Selamat Kembali</h2>
                <p className="text-sm text-slate-600">Sila masukkan butiran anda untuk log masuk.</p>
            </div>

            {status && (
                <div className="mb-4 text-sm font-medium text-emerald-600">
                    {status}
                </div>
            )}

            <form onSubmit={submit}>
                <div>
                    <InputLabel htmlFor="telephone" value="Nombor Telefon" className="text-slate-700" />

                    <TextInput
                        id="telephone"
                        type="tel"
                        name="telephone"
                        value={data.telephone}
                        className="mt-1 block w-full border-slate-300 focus:border-slate-400 focus:ring-slate-400 rounded-lg"
                        autoComplete="tel"
                        isFocused={true}
                        onChange={(e) => setData('telephone', e.target.value)}
                    />

                    <InputError message={errors.telephone} className="mt-2" />
                </div>

                <div className="mt-4">
                    <InputLabel htmlFor="password" value="Kata Laluan" className="text-slate-700" />

                    <TextInput
                        id="password"
                        type="password"
                        name="password"
                        value={data.password}
                        className="mt-1 block w-full border-slate-300 focus:border-slate-400 focus:ring-slate-400 rounded-lg"
                        autoComplete="current-password"
                        onChange={(e) => setData('password', e.target.value)}
                    />

                    <InputError message={errors.password} className="mt-2" />
                </div>

                <div className="mt-4 block">
                    <label className="flex items-center">
                        <Checkbox
                            name="remember"
                            checked={data.remember}
                            onChange={(e) =>
                                setData('remember', e.target.checked)
                            }
                            className="text-slate-600 focus:ring-slate-400 rounded"
                        />
                        <span className="ms-2 text-sm text-slate-600">
                            Ingat saya
                        </span>
                    </label>
                </div>

                <div className="mt-6 flex items-center justify-between">
                    <Link
                        href={route('register')}
                        className="text-sm text-slate-600 hover:text-slate-900 underline decoration-slate-300 underline-offset-4"
                    >
                        Belum mendaftar?
                    </Link>

                    <PrimaryButton className="ms-4 bg-slate-900 hover:bg-slate-800 focus:bg-slate-800 active:bg-slate-900 rounded-lg px-8" disabled={processing}>
                        Log Masuk
                    </PrimaryButton>
                </div>
            </form>
        </GuestLayout>
    );
}
