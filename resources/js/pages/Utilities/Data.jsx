import { Head } from '@inertiajs/react';
import Layout from '../../components/Layout';
import UtilityNavTabs from '../../components/Utilities/UtilityNavTabs';
import UtilityHeatMap from '../../components/Utilities/UtilityHeatMap';
import ExcludedPropertiesList from '../../components/Utilities/ExcludedPropertiesList';

export default function UtilitiesData({
    propertyComparison,
    selectedUtilityType,
    utilityTypes,
    heatMapStats,
    filters,
    propertyTypeOptions,
    excludedProperties,
}) {
    return (
        <Layout>
            <Head title="Utility Data" />

            <div className="space-y-6">
                {/* Header */}
                <div>
                    <h1 className="text-2xl font-bold text-gray-900">Utility Data</h1>
                    <p className="mt-1 text-sm text-gray-500">
                        Detailed property utility comparison and analysis
                    </p>
                </div>

                {/* Navigation Tabs */}
                <UtilityNavTabs currentView="data" />

                {/* Property Comparison Table */}
                <UtilityHeatMap
                    data={propertyComparison}
                    utilityTypes={utilityTypes}
                    selectedType={selectedUtilityType}
                    heatMapStats={heatMapStats}
                />

                {/* Excluded Properties */}
                <ExcludedPropertiesList excludedProperties={excludedProperties} />
            </div>
        </Layout>
    );
}
