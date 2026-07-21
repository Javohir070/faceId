import {
    AppstoreOutlined,
    BankOutlined,
    CalendarOutlined,
    ClusterOutlined,
    HomeOutlined,
    IdcardOutlined,
    KeyOutlined,
    SafetyCertificateOutlined,
    SettingOutlined,
    TeamOutlined,
} from '@ant-design/icons';

/** Sidebar guruhlari — tartib muhim */
export const adminNavGroups = [
    {
        id: 'main',
        title: null,
        items: [
            { key: 'home', href: '/dashboard', label: 'Bosh sahifa', icon: HomeOutlined },
            {
                key: 'devices-group',
                label: 'Qurilmalar',
                icon: ClusterOutlined,
                children: [
                    { key: 'categories', href: '/admin/categories', label: 'Kategoriyalar', icon: AppstoreOutlined },
                    { key: 'devices', href: '/admin/devices', label: 'Qurilmalar', icon: ClusterOutlined },
                ],
            },
            {
                key: 'organization-group',
                label: 'Tashkilot',
                icon: BankOutlined,
                children: [
                    { key: 'departments', href: '/admin/departments', label: "Bo'limlar", icon: BankOutlined },
                    { key: 'schedules', href: '/admin/schedules', label: 'Jadvallar', icon: CalendarOutlined },
                    { key: 'employees', href: '/admin/employees', label: 'Xodimlar', icon: IdcardOutlined },
                ],
            },
            {
                key: 'settings',
                label: 'Sozlamalar',
                icon: SettingOutlined,
                children: [
                    { key: 'users', href: '/admin/users', label: 'Foydalanuvchilar', icon: TeamOutlined },
                    { key: 'roles', href: '/admin/roles', label: 'Rollar', icon: SafetyCertificateOutlined },
                    { key: 'permissions', href: '/admin/permissions', label: 'Ruxsatlar', icon: KeyOutlined },
                ],
            },
        ],
    },
];

function isItemVisible(item, can = {}) {
    if (item.key === 'home') {
        return true;
    }

    if (item.children?.length) {
        return item.children.some((child) => can[child.key]);
    }

    return Boolean(can[item.key]);
}

/** Ruxsatga qarab faqat ko‘rinadigan guruhlarni qaytaradi */
export function getVisibleNavGroups(can = {}) {
    return adminNavGroups
        .map((group) => ({
            ...group,
            items: group.items
                .map((item) => {
                    if (!item.children?.length) {
                        return item;
                    }

                    return {
                        ...item,
                        children: item.children.filter((child) => can[child.key]),
                    };
                })
                .filter((item) => isItemVisible(item, can)),
        }))
        .filter((group) => group.items.length > 0);
}
