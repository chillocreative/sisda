import { Head, Link } from '@inertiajs/react';

/**
 * Senarai papan markah TERSIAR sahaja. Papan draf tidak pernah muncul di sini,
 * jadi halaman ini hanya mendedahkan apa yang pemilik pilih untuk siarkan.
 */
export default function ScoreboardIndex({ boards = [] }) {
    return (
        <>
            <Head title="Papan Markah" />
            <div className="min-h-screen bg-slate-50 py-10 px-4">
                <div className="max-w-3xl mx-auto">
                    <h1 className="text-2xl font-bold text-slate-900 mb-1">Papan Markah</h1>
                    <p className="text-sm text-slate-500 mb-6">Papan markah pilihan raya yang disiarkan.</p>

                    {boards.length === 0 ? (
                        <p className="text-sm text-slate-500">Tiada papan markah disiarkan buat masa ini.</p>
                    ) : (
                        <ul className="space-y-2">
                            {boards.map((b) => (
                                <li key={b.kod}>
                                    <Link href={b.url} className="block rounded-xl bg-white border border-slate-200 px-4 py-3 hover:border-slate-300">
                                        <span className="text-xs font-mono text-slate-500">{b.kod}</span>
                                        <span className="block text-sm font-semibold text-slate-900">{b.nama}</span>
                                        <span className="block text-xs text-slate-500">{b.title}</span>
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    )}
                </div>
            </div>
        </>
    );
}
