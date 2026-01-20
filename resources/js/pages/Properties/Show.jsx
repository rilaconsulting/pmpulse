import { Head, Link, useForm, usePage, router } from '@inertiajs/react';
import { useState, useCallback, useEffect } from 'react';
import { GoogleMap, LoadScript, Marker } from '@react-google-maps/api';
import Layout from '../../components/Layout';
import PageHeader from '../../components/PageHeader';
import PropertyTabs, { PropertyTabPanel } from '../../components/Property/PropertyTabs';
import AdjustmentList from '../../components/Property/AdjustmentList';
import AdjustedValue from '../../components/AdjustedValue';
import PropertyUtilityTrend from '../../components/Utilities/PropertyUtilityTrend';
import { formatCurrency as formatUtilityCurrency, findUtilityType, getIconComponent, getColorScheme } from '../../components/Utilities/constants';
import {
    MapPinIcon,
    HomeModernIcon,
    ChevronUpIcon,
    ChevronDownIcon,
    ChevronLeftIcon,
    ChevronRightIcon,
    Square3Stack3DIcon,
    CurrencyDollarIcon,
    FlagIcon,
    PlusIcon,
    XMarkIcon,
    ArrowTopRightOnSquareIcon,
    InformationCircleIcon,
    BoltIcon,
    WrenchScrewdriverIcon,
    Cog6ToothIcon,
} from '@heroicons/react/24/outline';

