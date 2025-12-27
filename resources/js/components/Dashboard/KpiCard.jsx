import { ArrowUpIcon, ArrowDownIcon } from '@heroicons/react/24/solid';

export default function KpiCard({ title, value, subtitle, trend, trendDirection, icon: Icon }) {
    return (
        <div className="card">
            <div className="card-body">
                <div className="flex items-center justify-between">
                    <div>
                        <p className="text-sm font-medium text-gray-500">{title}</p>
                        <p className="mt-1 text-2xl font-semibold text-gray-900">{value}</p>
                        {subtitle && (
                            <p className="mt-1 text-sm text-gray-500">{subtitle}</p>
                        )}
                    </div>
                    {Icon && (
                        <div className="p-3 bg-blue-50 rounded-lg">
                            <Icon className="w-6 h-6 text-blue-600" />
                        </div>
                    )}
                </div>
                {trend !== undefined && (
                    <div className="mt-4 flex items-center">
                        {trendDirection === 'up' ? (
                            <ArrowUpIcon className="w-4 h-4 text-green-500" />
                        ) : trendDirection === 'down' ? (
                            <ArrowDownIcon className="w-4 h-4 text-red-500" />
                        ) : null}
                        <span
                            className={`ml-1 text-sm font-medium ${
                                trendDirection === 'up'
                                    ? 'text-green-600'
                                    : trendDirection === 'down'
                                    ? 'text-red-600'
                                    : 'text-gray-600'
                            }`}
                        >
                            {trend}
                        </span>
                        <span className="ml-2 text-sm text-gray-500">vs last period</span>
                    </div>
                )}
            </div>
        </div>
    );
}
