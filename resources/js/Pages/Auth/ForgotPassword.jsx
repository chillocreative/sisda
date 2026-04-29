import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { CheckCircle2, MessageCircle, X } from 'lucide-react';

export default function ForgotPassword({ status }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        telephone: '',
    });

    const [showSuccess, setShowSuccess] = useState(false);

    useEffect(() => {
        if (status) {
            setShowSuccess(true);
            reset('telephone');
        }
    }, [status]);

    useEffect(() => {
        if (!showSuccess) return;
        const onKey = (e) => {
            if (e.key === 'Escape') setShowSuccess(false);
        };
        window.addEventListener('keydown', onKey);
        return () => window.removeEventListener('keydown', onKey);
    }, [showSuccess]);

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

            {showSuccess && (
                <div
                    className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-sm px-4"
                    onClick={() => setShowSuccess(false)}
                    role="dialog"
                    aria-modal="true"
                    aria-labelledby="forgot-password-success-title"
                >
                    <div
                        className="relative w-full max-w-md rounded-2xl bg-white p-6 sm:p-8 shadow-2xl ring-1 ring-slate-200 animate-in fade-in zoom-in-95"
                        onClick={(e) => e.stopPropagation()}
                    >
                        <button
                            type="button"
                            onClick={() => setShowSuccess(false)}
                            className="absolute top-3 right-3 rounded-full p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600 transition"
                            aria-label="Tutup"
                        >
                            <X className="h-5 w-5" />
                        </button>

                        <div className="flex flex-col items-center text-center">
                            <div className="flex h-16 w-16 items-center justify-center rounded-full bg-emerald-100 mb-4">
                                <CheckCircle2 className="h-10 w-10 text-emerald-600" />
                            </div>

                            <h3
                                id="forgot-password-success-title"
                                className="text-xl font-bold text-slate-900"
                            >
                                Berjaya Dihantar!
                            </h3>

                            <p className="mt-2 text-sm text-slate-600">
                                {status || 'Kata laluan baharu telah dihantar ke WhatsApp anda.'}
                            </p>

                            <div className="mt-5 w-full rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-left">
                                <div className="flex items-start gap-3">
                                    <MessageCircle className="h-5 w-5 flex-shrink-0 text-emerald-600 mt-0.5" />
                                    <div className="text-sm text-emerald-800">
                                        <p className="font-semibold">Sila semak WhatsApp anda</p>
                                        <p className="mt-1 text-emerald-700">
                                            Kami telah menghantar kata laluan baharu ke nombor WhatsApp anda. Gunakan kata laluan tersebut untuk log masuk dan tukar kata laluan baharu selepas log masuk.
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <div className="mt-6 flex w-full flex-col-reverse sm:flex-row gap-2 sm:gap-3">
                                <button
                                    type="button"
                                    onClick={() => setShowSuccess(false)}
                                    className="flex-1 rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50 transition"
                                >
                                    Tutup
                                </button>
                                <Link
                                    href={route('login')}
                                    className="flex-1 rounded-lg bg-slate-900 px-4 py-2.5 text-center text-sm font-semibold text-white hover:bg-slate-800 transition"
                                >
                                    Ke Log Masuk
                                </Link>
                            </div>
                        </div>
                    </div>
                </div>
            )}
        </GuestLayout>
    );
}
