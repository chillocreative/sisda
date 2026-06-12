import { usePilihanrayaTheme } from './PilihanrayaShell';

export default function TabBar({ tabs, active, onChange }) {
    const { t } = usePilihanrayaTheme();

    return (
        <div className={t.tabBar}>
            {tabs.map((tab) => {
                const Icon = tab.icon;

                return (
                    <button
                        key={tab.key}
                        type="button"
                        onClick={() => onChange(tab.key)}
                        className={active === tab.key ? t.tabActive : t.tabInactive}
                    >
                        {Icon && <Icon className="h-4 w-4" />}
                        {tab.label}
                    </button>
                );
            })}
        </div>
    );
}
