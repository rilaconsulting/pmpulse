import {
    CheckCircleIcon,
    ClockIcon,
    XCircleIcon,
    ExclamationTriangleIcon,
} from '@heroicons/react/24/outline';

/**
 * Insurance status badge for compact display (tables, lists)
 * @param {{ status: { overall?: string } }} props
 */
export default function InsuranceStatusBadge({ status }) {
    const overall = status?.overall || 'missing';

    const styles = {
        current: { bg: 'bg-green-100', text: 'text-green-800', icon: CheckCircleIcon, label: 'Current' },
        expiring_soon: { bg: 'bg-yellow-100', text: 'text-yellow-800', icon: ClockIcon, label: 'Expiring Soon' },
        expired: { bg: 'bg-red-100', text: 'text-red-800', icon: XCircleIcon, label: 'Expired' },
        missing: { bg: 'bg-gray-100', text: 'text-gray-600', icon: ExclamationTriangleIcon, label: 'Missing' },
    };

    const style = styles[overall] || styles.missing;
    const Icon = style.icon;

    return (
        <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${style.bg} ${style.text}`}>
            <Icon className="w-3.5 h-3.5 mr-1" />
            {style.label}
        </span>
    );
}
