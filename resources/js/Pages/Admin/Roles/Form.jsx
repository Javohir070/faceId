import { useForm } from '@inertiajs/react';
import { Checkbox, Input } from 'antd';
import AdminLayout from '../../../Layouts/AdminLayout';
import FormCard from '../../../Components/FormCard';
import FormField from '../../../Components/FormField';
import InputError from '../../../Components/InputError';

export default function Form({ role, permissions = {} }) {
    const isEdit = !!role;
    const { data, setData, post, put, processing, errors } = useForm({
        name: role?.name || '',
        permissions: role?.permissions || [],
    });

    const togglePermission = (name) => {
        setData(
            'permissions',
            data.permissions.includes(name)
                ? data.permissions.filter((item) => item !== name)
                : [...data.permissions, name],
        );
    };

    const submit = (e) => {
        e.preventDefault();
        if (isEdit) put(`/admin/roles/${role.id}`);
        else post('/admin/roles');
    };

    return (
        <AdminLayout title={isEdit ? 'Rolni tahrirlash' : "Rol qo'shish"}>
            <FormCard
                title={isEdit ? 'Rolni tahrirlash' : "Yangi rol"}
                subtitle="Rol nomi va ruxsatlar to‘plamini belgilang."
                backHref="/admin/roles"
                onSubmit={submit}
                processing={processing}
                cancelHref="/admin/roles"
            >
                <FormField label="Rol nomi" error={errors.name} required className="max-w-md">
                    <Input
                        value={data.name}
                        status={errors.name ? 'error' : undefined}
                        onChange={(e) => setData('name', e.target.value)}
                    />
                </FormField>

                {Object.entries(permissions).map(([group, items]) => (
                    items.length > 0 && (
                        <div key={group}>
                            <h3 className="mb-3 text-xs font-extrabold uppercase tracking-wide text-ink-subtle">
                                {group}
                            </h3>
                            <div className="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                                {items.map((permission) => (
                                    <label
                                        key={permission.id}
                                        className="flex items-center rounded-md border border-line px-3 py-2 text-sm transition hover:bg-surface-muted"
                                    >
                                        <Checkbox
                                            checked={data.permissions.includes(permission.name)}
                                            onChange={() => togglePermission(permission.name)}
                                        >
                                            {permission.name}
                                        </Checkbox>
                                    </label>
                                ))}
                            </div>
                        </div>
                    )
                ))}
                <InputError message={errors.permissions} />
            </FormCard>
        </AdminLayout>
    );
}
