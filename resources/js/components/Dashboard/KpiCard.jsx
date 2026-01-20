import { ArrowUpIcon, ArrowDownIcon } from '@heroicons/react/24/solid';

export default function KpiCard({ title, value, subtitle, trend, trendDirection, icon: Icon }) {
    return (
        <div className="card">
            <div className="card-body">
                <div className="flex items-start justify-between gap-2">
                    <div className="min-w-0 flex-1">
                        <p className="text-xs md:text-sm font-medium text-gray-500 truncate">{title}</p>
                        <p className="mt-1 text-xl md:text-2xl font-semibold text-gray-900 truncate">{value}</p>
                        {subtitle && (
                            <p className="mt-1 text-xs md:text-sm text-gray-500 truncate">{subtitle}</p>
                        )}
                    </div>
                    {Icon && (
                        <div className="p-2 md:p-3 bg-blue-50 rounded-lg flex-shrink-0">
                            <Icon className="w-5 h-5 md:w-6 md:h-6 text-blue-600" />
                        </div>
                    )}
                </div>
                {trend !== undefined && (
                    <div className="mt-3 md:mt-4 flex items-center flex-wrap">
                        {trendDirection === 'up' ? (
                            <ArrowUpIcon className="w-3 h-3 md:w-4 md:h-4 text-green-500" />
                        ) : trendDirection === 'down' ? (
                            <ArrowDownIcon className="w-3 h-3 md:w-4 md:h-4 text-red-500" />
                        ) : null}
                        <span
                            className={`ml-1 text-xs md:text-sm font-medium ${
                                trendDirection === 'up'
                                    ? 'text-green-600'
                                    : trendDirection === 'down'
                                    ? 'text-red-600'
                                    : 'text-gray-600'
                            }`}
                        >
                            {trend}
                        </span>
                        <span className="ml-1 md:ml-2 text-xs md:text-sm text-gray-500">vs last period</span>
                    </div>
                )}
            </div>
        </div>
    );
}
