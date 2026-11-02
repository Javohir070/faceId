import { router } from '@inertiajs/react';
import { EditOutlined } from '@ant-design/icons';
import { Button } from 'antd';
import { BackLink, DeleteButton, useActionAbility } from './PageHeader';

export function ShowHeader({
    resource,
    title,
    subtitle,
    editHref,
    deleteHref,
}) {
    const can = useActionAbility(resource);

    return (
        <div className="mb-5">
            <div className="page-header">
                <div className="min-w-0 flex-1">
                    <h1 className="text-xl font-extrabold tracking-tight text-ink sm:text-2xl">
                        {title}
                    </h1>
                    {subtitle && (
                        <p className="mt-1 text-sm leading-6 text-ink-muted">
                            {subtitle}
                        </p>
                    )}
                </div>
                <div className="flex shrink-0 flex-wrap items-center gap-2">
                    {editHref && can('edit') && (
                        <Button
                            type="primary"
                            size="large"
                            className="!h-11 !rounded-lg !px-5 !font-bold"
                            icon={<EditOutlined />}
                            onClick={() => router.visit(editHref)}
                        >
                            Tahrirlash
                        </Button>
                    )}
                    {deleteHref && can('delete') && <DeleteButton href={deleteHref} />}
                </div>
            </div>
        </div>
    );
}

export default function ShowPage({
    resource,
    backHref,
    backLabel,
    title,
    subtitle,
    editHref,
    deleteHref,
    maxWidth = '',
    children,
}) {
    return (
        <>
            <BackLink href={backHref} label={backLabel} />
            <ShowHeader
                resource={resource}
                title={title}
                subtitle={subtitle}
                editHref={editHref}
                deleteHref={deleteHref}
            />
            <div className={`card space-y-5 ${maxWidth}`.trim()}>
                {children}
            </div>
        </>
    );
}
