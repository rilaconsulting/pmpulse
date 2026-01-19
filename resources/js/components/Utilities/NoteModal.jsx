import { useState, useEffect } from 'react';
import { XMarkIcon, TrashIcon } from '@heroicons/react/24/outline';
import axios from 'axios';

export default function NoteModal({
    isOpen,
    onClose,
    propertyId,
    propertyName,
    utilityType, // Now expects full utility type object { id, key, label, icon, color_scheme }
    existingNote,
    onSave,
    onDelete,
}) {
    const [note, setNote] = useState('');
    const [isSaving, setIsSaving] = useState(false);
    const [isDeleting, setIsDeleting] = useState(false);
    const [error, setError] = useState(null);

    // Sync note text when modal opens or existingNote changes
    useEffect(() => {
        if (isOpen) {
            setNote(existingNote?.note || '');
            setError(null);
        }
    }, [isOpen, existingNote]);

    // Handle Escape key to close modal
    useEffect(() => {
        if (!isOpen) return;

        const handleKeyDown = (e) => {
            if (e.key === 'Escape') {
                if (isSaving || isDeleting) return;
                onClose();
            }
        };
        document.addEventListener('keydown', handleKeyDown);
        return () => document.removeEventListener('keydown', handleKeyDown);
    }, [isOpen, onClose, isSaving, isDeleting]);

    const handleSave = async () => {
        if (!note.trim()) {
            setError('Note cannot be empty.');
            return;
        }

        if (!utilityType?.id) {
            setError('Utility type is required.');
            return;
        }

        setIsSaving(true);
        setError(null);

        try {
            const response = await axios.post(route('utilities.notes.store', propertyId), {
                utility_type_id: utilityType.id,
                note: note.trim(),
            });

            onSave?.(response.data.note);
            onClose();
        } catch (err) {
            setError(err.response?.data?.message || 'Failed to save note.');
        } finally {
            setIsSaving(false);
        }
    };

    const handleDelete = async () => {
        if (!existingNote) return;

        setIsDeleting(true);
        setError(null);

        try {
            await axios.delete(route('utilities.notes.destroy', [propertyId, utilityType?.key]));
            onDelete?.();
            onClose();
        } catch (err) {
            setError(err.response?.data?.message || 'Failed to delete note.');
        } finally {
            setIsDeleting(false);
        }
    };

    const formatDate = (dateString) => {
        if (!dateString) return '';
        const date = new Date(dateString);
        return date.toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        });
    };

    if (!isOpen) return null;

    const isProcessing = isSaving || isDeleting;
    const charCount = note.length;
    const maxChars = 2000;

    return (
        <div
            className="fixed inset-0 z-50 overflow-y-auto"
            aria-labelledby="note-modal-title"
            role="dialog"
            aria-modal="true"
        >
            <div className="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                {/* Backdrop */}
                <div
                    className="fixed inset-0 bg-gray-500/75 transition-opacity"
                    aria-hidden="true"
                    onClick={() => !isProcessing && onClose()}
                />

                {/* Center modal */}
                <span className="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">
                    &#8203;
                </span>

                {/* Modal content */}
                <div className="relative inline-block align-bottom bg-white rounded-lg px-4 pt-5 pb-4 text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full sm:p-6">
                    {/* Close button */}
                    <button
                        type="button"
                        onClick={onClose}
                        disabled={isProcessing}
                        className="absolute top-4 right-4 text-gray-400 hover:text-gray-500 disabled:opacity-50"
                    >
                        <XMarkIcon className="w-5 h-5" />
                    </button>

                    {/* Header */}
                    <div className="mb-4">
                        <h3 id="note-modal-title" className="text-lg font-medium text-gray-900">
                            {existingNote ? 'Edit Note' : 'Add Note'}
                        </h3>
                        <p className="mt-1 text-sm text-gray-500">
                            {propertyName} - {utilityType?.label || 'Unknown'}
                        </p>
                    </div>

                    {/* Existing note metadata */}
                    {existingNote && (
                        <div className="mb-4 p-3 bg-gray-50 rounded-lg text-sm text-gray-600">
                            <p>
                                Last updated by <span className="font-medium">{existingNote.created_by}</span>
                            </p>
                            <p className="text-gray-500">{formatDate(existingNote.updated_at)}</p>
                        </div>
                    )}

                    {/* Error message */}
                    {error && (
                        <div className="mb-4 p-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-600">
                            {error}
                        </div>
                    )}

                    {/* Textarea */}
                    <div className="mb-4">
                        <textarea
                            value={note}
                            onChange={(e) => setNote(e.target.value)}
                            placeholder="Enter your note..."
                            rows={5}
                            maxLength={maxChars}
                            disabled={isProcessing}
                            className="w-full px-3 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500 disabled:bg-gray-100 disabled:cursor-not-allowed resize-none"
                        />
                        <div className="mt-1 flex justify-end">
                            <span className={`text-xs ${charCount > maxChars * 0.9 ? 'text-orange-500' : 'text-gray-400'}`}>
                                {charCount}/{maxChars}
                            </span>
                        </div>
                    </div>

                    {/* Action buttons */}
                    <div className="flex items-center justify-between">
                        {/* Delete button (only for existing notes) */}
                        <div>
                            {existingNote && (
                                <button
                                    type="button"
                                    onClick={handleDelete}
                                    disabled={isProcessing}
                                    className="inline-flex items-center px-3 py-2 text-sm font-medium text-red-600 hover:text-red-700 hover:bg-red-50 rounded-lg disabled:opacity-50 disabled:cursor-not-allowed"
                                >
                                    <TrashIcon className="w-4 h-4 mr-1" />
                                    {isDeleting ? 'Deleting...' : 'Delete'}
                                </button>
                            )}
                        </div>

                        {/* Cancel and Save buttons */}
                        <div className="flex gap-3">
                            <button
                                type="button"
                                onClick={onClose}
                                disabled={isProcessing}
                                className="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
                            >
                                Cancel
                            </button>
                            <button
                                type="button"
                                onClick={handleSave}
                                disabled={isProcessing || !note.trim()}
                                className="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed"
                            >
                                {isSaving ? 'Saving...' : 'Save Note'}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
}
