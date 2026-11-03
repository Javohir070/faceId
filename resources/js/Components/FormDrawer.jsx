import { router, useForm } from '@inertiajs/react';
import { Button, Drawer } from 'antd';
import { useState } from 'react';
import { route } from '../routes';

/**
 * Ro‘yxat sahifasidagi o‘ng panel holati: { mode: 'create', defaults? } | { mode: 'edit', record }.
 * initial — serverdan keladigan `drawer` prop (eski create/edit havolalari shu bilan panelni ochadi).
 */
export function useFormDrawer(initial, indexRoute) {
    const [panel, setPanel] = useState(initial ?? null);

    const close = () => {
        setPanel(null);

        // ?create=1 / ?edit={id} bilan kelingan bo‘lsa, yangilaganda panel qayta ochilmasin.
        if (/[?&](create|edit)=/.test(window.location.search)) {
            router.get(route(indexRoute), {}, {
                replace: true,
                preserveState: true,
                preserveScroll: true,
                only: ['drawer'],
            });
        }
    };

    return {
        panel,
        openCreate: (defaults) => setPanel({ mode: 'create', defaults }),
        openEdit: (record) => setPanel({ mode: 'edit', record }),
        close,
        done: () => setPanel(null),
    };
}

function DrawerForm({ record, initialValues, storeUrl, updateUrl, onDone, onCancel, children }) {
    const isEdit = Boolean(record);
    const form = useForm(initialValues);

    const submit = (e) => {
        e.preventDefault();
        const options = { preserveScroll: true, preserveState: true, onSuccess: onDone };

        if (isEdit) {
            form.put(updateUrl, options);
        } else {
            form.post(storeUrl, options);
        }
    };

    return (
        <form onSubmit={submit} className="flex h-full flex-col">
            <div className="flex-1 space-y-4">{children(form)}</div>
            <div className="mt-6 flex justify-end gap-2 border-t border-line pt-4">
                <Button onClick={onCancel}>Bekor qilish</Button>
                <Button type="primary" htmlType="submit" loading={form.processing} className="!font-bold">
                    {isEdit ? 'Saqlash' : 'Qo‘shish'}
                </Button>
            </div>
        </form>
    );
}

/**
 * Qo‘shish / tahrirlash paneli. children — form ({ data, setData, errors }) ni olib maydonlarni chizadi.
 * Xato panel ichida ko‘rinadi, saqlangach panel yopiladi (server ro‘yxatga qaytaradi).
 */
export default function FormDrawer({ drawer, title, initialValues, storeUrl, updateUrl, width = 420, children }) {
    const { panel, close, done } = drawer;
    const record = panel?.mode === 'edit' ? panel.record : null;

    return (
        <Drawer
            open={Boolean(panel)}
            onClose={close}
            placement="right"
            size={width}
            destroyOnHidden
            title={record ? title.edit : title.create}
        >
            {panel ? (
                <DrawerForm
                    key={record ? `edit-${record.id}` : 'create'}
                    record={record}
                    initialValues={initialValues(record, panel.defaults)}
                    storeUrl={storeUrl}
                    updateUrl={record ? updateUrl(record) : null}
                    onDone={done}
                    onCancel={close}
                >
                    {children}
                </DrawerForm>
            ) : null}
        </Drawer>
    );
}
