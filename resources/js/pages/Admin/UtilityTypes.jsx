import { useForm, router } from '@inertiajs/react';
import { useState } from 'react';
import AdminLayout from './Index';
import UtilityTypeIcon from '@/components/Utilities/UtilityTypeIcon';
import { AvailableUtilityIcons, AvailableColorSchemes, getIconComponent, getColorScheme } from '@/components/Utilities/availableIcons';
import {
    PlusIcon,
    PencilIcon,
    TrashIcon,
    XMarkIcon,
    CheckIcon,
    TagIcon,
    ArrowPathIcon,
    ChevronDownIcon,
} from '@heroicons/react/24/outline';

function IconSelector({ value, onChange }) {
    const [isOpen, setIsOpen] = useState(false);
    const currentIcon = AvailableUtilityIcons[value] || AvailableUtilityIcons.CubeIcon;
    const CurrentIconComponent = currentIcon.component;

    return (
        <div className="relative">
            <button
                type="button"
                onClick={() => setIsOpen(!isOpen)}
                className="flex items-center gap-2 px-3 py-2 border border-gray-300 rounded-md bg-white text-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500"
            >
                <CurrentIconComponent className="h-5 w-5 text-gray-600" />
                <span className="text-gray-700">{currentIcon.name}</span>
                <ChevronDownIcon className="h-4 w-4 text-gray-400" />
            </button>
            {isOpen && (
                <div className="absolute z-50 mt-1 w-64 bg-white rounded-md shadow-lg border border-gray-200 max-h-60 overflow-auto">
                    <div className="p-2 grid grid-cols-4 gap-1">
                        {Object.entries(AvailableUtilityIcons).map(([key, { name, component: IconComponent }]) => (
                            <button
                                key={key}
                                type="button"
                                onClick={() => {
                                    onChange(key);
                                    setIsOpen(false);
                                }}
                                className={`p-2 rounded hover:bg-gray-100 flex items-center justify-center ${
                                    value === key ? 'bg-blue-50 ring-2 ring-blue-500' : ''
                                }`}
                                title={name}
                            >
                                <IconComponent className="h-5 w-5 text-gray-600" />
                            </button>
                        ))}
                    </div>
                </div>
            )}
        </div>
    );
}

function ColorSelector({ value, onChange }) {
    const [isOpen, setIsOpen] = useState(false);
    const currentColor = AvailableColorSchemes[value] || AvailableColorSchemes.slate;

    return (
        <div className="relative">
            <button
                type="button"
                onClick={() => setIsOpen(!isOpen)}
                className="flex items-center gap-2 px-3 py-2 border border-gray-300 rounded-md bg-white text-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500"
            >
                <span className={`h-4 w-4 rounded ${currentColor.preview}`} />
                <span className="text-gray-700">{currentColor.name}</span>
                <ChevronDownIcon className="h-4 w-4 text-gray-400" />
            </button>
            {isOpen && (
                <div className="absolute z-50 mt-1 w-48 bg-white rounded-md shadow-lg border border-gray-200">
                    <div className="p-2 grid grid-cols-4 gap-1">
                        {Object.entries(AvailableColorSchemes).map(([key, scheme]) => (
                            <button
                                key={key}
                                type="button"
                                onClick={() => {
                                    onChange(key);
                                    setIsOpen(false);
                                }}
                                className={`p-2 rounded hover:bg-gray-100 flex items-center justify-center ${
                                    value === key ? 'ring-2 ring-blue-500' : ''
                                }`}
                                title={scheme.name}
                            >
                                <span className={`h-6 w-6 rounded ${scheme.preview}`} />
                            </button>
                        ))}
                    </div>
                </div>
            )}
        </div>
    );
}