export default function PropertyShow({
    property,
    units,
    unitFilters,
    stats,
    flagTypes,
    appfolioUrl,
    googleMapsApiKey,
    adjustableFields,
    activeAdjustments,
    historicalAdjustments,
    effectiveValues,
    initialTab,
    utilityData,
    workOrderData,
}) {
    const { auth } = usePage().props;
    const isAdmin = auth?.user?.role?.name === 'admin';

    // Tab state - initialized from URL param or default to 'overview'
    const [activeTab, setActiveTab] = useState(initialTab || 'overview');

    // Define tabs configuration
    const tabs = [
        { id: 'overview', label: 'Overview', icon: InformationCircleIcon },
        { id: 'units', label: 'Units', icon: HomeModernIcon, count: stats?.total_units },
        { id: 'utilities', label: 'Utilities', icon: BoltIcon },
        { id: 'work-orders', label: 'Work Orders', icon: WrenchScrewdriverIcon },
        ...(isAdmin ? [{ id: 'settings', label: 'Settings', icon: Cog6ToothIcon }] : []),
    ];

    // Handle tab change with URL persistence
    const handleTabChange = useCallback((tabId) => {
        setActiveTab(tabId);
        // Update URL without full page reload
        const url = new URL(window.location);
        if (tabId === 'overview') {
            url.searchParams.delete('tab');
        } else {
            url.searchParams.set('tab', tabId);
        }
        window.history.replaceState({}, '', url);
    }, []);

    const [showAddFlagModal, setShowAddFlagModal] = useState(false);
    const [deletingFlagId, setDeletingFlagId] = useState(null);

    // Helper to update unit filters via Inertia
    const updateUnitFilters = useCallback((newFilters) => {
        const merged = { ...unitFilters, ...newFilters };
        const data = {};

        if (merged.status) data.unit_status = merged.status;
        if (merged.sort && merged.sort !== 'unit_number') data.unit_sort = merged.sort;
        if (merged.direction && merged.direction !== 'asc') data.unit_direction = merged.direction;

        router.get(
            route('properties.show', property.id),
            data,
            { preserveScroll: true, preserveState: true }
        );
    }, [property.id, unitFilters]);

    // Form for adding flags
    const { data, setData, post, processing, errors, reset } = useForm({
        flag_type: '',
        reason: '',
    });

    const handleAddFlag = (e) => {
        e.preventDefault();
        post(route('properties.flags.store', property.id), {
            onSuccess: () => {
                reset();
                setShowAddFlagModal(false);
            },
        });
    };

    const handleDeleteFlag = (flagId) => {
        if (!confirm('Are you sure you want to remove this flag?')) return;
        setDeletingFlagId(flagId);
        router.delete(route('properties.flags.destroy', [property.id, flagId]), {
            onFinish: () => setDeletingFlagId(null),
        });
    };

    // Get available flag types (not already on property)
    const existingFlagTypes = (property.flags || []).map(f => f.flag_type);
    const availableFlagTypes = Object.entries(flagTypes || {}).filter(
        ([key]) => !existingFlagTypes.includes(key)
    );

    const getFlagBadgeClass = (flagType) => {
        switch (flagType) {
            case 'hoa':
                return 'bg-blue-100 text-blue-800';
            case 'tenant_pays_utilities':
                return 'bg-purple-100 text-purple-800';
            case 'exclude_from_reports':
                return 'bg-red-100 text-red-800';
            case 'under_renovation':
                return 'bg-orange-100 text-orange-800';
            case 'sold':
                return 'bg-gray-100 text-gray-800';
            default:
                return 'bg-yellow-100 text-yellow-800';
        }
    };

    const handleUnitSort = (field) => {
        const newDirection = unitFilters.sort === field && unitFilters.direction === 'asc' ? 'desc' : 'asc';
        updateUnitFilters({ sort: field, direction: newDirection });
    };

    const handleUnitStatusFilter = (status) => {
        updateUnitFilters({ status: status === 'all' ? '' : status });
    };

    const SortIcon = ({ field }) => {
        if (unitFilters.sort !== field) {
            return <ChevronUpIcon className="w-4 h-4 text-gray-300" />;
        }
        return unitFilters.direction === 'asc'
            ? <ChevronUpIcon className="w-4 h-4 text-blue-600" />
            : <ChevronDownIcon className="w-4 h-4 text-blue-600" />;
    };

    const SortableHeader = ({ field, children }) => {
        const isSorted = unitFilters.sort === field;
        const sortDirection = isSorted ? (unitFilters.direction === 'asc' ? 'ascending' : 'descending') : 'none';

        return (
            <th
                className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                aria-sort={sortDirection}
            >
                <button
                    type="button"
                    className="flex items-center gap-1 hover:text-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 rounded"
                    onClick={() => handleUnitSort(field)}
                >
                    {children}
                    <SortIcon field={field} />
                </button>
            </th>
        );
    };

    const getStatusBadgeClass = (status) => {
        switch (status) {
            case 'occupied':
                return 'bg-green-100 text-green-800';
            case 'vacant':
                return 'bg-yellow-100 text-yellow-800';
            case 'not_ready':
                return 'bg-red-100 text-red-800';
            default:
                return 'bg-gray-100 text-gray-800';
        }
    };

    const getStatusLabel = (status) => {
        switch (status) {
            case 'occupied':
                return 'Occupied';
            case 'vacant':
                return 'Vacant';
            case 'not_ready':
                return 'Not Ready';
            default:
                return status || 'Unknown';
        }
    };

    const formatCurrency = (amount) => {
        if (amount === null || amount === undefined) return '-';
        return new Intl.NumberFormat('en-US', {
            style: 'currency',
            currency: 'USD',
            minimumFractionDigits: 0,
            maximumFractionDigits: 0,
        }).format(amount);
    };

    return (
        <Layout>
            <Head title={property.name} />

            {/* Sticky Property Header */}
            <PageHeader
                title={property.name}
                backHref={route('properties.index')}
                statusBadge={{
                    label: property.is_active ? 'Active' : 'Inactive',
                    variant: property.is_active ? 'success' : 'danger',
                }}
                secondaryInfo={
                    <>
                        {property.address_line1 && (
                            <span className="truncate">
                                {property.address_line1}
                                {property.city && `, ${property.city}`}
                                {property.state && `, ${property.state}`}
                                {property.zip && ` ${property.zip}`}
                            </span>
                        )}
                        {property.address_line1 && property.portfolio && (
                            <span className="text-gray-300">•</span>
                        )}
                        {property.portfolio && (
                            <span className="flex-shrink-0">{property.portfolio}</span>
                        )}
                    </>
                }
                actions={
                    <>
                        {appfolioUrl && (
                            <a
                                href={appfolioUrl}
                                target="_blank"
                                rel="noopener noreferrer"
                                className="inline-flex items-center gap-1 px-2.5 py-1 rounded-md text-xs font-medium bg-indigo-50 text-indigo-700 hover:bg-indigo-100 transition-colors"
                            >
                                <ArrowTopRightOnSquareIcon className="w-3.5 h-3.5" />
                                AppFolio
                            </a>
                        )}
                        {/* Property Flags */}
                        {(property.flags || []).map((flag) => (
                            <span
                                key={flag.id}
                                className={`inline-flex items-center gap-1 px-2.5 py-1 rounded-md text-xs font-medium ${getFlagBadgeClass(flag.flag_type)}`}
                                title={flag.reason || undefined}
                            >
                                <FlagIcon className="w-3 h-3" />
                                {flagTypes?.[flag.flag_type] || flag.flag_type}
                                {isAdmin && (
                                    <button
                                        type="button"
                                        onClick={() => handleDeleteFlag(flag.id)}
                                        disabled={deletingFlagId === flag.id}
                                        className="ml-0.5 hover:opacity-75 focus:outline-none"
                                        title="Remove flag"
                                    >
                                        <XMarkIcon className="w-3 h-3" />
                                    </button>
                                )}
                            </span>
                        ))}
                        {/* Add Flag Button (Admin Only) */}
                        {isAdmin && availableFlagTypes.length > 0 && (
                            <button
                                type="button"
                                onClick={() => setShowAddFlagModal(true)}
                                className="inline-flex items-center gap-1 px-2.5 py-1 rounded-md text-xs font-medium bg-gray-100 text-gray-600 hover:bg-gray-200 transition-colors"
                            >
                                <PlusIcon className="w-3 h-3" />
                                Add Flag
                            </button>
                        )}
                    </>
                }
                sticky
            />

            {/* Tab Navigation */}
            <div className="mt-4">
                <PropertyTabs
                    tabs={tabs}
                    activeTab={activeTab}
                    onTabChange={handleTabChange}
                />
            </div>

            {/* Tab Content */}
            <div className="space-y-6 pt-4">

                {/* Overview Tab */}
                <PropertyTabPanel id="overview" isActive={activeTab === 'overview'}>
                    <div className="space-y-6">
                        {/* Stats Grid */}
                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                    {/* Total Units */}
                    <div className="card">
                        <div className="card-body">
                            <div className="flex items-center">
                                <div className="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center">
                                    <HomeModernIcon className="w-5 h-5 text-blue-600" />
                                </div>
                                <div className="ml-4">
                                    <p className="text-sm text-gray-500">Total Units</p>
                                    <p className="text-2xl font-semibold text-gray-900">
                                        <AdjustedValue
                                            value={effectiveValues?.unit_count?.value ?? stats.total_units}
                                            isAdjusted={effectiveValues?.unit_count?.is_adjusted}
                                            original={effectiveValues?.unit_count?.original}
                                            label="Unit Count"
                                            adjustment={effectiveValues?.unit_count?.adjustment}
                                            size="md"
                                        />
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    {/* Occupancy Rate */}
                    <div className="card">
                        <div className="card-body">
                            <div className="flex items-center">
                                <div className={`w-10 h-10 rounded-lg flex items-center justify-center ${
                                    stats.occupancy_rate >= 90
                                        ? 'bg-green-100'
                                        : stats.occupancy_rate >= 70
                                            ? 'bg-yellow-100'
                                            : 'bg-red-100'
                                }`}>
                                    <Square3Stack3DIcon className={`w-5 h-5 ${
                                        stats.occupancy_rate >= 90
                                            ? 'text-green-600'
                                            : stats.occupancy_rate >= 70
                                                ? 'text-yellow-600'
                                                : 'text-red-600'
                                    }`} />
                                </div>
                                <div className="ml-4">
                                    <p className="text-sm text-gray-500">Occupancy Rate</p>
                                    <p className="text-2xl font-semibold text-gray-900">{stats.occupancy_rate}%</p>
                                </div>
                            </div>
                            <div className="mt-3">
                                <div className="w-full bg-gray-200 rounded-full h-2">
                                    <div
                                        className={`h-2 rounded-full ${
                                            stats.occupancy_rate >= 90
                                                ? 'bg-green-500'
                                                : stats.occupancy_rate >= 70
                                                    ? 'bg-yellow-500'
                                                    : 'bg-red-500'
                                        }`}
                                        style={{ width: `${stats.occupancy_rate}%` }}
                                    />
                                </div>
                            </div>
                        </div>
                    </div>

                    {/* Vacant Units */}
                    <div className="card">
                        <div className="card-body">
                            <div className="flex items-center">
                                <div className="w-10 h-10 bg-yellow-100 rounded-lg flex items-center justify-center">
                                    <HomeModernIcon className="w-5 h-5 text-yellow-600" />
                                </div>
                                <div className="ml-4">
                                    <p className="text-sm text-gray-500">Vacant Units</p>
                                    <p className="text-2xl font-semibold text-gray-900">{stats.vacant_units}</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    {/* Total Market Rent */}
                    <div className="card">
                        <div className="card-body">
                            <div className="flex items-center">
                                <div className="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center">
                                    <CurrencyDollarIcon className="w-5 h-5 text-green-600" />
                                </div>
                                <div className="ml-4">
                                    <p className="text-sm text-gray-500">Total Market Rent</p>
                                    <p className="text-2xl font-semibold text-gray-900">
                                        {formatCurrency(stats.total_market_rent)}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {/* Property Details */}
                <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    {/* Details Card */}
                    <div className="card">
                        <div className="card-header">
                            <h2 className="text-lg font-medium text-gray-900">Property Details</h2>
                        </div>
                        <div className="card-body">
                            <dl className="space-y-4">
                                {property.property_type && (
                                    <div className="flex justify-between">
                                        <dt className="text-sm text-gray-500">Property Type</dt>
                                        <dd className="text-sm font-medium text-gray-900">{property.property_type}</dd>
                                    </div>
                                )}
                                {property.year_built && (
                                    <div className="flex justify-between">
                                        <dt className="text-sm text-gray-500">Year Built</dt>
                                        <dd className="text-sm font-medium text-gray-900">{property.year_built}</dd>
                                    </div>
                                )}
                                {(property.total_sqft || effectiveValues?.total_sqft?.value) && (
                                    <div className="flex justify-between">
                                        <dt className="text-sm text-gray-500">Total Sqft</dt>
                                        <dd className="text-sm font-medium text-gray-900">
                                            <AdjustedValue
                                                value={effectiveValues?.total_sqft?.value ?? property.total_sqft}
                                                isAdjusted={effectiveValues?.total_sqft?.is_adjusted}
                                                original={effectiveValues?.total_sqft?.original}
                                                label="Total Sqft"
                                                adjustment={effectiveValues?.total_sqft?.adjustment}
                                                formatter={(val) => val?.toLocaleString()}
                                            />
                                        </dd>
                                    </div>
                                )}
                                {(property.unit_count || effectiveValues?.unit_count?.value) && (
                                    <div className="flex justify-between">
                                        <dt className="text-sm text-gray-500">Unit Count</dt>
                                        <dd className="text-sm font-medium text-gray-900">
                                            <AdjustedValue
                                                value={effectiveValues?.unit_count?.value ?? property.unit_count}
                                                isAdjusted={effectiveValues?.unit_count?.is_adjusted}
                                                original={effectiveValues?.unit_count?.original}
                                                label="Unit Count"
                                                adjustment={effectiveValues?.unit_count?.adjustment}
                                            />
                                        </dd>
                                    </div>
                                )}
                                {property.county && (
                                    <div className="flex justify-between">
                                        <dt className="text-sm text-gray-500">County</dt>
                                        <dd className="text-sm font-medium text-gray-900">{property.county}</dd>
                                    </div>
                                )}
                                {stats.avg_market_rent > 0 && (
                                    <div className="flex justify-between">
                                        <dt className="text-sm text-gray-500">Avg Market Rent</dt>
                                        <dd className="text-sm font-medium text-gray-900">
                                            {formatCurrency(stats.avg_market_rent)}
                                        </dd>
                                    </div>
                                )}
                            </dl>
                        </div>
                    </div>

                    {/* Map */}
                    <div className="lg:col-span-2 card">
                        <div className="card-header">
                            <h2 className="text-lg font-medium text-gray-900">Location</h2>
                        </div>
                        <div className="card-body">
                            {property.latitude && property.longitude && googleMapsApiKey ? (
                                <div className="aspect-video rounded-lg overflow-hidden">
                                    <LoadScript googleMapsApiKey={googleMapsApiKey}>
                                        <GoogleMap
                                            mapContainerStyle={{ width: '100%', height: '100%' }}
                                            center={{
                                                lat: parseFloat(property.latitude),
                                                lng: parseFloat(property.longitude),
                                            }}
                                            zoom={16}
                                            options={{
                                                streetViewControl: true,
                                                mapTypeControl: true,
                                                fullscreenControl: true,
                                            }}
                                        >
                                            <Marker
                                                position={{
                                                    lat: parseFloat(property.latitude),
                                                    lng: parseFloat(property.longitude),
                                                }}
                                                title={property.name}
                                            />
                                        </GoogleMap>
                                    </LoadScript>
                                </div>
                            ) : property.latitude && property.longitude ? (
                                <div className="aspect-video bg-gray-100 rounded-lg flex items-center justify-center">
                                    <div className="text-center">
                                        <MapPinIcon className="w-12 h-12 text-gray-400 mx-auto mb-2" />
                                        <p className="text-sm text-gray-500">
                                            Coordinates: {property.latitude}, {property.longitude}
                                        </p>
                                        <a
                                            href={`https://www.google.com/maps/search/?api=1&query=${property.latitude},${property.longitude}`}
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            className="mt-2 inline-flex items-center text-sm text-blue-600 hover:text-blue-700"
                                        >
                                            View on Google Maps
                                        </a>
                                    </div>
                                </div>
                            ) : (
                                <div className="aspect-video bg-gray-100 rounded-lg flex items-center justify-center">
                                    <div className="text-center">
                                        <MapPinIcon className="w-12 h-12 text-gray-300 mx-auto mb-2" />
                                        <p className="text-sm text-gray-500">No coordinates available</p>
                                    </div>
                                </div>
                            )}
                        </div>
                    </div>
                </div>
                    </div>
                </PropertyTabPanel>

                {/* Units Tab */}
                <PropertyTabPanel id="units" isActive={activeTab === 'units'}>
                    <div className="space-y-6">
                        {/* Units List */}
                        <div className="card">
                    <div className="card-header flex items-center justify-between">
                        <h2 className="text-lg font-medium text-gray-900">
                            Units ({units?.total || 0})
                        </h2>
                        <div className="flex items-center gap-2">
                            <select
                                className="input text-sm"
                                value={unitFilters.status || 'all'}
                                onChange={(e) => handleUnitStatusFilter(e.target.value)}
                            >
                                <option value="all">All Units</option>
                                <option value="occupied">Occupied ({stats.occupied_units})</option>
                                <option value="vacant">Vacant ({stats.vacant_units})</option>
                                <option value="not_ready">Not Ready ({stats.not_ready_units})</option>
                            </select>
                        </div>
                    </div>
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-gray-200">
                            <thead className="bg-gray-50">
                                <tr>
                                    <SortableHeader field="unit_number">Unit</SortableHeader>
                                    <SortableHeader field="status">Status</SortableHeader>
                                    <SortableHeader field="bedrooms">Bed/Bath</SortableHeader>
                                    <SortableHeader field="sqft">Sqft</SortableHeader>
                                    <SortableHeader field="market_rent">Market Rent</SortableHeader>
                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Type
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="bg-white divide-y divide-gray-200">
                                {units?.data?.length === 0 ? (
                                    <tr>
                                        <td colSpan="6" className="px-6 py-12 text-center">
                                            <HomeModernIcon className="w-12 h-12 text-gray-300 mx-auto mb-4" />
                                            <p className="text-gray-500">
                                                {stats.total_units === 0
                                                    ? 'No units found for this property'
                                                    : 'No units match the selected filter'}
                                            </p>
                                        </td>
                                    </tr>
                                ) : (
                                    units?.data?.map((unit) => (
                                        <tr key={unit.id} className="hover:bg-gray-50">
                                            <td className="px-6 py-4 whitespace-nowrap">
                                                <div className="text-sm font-medium text-gray-900">
                                                    {unit.unit_number}
                                                </div>
                                            </td>
                                            <td className="px-6 py-4 whitespace-nowrap">
                                                <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${getStatusBadgeClass(unit.status)}`}>
                                                    {getStatusLabel(unit.status)}
                                                </span>
                                            </td>
                                            <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                {unit.bedrooms !== null || unit.bathrooms !== null
                                                    ? `${unit.bedrooms ?? '-'} / ${unit.bathrooms ?? '-'}`
                                                    : '-'}
                                            </td>
                                            <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                {unit.sqft ? unit.sqft.toLocaleString() : '-'}
                                            </td>
                                            <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                {formatCurrency(unit.market_rent)}
                                            </td>
                                            <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                {unit.unit_type || '-'}
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>

                    {/* Pagination */}
                    {units?.last_page > 1 && (
                        <div className="px-6 py-4 border-t border-gray-200 flex items-center justify-between">
                            <div className="text-sm text-gray-500">
                                Showing {units.from} to {units.to} of {units.total} units
                            </div>
                            <div className="flex gap-2">
                                {units.prev_page_url && (
                                    <Link
                                        href={units.prev_page_url}
                                        className="btn-secondary flex items-center text-sm"
                                        preserveScroll
                                    >
                                        <ChevronLeftIcon className="w-4 h-4 mr-1" />
                                        Previous
                                    </Link>
                                )}
                                {units.next_page_url && (
                                    <Link
                                        href={units.next_page_url}
                                        className="btn-secondary flex items-center text-sm"
                                        preserveScroll
                                    >
                                        Next
                                        <ChevronRightIcon className="w-4 h-4 ml-1" />
                                    </Link>
                                )}
                            </div>
                        </div>
                    )}
                </div>
                    </div>
                </PropertyTabPanel>

                {/* Utilities Tab */}
                <PropertyTabPanel id="utilities" isActive={activeTab === 'utilities'}>
                    <div className="space-y-6">
                        {/* Cost Breakdown */}
                        {utilityData?.costBreakdown && (
                            <div className="card">
                                <div className="card-header">
                                    <h2 className="text-lg font-medium text-gray-900">Utility Cost Breakdown</h2>
                                </div>
                                <div className="card-body">
                                    <div className="flex items-center justify-between mb-6">
                                        <div>
                                            <p className="text-sm text-gray-500">Total Utility Costs (This Month)</p>
                                            <p className="text-3xl font-bold text-gray-900">
                                                {formatUtilityCurrency(utilityData.costBreakdown.total)}
                                            </p>
                                        </div>
                                    </div>

                                    {utilityData.costBreakdown.breakdown?.length > 0 ? (
                                        <div className="space-y-3">
                                            {utilityData.costBreakdown.breakdown.map((item) => {
                                                const utilityType = findUtilityType(utilityData.utilityTypes, item.type);
                                                const Icon = getIconComponent(utilityType?.icon);
                                                const colors = getColorScheme(utilityType?.color_scheme);
                                                const widthPercent = utilityData.costBreakdown.total > 0
                                                    ? Math.max((item.cost / utilityData.costBreakdown.total) * 100, 2)
                                                    : 0;

                                                return (
                                                    <div key={item.type} className="flex items-center space-x-4">
                                                        <div className={`p-2 rounded-lg ${colors.bg} ${colors.text}`}>
                                                            <Icon className="w-4 h-4" />
                                                        </div>
                                                        <div className="flex-1">
                                                            <div className="flex items-center justify-between mb-1">
                                                                <span className="text-sm font-medium text-gray-700">
                                                                    {utilityType?.label || item.type}
                                                                </span>
                                                                <span className="text-sm text-gray-900">
                                                                    {formatUtilityCurrency(item.cost)}
                                                                    <span className="text-gray-500 ml-1">
                                                                        ({item.percentage}%)
                                                                    </span>
                                                                </span>
                                                            </div>
                                                            <div className="w-full bg-gray-100 rounded-full h-2">
                                                                <div
                                                                    className={`h-2 rounded-full ${colors.bar}`}
                                                                    style={{ width: `${widthPercent}%` }}
                                                                />
                                                            </div>
                                                        </div>
                                                    </div>
                                                );
                                            })}
                                        </div>
                                    ) : (
                                        <div className="text-center py-8">
                                            <BoltIcon className="w-12 h-12 text-gray-300 mx-auto mb-3" />
                                            <p className="text-gray-500">No utility expenses recorded this month</p>
                                        </div>
                                    )}
                                </div>
                            </div>
                        )}

                        {/* Historical Trend */}
                        {utilityData?.propertyTrend && utilityData?.utilityTypes && (
                            <PropertyUtilityTrend
                                data={utilityData.propertyTrend}
                                utilityTypes={utilityData.utilityTypes}
                            />
                        )}

                        {/* Recent Expenses */}
                        {utilityData?.recentExpenses && (
                            <div className="card">
                                <div className="card-header">
                                    <h2 className="text-lg font-medium text-gray-900">Recent Expenses</h2>
                                </div>
                                <div className="overflow-x-auto">
                                    <table className="min-w-full divide-y divide-gray-200">
                                        <thead className="bg-gray-50">
                                            <tr>
                                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                    Date
                                                </th>
                                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                    Type
                                                </th>
                                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                    Vendor
                                                </th>
                                                <th className="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                    Amount
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody className="bg-white divide-y divide-gray-200">
                                            {utilityData.recentExpenses.length === 0 ? (
                                                <tr>
                                                    <td colSpan="4" className="px-6 py-8 text-center text-gray-500">
                                                        No expense records found
                                                    </td>
                                                </tr>
                                            ) : (
                                                utilityData.recentExpenses.map((expense) => {
                                                    const utilityType = findUtilityType(utilityData.utilityTypes, expense.utility_type);
                                                    const colors = getColorScheme(utilityType?.color_scheme);
                                                    return (
                                                        <tr key={expense.id} className="hover:bg-gray-50">
                                                            <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                                {new Date(expense.expense_date).toLocaleDateString()}
                                                            </td>
                                                            <td className="px-6 py-4 whitespace-nowrap">
                                                                <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${colors.bg} ${colors.text}`}>
                                                                    {expense.utility_label}
                                                                </span>
                                                            </td>
                                                            <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                                {expense.vendor_name || '-'}
                                                            </td>
                                                            <td className="px-6 py-4 whitespace-nowrap text-right text-sm font-medium text-gray-900">
                                                                {formatUtilityCurrency(expense.amount)}
                                                            </td>
                                                        </tr>
                                                    );
                                                })
                                            )}
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        )}

                        {!utilityData && (
                            <div className="card">
                                <div className="card-body">
                                    <div className="text-center py-12">
                                        <BoltIcon className="w-12 h-12 text-gray-300 mx-auto mb-4" />
                                        <h3 className="text-lg font-medium text-gray-900 mb-2">Utility Expenses</h3>
                                        <p className="text-gray-500">
                                            No utility data available for this property.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        )}
                    </div>
                </PropertyTabPanel>

                {/* Work Orders Tab */}
                <PropertyTabPanel id="work-orders" isActive={activeTab === 'work-orders'}>
                    <div className="space-y-6">
                        {/* Work Order Stats */}
                        {workOrderData?.statusCounts && (
                            <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
                                <div className="card">
                                    <div className="card-body">
                                        <p className="text-sm text-gray-500">Open</p>
                                        <p className="text-2xl font-semibold text-yellow-600">
                                            {workOrderData.statusCounts.open}
                                        </p>
                                    </div>
                                </div>
                                <div className="card">
                                    <div className="card-body">
                                        <p className="text-sm text-gray-500">In Progress</p>
                                        <p className="text-2xl font-semibold text-blue-600">
                                            {workOrderData.statusCounts.in_progress}
                                        </p>
                                    </div>
                                </div>
                                <div className="card">
                                    <div className="card-body">
                                        <p className="text-sm text-gray-500">Completed (All Time)</p>
                                        <p className="text-2xl font-semibold text-green-600">
                                            {workOrderData.statusCounts.completed}
                                        </p>
                                    </div>
                                </div>
                                <div className="card">
                                    <div className="card-body">
                                        <p className="text-sm text-gray-500">Total Spend (12 mo)</p>
                                        <p className="text-2xl font-semibold text-gray-900">
                                            {formatCurrency(workOrderData.totalSpend)}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        )}

                        {/* Recent Work Orders Table */}
                        <div className="card">
                            <div className="card-header">
                                <h2 className="text-lg font-medium text-gray-900">Recent Work Orders</h2>
                            </div>
                            <div className="overflow-x-auto">
                                <table className="min-w-full divide-y divide-gray-200">
                                    <thead className="bg-gray-50">
                                        <tr>
                                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Date
                                            </th>
                                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Status
                                            </th>
                                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Category
                                            </th>
                                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Vendor
                                            </th>
                                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Unit
                                            </th>
                                            <th className="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Amount
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody className="bg-white divide-y divide-gray-200">
                                        {!workOrderData?.recentWorkOrders?.length ? (
                                            <tr>
                                                <td colSpan="6" className="px-6 py-12 text-center">
                                                    <WrenchScrewdriverIcon className="w-12 h-12 text-gray-300 mx-auto mb-4" />
                                                    <p className="text-gray-500">No work orders found</p>
                                                </td>
                                            </tr>
                                        ) : (
                                            workOrderData.recentWorkOrders.map((wo) => (
                                                <tr key={wo.id} className="hover:bg-gray-50">
                                                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                        {wo.opened_at ? new Date(wo.opened_at).toLocaleDateString() : '-'}
                                                    </td>
                                                    <td className="px-6 py-4 whitespace-nowrap">
                                                        <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${
                                                            wo.status === 'open' ? 'bg-yellow-100 text-yellow-800' :
                                                            wo.status === 'in_progress' ? 'bg-blue-100 text-blue-800' :
                                                            wo.status === 'completed' ? 'bg-green-100 text-green-800' :
                                                            wo.status === 'cancelled' ? 'bg-gray-100 text-gray-800' :
                                                            'bg-gray-100 text-gray-800'
                                                        }`}>
                                                            {wo.status === 'in_progress' ? 'In Progress' :
                                                             wo.status?.charAt(0).toUpperCase() + wo.status?.slice(1)}
                                                        </span>
                                                    </td>
                                                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                        {wo.category || '-'}
                                                    </td>
                                                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                        {wo.vendor_name || '-'}
                                                    </td>
                                                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                        {wo.unit_number || '-'}
                                                    </td>
                                                    <td className="px-6 py-4 whitespace-nowrap text-right text-sm font-medium text-gray-900">
                                                        {wo.amount ? formatCurrency(wo.amount) : '-'}
                                                    </td>
                                                </tr>
                                            ))
                                        )}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </PropertyTabPanel>

                {/* Settings Tab (Admin Only) */}
                {isAdmin && (
                    <PropertyTabPanel id="settings" isActive={activeTab === 'settings'}>
                        <div className="space-y-6">
                            {/* Data Adjustments */}
                            <AdjustmentList
                                property={property}
                                activeAdjustments={activeAdjustments || []}
                                historicalAdjustments={historicalAdjustments || []}
                                adjustableFields={adjustableFields || {}}
                                effectiveValues={effectiveValues || {}}
                            />
                        </div>
                    </PropertyTabPanel>
                )}
            </div>

            {/* Add Flag Modal */}
            {showAddFlagModal && (
                <div className="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
                    <div className="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                        {/* Background overlay */}
                        <div
                            className="fixed inset-0 bg-gray-500/75 transition-opacity"
                            aria-hidden="true"
                            onClick={() => {
                                reset();
                                setShowAddFlagModal(false);
                            }}
                        />

                        {/* Center modal */}
                        <span className="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

                        <div className="relative inline-block align-bottom bg-white rounded-lg px-4 pt-5 pb-4 text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full sm:p-6">
                            <div className="flex items-center justify-between mb-4">
                                <h3 className="text-lg font-medium text-gray-900" id="modal-title">
                                    Add Property Flag
                                </h3>
                                <button
                                    type="button"
                                    onClick={() => {
                                        reset();
                                        setShowAddFlagModal(false);
                                    }}
                                    className="text-gray-400 hover:text-gray-500"
                                >
                                    <XMarkIcon className="w-5 h-5" />
                                </button>
                            </div>

                            <form onSubmit={handleAddFlag}>
                                <div className="space-y-4">
                                    {/* Flag Type Select */}
                                    <div>
                                        <label htmlFor="flag_type" className="block text-sm font-medium text-gray-700">
                                            Flag Type
                                        </label>
                                        <select
                                            id="flag_type"
                                            className={`mt-1 input ${errors.flag_type ? 'border-red-300' : ''}`}
                                            value={data.flag_type}
                                            onChange={(e) => setData('flag_type', e.target.value)}
                                            required
                                        >
                                            <option value="">Select a flag type...</option>
                                            {availableFlagTypes.map(([key, label]) => (
                                                <option key={key} value={key}>
                                                    {label}
                                                </option>
                                            ))}
                                        </select>
                                        {errors.flag_type && (
                                            <p className="mt-1 text-sm text-red-600">{errors.flag_type}</p>
                                        )}
                                    </div>

                                    {/* Reason (Optional) */}
                                    <div>
                                        <label htmlFor="reason" className="block text-sm font-medium text-gray-700">
                                            Reason (Optional)
                                        </label>
                                        <textarea
                                            id="reason"
                                            className={`mt-1 input ${errors.reason ? 'border-red-300' : ''}`}
                                            value={data.reason}
                                            onChange={(e) => setData('reason', e.target.value)}
                                            rows={3}
                                            maxLength={500}
                                            placeholder="Add a reason for this flag..."
                                        />
                                        {errors.reason && (
                                            <p className="mt-1 text-sm text-red-600">{errors.reason}</p>
                                        )}
                                        <p className="mt-1 text-xs text-gray-500">
                                            {data.reason.length}/500 characters
                                        </p>
                                    </div>
                                </div>

                                <div className="mt-5 sm:mt-6 flex gap-3">
                                    <button
                                        type="button"
                                        onClick={() => {
                                            reset();
                                            setShowAddFlagModal(false);
                                        }}
                                        className="flex-1 btn btn-secondary"
                                    >
                                        Cancel
                                    </button>
                                    <button
                                        type="submit"
                                        disabled={processing || !data.flag_type}
                                        className="flex-1 btn btn-primary"
                                    >
                                        {processing ? 'Adding...' : 'Add Flag'}
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            )}
        </Layout>
    );
}
