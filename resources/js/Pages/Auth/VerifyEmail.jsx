import GuestLayout from '../../Layouts/GuestLayout';
import { useForm, usePage } from '@inertiajs/react';
import { MailOutlined } from '@ant-design/icons';
import { Alert, Button } from 'antd';

export default function VerifyEmail({ status }) {
    const { flash } = usePage().props;
    const { post, processing } = useForm({});

    return (
        <GuestLayout title="Emailni tasdiqlang" subtitle="Hisob xavfsizligi uchun elektron pochta manzilingizni tasdiqlang.">
            <span className="mb-4 flex h-11 w-11 items-center justify-center rounded-full bg-brand-50 text-brand-700">
                <MailOutlined />
            </span>
            {(status || flash?.status) && (
                <Alert className="mb-4" type="success" showIcon message={status || flash.status} />
            )}
            <p className="mb-5 text-sm leading-6 text-ink-muted">
                Pochtangizga yuborilgan tasdiqlash havolasini bosing. Xat kelmagan bo‘lsa, uni qayta yuborishingiz mumkin.
            </p>
            <Button
                type="primary"
                className="w-full"
                loading={processing}
                onClick={() => post('/email/verification-notification')}
            >
                Tasdiqlash xatini qayta yuborish
            </Button>
        </GuestLayout>
    );
}
