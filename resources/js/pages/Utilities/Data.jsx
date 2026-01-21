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

            <div className="flex flex-col h-[calc(100vh-64px)] -m-4 md:-m-8">
                {/* Header - doesn't scroll */}
                <div className="flex-shrink-0 px-4 md:px-8 pt-4 md:pt-8">
                    <PageHeader
                        title="Utility Data"
                        subtitle="Detailed property utility comparison and analysis"
                        tabs={[
                            { label: 'Data Table', href: route('utilities.data'), icon: TableCellsIcon },
                            { label: 'Dashboard', href: route('utilities.dashboard'), icon: ChartBarIcon },
                            { label: 'Excluded', href: route('utilities.excluded'), icon: EyeSlashIcon },
                        ]}
                        activeTab="Data Table"
                        sticky={false}
                    />
                </div>

                {/* Content area - grows to fill remaining space */}
                <div className="flex-1 min-h-0 px-4 md:px-8 pt-6 pb-4 md:pb-8 flex flex-col">
                    {/* Property Data Table with integrated filters */}
                    <UtilityDataTable
                        data={propertyComparison}
                        utilityTypes={utilityTypes}
                        selectedType={selectedUtilityType}
                        filters={filters}
                        propertyTypeOptions={propertyTypeOptions}
                    />
                </div>
            </div>
        </Layout>
    );
}
