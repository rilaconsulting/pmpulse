import { useForm, router, Link } from '@inertiajs/react';
import { useState } from 'react';
import AdminLayout from './Index';
import {
    PlusIcon,
    PencilIcon,
    TrashIcon,
    XMarkIcon,
    CheckIcon,
    BoltIcon,
    LightBulbIcon,
} from '@heroicons/react/24/outline';
import { getColorScheme } from '../../components/Utilities/constants';

function AddAccountForm({ utilityTypes, onCancel }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        gl_account_number: '',
        gl_account_name: '',
        utility_type_id: '',
        is_active: true,
    });

    const handleSubmit = (e) => {
        e.preventDefault();
        post(route('admin.utility-accounts.store'), {
            onSuccess: () => {
                reset();
                onCancel();
            },
        });
    };

    return (
        <tr className="bg-blue-50">
            <td className="px-6 py-4">
                <input
                    type="text"
                    value={data.gl_account_number}
                    onChange={(e) => setData('gl_account_number', e.target.value)}
                    placeholder="e.g., 6210"
                    className="input w-full"
                    autoFocus
                />
                {errors.gl_account_number && (
                    <p className="mt-1 text-xs text-red-600">{errors.gl_account_number}</p>
                )}
            </td>
            <td className="px-6 py-4">
                <input
                    type="text"
                    value={data.gl_account_name}
                    onChange={(e) => setData('gl_account_name', e.target.value)}
                    placeholder="e.g., Water Expense"
                    className="input w-full"
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
            <td className="px-6 py-4 text-center">
                <input
                    type="checkbox"
                    checked={data.is_active}
                    onChange={(e) => setData('is_active', e.target.checked)}
                    className="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                />
            </td>
            <td className="px-6 py-4 text-right space-x-2">
                <button
                    type="button"
                    onClick={handleSubmit}
                    disabled={processing}
                    className="inline-flex items-center px-2 py-1 text-sm font-medium text-white bg-blue-600 rounded hover:bg-blue-700 disabled:opacity-50"
                >
                    <CheckIcon className="w-4 h-4 mr-1" />
                    Save
                </button>
                <button
                    type="button"
                    onClick={onCancel}
                    className="inline-flex items-center px-2 py-1 text-sm font-medium text-gray-700 bg-gray-100 rounded hover:bg-gray-200"
                >
                    <XMarkIcon className="w-4 h-4 mr-1" />
                    Cancel
                </button>
            </td>
        </tr>
    );
}

function EditAccountRow({ account, utilityTypes, onCancel }) {
    const { data, setData, patch, processing, errors } = useForm({
        gl_account_number: account.gl_account_number,
        gl_account_name: account.gl_account_name,
        utility_type_id: account.utility_type_id,
        is_active: account.is_active,
    });

    const handleSubmit = (e) => {
        e.preventDefault();
        patch(route('admin.utility-accounts.update', account.id), {
            onSuccess: () => onCancel(),
        });
    };

    return (
        <tr className="bg-yellow-50">
            <td className="px-6 py-4">
                <input
                    type="text"
                    value={data.gl_account_number}
                    onChange={(e) => setData('gl_account_number', e.target.value)}
                    className="input w-full"
                />
                {errors.gl_account_number && (
                    <p className="mt-1 text-xs text-red-600">{errors.gl_account_number}</p>
                )}
            </td>
            <td className="px-6 py-4">
                <input
                    type="text"
                    value={data.gl_account_name}
                    onChange={(e) => setData('gl_account_name', e.target.value)}
                    className="input w-full"
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
                    {utilityTypes.map((type) => (
                        <option key={type.id} value={type.id}>{type.label}</option>
                    ))}
                </select>
                {errors.utility_type_id && (
                    <p className="mt-1 text-xs text-red-600">{errors.utility_type_id}</p>
                )}
            </td>
            <td className="px-6 py-4 text-center">
                <input
                    type="checkbox"
                    checked={data.is_active}
                    onChange={(e) => setData('is_active', e.target.checked)}
                    className="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                />
            </td>
            <td className="px-6 py-4 text-right space-x-2">
                <button
                    type="button"
                    onClick={handleSubmit}
                    disabled={processing}
                    className="inline-flex items-center px-2 py-1 text-sm font-medium text-white bg-blue-600 rounded hover:bg-blue-700 disabled:opacity-50"
                >
                    <CheckIcon className="w-4 h-4 mr-1" />
                    Save
                </button>
                <button
                    type="button"
                    onClick={onCancel}
                    className="inline-flex items-center px-2 py-1 text-sm font-medium text-gray-700 bg-gray-100 rounded hover:bg-gray-200"
                >
                    <XMarkIcon className="w-4 h-4 mr-1" />
                    Cancel
                </button>
            </td>
        </tr>
    );
}

