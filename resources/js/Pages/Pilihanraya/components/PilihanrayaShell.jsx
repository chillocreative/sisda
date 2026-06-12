import { createContext, useContext, useEffect, useState } from 'react';
import { Moon, Sun } from 'lucide-react';
import { tokens } from '../theme';

const PilihanrayaThemeContext = createContext(null);

export function usePilihanrayaTheme() {
    return useContext(PilihanrayaThemeContext);
}

/**
 * Module-local command-center wrapper. Dark theme by default, toggle
 * persisted in localStorage — scoped to Pilihanraya pages only, the
 * rest of SISDA is untouched.
 */
export default function PilihanrayaShell({ title, subtitle, actions = null, children }) {
    const [dark, setDark] = useState(() => {
        try {
            return localStorage.getItem('pilihanraya_theme') !== 'light';
        } catch {
            return true;
        }
    });

    useEffect(() => {
        try {
            localStorage.setItem('pilihanraya_theme', dark ? 'dark' : 'light');
        } catch {
            // storage unavailable — theme just won't persist
        }
    }, [dark]);

    const t = tokens(dark);

    return (
        <PilihanrayaThemeContext.Provider value={{ dark, setDark, t }}>
            <div className={t.page}>
                <div className="flex flex-wrap items-start justify-between gap-4 mb-6">
                    <div>
                        <h1 className={t.heading}>{title}</h1>
                        {subtitle && <p className={`${t.subheading} mt-1`}>{subtitle}</p>}
                    </div>
                    <div className="flex items-center gap-2">
                        {actions}
                        <button
                            type="button"
                            onClick={() => setDark(!dark)}
                            className={t.buttonSecondary}
                            title={dark ? 'Mod Cerah' : 'Mod Gelap'}
                        >
                            {dark ? <Sun className="h-4 w-4" /> : <Moon className="h-4 w-4" />}
                        </button>
                    </div>
                </div>
                {children}
            </div>
        </PilihanrayaThemeContext.Provider>
    );
}
