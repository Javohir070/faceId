import InputError from './InputError';

export default function FormField({
    label,
    htmlFor,
    error,
    required = false,
    hint,
    className = '',
    children,
}) {
    return (
        <div className={className}>
            {label && (
                <label htmlFor={htmlFor} className="label">
                    {label}
                    {required && <span className="ml-1 text-negative" aria-hidden="true">*</span>}
                </label>
            )}
            {children}
            {hint && !error && (
                <p className="mt-1.5 text-xs font-semibold text-ink-subtle">{hint}</p>
            )}
            <InputError message={error} />
        </div>
    );
}
