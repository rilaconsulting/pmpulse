import { useState, useEffect } from 'react';
import { useForm, usePage, router } from '@inertiajs/react';
import {
    AdjustmentsHorizontalIcon,
    PlusIcon,
    PencilIcon,
    XMarkIcon,
    ClockIcon,
    CheckCircleIcon,
    ExclamationTriangleIcon,
    TrashIcon,
} from '@heroicons/react/24/outline';

export default function AdjustmentList({
    property,
    activeAdjustments,
    historicalAdjustments,
    adjustableFields,
    effectiveValues,
}) {
    const { auth } = usePage().props;
    const isAdmin = auth?.user?.role?.name === 'admin';

    const [activeTab, setActiveTab] = useState('active');
    const [showCreateModal, setShowCreateModal] = useState(false);
    const [editingAdjustment, setEditingAdjustment] = useState(null);
    const [endingAdjustment, setEndingAdjustment] = useState(null);
    const [deletingAdjustment, setDeletingAdjustment] = useState(null);

    const adjustments = activeTab === 'active' ? activeAdjustments : historicalAdjustments;

    // Create form
    const createForm = useForm({
        field_name: '',
        adjusted_value: '',
        effective_from: new Date().toISOString().split('T')[0],
        effective_to: '',
        reason: '',
    });

    // Edit form
    const editForm = useForm({
        adjusted_value: '',
        effective_to: '',
        reason: '',
    });

    const handleCreate = (e) => {
        e.preventDefault();
        createForm.post(`/properties/${property.id}/adjustments`, {
            onSuccess: () => {
                createForm.reset();
                setShowCreateModal(false);
            },
        });
    };

    const handleEdit = (e) => {
        e.preventDefault();
        editForm.patch(`/properties/${property.id}/adjustments/${editingAdjustment.id}`, {
            onSuccess: () => {
                editForm.reset();
                setEditingAdjustment(null);
            },
        });
    };

    const handleEnd = (adjustment) => {
        router.post(`/properties/${property.id}/adjustments/${adjustment.id}/end`, {}, {
            onSuccess: () => setEndingAdjustment(null),
        });
    };

    const handleDelete = (adjustment) => {
        router.delete(`/properties/${property.id}/adjustments/${adjustment.id}`, {
            onSuccess: () => setDeletingAdjustment(null),
        });
    };

    const openEditModal = (adjustment) => {
        editForm.setData({
            adjusted_value: adjustment.adjusted_value,
            effective_to: adjustment.effective_to || '',
            reason: adjustment.reason,
        });
        setEditingAdjustment(adjustment);
    };

    const getFieldLabel = (fieldName) => {
        return adjustableFields[fieldName]?.label || fieldName;
    };

    const formatValue = (value, fieldName) => {
        if (value === null || value === undefined) return '-';
        const fieldType = adjustableFields[fieldName]?.type;
        if (fieldType === 'decimal') {
            return new Intl.NumberFormat('en-US', {
                style: 'currency',
                currency: 'USD',
            }).format(value);
        }
        if (fieldType === 'integer') {
            return new Intl.NumberFormat('en-US').format(value);
        }
        return value;
    };

    const formatDate = (date) => {
        if (!date) return 'Permanent';
        return new Date(date).toLocaleDateString('en-US', {
            month: 'short',
            day: 'numeric',
            year: 'numeric',
        });
    };

    return (
        <div className="card">
            <div className="card-header flex items-center justify-between">
                <div className="flex items-center gap-3">
                    <AdjustmentsHorizontalIcon className="w-5 h-5 text-gray-500" />
                    <h2 className="text-lg font-medium text-gray-900">Data Adjustments</h2>
                </div>
                {isAdmin && (
                    <button
                        type="button"
                        onClick={() => setShowCreateModal(true)}
                        className="btn btn-primary btn-sm"
                    >
                        <PlusIcon className="w-4 h-4 mr-1" />
                        Add Adjustment
                    </button>
                )}
            </div>

            {/* Tabs */}
            <div className="border-b border-gray-200">
                <nav className="flex -mb-px">
                    <button
                        type="button"
                        onClick={() => setActiveTab('active')}
                        className={`px-4 py-3 text-sm font-medium border-b-2 ${
                            activeTab === 'active'
                                ? 'border-blue-500 text-blue-600'
                                : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
                        }`}
                    >
                        Active ({activeAdjustments.length})
                    </button>
                    <button
                        type="button"
                        onClick={() => setActiveTab('historical')}
                        className={`px-4 py-3 text-sm font-medium border-b-2 ${
                            activeTab === 'historical'
                                ? 'border-blue-500 text-blue-600'
                                : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
                        }`}
                    >
                        Historical ({historicalAdjustments.length})
                    </button>
                </nav>
            </div>

            {/* Adjustments List */}
            <div className="card-body">
                {adjustments.length === 0 ? (
                    <div className="text-center py-8">
                        <AdjustmentsHorizontalIcon className="w-12 h-12 text-gray-300 mx-auto mb-3" />
                        <p className="text-gray-500">
                            {activeTab === 'active'
                                ? 'No active adjustments'
                                : 'No historical adjustments'}
                        </p>
                    </div>
                ) : (
                    <div className="space-y-4">
                        {adjustments.map((adjustment) => (
                            <div
                                key={adjustment.id}
                                className="border border-gray-200 rounded-lg p-4 hover:bg-gray-50"
                            >
                                <div className="flex items-start justify-between">
                                    <div className="flex-1">
                                        <div className="flex items-center gap-2 mb-2">
                                            <span className="font-medium text-gray-900">
                                                {getFieldLabel(adjustment.field_name)}
                                            </span>
                                            {adjustment.effective_to === null ? (
                                                <span className="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800">
                                                    Permanent
                                                </span>
                                            ) : (
                                                <span className="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-800">
                                                    Date Range
                                                </span>
                                            )}
                                        </div>

                                        <div className="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                                            <div>
                                                <span className="text-gray-500">Original:</span>
                                                <span className="ml-1 text-gray-900">
                                                    {formatValue(adjustment.original_value, adjustment.field_name)}
                                                </span>
                                            </div>
                                            <div>
                                                <span className="text-gray-500">Adjusted:</span>
                                                <span className="ml-1 font-medium text-blue-600">
                                                    {formatValue(adjustment.adjusted_value, adjustment.field_name)}
                                                </span>
                                            </div>
                                            <div>
                                                <span className="text-gray-500">From:</span>
                                                <span className="ml-1 text-gray-900">
                                                    {formatDate(adjustment.effective_from)}
                                                </span>
                                            </div>
                                            <div>
                                                <span className="text-gray-500">To:</span>
                                                <span className="ml-1 text-gray-900">
                                                    {formatDate(adjustment.effective_to)}
                                                </span>
                                            </div>
                                        </div>

                                        <div className="mt-2 text-sm text-gray-600">
                                            <span className="font-medium">Reason:</span> {adjustment.reason}
                                        </div>

                                        <div className="mt-2 text-xs text-gray-400 flex items-center gap-1">
                                            <ClockIcon className="w-3 h-3" />
                                            Created by {adjustment.creator?.name || 'Unknown'} on{' '}
                                            {new Date(adjustment.created_at).toLocaleDateString()}
                                        </div>
                                    </div>

                                    {isAdmin && activeTab === 'active' && (
                                        <div className="flex items-center gap-2 ml-4">
                                            <button
                                                type="button"
                                                onClick={() => openEditModal(adjustment)}
                                                className="p-1.5 text-gray-400 hover:text-blue-600 rounded"
                                                title="Edit adjustment"
                                            >
                                                <PencilIcon className="w-4 h-4" />
                                            </button>
                                            {adjustment.effective_to === null && (
                                                <button
                                                    type="button"
                                                    onClick={() => setEndingAdjustment(adjustment)}
                                                    className="p-1.5 text-gray-400 hover:text-orange-600 rounded"
                                                    title="End adjustment"
                                                >
                                                    <CheckCircleIcon className="w-4 h-4" />
                                                </button>
                                            )}
                                            <button
                                                type="button"
                                                onClick={() => setDeletingAdjustment(adjustment)}
                                                className="p-1.5 text-gray-400 hover:text-red-600 rounded"
                                                title="Delete adjustment"
                                            >
                                                <TrashIcon className="w-4 h-4" />
                                            </button>
                                        </div>
                                    )}
                                </div>
                            </div>
                        ))}
                    </div>
                )}
            </div>

            {/* Create Modal */}
            {showCreateModal && (
                <Modal onClose={() => { createForm.reset(); setShowCreateModal(false); }}>
                    <h3 className="text-lg font-medium text-gray-900 mb-4">Add Data Adjustment</h3>
                    <form onSubmit={handleCreate} className="space-y-4">
                        <div>
                            <label className="block text-sm font-medium text-gray-700">
                                Field to Adjust
                            </label>
                            <select
                                className={`mt-1 input ${createForm.errors.field_name ? 'border-red-300' : ''}`}
                                value={createForm.data.field_name}
                                onChange={(e) => createForm.setData('field_name', e.target.value)}
                                required
                            >
                                <option value="">Select a field...</option>
                                {Object.entries(adjustableFields).map(([key, field]) => (
                                    <option key={key} value={key}>
                                        {field.label}
                                    </option>
                                ))}
                            </select>
                            {createForm.errors.field_name && (
                                <p className="mt-1 text-sm text-red-600">{createForm.errors.field_name}</p>
                            )}
                            {createForm.data.field_name && (
                                <p className="mt-1 text-xs text-gray-500">
                                    {adjustableFields[createForm.data.field_name]?.description}
                                </p>
                            )}
                        </div>

                        {createForm.data.field_name && (
                            <>
                                <div className="grid grid-cols-2 gap-4">
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700">
                                            Original Value
                                        </label>
                                        <input
                                            type="text"
                                            className="mt-1 input bg-gray-50"
                                            value={formatValue(
                                                effectiveValues[createForm.data.field_name]?.original ??
                                                property[createForm.data.field_name],
                                                createForm.data.field_name
                                            )}
                                            disabled
                                        />
                                    </div>
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700">
                                            Adjusted Value
                                        </label>
                                        <input
                                            type="number"
                                            step={adjustableFields[createForm.data.field_name]?.type === 'decimal' ? '0.01' : '1'}
                                            className={`mt-1 input ${createForm.errors.adjusted_value ? 'border-red-300' : ''}`}
                                            value={createForm.data.adjusted_value}
                                            onChange={(e) => createForm.setData('adjusted_value', e.target.value)}
                                            required
                                            min="0"
                                        />
                                        {createForm.errors.adjusted_value && (
                                            <p className="mt-1 text-sm text-red-600">{createForm.errors.adjusted_value}</p>
                                        )}
                                    </div>
                                </div>

                                <div className="grid grid-cols-2 gap-4">
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700">
                                            Effective From
                                        </label>
                                        <input
                                            type="date"
                                            className={`mt-1 input ${createForm.errors.effective_from ? 'border-red-300' : ''}`}
                                            value={createForm.data.effective_from}
                                            onChange={(e) => createForm.setData('effective_from', e.target.value)}
                                            required
                                        />
                                        {createForm.errors.effective_from && (
                                            <p className="mt-1 text-sm text-red-600">{createForm.errors.effective_from}</p>
                                        )}
                                    </div>
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700">
                                            Effective To <span className="text-gray-400">(leave blank for permanent)</span>
                                        </label>
                                        <input
                                            type="date"
                                            className={`mt-1 input ${createForm.errors.effective_to ? 'border-red-300' : ''}`}
                                            value={createForm.data.effective_to}
                                            onChange={(e) => createForm.setData('effective_to', e.target.value)}
                                            min={createForm.data.effective_from}
                                        />
                                        {createForm.errors.effective_to && (
                                            <p className="mt-1 text-sm text-red-600">{createForm.errors.effective_to}</p>
                                        )}
                                    </div>
                                </div>

                                <div>
                                    <label className="block text-sm font-medium text-gray-700">
                                        Reason
                                    </label>
                                    <textarea
                                        className={`mt-1 input ${createForm.errors.reason ? 'border-red-300' : ''}`}
                                        value={createForm.data.reason}
                                        onChange={(e) => createForm.setData('reason', e.target.value)}
                                        rows={3}
                                        required
                                        maxLength={1000}
                                        placeholder="Why is this adjustment needed?"
                                    />
                                    {createForm.errors.reason && (
                                        <p className="mt-1 text-sm text-red-600">{createForm.errors.reason}</p>
                                    )}
                                    <p className="mt-1 text-xs text-gray-500">
                                        {createForm.data.reason.length}/1000 characters
                                    </p>
                                </div>
                            </>
                        )}

                        <div className="flex gap-3 pt-4">
                            <button
                                type="button"
                                onClick={() => { createForm.reset(); setShowCreateModal(false); }}
                                className="flex-1 btn btn-secondary"
                            >
                                Cancel
                            </button>
                            <button
                                type="submit"
                                disabled={createForm.processing || !createForm.data.field_name}
                                className="flex-1 btn btn-primary"
                            >
                                {createForm.processing ? 'Creating...' : 'Create Adjustment'}
                            </button>
                        </div>
                    </form>
                </Modal>
            )}

            {/* Edit Modal */}
            {editingAdjustment && (
                <Modal onClose={() => { editForm.reset(); setEditingAdjustment(null); }}>
                    <h3 className="text-lg font-medium text-gray-900 mb-4">Edit Adjustment</h3>
                    <form onSubmit={handleEdit} className="space-y-4">
                        <div>
                            <label className="block text-sm font-medium text-gray-700">Field</label>
                            <input
                                type="text"
                                className="mt-1 input bg-gray-50"
                                value={getFieldLabel(editingAdjustment.field_name)}
                                disabled
                            />
                        </div>

                        <div className="grid grid-cols-2 gap-4">
                            <div>
                                <label className="block text-sm font-medium text-gray-700">Original Value</label>
                                <input
                                    type="text"
                                    className="mt-1 input bg-gray-50"
                                    value={formatValue(editingAdjustment.original_value, editingAdjustment.field_name)}
                                    disabled
                                />
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-700">Adjusted Value</label>
                                <input
                                    type="number"
                                    step={adjustableFields[editingAdjustment.field_name]?.type === 'decimal' ? '0.01' : '1'}
                                    className={`mt-1 input ${editForm.errors.adjusted_value ? 'border-red-300' : ''}`}
                                    value={editForm.data.adjusted_value}
                                    onChange={(e) => editForm.setData('adjusted_value', e.target.value)}
                                    required
                                    min="0"
                                />
                                {editForm.errors.adjusted_value && (
                                    <p className="mt-1 text-sm text-red-600">{editForm.errors.adjusted_value}</p>
                                )}
                            </div>
                        </div>

                        <div>
                            <label className="block text-sm font-medium text-gray-700">
                                Effective To <span className="text-gray-400">(leave blank for permanent)</span>
                            </label>
                            <input
                                type="date"
                                className={`mt-1 input ${editForm.errors.effective_to ? 'border-red-300' : ''}`}
                                value={editForm.data.effective_to}
                                onChange={(e) => editForm.setData('effective_to', e.target.value)}
                                min={editingAdjustment.effective_from?.split('T')[0]}
                            />
                            {editForm.errors.effective_to && (
                                <p className="mt-1 text-sm text-red-600">{editForm.errors.effective_to}</p>
                            )}
                        </div>

                        <div>
                            <label className="block text-sm font-medium text-gray-700">Reason</label>
                            <textarea
                                className={`mt-1 input ${editForm.errors.reason ? 'border-red-300' : ''}`}
                                value={editForm.data.reason}
                                onChange={(e) => editForm.setData('reason', e.target.value)}
                                rows={3}
                                required
                                maxLength={1000}
                            />
                            {editForm.errors.reason && (
                                <p className="mt-1 text-sm text-red-600">{editForm.errors.reason}</p>
                            )}
                        </div>

                        <div className="flex gap-3 pt-4">
                            <button
                                type="button"
                                onClick={() => { editForm.reset(); setEditingAdjustment(null); }}
                                className="flex-1 btn btn-secondary"
                            >
                                Cancel
                            </button>
                            <button
                                type="submit"
                                disabled={editForm.processing}
                                className="flex-1 btn btn-primary"
                            >
                                {editForm.processing ? 'Saving...' : 'Save Changes'}
                            </button>
                        </div>
                    </form>
                </Modal>
            )}

            {/* End Confirmation Modal */}
            {endingAdjustment && (
                <Modal onClose={() => setEndingAdjustment(null)}>
                    <div className="text-center">
                        <ExclamationTriangleIcon className="w-12 h-12 text-orange-500 mx-auto mb-4" />
                        <h3 className="text-lg font-medium text-gray-900 mb-2">End Adjustment?</h3>
                        <p className="text-sm text-gray-500 mb-6">
                            This will set the end date of this adjustment to today. The adjustment will no longer
                            affect calculations after today.
                        </p>
                        <div className="flex gap-3">
                            <button
                                type="button"
                                onClick={() => setEndingAdjustment(null)}
                                className="flex-1 btn btn-secondary"
                            >
                                Cancel
                            </button>
                            <button
                                type="button"
                                onClick={() => handleEnd(endingAdjustment)}
                                className="flex-1 btn btn-warning"
                            >
                                End Adjustment
                            </button>
                        </div>
                    </div>
                </Modal>
            )}

            {/* Delete Confirmation Modal */}
            {deletingAdjustment && (
                <Modal onClose={() => setDeletingAdjustment(null)}>
                    <div className="text-center">
                        <ExclamationTriangleIcon className="w-12 h-12 text-red-500 mx-auto mb-4" />
                        <h3 className="text-lg font-medium text-gray-900 mb-2">Delete Adjustment?</h3>
                        <p className="text-sm text-gray-500 mb-6">
                            This will permanently delete this adjustment. This action cannot be undone.
                        </p>
                        <div className="flex gap-3">
                            <button
                                type="button"
                                onClick={() => setDeletingAdjustment(null)}
                                className="flex-1 btn btn-secondary"
                            >
                                Cancel
                            </button>
                            <button
                                type="button"
                                onClick={() => handleDelete(deletingAdjustment)}
                                className="flex-1 btn btn-danger"
                            >
                                Delete Adjustment
                            </button>
                        </div>
                    </div>
                </Modal>
            )}
        </div>
    );
}

// Simple Modal component
function Modal({ children, onClose }) {
    // Handle Escape key to close modal
    useEffect(() => {
        const handleKeyDown = (e) => {
            if (e.key === 'Escape') {
                onClose();
            }
        };
        document.addEventListener('keydown', handleKeyDown);
        return () => document.removeEventListener('keydown', handleKeyDown);
    }, [onClose]);

    return (
        <div
            className="fixed inset-0 z-50 overflow-y-auto"
            aria-labelledby="modal-title"
            role="dialog"
            aria-modal="true"
        >
            <div className="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <div
                    className="fixed inset-0 bg-gray-500/75 transition-opacity"
                    aria-hidden="true"
                    onClick={onClose}
                />
                <span className="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
                <div className="relative inline-block align-bottom bg-white rounded-lg px-4 pt-5 pb-4 text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full sm:p-6">
                    <button
                        type="button"
                        onClick={onClose}
                        className="absolute top-4 right-4 text-gray-400 hover:text-gray-500"
                    >
                        <XMarkIcon className="w-5 h-5" />
                    </button>
                    {children}
                </div>
            </div>
        </div>
    );
}
