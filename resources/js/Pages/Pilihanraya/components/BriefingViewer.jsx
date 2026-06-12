import { useState } from 'react';
import axios from 'axios';
import { FileSpreadsheet, FileText, Loader2 } from 'lucide-react';
import { usePilihanrayaTheme } from './PilihanrayaShell';

async function downloadBlob(routeName, payload, filename) {
    const res = await axios.post(route(routeName), payload, { responseType: 'blob', timeout: 60000 });
    const url = URL.createObjectURL(res.data);
    const link = document.createElement('a');
    link.href = url;
    link.download = filename;
    document.body.appendChild(link);
    link.click();
    link.remove();
    URL.revokeObjectURL(url);
}

export default function BriefingViewer({ briefing, seatScores }) {
    const { t } = usePilihanrayaTheme();
    const [downloading, setDownloading] = useState(null);
    const [downloadError, setDownloadError] = useState(null);

    if (!briefing) return null;

    const seksyen = Array.isArray(briefing.seksyen) ? briefing.seksyen : [];
    const tindakanSegera = Array.isArray(briefing.tindakan_segera) ? briefing.tindakan_segera : [];

    const exportAs = async (kind) => {
        setDownloading(kind);
        setDownloadError(null);
        const date = new Date().toISOString().slice(0, 10);
        try {
            if (kind === 'excel') {
                await downloadBlob('pilihanraya.briefing.export.excel', { briefing, seatScores }, `taklimat-pilihanraya-${date}.xlsx`);
            } else {
                await downloadBlob('pilihanraya.briefing.export.pdf', { briefing, seatScores }, `taklimat-pilihanraya-${date}.pdf`);
            }
        } catch {
            setDownloadError('Muat turun gagal. Sila cuba semula.');
        } finally {
            setDownloading(null);
        }
    };

    return (
        <div className={t.card}>
            <div className="flex flex-wrap items-start justify-between gap-3 mb-4">
                <div>
                    <h3 className={`text-lg font-semibold ${t.text}`}>{briefing.tajuk}</h3>
                    <p className={`${t.subtext} text-sm`}>{briefing.tarikh}</p>
                </div>
                <div className="flex gap-2">
                    <button type="button" onClick={() => exportAs('excel')} disabled={downloading !== null} className={t.buttonSecondary}>
                        {downloading === 'excel' ? <Loader2 className="h-4 w-4 animate-spin" /> : <FileSpreadsheet className="h-4 w-4" />}
                        Excel
                    </button>
                    <button type="button" onClick={() => exportAs('pdf')} disabled={downloading !== null} className={t.buttonSecondary}>
                        {downloading === 'pdf' ? <Loader2 className="h-4 w-4 animate-spin" /> : <FileText className="h-4 w-4" />}
                        PDF
                    </button>
                </div>
            </div>

            {downloadError && <div className={`${t.banner} mb-4`}>{downloadError}</div>}

            <div className={`space-y-5 divide-y ${t.divider}`}>
                {seksyen.map((section, i) => (
                    <div key={i} className={i > 0 ? 'pt-5' : ''}>
                        <h4 className={`text-base font-semibold ${t.text} mb-2`}>{i + 1}. {section.tajuk}</h4>
                        <p className={`${t.subtext} text-sm whitespace-pre-line`}>{section.kandungan}</p>
                        {Array.isArray(section.bullet_points) && section.bullet_points.length > 0 && (
                            <ul className={`${t.subtext} text-sm mt-2 list-disc list-inside space-y-1`}>
                                {section.bullet_points.map((point, j) => (
                                    <li key={j}>{point}</li>
                                ))}
                            </ul>
                        )}
                    </div>
                ))}
            </div>

            {briefing.kesimpulan && (
                <div className={`mt-5 pt-5 border-t ${t.border}`}>
                    <h4 className={`text-base font-semibold ${t.text} mb-2`}>Kesimpulan</h4>
                    <p className={`${t.subtext} text-sm whitespace-pre-line`}>{briefing.kesimpulan}</p>
                </div>
            )}

            {tindakanSegera.length > 0 && (
                <div className={`mt-5 ${t.banner}`}>
                    <p className="font-semibold mb-1">Tindakan Segera</p>
                    <ul className="list-disc list-inside space-y-1">
                        {tindakanSegera.map((action, i) => (
                            <li key={i}>{action}</li>
                        ))}
                    </ul>
                </div>
            )}
        </div>
    );
}
