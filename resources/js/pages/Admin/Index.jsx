import { Head, usePage } from '@inertiajs/react';
import Layout from '../../components/Layout';
import PageHeader from '../../components/PageHeader';
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

const adminTabs = [
    { label: 'Users', href: '/admin/users', icon: UsersIcon },
    { label: 'Integrations', href: '/admin/integrations', icon: CloudIcon },
    { label: 'Sync', href: '/admin/sync', icon: ArrowPathIcon },
    { label: 'Utility Accounts', href: '/admin/utility-accounts', icon: BoltIcon },
    { label: 'Utility Types', href: '/admin/utility-types', icon: TagIcon },
    { label: 'Formatting Rules', href: '/admin/utility-formatting-rules', icon: SwatchIcon },
    { label: 'Adjustments', href: '/admin/adjustments', icon: AdjustmentsHorizontalIcon },
    { label: 'Settings', href: '/admin/settings', icon: Cog6ToothIcon },
];

export default function AdminLayout({ children, currentTab }) {
    return (
        <Layout>
            <Head title="Admin" />

            <div className="space-y-6">
                {/* Header */}
                <PageHeader
                    title="Administration"
                    subtitle="Manage users, integrations, and system settings"
                    tabs={adminTabs}
                />

                {/* Tab Content */}
                <div>
                    {children}
                </div>
            </div>
        </Layout>
    );
}
