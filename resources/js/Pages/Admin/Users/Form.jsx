import { useForm } from '@inertiajs/react';
import { Checkbox, Input, Select } from 'antd';
import AdminLayout from '../../../Layouts/AdminLayout';
import { route } from '../../../routes';
import FormCard from '../../../Components/FormCard';
import FormField from '../../../Components/FormField';

export default function Form({ user, roles = [], departments = [] }) {
    const isEdit = !!user;
    const { data, setData, post, put, processing, errors } = useForm({
        name: user?.name || '',
        email: user?.email || '',
        password: '',
        password_confirmation: '',
        department_id: user?.department_id || null,
        roles: user?.roles || [],
    });

    const isSuperAdmin = data.roles.includes('super-admin');

    const toggleRole = (name) => {
        setData(
            'roles',
            data.roles.includes(name)
                ? data.roles.filter((role) => role !== name)
                : [...data.roles, name],
        );
    };

    const submit = (e) => {
        e.preventDefault();
        if (isEdit) put(route('admin.users.update', user.id));
        else post(route('admin.users.store'));
    };

    return (
        <AdminLayout title={isEdit ? 'Foydalanuvchini tahrirlash' : "Foydalanuvchi qo'shish"}>
            <FormCard
                title={isEdit ? 'Foydalanuvchini tahrirlash' : "Yangi foydalanuvchi"}
                subtitle="Ism, email, parol, bo‘lim va rollarni belgilang."
                backHref={route('admin.users.index')}
                onSubmit={submit}
                processing={processing}
                cancelHref={route('admin.users.index')}
                width="lg"
            >
                <FormField label="Ism" error={errors.name} required>
                    <Input
                        value={data.name}
                        status={errors.name ? 'error' : undefined}
                        onChange={(e) => setData('name', e.target.value)}
                    />
                </FormField>
                <FormField label="Email" error={errors.email} required>
                    <Input
                        type="email"
                        value={data.email}
                        status={errors.email ? 'error' : undefined}
                        onChange={(e) => setData('email', e.target.value)}
                    />
                </FormField>
                <FormField
                    label="Bo‘lim"
                    error={errors.department_id}
                    required={!isSuperAdmin}
                    hint={isSuperAdmin ? 'super-admin uchun ixtiyoriy (barcha bo‘limlar).' : null}
                >
                    <Select
                        allowClear={isSuperAdmin}
                        placeholder="Bo‘limni tanlang"
                        value={data.department_id}
                        status={errors.department_id ? 'error' : undefined}
                        options={departments.map((d) => ({ value: d.id, label: d.name }))}
                        onChange={(value) => setData('department_id', value ?? null)}
                        className="w-full"
                    />
                </FormField>
                <div className="grid gap-4 md:grid-cols-2">
                    <FormField
                        label={`Parol ${isEdit ? '(ixtiyoriy)' : ''}`}
                        error={errors.password}
                        required={!isEdit}
                    >
                        <Input.Password
                            value={data.password}
                            status={errors.password ? 'error' : undefined}
                            onChange={(e) => setData('password', e.target.value)}
                        />
                    </FormField>
                    <FormField label="Parolni tasdiqlash">
                        <Input.Password
                            value={data.password_confirmation}
                            onChange={(e) => setData('password_confirmation', e.target.value)}
                        />
                    </FormField>
                </div>
                <FormField label="Rollar" error={errors.roles}>
                    <div className="space-y-2">
                        {roles.map((role) => (
                            <Checkbox
                                key={role.id}
                                checked={data.roles.includes(role.name)}
                                onChange={() => toggleRole(role.name)}
                            >
                                {role.name}
                            </Checkbox>
                        ))}
                    </div>
                </FormField>
            </FormCard>
        </AdminLayout>
    );
}
