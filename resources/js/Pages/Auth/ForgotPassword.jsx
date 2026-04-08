import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, Link, useForm } from '@inertiajs/react';

export default function ForgotPassword({ status }) {
    const { data, setData, post, processing, errors } = useForm({
        telephone: '',
    });

    const submit = (e) => {
        e.preventDefault();
        post(route('password.forgot'));
    };

    return (
        <GuestLayout>
            <Head title="Lupa Kata Laluan" />

            <div className="mb-6 text-center">
                <h2 className="text-2xl font-bold text-slate-900">Lupa Kata Laluan</h2>
                <p className="text-sm text-slate-600 mt-2">
                    Masukkan nombor telefon anda dan kami akan menghantar kata laluan baharu melalui WhatsApp.
                </p>
            </div>

            {status && (
                <div className="mb-4 rounded-lg bg-emerald-50 border border-emerald-200 p-4 text-sm text-emerald-700">
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
                        isFocused={true}
                        placeholder="0123456789"
                        onChange={(e) => setData('telephone', e.target.value)}
                    />

                    <InputError message={errors.telephone} className="mt-2" />
                </div>

                <div className="mt-6">
                    <PrimaryButton
                        className="w-full justify-center bg-slate-900 hover:bg-slate-800 focus:bg-slate-800 active:bg-slate-900 rounded-lg"
                        disabled={processing}
                    >
                        {processing ? 'Menghantar...' : 'Hantar Kata Laluan Baharu'}
                    </PrimaryButton>
                </div>

                <div className="mt-4 text-center">
                    <Link
                        href={route('login')}
                        className="text-sm text-slate-600 hover:text-slate-900 underline decoration-slate-300 underline-offset-4"
                    >
                        Kembali ke Log Masuk
                    </Link>
                </div>
            </form>
        </GuestLayout>
    );
}
