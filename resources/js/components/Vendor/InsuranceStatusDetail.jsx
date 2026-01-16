import {
    CheckCircleIcon,
    ClockIcon,
    XCircleIcon,
    ExclamationTriangleIcon,
} from '@heroicons/react/24/outline';
import { formatDate } from './formatters';

/**
 * Detailed insurance status display with date and label
 * @param {{ status: string, date: string|null, label: string }} props
 */
export default function InsuranceStatusDetail({ status, date, label }) {
    const styles = {
        current: { bg: 'bg-green-100', text: 'text-green-800', icon: CheckCircleIcon },
        expiring_soon: { bg: 'bg-yellow-100', text: 'text-yellow-800', icon: ClockIcon },
        expired: { bg: 'bg-red-100', text: 'text-red-800', icon: XCircleIcon },
        missing: { bg: 'bg-gray-100', text: 'text-gray-600', icon: ExclamationTriangleIcon },
    };

    const style = styles[status] || styles.missing;
    const Icon = style.icon;

    return (
        <div className={`flex items-center justify-between p-3 rounded-lg ${style.bg}`}>
            <div className="flex items-center gap-2">
                <Icon className={`w-5 h-5 ${style.text}`} />
                <span className={`font-medium ${style.text}`}>{label}</span>
            </div>
            <span className={`text-sm ${style.text}`}>
                {date ? formatDate(date) : 'Not on file'}
            </span>
        </div>
    );
}
