import { Link } from '@inertiajs/react';
import { useState } from 'react';
import { route } from '../routes';
import { initials } from './InitialsAvatar';
import StatusTag from './StatusTag';

/** Faol xodimda belgi chiqmaydi: faqat e’tibor talab qiladiganlari ajralib turadi. */
export const validityTags = {
    expiring: { tone: 'warning', label: 'Tugashiga yaqin' },
    expired: { tone: 'danger', label: 'Muddati tugagan' },
    upcoming: { tone: 'info', label: 'Hali boshlanmagan' },
};

/** "Muddat" filtri: Xodimlar va Sinxronizatsiya sahifalarida bir xil. */
export const validityOptions = [
    { value: 'expired', label: 'Muddati tugagan' },
    { value: 'expiring', label: 'Tugashiga yaqin' },
    { value: 'upcoming', label: 'Hali boshlanmagan' },
    { value: 'active', label: 'Faol' },
];

/** Kartochka kamida 150px: ustunlar soni joyga qarab o‘zi tanlanadi. */
export const EMPLOYEE_CARD_GRID = 'grid grid-cols-[repeat(auto-fill,minmax(150px,1fr))] gap-3';

/** Yuz bo‘lsa — kichik nusxasi (ko‘ringanda yuklanadi), bo‘lmasa yoki ochilmasa — bosh harflar. */
function EmployeePhoto({ name, src }) {
    const [failed, setFailed] = useState(false);

    if (!src || failed) {
        return (
            <span
                aria-hidden="true"
                className="flex aspect-square w-full items-center justify-center rounded-lg bg-surface-muted text-3xl font-extrabold tracking-wide text-ink-subtle"
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
            className="aspect-square w-full rounded-lg bg-surface-muted object-cover"
        />
    );
}

/**
 * Xodim kartochkasi: katta yuz rasmi, ism, lavozim va muddat holati. Bosilsa xodim sahifasi ochiladi.
 * employee: { id, name, position, validity, face_url }
 */
export default function EmployeeCard({ employee }) {
    const tag = validityTags[employee.validity];

    return (
        <Link
            href={route('admin.employees.show', employee.id)}
            className="flex h-full flex-col rounded-xl border border-line bg-surface p-2 transition-colors duration-150 ease-productive hover:border-brand-600/40 hover:bg-surface-muted"
        >
            <EmployeePhoto name={employee.name} src={employee.face_url} />
            <span className="mt-2 line-clamp-2 text-sm font-bold leading-snug text-ink">
                {employee.name}
            </span>
            <span className="mt-0.5 truncate text-xs font-medium text-ink-muted">
                {employee.position || 'Lavozim kiritilmagan'}
            </span>
            {tag ? (
                <span className="mt-1.5">
                    <StatusTag tone={tag.tone}>{tag.label}</StatusTag>
                </span>
            ) : null}
        </Link>
    );
}
