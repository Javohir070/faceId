import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    CaretDownOutlined,
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
import { useEffect, useLayoutEffect, useMemo, useState } from 'react';
import { useAdminTheme } from '../Components/AdminThemeProvider';
import FlashMessages from '../Components/FlashMessages';
import { getVisibleNavGroups } from '../config/adminNav';

function roleLabel(roles = []) {
    if (!roles.length) {
        return 'FOYDALANUVCHI';
    }

    const primary = roles[0];
    if (primary === 'super-admin') {
        return 'TIZIM ADMINI';
    }

    return String(primary).replace(/-/g, ' ').toUpperCase();
}

export default function AdminLayout({ children, title }) {
    const page = usePage();
    const { auth, can, appName } = page.props;
    const [mobileOpen, setMobileOpen] = useState(false);
    const [collapsed, setCollapsed] = useState(false);
    const [openMenus, setOpenMenus] = useState({});
    const { mode, setAdminActive, toggleMode } = useAdminTheme();
    const current = typeof page.url === 'string' ? page.url : '';

    const navGroups = useMemo(() => getVisibleNavGroups(can || {}), [can]);
    const navItems = useMemo(
        () => navGroups.flatMap((group) => group.items),
        [navGroups],
    );

    const isActive = (href) => {
        if (!href) {
            return false;
        }
        if (href === '/dashboard') {
            return current === '/dashboard' || current === '/dashboard/';
        }
        return current.startsWith(href);
    };

    const isGroupActive = (item) =>
        Boolean(item.children?.some((child) => isActive(child.href)));

    useEffect(() => {
        setOpenMenus((prev) => {
            const next = { ...prev };
            navItems.forEach((item) => {
                if (item.children?.length && isGroupActive(item)) {
                    next[item.key] = true;
                }
            });
            return next;
        });
    }, [current, navItems]);

    const toggleMenu = (key) => {
        setOpenMenus((prev) => ({ ...prev, [key]: !prev[key] }));
    };

    const linkClassName = (active, { collapsed: isCollapsed, nested = false } = {}) =>
        `group flex min-h-11 items-center rounded-lg text-sm font-bold transition duration-200 ease-productive ${
            isCollapsed ? 'justify-center px-0' : nested ? 'gap-3 px-3 pl-10' : 'gap-3 px-3'
        } ${
            active
                ? 'bg-brand-50 text-brand-700'
                : 'text-ink-muted hover:bg-line/60 hover:text-brand-700'
        }`;

    const iconClassName = (active) =>
        `text-base ${active ? 'text-brand-600' : 'text-ink-subtle group-hover:text-brand-600'}`;

    useEffect(() => {
        const closeOnEscape = (event) => {
            if (event.key === 'Escape') {
                setMobileOpen(false);
            }
        };

        document.addEventListener('keydown', closeOnEscape);
        return () => {
            document.removeEventListener('keydown', closeOnEscape);
        };
    }, []);

    useEffect(() => {
        setMobileOpen(false);
    }, [current]);

    useLayoutEffect(() => {
        setAdminActive(true);
        return () => setAdminActive(false);
    }, [setAdminActive]);

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
            onClick: () => router.visit('/profile'),
        },
        { type: 'divider' },
        {
            key: 'logout',
            icon: <LogoutOutlined />,
            label: 'Chiqish',
            danger: true,
            onClick: () => router.post('/logout'),
        },
    ];

    const sidebarWidth = collapsed ? 'lg:w-[80px]' : 'lg:w-[260px]';

    return (
        <>
            <Head title={title} />
            <div className="admin-shell min-h-screen bg-canvas lg:flex">
                <aside
                    id="admin-sidebar"
                    className={`fixed inset-y-0 left-0 z-40 flex w-[260px] transform flex-col border-r border-line bg-surface transition-all duration-200 ease-productive lg:sticky lg:top-0 lg:h-screen lg:translate-x-0 ${sidebarWidth} ${
                        mobileOpen ? 'translate-x-0' : '-translate-x-full'
                    }`}
                >
                    <div className={`flex h-14 shrink-0 items-center border-b border-line ${collapsed ? 'justify-center px-3' : 'justify-between px-5'}`}>
                        <Link
                            href="/dashboard"
                            className="flex min-w-0 items-center gap-2.5 rounded-md focus-visible:outline-none"
                        >
                            <img
                                src="/admin/assets/img/eidora-logo.png"
                                alt="EIdora"
                                className="h-9 w-9 shrink-0 rounded-lg object-cover object-top"
                            />
                            {!collapsed ? (
                                <span className="truncate text-lg font-extrabold tracking-tight text-ink">
                                    {appName || 'EIdora'}
                                </span>
                            ) : null}
                        </Link>
                        <button
                            type="button"
                            className="inline-flex h-9 w-9 items-center justify-center rounded-lg text-ink-muted transition hover:bg-surface-muted hover:text-ink lg:hidden"
                            aria-label="Menyuni yopish"
                            onClick={() => setMobileOpen(false)}
                        >
                            <MenuFoldOutlined />
                        </button>
                    </div>

                    <nav className={`flex-1 space-y-1 overflow-y-auto py-3 ${collapsed ? 'px-2' : 'px-3'}`} aria-label="Asosiy navigatsiya">
                        {navItems.map((item) => {
                            if (item.children?.length) {
                                const Icon = item.icon;
                                const groupActive = isGroupActive(item);
                                const open = Boolean(openMenus[item.key]);

                                if (collapsed) {
                                    return item.children.map((child) => {
                                        const ChildIcon = child.icon;
                                        const active = isActive(child.href);

                                        return (
                                            <Link
                                                key={child.key}
                                                href={child.href}
                                                title={child.label}
                                                aria-current={active ? 'page' : undefined}
                                                className={linkClassName(active, { collapsed: true })}
                                                onClick={() => setMobileOpen(false)}
                                            >
                                                <ChildIcon aria-hidden="true" className={iconClassName(active)} />
                                            </Link>
                                        );
                                    });
                                }

                                return (
                                    <div key={item.key} className="space-y-1">
                                        <button
                                            type="button"
                                            className={`group flex min-h-11 w-full items-center gap-3 rounded-lg px-3 text-sm font-bold transition duration-200 ease-productive ${
                                                groupActive
                                                    ? 'text-brand-700'
                                                    : 'text-ink-muted hover:bg-line/60 hover:text-brand-700'
                                            }`}
                                            aria-expanded={open}
                                            onClick={() => toggleMenu(item.key)}
                                        >
                                            <CaretDownOutlined
                                                aria-hidden="true"
                                                className={`text-[10px] transition-transform duration-200 ease-productive ${
                                                    open ? 'rotate-0' : '-rotate-90'
                                                } ${groupActive ? 'text-brand-600' : 'text-ink-subtle'}`}
                                            />
                                            <Icon
                                                aria-hidden="true"
                                                className={iconClassName(groupActive)}
                                            />
                                            <span className="flex-1 text-left">{item.label}</span>
                                        </button>

                                        {open ? (
                                            <div className="space-y-1">
                                                {item.children.map((child) => {
                                                    const ChildIcon = child.icon;
                                                    const active = isActive(child.href);

                                                    return (
                                                        <Link
                                                            key={child.key}
                                                            href={child.href}
                                                            aria-current={active ? 'page' : undefined}
                                                            className={linkClassName(active, { nested: true })}
                                                            onClick={() => setMobileOpen(false)}
                                                        >
                                                            <ChildIcon
                                                                aria-hidden="true"
                                                                className={iconClassName(active)}
                                                            />
                                                            <span>{child.label}</span>
                                                        </Link>
                                                    );
                                                })}
                                            </div>
                                        ) : null}
                                    </div>
                                );
                            }

                            const Icon = item.icon;
                            const active = isActive(item.href);

                            return (
                                <Link
                                    key={item.key}
                                    href={item.href}
                                    title={collapsed ? item.label : undefined}
                                    aria-current={active ? 'page' : undefined}
                                    className={linkClassName(active, { collapsed })}
                                    onClick={() => setMobileOpen(false)}
                                >
                                    <Icon aria-hidden="true" className={iconClassName(active)} />
                                    {!collapsed ? <span>{item.label}</span> : null}
                                </Link>
                            );
                        })}
                    </nav>

                    <div className={`shrink-0 border-t border-line ${collapsed ? 'p-2' : 'p-3'}`}>
                        <button
                            type="button"
                            title={collapsed ? 'Chiqish' : undefined}
                            className={`flex min-h-11 w-full items-center rounded-lg text-sm font-bold text-negative transition duration-200 ease-productive hover:bg-negative/10 ${
                                collapsed ? 'justify-center px-0' : 'gap-3 px-3'
                            }`}
                            onClick={() => router.post('/logout')}
                        >
                            <LogoutOutlined aria-hidden="true" />
                            {!collapsed ? <span>Chiqish</span> : null}
                        </button>
                    </div>
                </aside>

                {mobileOpen ? (
                    <button
                        type="button"
                        className="fixed inset-0 z-30 bg-black/60 lg:hidden"
                        aria-label="Menyuni yopish"
                        onClick={() => setMobileOpen(false)}
                    />
                ) : null}

                <div className="flex min-h-screen min-w-0 flex-1 flex-col">
                    <header className="sticky top-0 z-20 flex h-14 shrink-0 items-center justify-between border-b border-line bg-surface-muted px-4 sm:px-5 lg:px-6">
                        <div className="flex min-w-0 items-center gap-3">
                            <button
                                type="button"
                                className="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg text-ink-muted transition hover:bg-surface hover:text-ink lg:hidden"
                                aria-label="Menyuni ochish"
                                aria-controls="admin-sidebar"
                                aria-expanded={mobileOpen}
                                onClick={() => setMobileOpen(true)}
                            >
                                <MenuOutlined />
                            </button>
                            <button
                                type="button"
                                className="hidden h-9 w-9 shrink-0 items-center justify-center rounded-lg text-ink-muted transition hover:bg-surface hover:text-ink lg:inline-flex"
                                aria-label={collapsed ? 'Menyuni ochish' : 'Menyuni yig‘ish'}
                                onClick={() => setCollapsed((value) => !value)}
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
                                onClick={toggleMode}
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

                    <main className="flex-1 px-4 py-5 sm:px-5 lg:px-6 lg:py-6">
                        <div className="page-section">
                            <FlashMessages />
                            {children}
                        </div>
                    </main>
                </div>
            </div>
        </>
    );
}
