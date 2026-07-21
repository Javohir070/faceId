import { ExclamationCircleOutlined } from '@ant-design/icons';

export default function InputError({ message, className = '' }) {
    if (!message) {
        return null;
    }

    return (
        <p className={`field-error ${className}`} role="alert">
            <ExclamationCircleOutlined className="mt-0.5 shrink-0" aria-hidden="true" />
            <span>{message}</span>
        </p>
    );
}
