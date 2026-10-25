import Icon from '@ant-design/icons';

// Erkak / ayol siluetlari (piktogramma) — @ant-design/icons da yo‘q, shuning uchun Ant Design `Icon` orqali o‘z SVG.
// Rang `currentColor`, o‘lcham `1em` — oddiy Ant Design ikonkasidek ishlatiladi.

function MaleSvg() {
    return (
        <svg viewBox="0 0 24 24" width="1em" height="1em" fill="currentColor" aria-hidden="true" focusable="false">
            <circle cx="12" cy="3.75" r="2.5" />
            <path d="M9.5 7h5a2.5 2.5 0 0 1 2.5 2.5V14a1 1 0 0 1-1 1h-1.25v5.75a1.25 1.25 0 0 1-2.5 0V15h-.5v5.75a1.25 1.25 0 0 1-2.5 0V15H8a1 1 0 0 1-1-1V9.5A2.5 2.5 0 0 1 9.5 7z" />
        </svg>
    );
}

function FemaleSvg() {
    return (
        <svg viewBox="0 0 24 24" width="1em" height="1em" fill="currentColor" aria-hidden="true" focusable="false">
            <circle cx="12" cy="3.75" r="2.5" />
            <path d="M10 7h4a2 2 0 0 1 1.94 1.52l1.96 7.24A1 1 0 0 1 16.93 17H15v3.75a1.25 1.25 0 0 1-2.5 0V17h-1v3.75a1.25 1.25 0 0 1-2.5 0V17H7.07a1 1 0 0 1-.97-1.24l1.96-7.24A2 2 0 0 1 10 7z" />
        </svg>
    );
}

export function MaleFigureIcon(props) {
    return <Icon component={MaleSvg} {...props} />;
}

export function FemaleFigureIcon(props) {
    return <Icon component={FemaleSvg} {...props} />;
}
