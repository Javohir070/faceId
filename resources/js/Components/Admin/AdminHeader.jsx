import { router } from '@inertiajs/react';
import {
    DownOutlined,
    LogoutOutlined,
    MenuFoldOutlined,
    MenuOutlined,
    MenuUnfoldOutlined,
    MoonOutlined,
    SunOutlined,
    UserOutlined,
} from '@ant-design/icons';
import { Avatar, Dropdown } from 'antd';
import { route } from '../../routes';
import { roleLabel } from './roleLabel';

export default function AdminHeader({
    title,
    auth,
    mode,
    mobileOpen,
    collapsed,
    onOpenMobile,
    onCloseMobile,
    onToggleCollapsed,
    onToggleMode,
}) {
    const displayName = auth?.user?.name || 'Foydalanuvchi';
    const shortName = displayName
        .split(' ')
        .filter(Boolean)
        .map((part, index, arr) => (index === 0 && arr.length > 1 ? `${part[0]}.` : part))
        .join(' ');

    const userMenuItems = [
        {
            key: 'profile',
            icon: <UserOutlined />,
            label: 'Profil',
            onClick: () => router.visit(route('profile.edit')),
        },
        { type: 'divider' },
        {
            key: 'logout',
            icon: <LogoutOutlined />,
            label: 'Chiqish',
            danger: true,
            onClick: () => router.post(route('logout')),
        },
    ];

    return (
        <header className="sticky top-0 z-20 flex h-14 shrink-0 items-center justify-between border-b border-line bg-surface-muted px-4 sm:px-5 lg:px-6">
            <div className="flex min-w-0 items-center gap-3">
                <button
                    type="button"
                    className="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg text-ink-muted transition hover:bg-surface hover:text-ink lg:hidden"
                    aria-label="Menyuni ochish"
                    aria-controls="admin-sidebar"
                    aria-expanded={mobileOpen}
                    onClick={onOpenMobile}
                >
                    <MenuOutlined />
                </button>
                <button
                    type="button"
                    className="hidden h-9 w-9 shrink-0 items-center justify-center rounded-lg text-ink-muted transition hover:bg-surface hover:text-ink lg:inline-flex"
                    aria-label={collapsed ? 'Menyuni ochish' : 'Menyuni yig‘ish'}
                    onClick={onToggleCollapsed}
                >
                    {collapsed ? <MenuUnfoldOutlined /> : <MenuFoldOutlined />}
                </button>
                <p className="truncate text-[15px] font-semibold text-ink-muted">
                    {title}
                </p>
            </div>

            <div className="flex items-center gap-1.5">
                <button
                    type="button"
                    className="inline-flex h-9 w-9 items-center justify-center rounded-lg text-ink-muted transition hover:bg-surface hover:text-ink"
                    aria-label={mode === 'dark' ? 'Yorug‘ rejimni yoqish' : 'Tungi rejimni yoqish'}
                    title={mode === 'dark' ? 'Yorug‘ rejim' : 'Tungi rejim'}
                    onClick={onToggleMode}
                >
                    {mode === 'dark' ? <SunOutlined /> : <MoonOutlined />}
                </button>

                <Dropdown menu={{ items: userMenuItems }} trigger={['click']} placement="bottomRight">
                    <button
                        type="button"
                        className="flex max-w-full items-center gap-2.5 rounded-lg px-1.5 py-1 transition hover:bg-surface"
                    >
                        <Avatar
                            className="bg-line text-ink-muted"
                            size={36}
                            icon={<UserOutlined />}
                        />
                        <div className="hidden min-w-0 text-left sm:block">
                            <p className="truncate text-sm font-bold leading-tight text-ink">
                                {shortName}
                            </p>
                            <p className="truncate text-[10px] font-bold uppercase tracking-wide text-ink-subtle">
                                {roleLabel(auth?.user?.roles)}
                            </p>
                        </div>
                        <DownOutlined className="hidden text-[10px] text-ink-subtle sm:inline" />
                    </button>
                </Dropdown>
            </div>
        </header>
    );
}