function AddTypeForm({ onCancel }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        key: '',
        label: '',
        icon: 'CubeIcon',
        color_scheme: 'slate',
    });

    const handleSubmit = (e) => {
        e.preventDefault();
        post(route('admin.utility-types.store'), {
            onSuccess: () => {
                reset();
                onCancel();
            },
        });
    };

    return (
        <tr className="bg-blue-50">
            <td className="px-6 py-4">
                <div className="flex items-center gap-3">
                    <UtilityTypeIcon
                        utilityType={{ icon: data.icon, color_scheme: data.color_scheme }}
                        size="md"
                    />
                    <input
                        type="text"
                        value={data.key}
                        onChange={(e) => setData('key', e.target.value.toLowerCase().replace(/[^a-z0-9_]/g, ''))}
                        placeholder="e.g., stormwater"
                        className="input flex-1 font-mono"
                        autoFocus
                    />
                </div>
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
            <td className="px-6 py-4">
                <IconSelector value={data.icon} onChange={(v) => setData('icon', v)} />
            </td>
            <td className="px-6 py-4">
                <ColorSelector value={data.color_scheme} onChange={(v) => setData('color_scheme', v)} />
            </td>
            <td className="px-6 py-4 text-center text-sm text-gray-500">-</td>
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

function EditTypeRow({ type, onCancel }) {
    const { data, setData, patch, processing, errors } = useForm({
        label: type.label,
        icon: type.icon,
        color_scheme: type.color_scheme,
    });

    const handleSubmit = (e) => {
        e.preventDefault();
        patch(route('admin.utility-types.update', type.id), {
            onSuccess: () => onCancel(),
        });
    };

    return (
        <tr className="bg-yellow-50">
            <td className="px-6 py-4">
                <div className="flex items-center gap-3">
                    <UtilityTypeIcon
                        utilityType={{ icon: data.icon, color_scheme: data.color_scheme }}
                        size="md"
                    />
                    <span className="font-mono font-medium text-gray-900">{type.key}</span>
                    {type.is_system && (
                        <span className="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-600">
                            system
                        </span>
                    )}
                </div>
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
            <td className="px-6 py-4">
                <IconSelector value={data.icon} onChange={(v) => setData('icon', v)} />
            </td>
            <td className="px-6 py-4">
                <ColorSelector value={data.color_scheme} onChange={(v) => setData('color_scheme', v)} />
            </td>
            <td className="px-6 py-4 text-center text-sm text-gray-500">-</td>
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

function TypeRow({ type, onEdit, onDelete }) {
    const hasUsage = type.accounts_count > 0;
    const canDelete = !hasUsage;

    return (
        <tr className="hover:bg-gray-50">
            <td className="px-6 py-4">
                <div className="flex items-center gap-3">
                    <UtilityTypeIcon utilityType={type} size="md" />
                    <span className="font-mono font-medium text-gray-900">{type.key}</span>
                    {type.is_system && (
                        <span className="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-600">
                            system
                        </span>
                    )}
                </div>
            </td>
            <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                {type.label}
            </td>
            <td className="px-6 py-4 whitespace-nowrap text-center text-sm text-gray-500">
                <span className="inline-flex items-center gap-1">
                    {(() => {
                        const IconComponent = getIconComponent(type.icon);
                        return <IconComponent className="h-4 w-4" />;
                    })()}
                    <span className="text-xs">{AvailableUtilityIcons[type.icon]?.name || type.icon}</span>
                </span>
            </td>
            <td className="px-6 py-4 whitespace-nowrap text-center text-sm text-gray-500">
                <span className="inline-flex items-center gap-1">
                    <span className={`h-4 w-4 rounded ${getColorScheme(type.color_scheme).preview}`} />
                    <span className="text-xs">{getColorScheme(type.color_scheme).name}</span>
                </span>
            </td>
            <td className="px-6 py-4 whitespace-nowrap text-center text-sm text-gray-500">
                <span title={`${type.accounts_count} accounts, ${type.expenses_count} expenses`}>
                    {type.accounts_count} / {type.expenses_count?.toLocaleString() || 0}
                </span>
            </td>
            <td className="px-6 py-4 whitespace-nowrap text-right text-sm font-medium space-x-2">
                <button
                    type="button"
                    onClick={() => onEdit(type)}
                    className="text-blue-600 hover:text-blue-900"
                    title="Edit type"
                >
                    <PencilIcon className="w-4 h-4" />
                </button>
                <button
                    type="button"
                    onClick={() => onDelete(type)}
                    className={`${canDelete ? 'text-red-600 hover:text-red-900' : 'text-gray-300 cursor-not-allowed'}`}
                    title={hasUsage ? 'Cannot delete - type is in use' : 'Delete'}
                    disabled={!canDelete}
                >
                    <TrashIcon className="w-4 h-4" />
                </button>
            </td>
        </tr>
    );
}

// Mobile card component
function TypeCard({ type, onEdit, onDelete }) {
    const hasUsage = type.accounts_count > 0;
    const canDelete = !hasUsage;

    return (
        <div className="p-4 border-b border-gray-200 last:border-b-0">
            <div className="flex items-start justify-between">
                <div className="flex items-center gap-3">
                    <UtilityTypeIcon utilityType={type} size="md" />
                    <div>
                        <div className="flex items-center gap-2">
                            <span className="font-mono font-medium text-gray-900">{type.key}</span>
                            {type.is_system && (
                                <span className="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-600">
                                    system
                                </span>
                            )}
                        </div>
                        <p className="text-sm text-gray-700">{type.label}</p>
                        <p className="text-xs text-gray-500 mt-1">
                            {type.accounts_count} accounts • {type.expenses_count?.toLocaleString() || 0} expenses
                        </p>
                    </div>
                </div>
                <div className="flex items-center gap-2">
                    <button
                        type="button"
                        onClick={() => onEdit(type)}
                        className="p-3 text-blue-600 hover:text-blue-900 hover:bg-blue-50 rounded-lg"
                        title="Edit"
                    >
                        <PencilIcon className="w-5 h-5" />
                    </button>
                    <button
                        type="button"
                        onClick={() => onDelete(type)}
                        className={`p-3 rounded-lg ${canDelete ? 'text-red-600 hover:text-red-900 hover:bg-red-50' : 'text-gray-300 cursor-not-allowed'}`}
                        title={hasUsage ? 'Cannot delete' : 'Delete'}
                        disabled={!canDelete}
                    >
                        <TrashIcon className="w-5 h-5" />
                    </button>
                </div>
            </div>
        </div>
    );
}

// Mobile form for adding/editing types
function MobileTypeForm({ type, onCancel, isEditing = false }) {
    const { data, setData, post, patch, processing, errors, reset } = useForm({
        key: type?.key || '',
        label: type?.label || '',
        icon: type?.icon || 'CubeIcon',
        color_scheme: type?.color_scheme || 'slate',
    });

    const handleSubmit = (e) => {
        e.preventDefault();
        if (isEditing) {
            patch(route('admin.utility-types.update', type.id), {
                onSuccess: () => onCancel(),
            });
        } else {
            post(route('admin.utility-types.store'), {
                onSuccess: () => {
                    reset();
                    onCancel();
                },
            });
        }
    };

    return (
        <div className="p-4 bg-blue-50 border-b border-blue-100">
            <div className="flex items-center gap-3 mb-3">
                <UtilityTypeIcon
                    utilityType={{ icon: data.icon, color_scheme: data.color_scheme }}
                    size="md"
                />
                <h4 className="text-sm font-medium text-gray-900">
                    {isEditing ? `Edit ${type.label}` : 'Add New Type'}
                </h4>
            </div>
            <form onSubmit={handleSubmit} className="space-y-3">
                {!isEditing && (
                    <div>
                        <label className="block text-xs font-medium text-gray-700 mb-1">Key</label>
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
                    </div>
                )}
                <div>
                    <label className="block text-xs font-medium text-gray-700 mb-1">Label</label>
                    <input
                        type="text"
                        value={data.label}
                        onChange={(e) => setData('label', e.target.value)}
                        placeholder="e.g., Stormwater"
                        className="input w-full"
                        autoFocus={isEditing}
                    />
                    {errors.label && (
                        <p className="mt-1 text-xs text-red-600">{errors.label}</p>
                    )}
                </div>
                <div className="grid grid-cols-2 gap-3">
                    <div>
                        <label className="block text-xs font-medium text-gray-700 mb-1">Icon</label>
                        <IconSelector value={data.icon} onChange={(v) => setData('icon', v)} />
                    </div>
                    <div>
                        <label className="block text-xs font-medium text-gray-700 mb-1">Color</label>
                        <ColorSelector value={data.color_scheme} onChange={(v) => setData('color_scheme', v)} />
                    </div>
                </div>
                <div className="flex gap-2 pt-2">
                    <button
                        type="submit"
                        disabled={processing}
                        className="flex-1 btn-primary"
                    >
                        <CheckIcon className="w-4 h-4 mr-1 inline" />
                        {processing ? 'Saving...' : 'Save'}
                    </button>
                    <button
                        type="button"
                        onClick={onCancel}
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

export default function UtilityTypes({ utilityTypes }) {
    const [isAdding, setIsAdding] = useState(false);
    const [editingId, setEditingId] = useState(null);

    const handleDelete = (type) => {
        if (confirm(`Are you sure you want to delete the utility type "${type.label}"?`)) {
            router.delete(route('admin.utility-types.destroy', type.id));
        }
    };

    const handleReset = () => {
        if (confirm('Remove all custom utility types? System types will remain. Custom types that are in use cannot be removed.')) {
            router.post(route('admin.utility-types.reset'));
        }
    };

    // utilityTypes is now an array of objects
    const types = Array.isArray(utilityTypes) ? utilityTypes : [];

    return (
        <AdminLayout currentTab="utility-types">
            <div className="space-y-6">
                {/* Header */}
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div>
                        <h2 className="text-lg font-medium text-gray-900">Utility Types</h2>
                        <p className="mt-1 text-sm text-gray-500">
                            Configure utility categories with custom icons and colors.
                        </p>
                    </div>
                    <div className="flex flex-col sm:flex-row gap-3">
                        <button
                            type="button"
                            onClick={handleReset}
                            className="btn-secondary"
                        >
                            <ArrowPathIcon className="w-4 h-4 mr-2 inline" />
                            Remove Custom
                        </button>
                        {!isAdding && (
                            <button
                                type="button"
                                onClick={() => setIsAdding(true)}
                                className="btn-primary"
                            >
                                <PlusIcon className="w-4 h-4 mr-2 inline" />
                                Add Type
                            </button>
                        )}
                    </div>
                </div>

                {/* Types List */}
                <div className="card overflow-visible">
                    {/* Mobile Card View */}
                    <div className="md:hidden">
                        {isAdding && (
                            <MobileTypeForm onCancel={() => setIsAdding(false)} />
                        )}
                        {types.length === 0 && !isAdding ? (
                            <div className="px-4 py-12 text-center">
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
                                        <PlusIcon className="w-4 h-4 mr-2 inline" />
                                        Add First Type
                                    </button>
                                </div>
                            </div>
                        ) : (
                            types.map((type) => (
                                editingId === type.id ? (
                                    <MobileTypeForm
                                        key={type.id}
                                        type={type}
                                        onCancel={() => setEditingId(null)}
                                        isEditing
                                    />
                                ) : (
                                    <TypeCard
                                        key={type.id}
                                        type={type}
                                        onEdit={(t) => setEditingId(t.id)}
                                        onDelete={handleDelete}
                                    />
                                )
                            ))
                        )}
                    </div>

                    {/* Desktop Table View */}
                    <div className="hidden md:block overflow-x-auto">
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
                                        Icon
                                    </th>
                                    <th scope="col" className="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Color
                                    </th>
                                    <th scope="col" className="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Accounts / Expenses
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
                                {types.map((type) => (
                                    editingId === type.id ? (
                                        <EditTypeRow
                                            key={type.id}
                                            type={type}
                                            onCancel={() => setEditingId(null)}
                                        />
                                    ) : (
                                        <TypeRow
                                            key={type.id}
                                            type={type}
                                            onEdit={(t) => setEditingId(t.id)}
                                            onDelete={handleDelete}
                                        />
                                    )
                                ))}
                                {types.length === 0 && !isAdding && (
                                    <tr>
                                        <td colSpan="6" className="px-6 py-12 text-center">
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
                </div>

                {/* Help Text */}
                <div className="card max-w-2xl">
                    <div className="card-body">
                        <h3 className="text-sm font-medium text-gray-900 mb-2">How It Works</h3>
                        <ul className="text-sm text-gray-600 space-y-2 list-disc list-inside">
                            <li>Utility types are categories for classifying expense accounts (water, electric, gas, etc.)</li>
                            <li>The <strong>key</strong> is used internally and must be lowercase letters, numbers, and underscores</li>
                            <li>Choose an <strong>icon</strong> and <strong>color</strong> to customize how the type appears in the app</li>
                            <li><strong>System types</strong> (water, electric, etc.) cannot be deleted but can be customized</li>
                            <li>Custom types with account mappings or expenses cannot be deleted</li>
                        </ul>
                    </div>
                </div>
            </div>
        </AdminLayout>
    );
}
