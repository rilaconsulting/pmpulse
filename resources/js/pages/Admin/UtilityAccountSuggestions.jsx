import { useForm, Link } from '@inertiajs/react';
import { useState } from 'react';
import AdminLayout from './Index';
import {
    LightBulbIcon,
    CheckIcon,
    XMarkIcon,
    ArrowRightIcon,
} from '@heroicons/react/24/outline';

// Desktop table row for suggestions
function SuggestionRow({ glAccount, count, utilityTypes, onMapped }) {
    const [isMapping, setIsMapping] = useState(false);
    const { data, setData, post, processing, errors, reset } = useForm({
        gl_account_number: glAccount,
        gl_account_name: '',
        utility_type_id: '',
        is_active: true,
    });

    const handleSubmit = (e) => {
        e.preventDefault();
        post(route('admin.utility-accounts.store'), {
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
                        value={data.utility_type_id}
                        onChange={(e) => setData('utility_type_id', e.target.value)}
                        className="input w-full"
                    >
                        <option value="">Select type...</option>
                        {utilityTypes.map((type) => (
                            <option key={type.id} value={type.id}>{type.label}</option>
                        ))}
                    </select>
                    {errors.utility_type_id && (
                        <p className="mt-1 text-xs text-red-600">{errors.utility_type_id}</p>
                    )}
                </td>
                <td className="px-6 py-4 text-center text-sm text-gray-500">
                    {count}
                </td>
                <td className="px-6 py-4 text-right space-x-2">
                    <button
                        type="button"
                        onClick={handleSubmit}
                        disabled={processing || !data.utility_type_id || !data.gl_account_name.trim()}
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

// Mobile card for suggestion
function SuggestionCard({ glAccount, count, utilityTypes, onMapped }) {
    const [isMapping, setIsMapping] = useState(false);
    const { data, setData, post, processing, errors, reset } = useForm({
        gl_account_number: glAccount,
        gl_account_name: '',
        utility_type_id: '',
        is_active: true,
    });

    const handleSubmit = (e) => {
        e.preventDefault();
        post(route('admin.utility-accounts.store'), {
            onSuccess: () => {
                reset();
                setIsMapping(false);
                onMapped(glAccount);
            },
        });
    };

    if (isMapping) {
        return (
            <div className="p-4 bg-blue-50 border-b border-blue-100">
                <div className="flex items-center justify-between mb-3">
                    <span className="text-sm font-mono font-medium text-gray-900">{glAccount}</span>
                    <span className="text-xs text-gray-500">{count} expenses</span>
                </div>
                <form onSubmit={handleSubmit} className="space-y-3">
                    <div>
                        <label className="block text-xs font-medium text-gray-700 mb-1">Account Name</label>
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
                    </div>
                    <div>
                        <label className="block text-xs font-medium text-gray-700 mb-1">Utility Type</label>
                        <select
                            value={data.utility_type_id}
                            onChange={(e) => setData('utility_type_id', e.target.value)}
                            className="input w-full"
                        >
                            <option value="">Select type...</option>
                            {utilityTypes.map((type) => (
                                <option key={type.id} value={type.id}>{type.label}</option>
                            ))}
                        </select>
                        {errors.utility_type_id && (
                            <p className="mt-1 text-xs text-red-600">{errors.utility_type_id}</p>
                        )}
                    </div>
                    <div className="flex gap-2">
                        <button
                            type="submit"
                            disabled={processing || !data.utility_type_id || !data.gl_account_name.trim()}
                            className="flex-1 btn-primary"
                        >
                            <CheckIcon className="w-4 h-4 mr-1 inline" />
                            Save
                        </button>
                        <button
                            type="button"
                            onClick={() => {
                                reset();
                                setIsMapping(false);
                            }}
                            className="flex-1 btn-secondary"
                        >
                            <XMarkIcon className="w-4 h-4 mr-1 inline" />
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        );
    }

    return (
        <div className="p-4 border-b border-gray-200 last:border-b-0">
            <div className="flex items-start justify-between">
                <div>
                    <span className="text-sm font-mono font-medium text-gray-900">{glAccount}</span>
                    <div className="mt-1 flex items-center gap-2">
                        <span className="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                            Unmapped
                        </span>
                        <span className="text-xs text-gray-500">{count} expenses</span>
                    </div>
                </div>
                <button
                    type="button"
                    onClick={() => setIsMapping(true)}
                    className="btn-primary text-sm"
                >
                    Map
                    <ArrowRightIcon className="w-4 h-4 ml-1 inline" />
                </button>
            </div>
        </div>
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
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div>
                        <h2 className="text-lg font-medium text-gray-900">Unmapped GL Accounts</h2>
                        <p className="mt-1 text-sm text-gray-500">
                            These GL accounts appeared in expense data but are not mapped to utility types.
                            Map them to include in utility tracking.
                        </p>
                    </div>
                    <Link
                        href={route('admin.utility-accounts.index')}
                        className="btn-secondary w-full sm:w-auto text-center"
                    >
                        View All Mappings
                    </Link>
                </div>

                {/* Account List */}
                {visibleAccounts.length > 0 ? (
                    <div className="card overflow-hidden">
                        {/* Mobile Card View */}
                        <div className="md:hidden">
                            {visibleAccounts.map(([glAccount, count]) => (
                                <SuggestionCard
                                    key={glAccount}
                                    glAccount={glAccount}
                                    count={count}
                                    utilityTypes={utilityTypes}
                                    onMapped={handleMapped}
                                />
                            ))}
                        </div>

                        {/* Desktop Table View */}
                        <div className="hidden md:block overflow-x-auto">
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
                                    href={route('admin.utility-accounts.index')}
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
