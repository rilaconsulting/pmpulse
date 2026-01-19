import { useForm, router } from '@inertiajs/react';
import { useState } from 'react';
import AdminLayout from './Index';
import {
    PlusIcon,
    PencilIcon,
    TrashIcon,
    XMarkIcon,
    CheckIcon,
    SwatchIcon,
} from '@heroicons/react/24/outline';

const UtilityTypeColors = {
    water: 'bg-blue-100 text-blue-800 border-blue-200',
    electric: 'bg-yellow-100 text-yellow-800 border-yellow-200',
    gas: 'bg-orange-100 text-orange-800 border-orange-200',
    garbage: 'bg-gray-100 text-gray-800 border-gray-200',
    sewer: 'bg-green-100 text-green-800 border-green-200',
    other: 'bg-purple-100 text-purple-800 border-purple-200',
};

function ColorPicker({ value, onChange, label }) {
    return (
        <div className="flex items-center gap-2">
            <input
                type="color"
                value={value || '#000000'}
                onChange={(e) => onChange(e.target.value)}
                className="w-8 h-8 p-0 border border-gray-300 rounded cursor-pointer"
                title={label}
            />
            <input
                type="text"
                value={value || ''}
                onChange={(e) => onChange(e.target.value)}
                placeholder="#000000"
                className="input w-24 text-xs font-mono"
            />
        </div>
    );
}

function AddRuleForm({ utilityType, operators, onCancel }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        utility_type: utilityType,
        name: '',
        operator: 'increase_percent',
        threshold: '',
        color: '#dc2626',
        background_color: '#fef2f2',
        priority: 0,
        enabled: true,
    });

    const handleSubmit = (e) => {
        e.preventDefault();
        post(route('admin.utility-formatting-rules.store'), {
            onSuccess: () => {
                reset();
                onCancel();
            },
        });
    };

    return (
        <tr className="bg-blue-50">
            <td className="px-4 py-3">
                <input
                    type="text"
                    value={data.name}
                    onChange={(e) => setData('name', e.target.value)}
                    placeholder="e.g., High cost alert"
                    className="input w-full text-sm"
                    autoFocus
                />
                {errors.name && (
                    <p className="mt-1 text-xs text-red-600">{errors.name}</p>
                )}
            </td>
            <td className="px-4 py-3">
                <select
                    value={data.operator}
                    onChange={(e) => setData('operator', e.target.value)}
                    className="input w-full text-sm"
                >
                    {Object.entries(operators).map(([value, label]) => (
                        <option key={value} value={value}>{label}</option>
                    ))}
                </select>
                {errors.operator && (
                    <p className="mt-1 text-xs text-red-600">{errors.operator}</p>
                )}
            </td>
            <td className="px-4 py-3">
                <div className="flex items-center gap-1">
                    <input
                        type="number"
                        value={data.threshold}
                        onChange={(e) => setData('threshold', e.target.value)}
                        placeholder="20"
                        min="0"
                        step="0.01"
                        className="input w-20 text-sm"
                    />
                    <span className="text-gray-500 text-sm">%</span>
                </div>
                {errors.threshold && (
                    <p className="mt-1 text-xs text-red-600">{errors.threshold}</p>
                )}
            </td>
            <td className="px-4 py-3">
                <ColorPicker
                    value={data.color}
                    onChange={(v) => setData('color', v)}
                    label="Text color"
                />
                {errors.color && (
                    <p className="mt-1 text-xs text-red-600">{errors.color}</p>
                )}
            </td>
            <td className="px-4 py-3">
                <ColorPicker
                    value={data.background_color}
                    onChange={(v) => setData('background_color', v)}
                    label="Background color"
                />
                {errors.background_color && (
                    <p className="mt-1 text-xs text-red-600">{errors.background_color}</p>
                )}
            </td>
            <td className="px-4 py-3">
                <input
                    type="number"
                    value={data.priority}
                    onChange={(e) => setData('priority', parseInt(e.target.value) || 0)}
                    min="0"
                    max="100"
                    className="input w-16 text-sm text-center"
                />
                {errors.priority && (
                    <p className="mt-1 text-xs text-red-600">{errors.priority}</p>
                )}
            </td>
            <td className="px-4 py-3 text-center">
                <input
                    type="checkbox"
                    checked={data.enabled}
                    onChange={(e) => setData('enabled', e.target.checked)}
                    className="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                />
            </td>
            <td className="px-4 py-3 text-right">
                <div className="flex items-center justify-end gap-2">
                    <button
                        type="button"
                        onClick={handleSubmit}
                        disabled={processing}
                        className="inline-flex items-center px-2 py-1 text-xs font-medium text-white bg-blue-600 rounded hover:bg-blue-700 disabled:opacity-50"
                    >
                        <CheckIcon className="w-3 h-3 mr-1" />
                        Save
                    </button>
                    <button
                        type="button"
                        onClick={onCancel}
                        className="inline-flex items-center px-2 py-1 text-xs font-medium text-gray-700 bg-gray-100 rounded hover:bg-gray-200"
                    >
                        <XMarkIcon className="w-3 h-3 mr-1" />
                        Cancel
                    </button>
                </div>
            </td>
        </tr>
    );
}

