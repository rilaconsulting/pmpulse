import { Link } from '@inertiajs/react';
import { ChartBarIcon, TableCellsIcon } from '@heroicons/react/24/outline';

const tabs = [
    {
        id: 'dashboard',
        name: 'Dashboard',
        routeName: 'utilities.dashboard',
        icon: ChartBarIcon,
    },
    {
        id: 'data',
        name: 'Data Table',
        routeName: 'utilities.data',
        icon: TableCellsIcon,
    },
];

export default function UtilityNavTabs({ currentView }) {
    return (
        <div className="border-b border-gray-200">
            <nav className="-mb-px flex space-x-8" aria-label="Utility views">
                {tabs.map((tab) => {
                    const isActive = currentView === tab.id;
                    return (
                        <Link
                            key={tab.id}
                            href={route(tab.routeName)}
                            className={`group flex items-center py-4 px-1 border-b-2 font-medium text-sm transition-colors ${
                                isActive
                                    ? 'border-blue-500 text-blue-600'
                                    : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
                            }`}
                            aria-current={isActive ? 'page' : undefined}
                        >
                            <tab.icon
                                className={`w-5 h-5 mr-2 transition-colors ${
                                    isActive ? 'text-blue-500' : 'text-gray-400 group-hover:text-gray-500'
                                }`}
                            />
                            <span>{tab.name}</span>
                        </Link>
                    );
                })}
            </nav>
        </div>
    );
}
