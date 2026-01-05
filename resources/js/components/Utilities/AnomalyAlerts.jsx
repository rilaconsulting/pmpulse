import { Link } from '@inertiajs/react';
import {
    ExclamationTriangleIcon,
    ArrowTrendingUpIcon,
    ArrowTrendingDownIcon,
} from '@heroicons/react/24/outline';

const getAnomalyIcon = (type) => {
    switch (type) {
        case 'high':
            return ArrowTrendingUpIcon;
        case 'low':
            return ArrowTrendingDownIcon;
        default:
            return ExclamationTriangleIcon;
    }
};

const getAnomalyColors = (type) => {
    switch (type) {
        case 'high':
            return {
                bg: 'bg-red-50',
                border: 'border-red-200',
                icon: 'text-red-500',
                text: 'text-red-800',
                badge: 'bg-red-100 text-red-700',
            };
        case 'low':
            return {
                bg: 'bg-green-50',
                border: 'border-green-200',
                icon: 'text-green-500',
                text: 'text-green-800',
                badge: 'bg-green-100 text-green-700',
            };
        default:
            return {
                bg: 'bg-yellow-50',
                border: 'border-yellow-200',
                icon: 'text-yellow-500',
                text: 'text-yellow-800',
                badge: 'bg-yellow-100 text-yellow-700',
            };
    }
};

export default function AnomalyAlerts({ anomalies }) {
    if (!anomalies || anomalies.length === 0) {
        return null;
    }

    const formatCurrency = (value) => {
        if (!value) return '$0';
        return new Intl.NumberFormat('en-US', {
            style: 'currency',
            currency: 'USD',
            minimumFractionDigits: 0,
            maximumFractionDigits: 0,
        }).format(value);
    };

    return (
        <div className="card border-yellow-200 bg-yellow-50">
            <div className="card-header border-yellow-200">
                <div className="flex items-center space-x-2">
                    <ExclamationTriangleIcon className="w-5 h-5 text-yellow-600" />
                    <h3 className="text-lg font-medium text-gray-900">Anomaly Alerts</h3>
                    <span className="px-2 py-0.5 text-xs font-medium bg-yellow-100 text-yellow-700 rounded-full">
                        {anomalies.length} detected
                    </span>
                </div>
                <p className="mt-1 text-sm text-gray-600">
                    Properties with utility costs significantly different from portfolio average
                </p>
            </div>
            <div className="p-4">
                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5 gap-3">
                    {anomalies.map((anomaly, index) => {
                        const Icon = getAnomalyIcon(anomaly.type);
                        const colors = getAnomalyColors(anomaly.type);

                        return (
                            <Link
                                key={`${anomaly.property_id}-${anomaly.utility_type}-${index}`}
                                href={`/utilities/property/${anomaly.property_id}`}
                                className={`block p-4 rounded-lg border ${colors.bg} ${colors.border} hover:shadow-md transition-shadow`}
                            >
                                <div className="flex items-start space-x-3">
                                    <div className={`p-1.5 rounded-full ${colors.badge}`}>
                                        <Icon className="w-4 h-4" />
                                    </div>
                                    <div className="flex-1 min-w-0">
                                        <p className={`text-sm font-medium ${colors.text} truncate`}>
                                            {anomaly.property_name}
                                        </p>
                                        <p className="text-xs text-gray-600 mt-0.5">
                                            {anomaly.utility_label}
                                        </p>
                                        <div className="mt-2 flex items-baseline space-x-2">
                                            <span className="text-sm font-semibold text-gray-900">
                                                {formatCurrency(anomaly.value)}/unit
                                            </span>
                                            <span className={`text-xs ${colors.text}`}>
                                                ({anomaly.deviation > 0 ? '+' : ''}{anomaly.deviation.toFixed(1)} SD)
                                            </span>
                                        </div>
                                        <p className="text-xs text-gray-500 mt-1">
                                            Avg: {formatCurrency(anomaly.average)}/unit
                                        </p>
                                    </div>
                                </div>
                            </Link>
                        );
                    })}
                </div>
            </div>
        </div>
    );
}
