import { useState } from 'react';
import axios from 'axios';
import { Loader2, Send, Swords } from 'lucide-react';
import { usePilihanrayaTheme } from './PilihanrayaShell';

const SUGGESTIONS = [
    'Apa berlaku jika sokongan Melayu meningkat 5%?',
    'Apa berlaku jika keluar mengundi jatuh 10%?',
    'Kerusi mana boleh dimenangi dengan usaha tambahan?',
    'Apakah laluan terpantas ke arah kemenangan?',
    'Di mana sumber kempen patut difokuskan?',
];

const IMPAK_STYLE = {
    positif: 'bg-emerald-500/15 text-emerald-500 border border-emerald-500/40',
    negatif: 'bg-red-500/15 text-red-500 border border-red-500/40',
    neutral: 'bg-slate-500/15 text-slate-400 border border-slate-500/40',
};

export default function ScenarioChat({ filters, sliders }) {
    const { t } = usePilihanrayaTheme();
    const [question, setQuestion] = useState('');
    const [history, setHistory] = useState([]);
    const [loading, setLoading] = useState(false);

    const ask = async (q) => {
        const text = (q || question).trim();
        if (!text || loading) return;
        setLoading(true);
        setQuestion('');
        try {
            const res = await axios.post(route('pilihanraya.api.war-game'), {
                ...filters,
                question: text,
                sliders,
            }, { timeout: 130000 });
            setHistory((h) => [{ question: text, ...res.data }, ...h]);
        } catch {
            setHistory((h) => [{
                question: text,
                status: 'error',
                result: { answer: 'Ralat ketika menghubungi enjin AI. Sila cuba lagi.', affected_seats: [], recommendations: [] },
            }, ...h]);
        } finally {
            setLoading(false);
        }
    };

    return (
        <div className="space-y-4">
            <div className={t.card}>
                <h3 className={t.cardTitle}>War Gaming AI</h3>
                <p className={`${t.subtext} text-sm mb-4`}>
                    Tanya senario hipotetikal — AI menganalisis data culaan agregat dan skor kerusi semasa
                    (termasuk tetapan slider What-If anda) untuk menjawab.
                </p>
                <div className="flex flex-wrap gap-2 mb-4">
                    {SUGGESTIONS.map((s) => (
                        <button key={s} type="button" onClick={() => ask(s)} disabled={loading} className={`${t.buttonSecondary} text-xs`}>
                            {s}
                        </button>
                    ))}
                </div>
                <div className="flex gap-2">
                    <input
                        type="text"
                        value={question}
                        onChange={(e) => setQuestion(e.target.value)}
                        onKeyDown={(e) => e.key === 'Enter' && ask()}
                        placeholder="Contoh: Apa berlaku jika momentum pembangkang meningkat di kerusi bandar?"
                        maxLength={1000}
                        className={t.input}
                    />
                    <button type="button" onClick={() => ask()} disabled={loading || !question.trim()} className={t.buttonPrimary}>
                        {loading ? <Loader2 className="h-4 w-4 animate-spin" /> : <Send className="h-4 w-4" />}
                        Tanya
                    </button>
                </div>
            </div>

            {history.map((entry, i) => (
                <div key={i} className={t.card}>
                    <div className="flex items-start gap-3">
                        <Swords className="h-5 w-5 text-emerald-500 shrink-0 mt-1" />
                        <div className="min-w-0 flex-1">
                            <p className={`text-sm font-semibold ${t.text}`}>{entry.question}</p>
                            {entry.status === 'fallback' && (
                                <p className={`${t.banner} mt-2`}>AI tidak tersedia — sila semak Tetapan Claude AI.</p>
                            )}
                            <p className={`${t.subtext} text-sm mt-2 whitespace-pre-line`}>{entry.result.answer}</p>

                            {entry.result.affected_seats?.length > 0 && (
                                <div className="mt-3 flex flex-wrap gap-2">
                                    {entry.result.affected_seats.map((seat) => (
                                        <span key={seat.kerusi} className={`${t.badge} ${IMPAK_STYLE[seat.impak] || IMPAK_STYLE.neutral}`}>
                                            {seat.kerusi}{seat.anggaran_perubahan ? ` · ${seat.anggaran_perubahan}` : ''}
                                        </span>
                                    ))}
                                </div>
                            )}

                            {entry.result.recommendations?.length > 0 && (
                                <ul className={`${t.subtext} text-sm mt-3 list-disc list-inside space-y-1`}>
                                    {entry.result.recommendations.map((rec, j) => (
                                        <li key={j}>{rec}</li>
                                    ))}
                                </ul>
                            )}
                        </div>
                    </div>
                </div>
            ))}
        </div>
    );
}
