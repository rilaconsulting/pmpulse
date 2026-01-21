import { Head } from '@inertiajs/react';
import Layout from '../../components/Layout';
import PageHeader from '../../components/PageHeader';
import UtilityDataTable from '../../components/Utilities/UtilityDataTable';
import { ChartBarIcon, TableCellsIcon, EyeSlashIcon } from '@heroicons/react/24/outline';

export default function UtilitiesData({
    propertyComparison,
    selectedUtilityType,
    utilityTypes,
    filters,
    propertyTypeOptions,
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
                        { label: 'Data Table', href: route('utilities.data'), icon: TableCellsIcon },
                        { label: 'Dashboard', href: route('utilities.dashboard'), icon: ChartBarIcon },
                        { label: 'Excluded', href: route('utilities.excluded'), icon: EyeSlashIcon },
                    ]}
                    activeTab="Data Table"
                />

                {/* Property Data Table with integrated filters */}
                <UtilityDataTable
                    data={propertyComparison}
                    utilityTypes={utilityTypes}
                    selectedType={selectedUtilityType}
                    filters={filters}
                    propertyTypeOptions={propertyTypeOptions}
                />
            </div>
        </Layout>
    );
}
