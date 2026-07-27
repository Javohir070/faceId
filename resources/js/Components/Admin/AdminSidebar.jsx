import { Link, router } from '@inertiajs/react';
import { CaretDownOutlined, LogoutOutlined, MenuFoldOutlined } from '@ant-design/icons';
import { route } from '../../routes';
import { iconClassName, linkClassName } from './adminNavStyles';

export default function AdminSidebar({
    appName,
    collapsed,
    mobileOpen,
    navItems,
    openMenus,
    onCloseMobile,
    onToggleMenu,
    isActive,
    isGroupActive,
}) {
    const sidebarWidth = collapsed ? 'lg:w-[80px]' : 'lg:w-[260px]';

    return (
        <aside
            id="admin-sidebar"
            className={`fixed inset-y-0 left-0 z-40 flex w-[260px] transform flex-col border-r border-line bg-surface transition-all duration-200 ease-productive lg:sticky lg:top-0 lg:h-screen lg:translate-x-0 ${sidebarWidth} ${
                mobileOpen ? 'translate-x-0' : '-translate-x-full'
            }`}
        >
            <div className={`flex h-14 shrink-0 items-center border-b border-line ${collapsed ? 'justify-center px-3' : 'justify-between px-5'}`}>
                <Link
                    href={route('home.index')}
                    className="flex min-w-0 items-center gap-3 rounded-md focus-visible:outline-none"
                >
                    <img
                        src="/admin/assets/img/eidora-icon.png"
                        alt=""
                        className="h-8 w-auto shrink-0 object-contain"
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
                    onClick={onCloseMobile}
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
                                        onClick={onCloseMobile}
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
                                    onClick={() => onToggleMenu(item.key)}
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
                                                    onClick={onCloseMobile}
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
                            onClick={onCloseMobile}
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
                    onClick={() => router.post(route('logout'))}
                >
                    <LogoutOutlined aria-hidden="true" />
                    {!collapsed ? <span>Chiqish</span> : null}
                </button>
            </div>
        </aside>
    );
}
