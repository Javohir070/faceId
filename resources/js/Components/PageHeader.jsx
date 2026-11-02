import { router, usePage } from '@inertiajs/react';
import {
    ArrowLeftOutlined,
    DeleteOutlined,
    EditOutlined,
    EyeOutlined,
    PlusOutlined,
} from '@ant-design/icons';
import { App as AntApp, Button, Empty, Tooltip } from 'antd';
import { useState } from 'react';

export function BackLink({ href, label = 'Orqaga' }) {
    return (
        <Button
            type="text"
            className="mb-3 -ml-2"
            icon={<ArrowLeftOutlined />}
            onClick={() => router.visit(href)}
        >
            {label}
        </Button>
    );
}

/**
 * Amal ruxsati: resource berilsa tugma `can['<resource>.create|edit|delete']` bo‘yicha chiqadi
 * (config/menu_permissions.php → actions). Server ham shu ruxsatni tekshiradi.
 */
export function useActionAbility(resource) {
    const can = usePage().props.can || {};

    return (action) => !resource || Boolean(can[`${resource}.${action}`]);
}

export default function PageHeader({
    title,
    subtitle,
    backHref,
    backLabel = 'Orqaga',
    resource,
    createHref,
    onCreate,
    createLabel = "Yangi qo'shish",
    actions,
}) {
    const can = useActionAbility(resource);

    return (
        <div className="mb-5">
            {backHref && <BackLink href={backHref} label={backLabel} />}
            <div className="page-header">
                <div className="min-w-0 flex-1">
                    {title ? (
                        <h1 className="text-xl font-extrabold tracking-tight text-ink text-balance sm:text-2xl">
                            {title}
                        </h1>
                    ) : null}
                    {subtitle ? (
                        <p className="mt-1 text-sm leading-6 text-ink-muted text-pretty">
                            {subtitle}
                        </p>
                    ) : null}
                </div>
                {actions || ((createHref || onCreate) && can('create') ? (
                    <Button
                        type="primary"
                        size="large"
                        className="!h-11 shrink-0 !rounded-lg !px-5 !font-bold"
                        icon={<PlusOutlined />}
                        onClick={() => (onCreate ? onCreate() : router.visit(createHref))}
                    >
                        {createLabel}
                    </Button>
                ) : null)}
            </div>
        </div>
    );
}

export function DeleteButton({ href, confirmMessage = "O'chirishni tasdiqlaysizmi?" }) {
    const { modal } = AntApp.useApp();
    const [deleting, setDeleting] = useState(false);

    const confirmDelete = () => {
        if (deleting) {
            return;
        }

        modal.confirm({
            title: 'Yozuvni o‘chirish',
            content: confirmMessage,
            okText: 'Ha, o‘chirish',
            cancelText: 'Bekor qilish',
            okButtonProps: { danger: true },
            onOk: () => new Promise((resolve, reject) => {
                setDeleting(true);
                router.delete(href, {
                    preserveScroll: true,
                    onSuccess: resolve,
                    onError: reject,
                    onFinish: () => setDeleting(false),
                });
            }),
        });
    };

    return (
        <Tooltip title="O‘chirish">
            <button
                type="button"
                className="table-action-delete disabled:cursor-wait disabled:opacity-60"
                aria-label="O‘chirish"
                disabled={deleting}
                aria-busy={deleting}
                onClick={confirmDelete}
            >
                <DeleteOutlined aria-hidden="true" />
            </button>
        </Tooltip>
    );
}

export function RoleBadge({ children }) {
    return <span className="role-badge">{children}</span>;
}

/** onEdit berilsa tahrirlash sahifaga o‘tmaydi — shu sahifadagi panel (Drawer) ochiladi. */
export function ResourceActions({ resource, showHref, editHref, onEdit, deleteHref }) {
    const can = useActionAbility(resource);

    return (
        <div className="flex items-center justify-end gap-2">
            {showHref ? (
                <Tooltip title="Ko‘rish">
                    <button
                        type="button"
                        className="table-action-view"
                        aria-label="Ko‘rish"
                        onClick={() => router.visit(showHref)}
                    >
                        <EyeOutlined aria-hidden="true" />
                    </button>
                </Tooltip>
            ) : null}
            {(editHref || onEdit) && can('edit') ? (
                <Tooltip title="Tahrirlash">
                    <button
                        type="button"
                        className="table-action-edit"
                        aria-label="Tahrirlash"
                        onClick={() => (onEdit ? onEdit() : router.visit(editHref))}
                    >
                        <EditOutlined aria-hidden="true" />
                    </button>
                </Tooltip>
            ) : null}
            {deleteHref && can('delete') ? <DeleteButton href={deleteHref} /> : null}
        </div>
    );
}

export function FormActions({ cancelHref, processing, submitLabel = 'Saqlash' }) {
    return (
        <div className="flex flex-wrap items-center gap-3 border-t border-line pt-5 justify-end">
            <Button onClick={() => router.visit(cancelHref)}>Bekor qilish</Button>
            <Button type="primary" htmlType="submit" loading={processing}>
                {submitLabel}
            </Button>
        </div>
    );
}

export function EmptyState({
    title = 'Hozircha ma’lumot yo‘q',
    description = 'Birinchi yozuvni qo‘shib, ushbu bo‘limni to‘ldiring.',
    resource,
    createHref,
    onCreate,
    createLabel = 'Birinchi yozuvni qo‘shish',
}) {
    const canCreate = useActionAbility(resource)('create');

    return (
        <div className="empty-state">
            <Empty
                image={Empty.PRESENTED_IMAGE_SIMPLE}
                description={(
                    <div>
                        <h2 className="text-base font-extrabold text-ink">{title}</h2>
                        <p className="mt-2 max-w-md text-sm leading-6 text-ink-muted">
                            {canCreate ? description : 'Bu yerda hozircha yozuv yo‘q.'}
                        </p>
                    </div>
                )}
            >
                {(createHref || onCreate) && canCreate ? (
                    <Button
                        type="primary"
                        icon={<PlusOutlined />}
                        onClick={() => (onCreate ? onCreate() : router.visit(createHref))}
                    >
                        {createLabel}
                    </Button>
                ) : null}
            </Empty>
        </div>
    );
}
