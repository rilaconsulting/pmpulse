import { useState } from 'react';
import { Link } from '@inertiajs/react';
import {
    EyeSlashIcon,
    ChevronDownIcon,
    ChevronUpIcon,
    FlagIcon,
    XCircleIcon,
} from '@heroicons/react/24/outline';
import { UtilityIcons, UtilityColors } from './constants';

const FlagLabels = {
    hoa: 'HOA Property',
    tenant_pays_utilities: 'Tenant Pays Utilities',
    exclude_from_reports: 'Excluded from Reports',
};

export default function ExcludedPropertiesList({ excludedProperties }) {
    const [isExpanded, setIsExpanded] = useState(false);

    if (!excludedProperties || excludedProperties.total_count === 0) {
        return null;
    }

    const { total_count, flag_excluded_count, utility_excluded_count, properties } = excludedProperties;

    return (
        <div className="card border-gray-200 bg-gray-50">
            <button
                onClick={() => setIsExpanded(!isExpanded)}
                className="w-full card-header border-gray-200 flex items-center justify-between hover:bg-gray-100 transition-colors cursor-pointer"
            >
                <div className="flex items-center space-x-2">
                    <EyeSlashIcon className="w-5 h-5 text-gray-500" />
                    <h3 className="text-lg font-medium text-gray-900">Excluded Properties</h3>
                    <span className="px-2 py-0.5 text-xs font-medium bg-gray-200 text-gray-700 rounded-full">
                        {total_count} {total_count === 1 ? 'property' : 'properties'}
                    </span>
                </div>
                <div className="flex items-center space-x-4">
                    <div className="flex items-center space-x-2 text-sm text-gray-500">
                        {flag_excluded_count > 0 && (
                            <span className="flex items-center space-x-1">
                                <FlagIcon className="w-4 h-4" />
                                <span>{flag_excluded_count} flagged</span>
                            </span>
                        )}
                        {utility_excluded_count > 0 && (
                            <span className="flex items-center space-x-1">
                                <XCircleIcon className="w-4 h-4" />
                                <span>{utility_excluded_count} utility-specific</span>
                            </span>
                        )}
                    </div>
                    {isExpanded ? (
                        <ChevronUpIcon className="w-5 h-5 text-gray-400" />
                    ) : (
                        <ChevronDownIcon className="w-5 h-5 text-gray-400" />
                    )}
                </div>
            </button>

            {isExpanded && (
                <div className="p-4">
                    <p className="text-sm text-gray-600 mb-4">
                        These properties are excluded from utility analytics calculations.
                        Properties may be excluded entirely (via flags) or from specific utility types.
                    </p>

                    <div className="space-y-3">
                        {properties.map((property) => (
                            <div
                                key={property.id}
                                className="p-3 bg-white rounded-lg border border-gray-200"
                            >
                                <div className="flex items-start justify-between">
                                    <div>
                                        <Link
                                            href={`/properties/${property.id}`}
                                            className="text-sm font-medium text-gray-900 hover:text-blue-600"
                                        >
                                            {property.name}
                                        </Link>

                                        {/* Flag-based exclusions (excluded from all utilities) */}
                                        {property.exclusion_type === 'all_utilities' && property.flags.length > 0 && (
                                            <div className="mt-2">
                                                <span className="text-xs font-medium text-gray-500 uppercase tracking-wide">
                                                    Excluded from all utilities
                                                </span>
                                                <div className="mt-1 flex flex-wrap gap-2">
                                                    {property.flags.map((flag, idx) => (
                                                        <span
                                                            key={idx}
                                                            className="inline-flex items-center px-2 py-1 rounded-md bg-amber-50 text-amber-700 text-xs"
                                                            title={flag.reason || ''}
                                                        >
                                                            <FlagIcon className="w-3 h-3 mr-1" />
                                                            {FlagLabels[flag.type] || flag.label || flag.type}
                                                            {flag.reason && (
                                                                <span className="ml-1 text-amber-500">
                                                                    ({flag.reason})
                                                                </span>
                                                            )}
                                                        </span>
                                                    ))}
                                                </div>
                                            </div>
                                        )}

                                        {/* Utility-specific exclusions */}
                                        {property.exclusion_type === 'specific_utilities' && property.utility_exclusions.length > 0 && (
                                            <div className="mt-2">
                                                <span className="text-xs font-medium text-gray-500 uppercase tracking-wide">
                                                    Excluded from specific utilities
                                                </span>
                                                <div className="mt-1 flex flex-wrap gap-2">
                                                    {property.utility_exclusions.map((exclusion, idx) => {
                                                        const Icon = UtilityIcons[exclusion.utility_type];
                                                        const colors = UtilityColors[exclusion.utility_type] || UtilityColors.other;

                                                        return (
                                                            <span
                                                                key={idx}
                                                                className={`inline-flex items-center px-2 py-1 rounded-md text-xs ${colors.bg} ${colors.text}`}
                                                                title={exclusion.reason ? `Reason: ${exclusion.reason}` : ''}
                                                            >
                                                                {Icon && <Icon className="w-3 h-3 mr-1" />}
                                                                {exclusion.utility_label}
                                                                {exclusion.reason && (
                                                                    <span className="ml-1 opacity-75">
                                                                        ({exclusion.reason})
                                                                    </span>
                                                                )}
                                                            </span>
                                                        );
                                                    })}
                                                </div>
                                            </div>
                                        )}
                                    </div>
                                </div>
                            </div>
                        ))}
                    </div>

                    <p className="mt-4 text-xs text-gray-500">
                        To manage exclusions, visit the property settings page or use property flags.
                    </p>
                </div>
            )}
        </div>
    );
}
