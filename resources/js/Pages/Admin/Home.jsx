import { Link } from '@inertiajs/react';
import { KeyOutlined, SafetyCertificateOutlined, TeamOutlined } from '@ant-design/icons';
import AdminLayout from '../../Layouts/AdminLayout';
import PageHeader from '../../Components/PageHeader';

const quickLinks = [
    {
        href: '/admin/users',
        label: 'Foydalanuvchilar',
        description: 'Tizim foydalanuvchilarini boshqarish',
        icon: TeamOutlined,
    },
    {
        href: '/admin/roles',
        label: 'Rollar',
        description: 'Foydalanuvchi rollarini sozlash',
        icon: SafetyCertificateOutlined,
    },
    {
        href: '/admin/permissions',
        label: 'Ruxsatlar',
        description: 'Kirish huquqlarini boshqarish',
        icon: KeyOutlined,
    },
];

export default function Home() {
    return (
        <AdminLayout title="Bosh sahifa">
            <PageHeader
                title="Boshqaruv paneli"
                subtitle="Biocontrol tizimi — foydalanuvchilar, rollar va ruxsatlarni boshqaring."
            />

            <section className="overflow-hidden rounded-lg border border-line bg-surface shadow-ambient">
                <div className="border-b border-line px-5 py-4">
                    <h2 className="text-lg font-extrabold text-ink">Tezkor kirish</h2>
                    <p className="mt-1 text-sm text-ink-muted">Asosiy admin bo‘limlari</p>
                </div>

                <div className="divide-y divide-line">
                    {quickLinks.map((item) => {
                        const Icon = item.icon;

                        return (
                            <Link
                                key={item.href}
                                href={item.href}
                                className="group flex items-center gap-4 px-5 py-4 transition-colors duration-200 hover:bg-brand-50/50"
                            >
                                <span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-md bg-brand-50 text-brand-700">
                                    <Icon className="text-lg" />
                                </span>
                                <div className="min-w-0 flex-1">
                                    <h3 className="font-extrabold text-ink group-hover:text-brand-800">
                                        {item.label}
                                    </h3>
                                    <p className="mt-1 text-sm text-ink-muted">{item.description}</p>
                                </div>
                                <span className="text-sm font-bold text-brand-600">Ochish →</span>
                            </Link>
                        );
                    })}
                </div>
            </section>
        </AdminLayout>
    );
}
