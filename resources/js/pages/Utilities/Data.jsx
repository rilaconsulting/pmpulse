import { Head } from '@inertiajs/react';
import Layout from '../../components/Layout';
import PageHeader from '../../components/PageHeader';
import UtilityFiltersBar from '../../components/Utilities/UtilityFiltersBar';
import UtilityDataTable from '../../components/Utilities/UtilityDataTable';
import ExcludedPropertiesList from '../../components/Utilities/ExcludedPropertiesList';
import { ChartBarIcon, TableCellsIcon } from '@heroicons/react/24/outline';

export default function UtilitiesData({
    propertyComparison,
    selectedUtilityType,
    utilityTypes,
    filters,
    propertyTypeOptions,
    excludedProperties,
}) {
    return (
        <Layout>
            <Head title="Utility Data" />

            <div className="space-y-6">
                {/* Header */}
                <PageHeader
                    title="Utility Data"
                    subtitle="Detailed property utility comparison and analysis"
                    tabs={[
                        { label: 'Dashboard', href: route('utilities.dashboard'), icon: ChartBarIcon },
                        { label: 'Data Table', href: route('utilities.data'), icon: TableCellsIcon },
                    ]}
                    activeTab="Data Table"
                />

                {/* Filters Bar */}
                <UtilityFiltersBar
                    filters={filters}
                    utilityTypes={utilityTypes}
                    selectedUtilityType={selectedUtilityType}
                    propertyTypeOptions={propertyTypeOptions}
                />

                {/* Property Data Table */}
                <UtilityDataTable
                    data={propertyComparison}
                    utilityTypes={utilityTypes}
                    selectedType={selectedUtilityType}
                />

                {/* Excluded Properties */}
                <ExcludedPropertiesList excludedProperties={excludedProperties} />
            </div>
        </Layout>
    );
}
