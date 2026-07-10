import { createContext, useContext } from 'react';
import { tokens } from '../theme';

const PilihanrayaThemeContext = createContext(null);

export function usePilihanrayaTheme() {
    return useContext(PilihanrayaThemeContext);
}

/**
 * Module-local command-center wrapper. Light theme only — SISDA has no
 * dark mode. The context still exposes the token map so the pages don't
 * need to import the theme directly.
 */
export default function PilihanrayaShell({ title, subtitle, actions = null, children }) {
    const t = tokens();

    return (
        <PilihanrayaThemeContext.Provider value={{ t }}>
            <div className={t.page}>
                <div className="flex flex-wrap items-start justify-between gap-4 mb-6">
                    <div>
                        <h1 className={t.heading}>{title}</h1>
                        {subtitle && <p className={`${t.subheading} mt-1`}>{subtitle}</p>}
                    </div>
                    {actions && <div className="flex items-center gap-2">{actions}</div>}
                </div>
                {children}
            </div>
        </PilihanrayaThemeContext.Provider>
    );
}
