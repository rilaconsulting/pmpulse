import { Head } from '@inertiajs/react';
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

export default function AdminLayout({ children, currentTab }) {
    // Generate tabs with route() helper for maintainability
    const adminTabs = [
        { label: 'Users', href: route('admin.users.index'), icon: UsersIcon },
        { label: 'Integrations', href: route('admin.integrations'), icon: CloudIcon },
        { label: 'Sync', href: route('admin.sync'), icon: ArrowPathIcon },
        { label: 'Utility Accounts', href: route('admin.utility-accounts.index'), icon: BoltIcon },
        { label: 'Utility Types', href: route('admin.utility-types.index'), icon: TagIcon },
        { label: 'Formatting Rules', href: route('admin.utility-formatting-rules.index'), icon: SwatchIcon },
        { label: 'Adjustments', href: route('admin.adjustments.index'), icon: AdjustmentsHorizontalIcon },
        { label: 'Settings', href: route('admin.settings'), icon: Cog6ToothIcon },
    ];

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
