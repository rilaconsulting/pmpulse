import { ArrowUpIcon, ArrowDownIcon } from '@heroicons/react/24/solid';

/**
 * StatCard - A stat display card for use in StatsGrid.
 *
 * @param {Object} props
 * @param {string} props.label - Stat label
 * @param {string|number} props.value - Stat value
 * @param {string} props.trend - Optional trend indicator (e.g., "+5%", "-3%")
 * @param {string} props.trendDirection - 'up' | 'down' | 'neutral' (auto-detected from trend if not provided)
 * @param {React.ReactNode} props.icon - Optional icon component
 * @param {string} props.iconBgColor - Icon background color (default: bg-blue-100)
 * @param {string} props.iconColor - Icon color (default: text-blue-600)
 * @param {string} props.subtitle - Optional subtitle/description
 * @param {Function} props.onClick - Optional click handler
 * @param {string} props.className - Additional classes
 */
export default function StatCard({
    label,
    value,
    trend,
    trendDirection,
    icon,
    iconBgColor = 'bg-blue-100',
    iconColor = 'text-blue-600',
    subtitle,
    onClick,
    className = '',
}) {
    // Auto-detect trend direction if not provided
    const direction = trendDirection || (trend?.startsWith('+') ? 'up' : trend?.startsWith('-') ? 'down' : 'neutral');

    const trendColors = {
        up: 'text-green-600',
        down: 'text-red-600',
        neutral: 'text-gray-500',
    };

    const CardWrapper = onClick ? 'button' : 'div';

    return (
        <CardWrapper
            type={onClick ? 'button' : undefined}
            onClick={onClick}
            className={`card ${onClick ? 'hover:bg-gray-50 active:bg-gray-100 cursor-pointer transition-colors' : ''} ${className}`}
        >
            <div className="p-4 md:p-5">
                <div className="flex items-start justify-between">
                    <div className="flex-1 min-w-0">
                        {/* Label */}
                        <p className="text-xs md:text-sm font-medium text-gray-500 truncate">
                            {label}
                        </p>

                        {/* Value */}
                        <p className="mt-1 text-xl md:text-2xl font-semibold text-gray-900 truncate">
                            {value}
                        </p>

                        {/* Trend or subtitle */}
                        {(trend || subtitle) && (
                            <div className="mt-1 flex items-center gap-1">
                                {trend && (
                                    <>
                                        {direction === 'up' && (
                                            <ArrowUpIcon className={`w-3 h-3 ${trendColors.up}`} />
                                        )}
                                        {direction === 'down' && (
                                            <ArrowDownIcon className={`w-3 h-3 ${trendColors.down}`} />
                                        )}
                                        <span className={`text-xs font-medium ${trendColors[direction]}`}>
                                            {trend}
                                        </span>
                                    </>
                                )}
                                {subtitle && !trend && (
                                    <span className="text-xs text-gray-500">{subtitle}</span>
                                )}
                            </div>
                        )}
                    </div>

                    {/* Icon */}
                    {icon && (
                        <div className={`flex-shrink-0 w-10 h-10 md:w-12 md:h-12 ${iconBgColor} rounded-lg flex items-center justify-center`}>
                            <div className={`w-5 h-5 md:w-6 md:h-6 ${iconColor}`}>
                                {icon}
                            </div>
                        </div>
                    )}
                </div>
            </div>
        </CardWrapper>
    );
}
