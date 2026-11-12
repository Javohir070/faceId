import { CheckOutlined, CloseOutlined, FileTextOutlined } from '@ant-design/icons';
import { Link } from '@inertiajs/react';
import { Popover } from 'antd';
import { useState } from 'react';
import { route } from '../../routes';
import { initials } from '../InitialsAvatar';

const PHOTO_SIZES = {
    sm: 'h-10 w-10 text-xs',
    md: 'h-12 w-12 text-sm',
    lg: 'h-24 w-24 text-2xl',
};

/** Dumaloq yuz rasmi; rasm bo‘lmasa yoki ochilmasa — bosh harflar. */
export function Photo({ name, src, size }) {
    const [failed, setFailed] = useState(false);
    const sizeClass = PHOTO_SIZES[size];

    if (!src || failed) {
        return (
            <span
                aria-hidden="true"
                className={`flex shrink-0 select-none items-center justify-center rounded-full bg-surface-muted font-extrabold tracking-wide text-ink-muted ${sizeClass}`}
            >
                {initials(name)}
            </span>
        );
    }

    return (
        <img
            src={src}
            alt=""
            loading="lazy"
            onError={() => setFailed(true)}
            className={`shrink-0 rounded-full bg-surface-muted object-cover ${sizeClass}`}
        />
    );
}

/**
 * Rang yolg‘iz qolmasin (DESIGN.md): ✓ — binoda, hujjat belgisi — binoda emas, lekin sababli, ✕ — binoda emas.
 * Sababli to‘q sariq: yashil (binoda) va qizil (sababsiz) bilan adashmasin.
 */
export function StatusBadge({ inside, excused = false }) {
    const [tone, icon] = inside
        ? ['bg-positive', <CheckOutlined key="icon" />]
        : excused
            ? ['bg-caution', <FileTextOutlined key="icon" />]
            : ['bg-negative', <CloseOutlined key="icon" />];

    return (
        <span
            aria-hidden="true"
            className={`absolute -bottom-1 -right-1 flex h-4 w-4 items-center justify-center rounded-full text-[9px] text-white ring-2 ring-surface ${tone}`}
        >
            {icon}
        </span>
    );
}

/** Izoh sababi: turi (Kechikdi / Erta ketdi / Kelmadi) va matni — ko‘zga tashlanadigan blok. */
export function ReasonList({ notes, className = '' }) {
    if (!notes.length) {
        return null;
    }

    return (
        <ul className={`space-y-1.5 ${className}`.trim()}>
            {notes.map((note, index) => (
                <li
                    // Izohning id si yuborilmaydi; bitta xodimning bugungi izohlari tartibi o‘zgarmaydi.
                    key={index}
                    className="flex items-start gap-2 rounded-lg border border-caution/40 bg-caution/10 px-2.5 py-1.5 text-left text-xs leading-snug"
                >
                    <FileTextOutlined aria-hidden="true" className="mt-0.5 shrink-0 text-caution" />
                    <span className="min-w-0 break-words">
                        <span className="font-extrabold text-ink">{note.type}</span>
                        {note.text ? <span className="text-ink">: {note.text}</span> : null}
                    </span>
                </li>
            ))}
        </ul>
    );
}

// Tailwind sinflari to‘liq yozilishi kerak (dinamik `bg-${tone}` purge'da yo‘qoladi).
export const TONE_BG = { positive: 'bg-positive', caution: 'bg-caution', negative: 'bg-negative' };
export const TONE_TEXT = { positive: 'text-positive', caution: 'text-caution', negative: 'text-negative' };

/** Holat matni: sababli bo‘lsa shu ham qo‘shiladi. */
export function statusText(employee) {
    if (employee.inside) {
        return 'Binoda';
    }

    return employee.excused ? 'Binoda emas · Sababli' : 'Binoda emas';
}

function Detail({ label, value }) {
    return (
        <div className="flex items-baseline justify-between gap-3">
            <dt className="shrink-0 text-ink-muted">{label}</dt>
            <dd className="min-w-0 text-right font-bold text-ink">{value}</dd>
        </div>
    );
}

/**
 * Kartochkadagi xodim: yashil ramka — binoda, qizil — binoda emas. Bosilsa tafsilot oynasi ochiladi.
 * employee: { id, name, position, group, inside, excused, unexcused, in, out, notes, face_url }
 */
export default function EmployeeBubble({ employee, canOpenProfile }) {
    const status = statusText(employee);
    const tone = employee.inside ? 'positive' : employee.excused ? 'caution' : 'negative';

    const content = (
        // Popover body’ga portal qilinadi, lekin React hodisasi kartochkaga ko‘tariladi — panel ochilmasin.
        <div className="w-64 max-w-full" onClick={(event) => event.stopPropagation()}>
            <div className="flex flex-col items-center text-center">
                <span className="relative">
                    <Photo name={employee.name} src={employee.face_url} size="lg" />
                    <span
                        aria-hidden="true"
                        className={`absolute bottom-1 right-1 h-4 w-4 rounded-full border-2 border-surface ${TONE_BG[tone]}`}
                    />
                </span>
                <p className="mt-3 text-sm font-extrabold leading-snug text-ink">{employee.name}</p>
                {employee.position ? <p className="mt-0.5 text-xs text-ink-muted">{employee.position}</p> : null}
                <p className={`mt-1.5 text-xs font-bold ${TONE_TEXT[tone]}`}>{status}</p>
            </div>

            <dl className="mt-3 space-y-1.5 border-t border-line pt-3 text-sm">
                {employee.group ? <Detail label="Guruh" value={employee.group} /> : null}
                <Detail label="Kirgan vaqti" value={employee.in ?? 'Bugun kelmagan'} />
                {employee.out ? <Detail label="Chiqqan vaqti" value={employee.out} /> : null}
            </dl>

            <ReasonList notes={employee.notes} className="mt-3" />

            {canOpenProfile ? (
                <Link
                    href={route('admin.employees.show', employee.id)}
                    className="mt-3 block rounded-lg border border-line py-1.5 text-center text-sm font-bold text-brand-600 transition-colors duration-200 hover:bg-surface-muted"
                >
                    Profilni ochish
                </Link>
            ) : null}
        </div>
    );

    return (
        <Popover content={content} trigger="click" placement="bottom">
            <button
                type="button"
                aria-label={`${employee.name} — ${status}`}
                onClick={(event) => event.stopPropagation()}
                className={`relative rounded-full ring-2 ring-offset-2 ring-offset-surface transition-transform duration-150 hover:scale-105 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-brand-600 ${employee.inside ? 'ring-positive' : 'ring-negative'}`}
            >
                <Photo name={employee.name} src={employee.face_url} size="sm" />
                <StatusBadge inside={employee.inside} excused={employee.excused} />
            </button>
        </Popover>
    );
}
