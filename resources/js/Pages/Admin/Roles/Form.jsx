import { useForm } from '@inertiajs/react';
import { Button, Checkbox, Input } from 'antd';
import AdminLayout from '../../../Layouts/AdminLayout';
import { route } from '../../../routes';
import FormCard from '../../../Components/FormCard';
import FormField from '../../../Components/FormField';
import InputError from '../../../Components/InputError';

const ACTIONS = [
    { key: 'create', label: 'Qo‘shish' },
    { key: 'edit', label: 'Tahrirlash' },
    { key: 'delete', label: 'O‘chirish' },
];

function rowNames(row) {
    return [row.name, ...ACTIONS.map((action) => row[action.key]).filter(Boolean)];
}

/**
 * Bo‘lim × amal jadvali. Amal belgilansa ko‘rish ham belgilanadi (ko‘rishsiz sahifaga kirib bo‘lmaydi);
 * ko‘rish olib tashlansa shu qatordagi amallar ham olib tashlanadi.
 */
function PermissionMatrix({ groups, selected, onChange }) {
    const isOn = (name) => selected.includes(name);
    const groupEntries = Object.entries(groups).filter(([, rows]) => rows.length > 0);
    const allNames = groupEntries.flatMap(([, rows]) => rows.flatMap(rowNames));

    const toggleView = (row, checked) => onChange(checked
        ? [...new Set([...selected, row.name])]
        : selected.filter((name) => !rowNames(row).includes(name)));

    const toggleAction = (row, name, checked) => onChange(checked
        ? [...new Set([...selected, row.name, name])]
        : selected.filter((item) => item !== name));

    return (
        <div>
            <div className="mb-2 flex flex-wrap items-center justify-between gap-2">
                <p className="text-sm font-bold text-ink">Ruxsatlar</p>
                <div className="flex gap-2">
                    <Button size="small" className="!font-semibold" onClick={() => onChange(allNames)}>
                        Hammasini belgilash
                    </Button>
                    <Button size="small" className="!font-semibold" onClick={() => onChange([])}>
                        Tozalash
                    </Button>
                </div>
            </div>
            <div className="overflow-x-auto rounded-lg border border-line">
                <table className="w-full min-w-[560px] text-sm">
                    <thead>
                        <tr className="text-xs font-bold text-ink-muted">
                            <th className="px-4 py-2.5 text-left">Bo‘lim</th>
                            <th className="w-24 px-2 py-2.5 text-center">Ko‘rish</th>
                            {ACTIONS.map((action) => (
                                <th key={action.key} className="w-24 px-2 py-2.5 text-center">{action.label}</th>
                            ))}
                        </tr>
                    </thead>
                    {groupEntries.map(([group, rows]) => (
                        <tbody key={group}>
                            <tr>
                                <th
                                    colSpan={ACTIONS.length + 2}
                                    className="border-t border-line bg-surface-muted px-4 py-2 text-left text-xs font-extrabold uppercase tracking-wide text-ink-subtle"
                                >
                                    {group}
                                </th>
                            </tr>
                            {rows.map((row) => (
                                <tr key={row.id} className="transition-colors duration-150 ease-productive hover:bg-surface-muted/60">
                                    <td className="border-t border-line px-4 py-2.5 font-semibold text-ink">{row.name}</td>
                                    <td className="border-t border-line px-2 py-2.5 text-center">
                                        <Checkbox
                                            checked={isOn(row.name)}
                                            aria-label={`${row.name}: ko‘rish`}
                                            onChange={(e) => toggleView(row, e.target.checked)}
                                        />
                                    </td>
                                    {ACTIONS.map((action) => (
                                        <td key={action.key} className="border-t border-line px-2 py-2.5 text-center">
                                            {row[action.key] ? (
                                                <Checkbox
                                                    checked={isOn(row[action.key])}
                                                    aria-label={`${row.name}: ${action.label}`}
                                                    onChange={(e) => toggleAction(row, row[action.key], e.target.checked)}
                                                />
                                            ) : (
                                                <span aria-hidden="true" className="text-ink-subtle">—</span>
                                            )}
                                        </td>
                                    ))}
                                </tr>
                            ))}
                        </tbody>
                    ))}
                </table>
            </div>
        </div>
    );
}

export default function Form({ role, permissions = {} }) {
    const isEdit = !!role;
    const { data, setData, post, put, processing, errors } = useForm({
        name: role?.name || '',
        permissions: role?.permissions || [],
    });

    const submit = (e) => {
        e.preventDefault();
        if (isEdit) put(route('admin.roles.update', role.id));
        else post(route('admin.roles.store'));
    };

    return (
        <AdminLayout title={isEdit ? 'Rolni tahrirlash' : "Rol qo'shish"}>
            <FormCard
                title={isEdit ? 'Rolni tahrirlash' : "Yangi rol"}
                subtitle="Rol nomi va ruxsatlar to‘plamini belgilang."
                backHref={route('admin.roles.index')}
                onSubmit={submit}
                processing={processing}
                cancelHref={route('admin.roles.index')}
            >
                <FormField label="Rol nomi" error={errors.name} required className="max-w-md">
                    <Input
                        value={data.name}
                        status={errors.name ? 'error' : undefined}
                        onChange={(e) => setData('name', e.target.value)}
                    />
                </FormField>

                <PermissionMatrix
                    groups={permissions}
                    selected={data.permissions}
                    onChange={(names) => setData('permissions', names)}
                />
                <InputError message={errors.permissions} />
            </FormCard>
        </AdminLayout>
    );
}
