import { useForm } from '@inertiajs/react';
import { Input } from 'antd';
import AdminLayout from '../../../Layouts/AdminLayout';
import { route } from '../../../routes';
import FormCard from '../../../Components/FormCard';
import FormField from '../../../Components/FormField';

export default function Form({ department }) {
    const isEdit = !!department;
    const { data, setData, post, put, processing, errors } = useForm({
        name: department?.name || '',
    });

    const submit = (e) => {
        e.preventDefault();
        if (isEdit) put(route('admin.departments.update', department.id));
        else post(route('admin.departments.store'));
    };

    return (
        <AdminLayout title={isEdit ? "Bo'limni tahrirlash" : "Bo'lim qo'shish"}>
            <FormCard
                title={isEdit ? "Bo'limni tahrirlash" : "Yangi bo'lim"}
                subtitle="Bo'lim nomini kiriting."
                backHref={route('admin.departments.index')}
                onSubmit={submit}
                processing={processing}
                cancelHref={route('admin.departments.index')}
                width="sm"
            >
                <FormField label="Nomi" error={errors.name} required>
                    <Input
                        value={data.name}
                        status={errors.name ? 'error' : undefined}
                        onChange={(e) => setData('name', e.target.value)}
                    />
                </FormField>
            </FormCard>
        </AdminLayout>
    );
}
