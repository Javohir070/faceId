import { router } from '@inertiajs/react';
import {
    CompressOutlined,
    DeleteOutlined,
    EditOutlined,
    ExpandOutlined,
    EyeOutlined,
    SearchOutlined,
} from '@ant-design/icons';
import { App as AntApp, Button, Input, Select, Tooltip, Tree } from 'antd';
import { useEffect, useMemo, useState } from 'react';
import AdminLayout from '../../../Layouts/AdminLayout';
import { route } from '../../../routes';
import PageHeader, { EmptyState } from '../../../Components/PageHeader';

function countGroups(nodes = []) {
    return nodes.reduce((total, node) => {
        if (node.type === 'group') {
            return total + 1 + countGroups(node.children || []);
        }

        return total + countGroups(node.children || []);
    }, 0);
}

function collectExpandableKeys(nodes = []) {
    const keys = [];

    nodes.forEach((node) => {
        if (node.children?.length) {
            keys.push(node.key);
            keys.push(...collectExpandableKeys(node.children));
        }
    });

    return keys;
}

function filterTree(nodes, query) {
    if (!query) {
        return nodes;
    }

    const needle = query.trim().toLowerCase();

    return nodes
        .map((node) => {
            const children = filterTree(node.children || [], needle);
            const selfMatch = node.title.toLowerCase().includes(needle);

            if (!selfMatch && children.length === 0) {
                return null;
            }

            return {
                ...node,
                children,
            };
        })
        .filter(Boolean);
}

function GroupTitle({ node, onShow, onEdit }) {
    return (
        <div className="group/row flex min-w-0 flex-1 items-center justify-between gap-3 py-0.5 pr-1">
            <button
                type="button"
                className="min-w-0 truncate text-left text-sm font-bold text-ink transition hover:text-brand-700 dark:hover:text-brand-400"
                onClick={(e) => {
                    e.stopPropagation();
                    onShow(node.id);
                }}
            >
                {node.title}
            </button>
            <div className="flex shrink-0 items-center gap-1 opacity-100 sm:opacity-0 sm:transition sm:group-hover/row:opacity-100">
                <Tooltip title="Ko‘rish">
                    <button
                        type="button"
                        className="table-action-view"
                        aria-label="Ko‘rish"
                        onClick={(e) => {
                            e.stopPropagation();
                            onShow(node.id);
                        }}
                    >
                        <EyeOutlined aria-hidden="true" />
                    </button>
                </Tooltip>
                <Tooltip title="Tahrirlash">
                    <button
                        type="button"
                        className="table-action-edit"
                        aria-label="Tahrirlash"
                        onClick={(e) => {
                            e.stopPropagation();
                            onEdit(node.id);
                        }}
                    >
                        <EditOutlined aria-hidden="true" />
                    </button>
                </Tooltip>
            </div>
        </div>
    );
}

function DepartmentTitle({ node }) {
    const total = countGroups(node.children || []);

    return (
        <span className="inline-flex items-center gap-2 py-0.5">
            <span className="text-sm font-extrabold text-ink">{node.title}</span>
            <span className="rounded-md bg-surface-muted px-2 py-0.5 text-xs font-bold text-ink-muted">
                {total}
                {' '}
                guruh
            </span>
        </span>
    );
}

function toTreeData(nodes, onShow, onEdit) {
    return nodes.map((node) => ({
        key: node.key,
        checkable: node.type === 'group',
        disableCheckbox: node.type === 'department',
        title: node.type === 'department'
            ? <DepartmentTitle node={node} />
            : <GroupTitle node={node} onShow={onShow} onEdit={onEdit} />,
        children: node.children?.length
            ? toTreeData(node.children, onShow, onEdit)
            : undefined,
    }));
}

