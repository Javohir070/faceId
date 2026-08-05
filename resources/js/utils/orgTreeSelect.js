export const encodeOrgFilter = (departmentIds = [], groupIds = []) => [
    ...departmentIds.map((id) => `d-${id}`),
    ...groupIds.map((id) => `g-${id}`),
];

export const parseOrgFilter = (values = []) => {
    const department_ids = [];
    const group_ids = [];

    values.forEach((value) => {
        if (typeof value !== 'string') {
            return;
        }

        if (value.startsWith('d-')) {
            const id = Number(value.slice(2));
            if (id) {
                department_ids.push(id);
            }
            return;
        }

        if (value.startsWith('g-')) {
            const id = Number(value.slice(2));
            if (id) {
                group_ids.push(id);
            }
        }
    });

    return { department_ids, group_ids };
};

const buildGroupNodes = (groups, parentId = null) => groups
    .filter((group) => (group.parent_id ?? null) === parentId)
    .map((group) => {
        const children = buildGroupNodes(groups, group.id);

        return {
            title: group.name,
            value: `g-${group.id}`,
            key: `g-${group.id}`,
            children: children.length > 0 ? children : undefined,
        };
    });

export const buildOrgTreeData = (departments, groups) => departments.map((department) => {
    const departmentGroups = groups.filter((group) => group.department_id === department.id);
    const groupIds = new Set(departmentGroups.map((group) => group.id));
    const rootGroups = departmentGroups.filter(
        (group) => group.parent_id == null || !groupIds.has(group.parent_id),
    );

    const children = rootGroups.map((group) => {
        const nested = buildGroupNodes(departmentGroups, group.id);

        return {
            title: group.name,
            value: `g-${group.id}`,
            key: `g-${group.id}`,
            children: nested.length > 0 ? nested : undefined,
        };
    });

    return {
        title: department.name,
        value: `d-${department.id}`,
        key: `d-${department.id}`,
        children: children.length > 0 ? children : undefined,
    };
});
