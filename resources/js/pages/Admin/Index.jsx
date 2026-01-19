import { Head, Link, usePage } from '@inertiajs/react';
import Layout from '../../components/Layout';
import {
    UsersIcon,
    Cog6ToothIcon,
    CloudIcon,
    AdjustmentsHorizontalIcon,
    BoltIcon,
    TagIcon,
    ArrowPathIcon,
    SwatchIcon,
} from '@heroicons/react/24/outline';

const tabs = [
    { name: 'Users', routeName: 'admin.users.index', icon: UsersIcon },
    { name: 'Integrations', routeName: 'admin.integrations', icon: CloudIcon },
    { name: 'Sync', routeName: 'admin.sync', icon: ArrowPathIcon },
    { name: 'Utility Accounts', routeName: 'admin.utility-accounts.index', icon: BoltIcon },
    { name: 'Utility Types', routeName: 'admin.utility-types.index', icon: TagIcon },
    { name: 'Formatting Rules', routeName: 'admin.utility-formatting-rules.index', icon: SwatchIcon },
    { name: 'Adjustments', routeName: 'admin.adjustments.index', icon: AdjustmentsHorizontalIcon },
    { name: 'Settings', routeName: 'admin.settings', icon: Cog6ToothIcon },
];

export default function AdminLayout({ children, currentTab }) {
    const currentPath = usePage().url;

    return (
        <Layout>
            <Head title="Admin" />

            <div className="space-y-6">
                {/* Header */}
                <div>
                    <h1 className="text-2xl font-bold text-gray-900">Administration</h1>
                    <p className="mt-1 text-sm text-gray-500">
                        Manage users, integrations, and system settings
                    </p>
                </div>

                {/* Tab Navigation */}
                <div className="border-b border-gray-200">
                    <nav className="-mb-px flex space-x-8">
                        {tabs.map((tab) => {
                            const href = route(tab.routeName);
                            const isActive = currentPath.startsWith(href);
                            return (
                                <Link
                                    key={tab.name}
                                    href={href}
                                    className={`flex items-center py-4 px-1 border-b-2 font-medium text-sm ${
                                        isActive
                                            ? 'border-blue-500 text-blue-600'
                                            : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
                                    }`}
                                >
                                    <tab.icon className={`w-5 h-5 mr-2 ${
                                        isActive ? 'text-blue-500' : 'text-gray-400'
                                    }`} />
                                    {tab.name}
                                </Link>
                            );
                        })}
                    </nav>
                </div>

                {/* Tab Content */}
                <div>
                    {children}
                </div>
            </div>
        </Layout>
    );
}
