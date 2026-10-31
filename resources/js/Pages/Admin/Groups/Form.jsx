import { useForm } from '@inertiajs/react';
import { Input, Select } from 'antd';
import { useMemo } from 'react';
import AdminLayout from '../../../Layouts/AdminLayout';
import { route } from '../../../routes';
import FormCard from '../../../Components/FormCard';
import FormField from '../../../Components/FormField';

export default function Form({ group, departments = [], groups = [], defaults = {} }) {
    const isEdit = !!group;
    const { data, setData, post, put, processing, errors } = useForm({
        name: group?.name || '',
        department_id: group?.department_id || defaults.department_id || null,
        parent_id: group?.parent_id || defaults.parent_id || null,
    });

    const parentOptions = useMemo(() => {
        if (!data.department_id) {
            return [];
        }

        return groups
            .filter((item) => item.department_id === data.department_id)
            .map((item) => ({ value: item.id, label: item.name }));
    }, [groups, data.department_id]);

    const submit = (e) => {
        e.preventDefault();
        if (isEdit) {
            put(route('admin.groups.update', group.id));
        } else {
            post(route('admin.groups.store'));
        }
    };

    return (
        <AdminLayout title={isEdit ? 'Guruhni tahrirlash' : 'Guruh qo‘shish'}>
            <FormCard
                title={isEdit ? 'Guruhni tahrirlash' : 'Yangi guruh'}
                subtitle="Bo‘lim majburiy. Yuqori guruh ixtiyoriy — pastki guruh uchun tanlang."
                backHref={route('admin.groups.index')}
                onSubmit={submit}
                processing={processing}
                cancelHref={route('admin.groups.index')}
                width="lg"
            >
                    <FormField label="Guruh nomi" error={errors.name} required>
                        <Input
                            size="large"
                            value={data.name}
                            status={errors.name ? 'error' : undefined}
                            placeholder="Masalan: Backend jamoa"
                            onChange={(e) => setData('name', e.target.value)}
                        />
                    </FormField>

                    <FormField label="Bo‘lim" error={errors.department_id} required>
                        <Select
                            showSearch
                            className="w-full"
                            size="large"
                            placeholder="Bo‘lim tanlang"
                            optionFilterProp="label"
                            value={data.department_id}
                            status={errors.department_id ? 'error' : undefined}
                            options={departments.map((department) => ({
                                value: department.id,
                                label: department.name,
                            }))}
                            onChange={(value) => {
                                setData({
                                    ...data,
                                    department_id: value,
                                    parent_id: null,
                                });
                            }}
                        />
                    </FormField>

                    <FormField
                        label="Yuqori guruh"
                        error={errors.parent_id}
                        hint={data.department_id
                            ? 'Bo‘sh qoldirsangiz — bo‘lim ostida ildiz guruh bo‘ladi.'
                            : 'Avval bo‘limni tanlang.'}
                    >
                        <Select
                            allowClear
                            showSearch
                            className="w-full"
                            size="large"
                            placeholder="Ixtiyoriy — yuqori guruh"
                            optionFilterProp="label"
                            value={data.parent_id}
                            status={errors.parent_id ? 'error' : undefined}
                            disabled={!data.department_id}
                            options={parentOptions}
                            onChange={(value) => setData('parent_id', value ?? null)}
                        />
                    </FormField>
            </FormCard>
        </AdminLayout>
    );
}
