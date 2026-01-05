import { Head, Link, usePage } from '@inertiajs/react';
import Layout from '../../components/Layout';
import {
    UsersIcon,
    Cog6ToothIcon,
    CloudIcon,
    KeyIcon,
} from '@heroicons/react/24/outline';

const tabs = [
    { name: 'Users', href: '/admin/users', icon: UsersIcon },
    { name: 'Integrations', href: '/admin/integrations', icon: CloudIcon },
    { name: 'Authentication', href: '/admin/authentication', icon: KeyIcon },
    { name: 'Settings', href: '/admin/settings', icon: Cog6ToothIcon },
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
                            const isActive = currentPath.startsWith(tab.href);
                            return (
                                <Link
                                    key={tab.name}
                                    href={tab.href}
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
