import { Tag } from 'antd';

const toneClass = {
    success: 'status-tag status-tag--success',
    neutral: 'status-tag status-tag--neutral',
    warning: 'status-tag status-tag--warning',
    info: 'status-tag status-tag--info',
    danger: 'status-tag status-tag--danger',
};

/**
 * Theme-aware status chip (light + dark). Prefer this over Ant Tag color presets.
 */
export default function StatusTag({ tone = 'neutral', children, className = '', ...props }) {
    return (
        <Tag
            bordered
            className={`${toneClass[tone] || toneClass.neutral} ${className}`.trim()}
            {...props}
        >
            {children}
        </Tag>
    );
}