function AccountRow({ account, onEdit, onDelete }) {
    const utilityType = account.utility_type;
    const colors = getColorScheme(utilityType?.color_scheme);

    return (
        <tr className="hover:bg-gray-50">
            <td className="px-6 py-4 whitespace-nowrap text-sm font-mono font-medium text-gray-900">
                {account.gl_account_number}
            </td>
            <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                {account.gl_account_name}
            </td>
            <td className="px-6 py-4 whitespace-nowrap">
                <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${colors.bg} ${colors.text}`}>
                    {utilityType?.label || 'Unknown'}
                </span>
            </td>
            <td className="px-6 py-4 whitespace-nowrap text-center">
                {account.is_active ? (
                    <span className="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800">
                        Active
                    </span>
                ) : (
                    <span className="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-800">
                        Inactive
                    </span>
                )}
            </td>
            <td className="px-6 py-4 whitespace-nowrap text-right text-sm font-medium space-x-2">
                <button
                    type="button"
                    onClick={() => onEdit(account)}
                    className="text-blue-600 hover:text-blue-900"
                    title="Edit"
                >
                    <PencilIcon className="w-4 h-4" />
                </button>
                <button
                    type="button"
                    onClick={() => onDelete(account)}
                    className="text-red-600 hover:text-red-900"
                    title="Delete"
                >
                    <TrashIcon className="w-4 h-4" />
                </button>
            </td>
        </tr>
    );
}

export default function UtilityAccounts({ accounts, utilityTypes }) {
    const [isAdding, setIsAdding] = useState(false);
    const [editingId, setEditingId] = useState(null);

    const handleDelete = (account) => {
        if (confirm(`Are you sure you want to delete the mapping for GL account "${account.gl_account_number}"?`)) {
            router.delete(route('admin.utility-accounts.destroy', account.id));
        }
    };

    return (
        <AdminLayout currentTab="utility-accounts">
            <div className="space-y-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div>
                        <h2 className="text-lg font-medium text-gray-900">Utility Account Mappings</h2>
                        <p className="mt-1 text-sm text-gray-500">
                            Configure which GL accounts represent utility expenses for tracking and reporting.
                        </p>
                    </div>
                    <div className="flex items-center gap-3">
                        <Link
                            href={route('admin.utility-accounts.suggestions')}
                            className="btn-secondary"
                        >
                            <LightBulbIcon className="w-4 h-4 mr-2" />
                            View Suggestions
                        </Link>
                        {!isAdding && (
                            <button
                                type="button"
                                onClick={() => setIsAdding(true)}
                                className="btn-primary"
                            >
                                <PlusIcon className="w-4 h-4 mr-2" />
                                Add Mapping
                            </button>
                        )}
                    </div>
                </div>

                {/* Account List */}
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
                                    Utility Type
                                </th>
                                <th scope="col" className="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Status
                                </th>
                                <th scope="col" className="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody className="bg-white divide-y divide-gray-200">
                            {isAdding && (
                                <AddAccountForm
                                    utilityTypes={utilityTypes}
                                    onCancel={() => setIsAdding(false)}
                                />
                            )}
                            {accounts.map((account) => (
                                editingId === account.id ? (
                                    <EditAccountRow
                                        key={account.id}
                                        account={account}
                                        utilityTypes={utilityTypes}
                                        onCancel={() => setEditingId(null)}
                                    />
                                ) : (
                                    <AccountRow
                                        key={account.id}
                                        account={account}
                                        onEdit={(account) => setEditingId(account.id)}
                                        onDelete={handleDelete}
                                    />
                                )
                            ))}
                            {accounts.length === 0 && !isAdding && (
                                <tr>
                                    <td colSpan="5" className="px-6 py-12 text-center">
                                        <BoltIcon className="mx-auto h-12 w-12 text-gray-400" />
                                        <h3 className="mt-2 text-sm font-medium text-gray-900">No utility accounts configured</h3>
                                        <p className="mt-1 text-sm text-gray-500">
                                            Add GL account mappings to track utility expenses.
                                        </p>
                                        <div className="mt-6">
                                            <button
                                                type="button"
                                                onClick={() => setIsAdding(true)}
                                                className="btn-primary"
                                            >
                                                <PlusIcon className="w-4 h-4 mr-2" />
                                                Add First Mapping
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                {/* Help Text */}
                <div className="card max-w-2xl">
                    <div className="card-body">
                        <h3 className="text-sm font-medium text-gray-900 mb-2">How It Works</h3>
                        <ul className="text-sm text-gray-600 space-y-2 list-disc list-inside">
                            <li>Map your GL account numbers to utility types (water, electric, gas, etc.)</li>
                            <li>During expense sync, matching GL accounts will be categorized as utility expenses</li>
                            <li>Utility expenses will appear in dedicated dashboards and reports</li>
                            <li>Inactive mappings will be ignored during sync</li>
                        </ul>
                    </div>
                </div>
            </div>
        </AdminLayout>
    );
}
