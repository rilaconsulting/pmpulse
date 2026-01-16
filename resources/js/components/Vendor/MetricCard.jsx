import {
    ArrowTrendingUpIcon,
    ArrowTrendingDownIcon,
    MinusIcon,
} from '@heroicons/react/24/outline';

/**
 * Metric display card with optional change indicator
 * @param {{ title: string, value: string|number, subtitle?: string, change?: number, invertChange?: boolean }} props
 */
export default function MetricCard({ title, value, subtitle, change, invertChange = false }) {
    const showChange = change !== null && change !== undefined;
    const isPositive = invertChange ? change < 0 : change > 0;
    const isNegative = invertChange ? change > 0 : change < 0;

    return (
        <div className="card">
            <div className="card-body">
                <p className="text-sm font-medium text-gray-500">{title}</p>
                <p className="text-2xl font-semibold text-gray-900">{value}</p>
                {subtitle && <p className="text-xs text-gray-500 mt-1">{subtitle}</p>}
                {showChange && (
                    <div className={`flex items-center mt-2 text-sm ${
                        isPositive ? 'text-green-600' : isNegative ? 'text-red-600' : 'text-gray-500'
                    }`}>
                        {isPositive ? (
                            <ArrowTrendingUpIcon className="w-4 h-4 mr-1" />
                        ) : isNegative ? (
                            <ArrowTrendingDownIcon className="w-4 h-4 mr-1" />
                        ) : (
                            <MinusIcon className="w-4 h-4 mr-1" />
                        )}
                        {Math.abs(change)}% vs last year
                    </div>
                )}
            </div>
        </div>
    );
}