export default function Index({ tree = [], filters = {}, departments = [] }) {
    const { modal } = AntApp.useApp();
    const [search, setSearch] = useState('');
    const [checkedKeys, setCheckedKeys] = useState([]);
    const [expandedKeys, setExpandedKeys] = useState([]);

    const filteredTree = useMemo(() => filterTree(tree, search), [tree, search]);
    const expandableKeys = useMemo(() => collectExpandableKeys(filteredTree), [filteredTree]);
    const totalGroups = useMemo(() => countGroups(tree), [tree]);

    useEffect(() => {
        setExpandedKeys(collectExpandableKeys(tree));
        setCheckedKeys([]);
    }, [tree]);

    useEffect(() => {
        if (search.trim()) {
            setExpandedKeys(expandableKeys);
        }
    }, [search, expandableKeys]);

    const selectedGroupIds = useMemo(
        () => checkedKeys
            .filter((key) => String(key).startsWith('group-'))
            .map((key) => Number(String(key).replace('group-', '')))
            .filter(Boolean),
        [checkedKeys],
    );

    const treeData = useMemo(
        () => toTreeData(
            filteredTree,
            (id) => router.visit(route('admin.groups.show', id)),
            (id) => router.visit(route('admin.groups.edit', id)),
        ),
        [filteredTree],
    );

    const updateFilter = (departmentId) => {
        router.get(route('admin.groups.index'), {
            department_id: departmentId || undefined,
        }, {
            preserveScroll: true,
            preserveState: true,
        });
    };

    const deleteSelected = () => {
        if (!selectedGroupIds.length) {
            return;
        }

        modal.confirm({
            title: 'Tanlangan guruhlarni o‘chirish',
            content: `${selectedGroupIds.length} ta guruh o‘chiriladi. Ichida pastki guruhlari borlar o‘chirilmaydi.`,
            okText: 'Ha, o‘chirish',
            cancelText: 'Bekor qilish',
            okButtonProps: { danger: true },
            onOk: () => new Promise((resolve, reject) => {
                router.post(route('admin.groups.bulk-destroy'), {
                    group_ids: selectedGroupIds,
                }, {
                    preserveScroll: true,
                    onSuccess: () => {
                        setCheckedKeys([]);
                        resolve();
                    },
                    onError: reject,
                });
            }),
        });
    };

    return (
        <AdminLayout title="Guruhlar">
            <PageHeader
                title="Guruhlar"
                subtitle="Bo‘lim ichidagi guruhlarni daraxt ko‘rinishida boshqaring."
                createHref={route('admin.groups.create')}
                createLabel="Guruh qo‘shish"
            />

            <div className="mb-4 flex flex-wrap items-center gap-3 rounded-xl border border-line bg-surface p-3 shadow-ambient sm:p-4">
                <Input
                    allowClear
                    size="large"
                    className="min-w-[200px] flex-1 sm:max-w-xs"
                    placeholder="Guruh yoki bo‘lim qidirish…"
                    prefix={<SearchOutlined className="text-ink-muted" />}
                    value={search}
                    onChange={(e) => setSearch(e.target.value)}
                />
                <Select
                    allowClear
                    showSearch
                    size="large"
                    className="w-full min-w-[200px] sm:w-64"
                    placeholder="Barcha bo‘limlar"
                    optionFilterProp="label"
                    value={filters.department_id}
                    options={departments.map((department) => ({
                        value: department.id,
                        label: department.name,
                    }))}
                    onChange={(value) => updateFilter(value ?? null)}
                />
                <div className="ml-auto flex flex-wrap items-center gap-2">
                    <Button
                        size="large"
                        className="!rounded-lg !font-bold"
                        icon={<ExpandOutlined />}
                        onClick={() => setExpandedKeys(expandableKeys)}
                        disabled={!expandableKeys.length}
                    >
                        Ochish
                    </Button>
                    <Button
                        size="large"
                        className="!rounded-lg !font-bold"
                        icon={<CompressOutlined />}
                        onClick={() => setExpandedKeys([])}
                        disabled={!expandedKeys.length}
                    >
                        Yopish
                    </Button>
                </div>
            </div>

            {selectedGroupIds.length > 0 ? (
                <div className="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-brand-600/20 bg-brand-50 px-4 py-3 dark:border-brand-400/20 dark:bg-brand-950/40">
                    <p className="text-sm font-bold text-ink">
                        {selectedGroupIds.length}
                        {' '}
                        ta guruh tanlandi
                    </p>
                    <div className="flex items-center gap-2">
                        <Button
                            className="!rounded-lg !font-bold"
                            onClick={() => setCheckedKeys([])}
                        >
                            Bekor qilish
                        </Button>
                        <Button
                            danger
                            type="primary"
                            className="!rounded-lg !font-bold"
                            icon={<DeleteOutlined />}
                            onClick={deleteSelected}
                        >
                            O‘chirish
                        </Button>
                    </div>
                </div>
            ) : null}

            <div className="overflow-hidden rounded-xl border border-line bg-surface shadow-ambient">
                <div className="flex items-center justify-between gap-3 border-b border-line bg-surface-muted px-4 py-3 sm:px-5">
                    <p className="text-sm font-bold text-ink">
                        Tashkilot strukturasi
                    </p>
                    <p className="text-xs font-semibold text-ink-muted">
                        Jami
                        {' '}
                        {totalGroups}
                        {' '}
                        guruh
                    </p>
                </div>

                {treeData.length === 0 ? (
                    search.trim() ? (
                        <div className="px-4 py-12 text-center">
                            <p className="text-base font-extrabold text-ink">Topilmadi</p>
                            <p className="mt-2 text-sm text-ink-muted">
                                «
                                {search.trim()}
                                » bo‘yicha guruh yo‘q. Qidiruvni o‘zgartiring.
                            </p>
                            <Button className="mt-4 !rounded-lg !font-bold" onClick={() => setSearch('')}>
                                Qidiruvni tozalash
                            </Button>
                        </div>
                    ) : (
                        <EmptyState
                            title="Hali guruh yo‘q"
                            description="Birinchi guruhni qo‘shing — u tanlangan bo‘lim ostida daraxt sifatida chiqadi."
                            createHref={route('admin.groups.create')}
                            createLabel="Guruh qo‘shish"
                        />
                    )
                ) : (
                    <div className="groups-tree px-2 py-3 sm:px-3">
                        <Tree
                            checkable
                            showLine={{ showLeafIcon: false }}
                            blockNode
                            selectable={false}
                            treeData={treeData}
                            checkedKeys={checkedKeys}
                            expandedKeys={expandedKeys}
                            onCheck={(keys) => setCheckedKeys(keys)}
                            onExpand={(keys) => setExpandedKeys(keys)}
                        />
                    </div>
                )}
            </div>

            <p className="mt-3 text-xs font-semibold text-ink-muted">
                Maslahat: guruh nomini bosing — ko‘rish; qalam — tahrirlash; checkbox — bir nechtasini tanlab o‘chirish.
            </p>
        </AdminLayout>
    );
}