function EditRuleRow({ rule, operators, onCancel }) {
    const { data, setData, patch, processing, errors } = useForm({
        name: rule.name,
        operator: rule.operator,
        threshold: rule.threshold,
        color: rule.color,
        background_color: rule.background_color || '',
        priority: rule.priority,
        enabled: rule.enabled,
    });

    const handleSubmit = (e) => {
        e.preventDefault();
        patch(route('admin.utility-formatting-rules.update', rule.id), {
            onSuccess: () => onCancel(),
        });
    };

    return (
        <tr className="bg-yellow-50">
            <td className="px-4 py-3">
                <input
                    type="text"
                    value={data.name}
                    onChange={(e) => setData('name', e.target.value)}
                    className="input w-full text-sm"
                />
                {errors.name && (
                    <p className="mt-1 text-xs text-red-600">{errors.name}</p>
                )}
            </td>
            <td className="px-4 py-3">
                <select
                    value={data.operator}
                    onChange={(e) => setData('operator', e.target.value)}
                    className="input w-full text-sm"
                >
                    {Object.entries(operators).map(([value, label]) => (
                        <option key={value} value={value}>{label}</option>
                    ))}
                </select>
                {errors.operator && (
                    <p className="mt-1 text-xs text-red-600">{errors.operator}</p>
                )}
            </td>
            <td className="px-4 py-3">
                <div className="flex items-center gap-1">
                    <input
                        type="number"
                        value={data.threshold}
                        onChange={(e) => setData('threshold', e.target.value)}
                        min="0"
                        step="0.01"
                        className="input w-20 text-sm"
                    />
                    <span className="text-gray-500 text-sm">%</span>
                </div>
                {errors.threshold && (
                    <p className="mt-1 text-xs text-red-600">{errors.threshold}</p>
                )}
            </td>
            <td className="px-4 py-3">
                <ColorPicker
                    value={data.color}
                    onChange={(v) => setData('color', v)}
                    label="Text color"
                />
                {errors.color && (
                    <p className="mt-1 text-xs text-red-600">{errors.color}</p>
                )}
            </td>
            <td className="px-4 py-3">
                <ColorPicker
                    value={data.background_color}
                    onChange={(v) => setData('background_color', v)}
                    label="Background color"
                />
                {errors.background_color && (
                    <p className="mt-1 text-xs text-red-600">{errors.background_color}</p>
                )}
            </td>
            <td className="px-4 py-3">
                <input
                    type="number"
                    value={data.priority}
                    onChange={(e) => setData('priority', parseInt(e.target.value) || 0)}
                    min="0"
                    max="100"
                    className="input w-16 text-sm text-center"
                />
                {errors.priority && (
                    <p className="mt-1 text-xs text-red-600">{errors.priority}</p>
                )}
            </td>
            <td className="px-4 py-3 text-center">
                <input
                    type="checkbox"
                    checked={data.enabled}
                    onChange={(e) => setData('enabled', e.target.checked)}
                    className="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                />
            </td>
            <td className="px-4 py-3 text-right">
                <div className="flex items-center justify-end gap-2">
                    <button
                        type="button"
                        onClick={handleSubmit}
                        disabled={processing}
                        className="inline-flex items-center px-2 py-1 text-xs font-medium text-white bg-blue-600 rounded hover:bg-blue-700 disabled:opacity-50"
                    >
                        <CheckIcon className="w-3 h-3 mr-1" />
                        Save
                    </button>
                    <button
                        type="button"
                        onClick={onCancel}
                        className="inline-flex items-center px-2 py-1 text-xs font-medium text-gray-700 bg-gray-100 rounded hover:bg-gray-200"
                    >
                        <XMarkIcon className="w-3 h-3 mr-1" />
                        Cancel
                    </button>
                </div>
            </td>
        </tr>
    );
}

