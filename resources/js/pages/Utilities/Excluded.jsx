import { Head } from '@inertiajs/react';
import { Link } from '@inertiajs/react';
import Layout from '../../components/Layout';
import PageHeader from '../../components/PageHeader';
import { getIconComponent, getColorScheme } from '../../components/Utilities/constants';
import {
    ChartBarIcon,
    TableCellsIcon,
    EyeSlashIcon,
    FlagIcon,
    XCircleIcon,
} from '@heroicons/react/24/outline';

const FlagLabels = {
    hoa: 'HOA Property',
    tenant_pays_utilities: 'Tenant Pays Utilities',
    exclude_from_reports: 'Excluded from Reports',
};

export default function UtilitiesExcluded({ excludedProperties }) {
    const { total_count = 0, flag_excluded_count = 0, utility_excluded_count = 0, properties = [] } = excludedProperties || {};

    return (
        <Layout>
            <Head title="Excluded Properties" />

            <div className="space-y-6">
                {/* Header */}
                <PageHeader
                    title="Excluded Properties"
                    subtitle="Properties excluded from utility analytics calculations"
                    tabs={[
                        { label: 'Data Table', href: route('utilities.data'), icon: TableCellsIcon },
                        { label: 'Dashboard', href: route('utilities.dashboard'), icon: ChartBarIcon },
                        { label: 'Excluded', href: route('utilities.excluded'), icon: EyeSlashIcon },
                    ]}
                    activeTab="Excluded"
                />

                {/* Summary Stats */}
                <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div className="card">
                        <div className="card-body">
                            <div className="flex items-center space-x-3">
                                <div className="p-2 rounded-lg bg-gray-100">
                                    <EyeSlashIcon className="w-5 h-5 text-gray-600" />
                                </div>
                                <div>
                                    <p className="text-sm text-gray-500">Total Excluded</p>
                                    <p className="text-2xl font-semibold text-gray-900">{total_count}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div className="card">
                        <div className="card-body">
                            <div className="flex items-center space-x-3">
                                <div className="p-2 rounded-lg bg-amber-100">
                                    <FlagIcon className="w-5 h-5 text-amber-600" />
                                </div>
                                <div>
                                    <p className="text-sm text-gray-500">Flagged Properties</p>
                                    <p className="text-2xl font-semibold text-gray-900">{flag_excluded_count}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div className="card">
                        <div className="card-body">
                            <div className="flex items-center space-x-3">
                                <div className="p-2 rounded-lg bg-blue-100">
                                    <XCircleIcon className="w-5 h-5 text-blue-600" />
                                </div>
                                <div>
                                    <p className="text-sm text-gray-500">Utility-Specific</p>
                                    <p className="text-2xl font-semibold text-gray-900">{utility_excluded_count}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {/* Properties List */}
                <div className="card">
                    <div className="card-header">
                        <h2 className="text-lg font-medium text-gray-900">Excluded Properties</h2>
                    </div>
                    <div className="card-body">
                        {properties.length === 0 ? (
                            <div className="text-center py-12">
                                <EyeSlashIcon className="w-12 h-12 text-gray-300 mx-auto mb-4" />
                                <h3 className="text-lg font-medium text-gray-900 mb-2">No Excluded Properties</h3>
                                <p className="text-gray-500">
                                    All properties are included in utility analytics calculations.
                                </p>
                            </div>
                        ) : (
                            <div className="space-y-3">
                                {properties.map((property) => (
                                    <div
                                        key={property.id}
                                        className="p-4 bg-gray-50 rounded-lg border border-gray-200"
                                    >
                                        <div className="flex items-start justify-between">
                                            <div className="flex-1">
                                                <Link
                                                    href={`/properties/${property.id}`}
                                                    className="text-sm font-medium text-gray-900 hover:text-blue-600"
                                                >
                                                    {property.name}
                                                </Link>

                                                {/* Flag-based exclusions (excluded from all utilities) */}
                                                {property.exclusion_type === 'all_utilities' && property.flags?.length > 0 && (
                                                    <div className="mt-2">
                                                        <span className="text-xs font-medium text-gray-500 uppercase tracking-wide">
                                                            Excluded from all utilities
                                                        </span>
                                                        <div className="mt-1 flex flex-wrap gap-2">
                                                            {property.flags.map((flag) => (
                                                                <span
                                                                    key={flag.type}
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
                                                {property.exclusion_type === 'specific_utilities' && property.utility_exclusions?.length > 0 && (
                                                    <div className="mt-2">
                                                        <span className="text-xs font-medium text-gray-500 uppercase tracking-wide">
                                                            Excluded from specific utilities
                                                        </span>
                                                        <div className="mt-1 flex flex-wrap gap-2">
                                                            {property.utility_exclusions.map((exclusion) => {
                                                                const Icon = getIconComponent(exclusion.icon);
                                                                const colors = getColorScheme(exclusion.color_scheme);

                                                                return (
                                                                    <span
                                                                        key={exclusion.utility_type}
                                                                        className={`inline-flex items-center px-2 py-1 rounded-md text-xs ${colors.bg} ${colors.text}`}
                                                                        title={exclusion.reason ? `Reason: ${exclusion.reason}` : ''}
                                                                    >
                                                                        <Icon className="w-3 h-3 mr-1" />
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
                        )}

                        {properties.length > 0 && (
                            <p className="mt-4 text-xs text-gray-500">
                                To manage exclusions, visit the property settings page or use property flags.
                            </p>
                        )}
                    </div>
                </div>
            </div>
        </Layout>
    );
}
