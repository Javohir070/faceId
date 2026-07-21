import { router } from '@inertiajs/react';
import {
    ArrowLeftOutlined,
    DeleteOutlined,
    EditOutlined,
    EyeOutlined,
    PlusOutlined,
} from '@ant-design/icons';
import { App as AntApp, Button, Empty, Tooltip } from 'antd';

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

export default function PageHeader({
    title,
    subtitle,
    backHref,
    backLabel = 'Orqaga',
    createHref,
    createLabel = "Yangi qo'shish",
    actions,
}) {
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
                {actions || (createHref ? (
                    <Button
                        type="primary"
                        size="large"
                        className="!h-11 shrink-0 !rounded-lg !px-5 !font-bold"
                        icon={<PlusOutlined />}
                        onClick={() => router.visit(createHref)}
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

    const confirmDelete = () => {
        modal.confirm({
            title: 'Yozuvni o‘chirish',
            content: confirmMessage,
            okText: 'Ha, o‘chirish',
            cancelText: 'Bekor qilish',
            okButtonProps: { danger: true },
            onOk: () => new Promise((resolve, reject) => {
                router.delete(href, {
                    preserveScroll: true,
                    onSuccess: resolve,
                    onError: reject,
                });
            }),
        });
    };

    return (
        <Tooltip title="O‘chirish">
            <button
                type="button"
                className="table-action-delete"
                aria-label="O‘chirish"
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

export function ResourceActions({ showHref, editHref, deleteHref }) {
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
            {editHref ? (
                <Tooltip title="Tahrirlash">
                    <button
                        type="button"
                        className="table-action-edit"
                        aria-label="Tahrirlash"
                        onClick={() => router.visit(editHref)}
                    >
                        <EditOutlined aria-hidden="true" />
                    </button>
                </Tooltip>
            ) : null}
            {deleteHref ? <DeleteButton href={deleteHref} /> : null}
        </div>
    );
}

export function FormActions({ cancelHref, processing, submitLabel = 'Saqlash' }) {
    return (
        <div className="flex flex-wrap items-center gap-3 border-t border-line pt-5">
            <Button type="primary" htmlType="submit" loading={processing}>
                {submitLabel}
            </Button>
            <Button onClick={() => router.visit(cancelHref)}>Bekor qilish</Button>
        </div>
    );
}

export function EmptyState({
    title = 'Hozircha ma’lumot yo‘q',
    description = 'Birinchi yozuvni qo‘shib, ushbu bo‘limni to‘ldiring.',
    createHref,
    createLabel = 'Birinchi yozuvni qo‘shish',
}) {
    return (
        <div className="empty-state">
            <Empty
                image={Empty.PRESENTED_IMAGE_SIMPLE}
                description={(
                    <div>
                        <h2 className="text-base font-extrabold text-ink">{title}</h2>
                        <p className="mt-2 max-w-md text-sm leading-6 text-ink-muted">{description}</p>
                    </div>
                )}
            >
                {createHref ? (
                    <Button type="primary" icon={<PlusOutlined />} onClick={() => router.visit(createHref)}>
                        {createLabel}
                    </Button>
                ) : null}
            </Empty>
        </div>
    );
}