function RuleRow({ rule, operators, onEdit, onDelete }) {
    return (
        <tr className="hover:bg-gray-50">
            <td className="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900">
                {rule.name}
            </td>
            <td className="px-4 py-3 whitespace-nowrap text-sm text-gray-600">
                {rule.operator_label}
            </td>
            <td className="px-4 py-3 whitespace-nowrap text-sm text-gray-900 font-mono">
                {rule.threshold}%
            </td>
            <td className="px-4 py-3 whitespace-nowrap">
                <div className="flex items-center gap-2">
                    <span
                        className="w-5 h-5 rounded border border-gray-300"
                        style={{ backgroundColor: rule.color }}
                        title={rule.color}
                    />
                    <span className="text-xs text-gray-500 font-mono">{rule.color}</span>
                </div>
            </td>
            <td className="px-4 py-3 whitespace-nowrap">
                <div className="flex items-center gap-2">
                    <span
                        className="w-5 h-5 rounded border border-gray-300"
                        style={{ backgroundColor: rule.background_color || 'transparent' }}
                        title={rule.background_color || 'None'}
                    />
                    <span className="text-xs text-gray-500 font-mono">
                        {rule.background_color || '-'}
                    </span>
                </div>
            </td>
            <td className="px-4 py-3 whitespace-nowrap text-sm text-gray-600 text-center">
                {rule.priority}
            </td>
            <td className="px-4 py-3 whitespace-nowrap text-center">
                {rule.enabled ? (
                    <span className="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800">
                        Enabled
                    </span>
                ) : (
                    <span className="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-800">
                        Disabled
                    </span>
                )}
            </td>
            <td className="px-4 py-3 whitespace-nowrap text-right text-sm font-medium">
                <div className="flex items-center justify-end gap-2">
                    <button
                        type="button"
                        onClick={() => onEdit(rule)}
                        className="text-blue-600 hover:text-blue-900"
                        title="Edit"
                    >
                        <PencilIcon className="w-4 h-4" />
                    </button>
                    <button
                        type="button"
                        onClick={() => onDelete(rule)}
                        className="text-red-600 hover:text-red-900"
                        title="Delete"
                    >
                        <TrashIcon className="w-4 h-4" />
                    </button>
                </div>
            </td>
        </tr>
    );
}

