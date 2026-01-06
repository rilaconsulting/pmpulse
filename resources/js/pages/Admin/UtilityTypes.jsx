import { useForm, router } from '@inertiajs/react';
import { useState } from 'react';
import AdminLayout from './Index';
import {
    PlusIcon,
    PencilIcon,
    TrashIcon,
    XMarkIcon,
    CheckIcon,
    TagIcon,
    ArrowPathIcon,
} from '@heroicons/react/24/outline';

function AddTypeForm({ onCancel }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        key: '',
        label: '',
    });

    const handleSubmit = (e) => {
        e.preventDefault();
        post('/admin/utility-types', {
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
                    value={data.key}
                    onChange={(e) => setData('key', e.target.value.toLowerCase().replace(/[^a-z0-9_]/g, ''))}
                    placeholder="e.g., stormwater"
                    className="input w-full font-mono"
                    autoFocus
                />
                {errors.key && (
                    <p className="mt-1 text-xs text-red-600">{errors.key}</p>
                )}
            </td>
            <td className="px-6 py-4">
                <input
                    type="text"
                    value={data.label}
                    onChange={(e) => setData('label', e.target.value)}
                    placeholder="e.g., Stormwater"
                    className="input w-full"
                />
                {errors.label && (
                    <p className="mt-1 text-xs text-red-600">{errors.label}</p>
                )}
            </td>
            <td className="px-6 py-4 text-center text-sm text-gray-500">
                -
            </td>
            <td className="px-6 py-4 text-center text-sm text-gray-500">
                -
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

function EditTypeRow({ typeKey, typeLabel, onCancel }) {
    const { data, setData, patch, processing, errors } = useForm({
        label: typeLabel,
    });

    const handleSubmit = (e) => {
        e.preventDefault();
        patch(`/admin/utility-types/${typeKey}`, {
            onSuccess: () => onCancel(),
        });
    };

    return (
        <tr className="bg-yellow-50">
            <td className="px-6 py-4 whitespace-nowrap text-sm font-mono font-medium text-gray-900">
                {typeKey}
            </td>
            <td className="px-6 py-4">
                <input
                    type="text"
                    value={data.label}
                    onChange={(e) => setData('label', e.target.value)}
                    className="input w-full"
                    autoFocus
                />
                {errors.label && (
                    <p className="mt-1 text-xs text-red-600">{errors.label}</p>
                )}
            </td>
            <td className="px-6 py-4 text-center text-sm text-gray-500">
                -
            </td>
            <td className="px-6 py-4 text-center text-sm text-gray-500">
                -
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

function TypeRow({ typeKey, typeLabel, counts, isDefault, onEdit, onDelete }) {
    const hasUsage = counts.accounts > 0 || counts.expenses > 0;

    return (
        <tr className="hover:bg-gray-50">
            <td className="px-6 py-4 whitespace-nowrap text-sm font-mono font-medium text-gray-900">
                {typeKey}
                {isDefault && (
                    <span className="ml-2 inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-600">
                        default
                    </span>
                )}
            </td>
            <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                {typeLabel}
            </td>
            <td className="px-6 py-4 whitespace-nowrap text-center text-sm text-gray-500">
                {counts.accounts}
            </td>
            <td className="px-6 py-4 whitespace-nowrap text-center text-sm text-gray-500">
                {counts.expenses.toLocaleString()}
            </td>
            <td className="px-6 py-4 whitespace-nowrap text-right text-sm font-medium space-x-2">
                <button
                    type="button"
                    onClick={() => onEdit(typeKey, typeLabel)}
                    className="text-blue-600 hover:text-blue-900"
                    title="Edit label"
                >
                    <PencilIcon className="w-4 h-4" />
                </button>
                <button
                    type="button"
                    onClick={() => onDelete(typeKey, typeLabel)}
                    className={`${hasUsage ? 'text-gray-300 cursor-not-allowed' : 'text-red-600 hover:text-red-900'}`}
                    title={hasUsage ? 'Cannot delete - type is in use' : 'Delete'}
                    disabled={hasUsage}
                >
                    <TrashIcon className="w-4 h-4" />
                </button>
            </td>
        </tr>
    );
}

export default function UtilityTypes({ utilityTypes, typeCounts, defaultTypes }) {
    const [isAdding, setIsAdding] = useState(false);
    const [editingKey, setEditingKey] = useState(null);

    const defaultKeys = Object.keys(defaultTypes);

    const handleDelete = (key, label) => {
        if (confirm(`Are you sure you want to delete the utility type "${label}"?`)) {
            router.delete(`/admin/utility-types/${key}`);
        }
    };

    const handleReset = () => {
        if (confirm('Reset all utility types to their default values? This will remove any custom types that are not in use.')) {
            router.post('/admin/utility-types/reset');
        }
    };

    const typeEntries = Object.entries(utilityTypes);

    return (
        <AdminLayout currentTab="utility-types">
            <div className="space-y-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div>
                        <h2 className="text-lg font-medium text-gray-900">Utility Types</h2>
                        <p className="mt-1 text-sm text-gray-500">
                            Configure the available utility categories for expense classification.
                        </p>
                    </div>
                    <div className="flex space-x-3">
                        <button
                            type="button"
                            onClick={handleReset}
                            className="btn-secondary"
                        >
                            <ArrowPathIcon className="w-4 h-4 mr-2" />
                            Reset to Defaults
                        </button>
                        {!isAdding && (
                            <button
                                type="button"
                                onClick={() => setIsAdding(true)}
                                className="btn-primary"
                            >
                                <PlusIcon className="w-4 h-4 mr-2" />
                                Add Type
                            </button>
                        )}
                    </div>
                </div>

                {/* Types List */}
                <div className="card overflow-hidden">
                    <table className="min-w-full divide-y divide-gray-200">
                        <thead className="bg-gray-50">
                            <tr>
                                <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Key
                                </th>
                                <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Label
                                </th>
                                <th scope="col" className="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Account Mappings
                                </th>
                                <th scope="col" className="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Expenses
                                </th>
                                <th scope="col" className="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody className="bg-white divide-y divide-gray-200">
                            {isAdding && (
                                <AddTypeForm onCancel={() => setIsAdding(false)} />
                            )}
                            {typeEntries.map(([key, label]) => (
                                editingKey === key ? (
                                    <EditTypeRow
                                        key={key}
                                        typeKey={key}
                                        typeLabel={label}
                                        onCancel={() => setEditingKey(null)}
                                    />
                                ) : (
                                    <TypeRow
                                        key={key}
                                        typeKey={key}
                                        typeLabel={label}
                                        counts={typeCounts[key] || { accounts: 0, expenses: 0 }}
                                        isDefault={defaultKeys.includes(key)}
                                        onEdit={(key, label) => setEditingKey(key)}
                                        onDelete={handleDelete}
                                    />
                                )
                            ))}
                            {typeEntries.length === 0 && !isAdding && (
                                <tr>
                                    <td colSpan="5" className="px-6 py-12 text-center">
                                        <TagIcon className="mx-auto h-12 w-12 text-gray-400" />
                                        <h3 className="mt-2 text-sm font-medium text-gray-900">No utility types configured</h3>
                                        <p className="mt-1 text-sm text-gray-500">
                                            Add utility types to categorize expense accounts.
                                        </p>
                                        <div className="mt-6">
                                            <button
                                                type="button"
                                                onClick={() => setIsAdding(true)}
                                                className="btn-primary"
                                            >
                                                <PlusIcon className="w-4 h-4 mr-2" />
                                                Add First Type
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
                            <li>Utility types are categories for classifying expense accounts (water, electric, gas, etc.)</li>
                            <li>The <strong>key</strong> is used internally and must be lowercase letters, numbers, and underscores</li>
                            <li>The <strong>label</strong> is what users see in the interface</li>
                            <li>Types with account mappings or expenses cannot be deleted</li>
                            <li>You can reset to default types at any time (custom types not in use will be removed)</li>
                        </ul>
                    </div>
                </div>
            </div>
        </AdminLayout>
    );
}
