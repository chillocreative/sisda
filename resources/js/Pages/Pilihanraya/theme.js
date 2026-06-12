// Module-local theme tokens for the Pilihanraya war room. SISDA has no
// global dark mode, so both variants are written as full literal class
// strings (picked up by the Tailwind content scan) and swapped at render
// time via PilihanrayaShell's theme context.

export const CHART_COLORS = {
    putih: '#10b981',
    hitam: '#ef4444',
    kelabu: '#94a3b8',
    blue: '#3b82f6',
    amber: '#f59e0b',
    violet: '#8b5cf6',
};

export function tokens(dark) {
    return dark
        ? {
            page: 'rounded-2xl bg-slate-950 p-4 sm:p-6 min-h-[80vh]',
            heading: 'text-2xl font-bold text-white',
            subheading: 'text-sm text-slate-400',
            card: 'bg-slate-900 rounded-xl border border-slate-800 p-6 shadow-sm',
            cardTight: 'bg-slate-900 rounded-xl border border-slate-800 p-4 shadow-sm',
            cardTitle: 'text-lg font-semibold text-slate-100 mb-4',
            text: 'text-slate-100',
            subtext: 'text-slate-400',
            kpiLabel: 'text-sm font-medium text-slate-400',
            kpiValue: 'text-3xl font-bold text-white mt-2',
            border: 'border-slate-800',
            divider: 'divide-slate-800',
            tabBar: 'flex flex-wrap gap-2 border-b border-slate-800 pb-3',
            tabActive: 'flex items-center gap-2 px-4 py-2 rounded-lg bg-emerald-600 text-white text-sm font-medium',
            tabInactive: 'flex items-center gap-2 px-4 py-2 rounded-lg text-slate-300 hover:bg-slate-800 text-sm font-medium',
            input: 'w-full px-3 py-2 bg-slate-900 border border-slate-700 text-slate-100 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 text-sm',
            label: 'block text-sm font-medium text-slate-300 mb-1',
            buttonPrimary: 'inline-flex items-center gap-2 px-4 py-2 bg-emerald-600 hover:bg-emerald-500 text-white rounded-lg text-sm font-medium disabled:opacity-50',
            buttonSecondary: 'inline-flex items-center gap-2 px-4 py-2 border border-slate-700 text-slate-200 hover:bg-slate-800 rounded-lg text-sm font-medium disabled:opacity-50',
            tableHead: 'text-left text-xs font-semibold uppercase tracking-wider text-slate-400 px-3 py-2',
            tableCell: 'px-3 py-2 text-sm text-slate-200',
            tableRow: 'border-t border-slate-800 hover:bg-slate-800/50',
            badge: 'inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium',
            banner: 'bg-amber-500/10 border border-amber-500/40 text-amber-300 rounded-lg px-4 py-3 text-sm',
            chartGrid: '#1e293b',
            chartTick: '#94a3b8',
            tooltip: { backgroundColor: '#0f172a', border: '1px solid #334155', borderRadius: '8px', color: '#f1f5f9', fontSize: '12px' },
        }
        : {
            page: 'rounded-2xl bg-slate-50 p-4 sm:p-6 min-h-[80vh]',
            heading: 'text-2xl font-bold text-slate-900',
            subheading: 'text-sm text-slate-500',
            card: 'bg-white rounded-xl border border-slate-200 p-6 shadow-sm',
            cardTight: 'bg-white rounded-xl border border-slate-200 p-4 shadow-sm',
            cardTitle: 'text-lg font-semibold text-slate-900 mb-4',
            text: 'text-slate-900',
            subtext: 'text-slate-500',
            kpiLabel: 'text-sm font-medium text-slate-600',
            kpiValue: 'text-3xl font-bold text-slate-900 mt-2',
            border: 'border-slate-200',
            divider: 'divide-slate-200',
            tabBar: 'flex flex-wrap gap-2 border-b border-slate-200 pb-3',
            tabActive: 'flex items-center gap-2 px-4 py-2 rounded-lg bg-slate-900 text-white text-sm font-medium',
            tabInactive: 'flex items-center gap-2 px-4 py-2 rounded-lg text-slate-600 hover:bg-slate-100 text-sm font-medium',
            input: 'w-full px-3 py-2 bg-white border border-slate-300 text-slate-900 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-slate-400 text-sm',
            label: 'block text-sm font-medium text-slate-700 mb-1',
            buttonPrimary: 'inline-flex items-center gap-2 px-4 py-2 bg-slate-900 hover:bg-slate-700 text-white rounded-lg text-sm font-medium disabled:opacity-50',
            buttonSecondary: 'inline-flex items-center gap-2 px-4 py-2 border border-slate-300 text-slate-700 hover:bg-slate-50 rounded-lg text-sm font-medium disabled:opacity-50',
            tableHead: 'text-left text-xs font-semibold uppercase tracking-wider text-slate-500 px-3 py-2',
            tableCell: 'px-3 py-2 text-sm text-slate-700',
            tableRow: 'border-t border-slate-200 hover:bg-slate-50',
            badge: 'inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium',
            banner: 'bg-amber-50 border border-amber-300 text-amber-800 rounded-lg px-4 py-3 text-sm',
            chartGrid: '#e2e8f0',
            chartTick: '#64748b',
            tooltip: { backgroundColor: '#ffffff', border: '1px solid #e2e8f0', borderRadius: '8px', color: '#0f172a', fontSize: '12px' },
        };
}

export const SEVERITY_STYLES = {
    high: { label: 'TINGGI', chip: 'bg-red-500/15 text-red-500 border border-red-500/40' },
    medium: { label: 'SEDERHANA', chip: 'bg-amber-500/15 text-amber-500 border border-amber-500/40' },
    low: { label: 'RENDAH', chip: 'bg-blue-500/15 text-blue-500 border border-blue-500/40' },
};
