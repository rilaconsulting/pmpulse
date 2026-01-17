import { useForm, Link } from '@inertiajs/react';
import { useState } from 'react';
import AdminLayout from './Index';
import {
    LightBulbIcon,
    CheckIcon,
    XMarkIcon,
    ArrowRightIcon,
} from '@heroicons/react/24/outline';

const UtilityTypeColors = {
    water: 'bg-blue-100 text-blue-800',
    electric: 'bg-yellow-100 text-yellow-800',
    gas: 'bg-orange-100 text-orange-800',
    garbage: 'bg-gray-100 text-gray-800',
    sewer: 'bg-green-100 text-green-800',
    other: 'bg-purple-100 text-purple-800',
};

function SuggestionRow({ glAccount, count, utilityTypes, onMapped }) {
    const [isMapping, setIsMapping] = useState(false);
    const { data, setData, post, processing, errors, reset } = useForm({
        gl_account_number: glAccount,
        gl_account_name: '',
        utility_type: '',
        is_active: true,
    });

    const handleSubmit = (e) => {
        e.preventDefault();
        post('/admin/utility-accounts', {
            onSuccess: () => {
                reset();
                setIsMapping(false);
                onMapped(glAccount);
            },
        });
    };

    if (isMapping) {
        return (
            <tr className="bg-blue-50">
                <td className="px-6 py-4 whitespace-nowrap text-sm font-mono font-medium text-gray-900">
                    {glAccount}
                </td>
                <td className="px-6 py-4">
                    <input
                        type="text"
                        value={data.gl_account_name}
                        onChange={(e) => setData('gl_account_name', e.target.value)}
                        placeholder="e.g., Water Expense"
                        className="input w-full"
                        autoFocus
                    />
                    {errors.gl_account_name && (
                        <p className="mt-1 text-xs text-red-600">{errors.gl_account_name}</p>
                    )}
                </td>
                <td className="px-6 py-4">
                    <select
                        value={data.utility_type}
                        onChange={(e) => setData('utility_type', e.target.value)}
                        className="input w-full"
                    >
                        <option value="">Select type...</option>
                        {Object.entries(utilityTypes).map(([value, label]) => (
                            <option key={value} value={value}>{label}</option>
                        ))}
                    </select>
                    {errors.utility_type && (
                        <p className="mt-1 text-xs text-red-600">{errors.utility_type}</p>
                    )}
                </td>
                <td className="px-6 py-4 text-center text-sm text-gray-500">
                    {count}
                </td>
                <td className="px-6 py-4 text-right space-x-2">
                    <button
                        type="button"
                        onClick={handleSubmit}
                        disabled={processing || !data.utility_type}
                        className="inline-flex items-center px-2 py-1 text-sm font-medium text-white bg-blue-600 rounded hover:bg-blue-700 disabled:opacity-50"
                    >
                        <CheckIcon className="w-4 h-4 mr-1" />
                        Save
                    </button>
                    <button
                        type="button"
                        onClick={() => {
                            reset();
                            setIsMapping(false);
                        }}
                        className="inline-flex items-center px-2 py-1 text-sm font-medium text-gray-700 bg-gray-100 rounded hover:bg-gray-200"
                    >
                        <XMarkIcon className="w-4 h-4 mr-1" />
                        Cancel
                    </button>
                </td>
            </tr>
        );
    }

    return (
        <tr className="hover:bg-gray-50">
            <td className="px-6 py-4 whitespace-nowrap text-sm font-mono font-medium text-gray-900">
                {glAccount}
            </td>
            <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-400 italic">
                Not mapped
            </td>
            <td className="px-6 py-4 whitespace-nowrap">
                <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                    Unmapped
                </span>
            </td>
            <td className="px-6 py-4 whitespace-nowrap text-center text-sm text-gray-500">
                {count}
            </td>
            <td className="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                <button
                    type="button"
                    onClick={() => setIsMapping(true)}
                    className="inline-flex items-center text-blue-600 hover:text-blue-900"
                >
                    Map Account
                    <ArrowRightIcon className="w-4 h-4 ml-1" />
                </button>
            </td>
        </tr>
    );
}

export default function UtilityAccountSuggestions({ unmatchedAccounts, utilityTypes }) {
    const [mappedAccounts, setMappedAccounts] = useState([]);

    const handleMapped = (glAccount) => {
        setMappedAccounts((prev) => [...prev, glAccount]);
    };

    // Filter out already mapped accounts from the display
    const visibleAccounts = Object.entries(unmatchedAccounts).filter(
        ([glAccount]) => !mappedAccounts.includes(glAccount)
    );

    return (
        <AdminLayout currentTab="utility-accounts">
            <div className="space-y-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div>
                        <h2 className="text-lg font-medium text-gray-900">Unmapped GL Accounts</h2>
                        <p className="mt-1 text-sm text-gray-500">
                            These GL accounts appeared in expense data but are not mapped to utility types.
                            Map them to include in utility tracking.
                        </p>
                    </div>
                    <Link
                        href="/admin/utility-accounts"
                        className="btn-secondary"
                    >
                        View All Mappings
                    </Link>
                </div>

                {/* Account List */}
                {visibleAccounts.length > 0 ? (
                    <div className="card overflow-hidden">
                        <table className="min-w-full divide-y divide-gray-200">
                            <thead className="bg-gray-50">
                                <tr>
                                    <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        GL Account #
                                    </th>
                                    <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Account Name
                                    </th>
                                    <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Status
                                    </th>
                                    <th scope="col" className="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Expense Count
                                    </th>
                                    <th scope="col" className="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Actions
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="bg-white divide-y divide-gray-200">
                                {visibleAccounts.map(([glAccount, count]) => (
                                    <SuggestionRow
                                        key={glAccount}
                                        glAccount={glAccount}
                                        count={count}
                                        utilityTypes={utilityTypes}
                                        onMapped={handleMapped}
                                    />
                                ))}
                            </tbody>
                        </table>
                    </div>
                ) : (
                    <div className="card">
                        <div className="px-6 py-12 text-center">
                            <LightBulbIcon className="mx-auto h-12 w-12 text-green-400" />
                            <h3 className="mt-2 text-sm font-medium text-gray-900">All accounts mapped!</h3>
                            <p className="mt-1 text-sm text-gray-500">
                                {mappedAccounts.length > 0
                                    ? `You just mapped ${mappedAccounts.length} account(s). `
                                    : ''}
                                No unmapped GL accounts found in recent expense data.
                            </p>
                            <div className="mt-6">
                                <Link
                                    href="/admin/utility-accounts"
                                    className="btn-primary"
                                >
                                    View All Mappings
                                </Link>
                            </div>
                        </div>
                    </div>
                )}

                {/* Help Text */}
                <div className="card max-w-2xl">
                    <div className="card-body">
                        <h3 className="text-sm font-medium text-gray-900 mb-2">About This Page</h3>
                        <ul className="text-sm text-gray-600 space-y-2 list-disc list-inside">
                            <li>Shows GL accounts from the last 90 days of expense data that are not yet mapped</li>
                            <li>Accounts are sorted by expense count (most frequent first)</li>
                            <li>Click "Map Account" to assign a utility type to each GL account</li>
                            <li>After mapping, expenses will be categorized in utility dashboards on the next sync</li>
                        </ul>
                    </div>
                </div>
            </div>
        </AdminLayout>
    );
}
