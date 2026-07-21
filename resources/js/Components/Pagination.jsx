import { router } from '@inertiajs/react';
import { Pagination as AntPagination } from 'antd';

export default function Pagination({
    links = [],
    total,
    perPage,
    className = '',
}) {
    if (!links.length || links.length <= 3 || !total) {
        return null;
    }

    const pageLinks = links.slice(1, -1);
    const activeLink = pageLinks.find((link) => link.active);
    const current = Number(activeLink?.label) || 1;
    const pageSize = Math.max(Number(perPage) || 10, 1);

    const visitPage = (page) => {
        const target = pageLinks.find((link) => Number(link.label) === page);
        if (target?.url) {
            router.visit(target.url, { preserveScroll: true });
            return;
        }

        const fallback = new URL(window.location.href);
        fallback.searchParams.set('page', page);
        router.visit(`${fallback.pathname}${fallback.search}`, { preserveScroll: true });
    };

    return (
        <nav
            className={`flex flex-wrap items-center justify-end gap-3 ${className}`.trim()}
            aria-label="Sahifalash"
        >
            <AntPagination
                current={current}
                pageSize={pageSize}
                total={total}
                showSizeChanger={false}
                hideOnSinglePage
                onChange={visitPage}
            />
            <span className="inline-flex min-h-8 items-center rounded-md border border-line bg-surface px-3 text-xs font-semibold text-ink-muted">
                {pageSize}
                {' '}
                / sahifa
            </span>
        </nav>
    );
}
