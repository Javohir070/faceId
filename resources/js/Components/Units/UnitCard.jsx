import { RightOutlined } from '@ant-design/icons';
import { Tooltip } from 'antd';
import EmployeeBubble from './EmployeeBubble';

/**
 * Bitta guruh kartochkasi. `employees` — filtrdan o‘tganlar; Jami va Binoda esa guruhning to‘liq sonlari.
 * Kartochka bosilsa o‘ngdan guruh paneli ochiladi (`onOpen`), rasm bosilsa — xodim oynasi.
 * unit: { key, title, department, total, inside }
 */
export default function UnitCard({ unit, employees, canOpenProfile, onOpen }) {
    return (
        // Klaviatura uchun sarlavha tugmasi bor; butun kartochka bosilishi — sichqoncha uchun qulaylik.
        <section
            onClick={onOpen}
            className="group flex min-w-0 cursor-pointer flex-col rounded-xl border border-line bg-surface p-4 shadow-ambient transition-colors duration-150 hover:border-brand-600/50"
        >
            <Tooltip title={unit.title} placement="topLeft">
                <h2 className="min-w-0">
                    <button
                        type="button"
                        onClick={(event) => {
                            event.stopPropagation();
                            onOpen();
                        }}
                        className="flex w-full min-w-0 items-center gap-2 rounded text-left text-base font-extrabold text-ink focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-600"
                    >
                        <span className="truncate">{unit.title}</span>
                        <RightOutlined
                            aria-hidden="true"
                            className="ml-auto shrink-0 text-xs text-ink-subtle transition-colors duration-150 group-hover:text-brand-600"
                        />
                    </button>
                </h2>
            </Tooltip>

            <div className="mt-1.5 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-ink-muted">
                <span className="max-w-full truncate rounded border border-line px-1.5 py-0.5 text-[11px] font-bold uppercase tracking-wide">
                    {unit.department}
                </span>
                <span>
                    Jami — <span className="font-bold tabular-nums text-ink">{unit.total}</span> nafar
                </span>
                <span>
                    Binoda — <span className="font-bold tabular-nums text-positive">{unit.inside}</span> nafar
                </span>
            </div>

            <div className="mt-4 flex flex-wrap gap-2.5 p-0.5">
                {employees.map((employee) => (
                    <EmployeeBubble key={employee.id} employee={employee} canOpenProfile={canOpenProfile} />
                ))}
            </div>
        </section>
    );
}