function UtilityTypeSection({ utilityType, utilityTypeName, rules, operators, editingId, setEditingId, addingType, setAddingType }) {
    const handleDelete = (rule) => {
        if (confirm(`Are you sure you want to delete the formatting rule "${rule.name}"?`)) {
            router.delete(route('admin.utility-formatting-rules.destroy', rule.id));
        }
    };

    const colorClasses = UtilityTypeColors[utilityType] || UtilityTypeColors.other;

    return (
        <div className="card overflow-hidden">
            {/* Section Header */}
            <div className={`px-4 py-3 border-b ${colorClasses}`}>
                <div className="flex items-center justify-between">
                    <h3 className="font-medium">{utilityTypeName}</h3>
                    {addingType !== utilityType && (
                        <button
                            type="button"
                            onClick={() => setAddingType(utilityType)}
                            className="inline-flex items-center px-2 py-1 text-xs font-medium text-gray-700 bg-white rounded border border-gray-300 hover:bg-gray-50"
                        >
                            <PlusIcon className="w-3 h-3 mr-1" />
                            Add Rule
                        </button>
                    )}
                </div>
            </div>

            {/* Rules Table */}
            <div className="overflow-x-auto">
                <table className="min-w-full divide-y divide-gray-200">
                    <thead className="bg-gray-50">
                        <tr>
                            <th scope="col" className="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Name
                            </th>
                            <th scope="col" className="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Condition
                            </th>
                            <th scope="col" className="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Threshold
                            </th>
                            <th scope="col" className="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Text Color
                            </th>
                            <th scope="col" className="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Background
                            </th>
                            <th scope="col" className="px-4 py-2 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Priority
                            </th>
                            <th scope="col" className="px-4 py-2 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Status
                            </th>
                            <th scope="col" className="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody className="bg-white divide-y divide-gray-200">
                        {addingType === utilityType && (
                            <AddRuleForm
                                utilityType={utilityType}
                                operators={operators}
                                onCancel={() => setAddingType(null)}
                            />
                        )}
                        {rules.map((rule) => (
                            editingId === rule.id ? (
                                <EditRuleRow
                                    key={rule.id}
                                    rule={rule}
                                    operators={operators}
                                    onCancel={() => setEditingId(null)}
                                />
                            ) : (
                                <RuleRow
                                    key={rule.id}
                                    rule={rule}
                                    operators={operators}
                                    onEdit={(rule) => setEditingId(rule.id)}
                                    onDelete={handleDelete}
                                />
                            )
                        ))}
                        {rules.length === 0 && addingType !== utilityType && (
                            <tr>
                                <td colSpan="8" className="px-4 py-6 text-center text-sm text-gray-500">
                                    No formatting rules configured for {utilityTypeName.toLowerCase()}.
                                    <button
                                        type="button"
                                        onClick={() => setAddingType(utilityType)}
                                        className="ml-2 text-blue-600 hover:text-blue-800"
                                    >
                                        Add one
                                    </button>
                                </td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>
        </div>
    );
}

export default function UtilityFormattingRules({ rules, rulesByType, utilityTypes, operators }) {
    const [addingType, setAddingType] = useState(null);
    const [editingId, setEditingId] = useState(null);

    return (
        <AdminLayout currentTab="utility-formatting-rules">
            <div className="space-y-6">
                {/* Header */}
                <div>
                    <h2 className="text-lg font-medium text-gray-900">Conditional Formatting Rules</h2>
                    <p className="mt-1 text-sm text-gray-500">
                        Configure color-coded alerts for utility costs that exceed or fall below the 12-month average.
                    </p>
                </div>

                {/* Rules by Utility Type */}
                <div className="space-y-6">
                    {Object.entries(utilityTypes).map(([type, label]) => (
                        <UtilityTypeSection
                            key={type}
                            utilityType={type}
                            utilityTypeName={label}
                            rules={rulesByType[type] || []}
                            operators={operators}
                            editingId={editingId}
                            setEditingId={setEditingId}
                            addingType={addingType}
                            setAddingType={setAddingType}
                        />
                    ))}
                </div>

                {/* Help Text */}
                <div className="card max-w-3xl">
                    <div className="card-body">
                        <h3 className="text-sm font-medium text-gray-900 mb-2">How Formatting Rules Work</h3>
                        <ul className="text-sm text-gray-600 space-y-2 list-disc list-inside">
                            <li>Rules compare current period costs against the 12-month average</li>
                            <li><strong>Increase %</strong>: Highlights when costs are above average by the threshold percentage</li>
                            <li><strong>Decrease %</strong>: Highlights when costs are below average by the threshold percentage</li>
                            <li>Higher priority rules are evaluated first; the first matching rule is applied</li>
                            <li>Colors are applied to the utility data table cells</li>
                            <li>Disabled rules are not evaluated</li>
                        </ul>
                        <div className="mt-4 p-3 bg-gray-50 rounded-lg">
                            <p className="text-xs text-gray-500">
                                <strong>Example:</strong> A rule with "Increase % over average" at 20% threshold will highlight cells where the current value is 20% or more above the 12-month average.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </AdminLayout>
    );
}
