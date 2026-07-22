export function linkClassName(active, { collapsed = false, nested = false } = {}) {
    return `group flex min-h-11 items-center rounded-lg text-sm font-bold transition duration-200 ease-productive ${
        collapsed ? 'justify-center px-0' : nested ? 'gap-3 px-3 pl-10' : 'gap-3 px-3'
    } ${
        active
            ? 'bg-brand-50 text-brand-700'
            : 'text-ink-muted hover:bg-line/60 hover:text-brand-700'
    }`;
}

export function iconClassName(active) {
    return `text-base ${active ? 'text-brand-600' : 'text-ink-subtle group-hover:text-brand-600'}`;
}
