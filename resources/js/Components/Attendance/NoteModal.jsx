import { useForm } from '@inertiajs/react';
import { Input, Modal, Select } from 'antd';
import { useEffect } from 'react';
import FormField from '../FormField';
import { route } from '../../routes';

/** "Ariza berish" — izoh (sababli kun) qo‘shish oynasi. Saqlangach sahifa o‘sha holatda qayta yuklanadi. */
export default function NoteModal({ open, prefill, employees = [], types = [], onClose }) {
    const { data, setData, post, processing, errors, reset, clearErrors } = useForm({
        employee_id: null,
        type: null,
        dt: '',
        note: '',
    });

    useEffect(() => {
        if (open) {
            clearErrors();
            setData({
                employee_id: prefill?.employee_id ?? null,
                type: null,
                dt: prefill?.dt ?? '',
                note: '',
            });
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, prefill?.employee_id, prefill?.dt]);

    const submit = () => {
        post(route('admin.attendance.notes.store'), {
            preserveScroll: true,
            onSuccess: () => {
                reset();
                onClose();
            },
        });
    };

    return (
        <Modal
            open={open}
            title="Ariza berish"
            okText="Saqlash"
            cancelText="Bekor qilish"
            confirmLoading={processing}
            onOk={submit}
            onCancel={onClose}
            destroyOnHidden
        >
            <div className="space-y-4 pt-2">
                <FormField label="Xodim" error={errors.employee_id} required>
                    <Select
                        showSearch
                        optionFilterProp="label"
                        className="w-full"
                        placeholder="Xodimni tanlang"
                        value={data.employee_id}
                        options={employees.map((employee) => ({ value: employee.id, label: employee.name }))}
                        onChange={(value) => setData('employee_id', value)}
                        status={errors.employee_id ? 'error' : undefined}
                    />
                </FormField>
                <FormField label="Tur" error={errors.type} required>
                    <Select
                        className="w-full"
                        placeholder="Turini tanlang"
                        value={data.type}
                        options={types}
                        onChange={(value) => setData('type', value)}
                        status={errors.type ? 'error' : undefined}
                    />
                </FormField>
                <FormField label="Sana" error={errors.dt} required>
                    <Input
                        type="date"
                        value={data.dt}
                        onChange={(event) => setData('dt', event.target.value)}
                        status={errors.dt ? 'error' : undefined}
                    />
                </FormField>
                <FormField label="Izoh" error={errors.note} required>
                    <Input.TextArea
                        rows={4}
                        value={data.note}
                        placeholder="Sababini yozing"
                        onChange={(event) => setData('note', event.target.value)}
                        status={errors.note ? 'error' : undefined}
                    />
                </FormField>
            </div>
        </Modal>
    );
}
