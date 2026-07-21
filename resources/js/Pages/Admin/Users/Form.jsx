import { useForm } from '@inertiajs/react';
import { Checkbox, Input } from 'antd';
import AdminLayout from '../../../Layouts/AdminLayout';
import FormCard from '../../../Components/FormCard';
import FormField from '../../../Components/FormField';

export default function Form({ user, roles = [] }) {
    const isEdit = !!user;
    const { data, setData, post, put, processing, errors } = useForm({
        name: user?.name || '',
        email: user?.email || '',
        password: '',
        password_confirmation: '',
        roles: user?.roles || [],
    });

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
        if (isEdit) put(`/admin/users/${user.id}`);
        else post('/admin/users');
    };

    return (
        <AdminLayout title={isEdit ? 'Foydalanuvchini tahrirlash' : "Foydalanuvchi qo'shish"}>
            <FormCard
                title={isEdit ? 'Foydalanuvchini tahrirlash' : "Yangi foydalanuvchi"}
                subtitle="Ism, email, parol va rollarni belgilang."
                backHref="/admin/users"
                onSubmit={submit}
                processing={processing}
                cancelHref="/admin/users"
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
