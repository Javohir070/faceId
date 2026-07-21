import PageHeader, { FormActions } from './PageHeader';

const widthClass = {
    sm: 'max-w-md',
    md: 'max-w-xl',
    lg: 'max-w-2xl',
    full: '',
};

export default function FormCard({
    title,
    subtitle,
    backHref,
    onSubmit,
    processing,
    cancelHref,
    submitLabel = 'Saqlash',
    width = 'full',
    children,
}) {
    return (
        <>
            {(title || backHref) && (
                <PageHeader
                    title={title}
                    subtitle={subtitle}
                    backHref={backHref}
                />
            )}
            <form
                onSubmit={onSubmit}
                className={`card space-y-5 ${widthClass[width] || ''}`.trim()}
            >
                {children}
                {cancelHref && (
                    <FormActions
                        cancelHref={cancelHref}
                        processing={processing}
                        submitLabel={submitLabel}
                    />
                )}
            </form>
        </>
    );
}
