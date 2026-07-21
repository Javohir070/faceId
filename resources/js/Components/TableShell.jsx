import { EmptyState } from './PageHeader';

export default function TableShell({
    empty = false,
    emptyState,
    createHref,
    createLabel,
    footer,
    children,
}) {
    const hasFooter = Boolean(footer);

    return (
        <div className="table-shell" style={{ padding: '20px 24px' }}>
            {empty ? (
                emptyState || (
                    <EmptyState createHref={createHref} createLabel={createLabel} />
                )
            ) : (
                <>
                    <div className="overflow-x-auto" style={{ borderRadius: '12px' }}>{children}</div>
                    {hasFooter ? <div className="table-footer">{footer}</div> : null}
                </>
            )}
        </div>
    );
}
