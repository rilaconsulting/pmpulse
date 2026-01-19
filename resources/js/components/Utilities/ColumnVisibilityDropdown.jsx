import { useState, useRef, useEffect } from 'react';
import { ViewColumnsIcon, ChevronDownIcon } from '@heroicons/react/24/outline';

export default function ColumnVisibilityDropdown({ columns, visibleColumns, onChange, onBatchChange }) {
    const [isOpen, setIsOpen] = useState(false);
    const dropdownRef = useRef(null);

    // Close dropdown when clicking outside
    useEffect(() => {
        const handleClickOutside = (event) => {
            if (dropdownRef.current && !dropdownRef.current.contains(event.target)) {
                setIsOpen(false);
            }
        };

        if (isOpen) {
            document.addEventListener('mousedown', handleClickOutside);
        }

        return () => {
            document.removeEventListener('mousedown', handleClickOutside);
        };
    }, [isOpen]);

    // Close on escape key
    useEffect(() => {
        const handleEscape = (event) => {
            if (event.key === 'Escape') {
                setIsOpen(false);
            }
        };

        if (isOpen) {
            document.addEventListener('keydown', handleEscape);
        }

        return () => {
            document.removeEventListener('keydown', handleEscape);
        };
    }, [isOpen]);

    const visibleCount = Object.values(visibleColumns).filter(Boolean).length;
    const totalCount = columns.length;

    return (
        <div className="relative" ref={dropdownRef}>
            <button
                type="button"
                onClick={() => setIsOpen(!isOpen)}
                className="btn-secondary text-sm flex items-center"
                aria-expanded={isOpen}
                aria-haspopup="listbox"
            >
                <ViewColumnsIcon className="w-4 h-4 mr-1.5" />
                Columns
                <span className="ml-1.5 text-xs text-gray-500">
                    ({visibleCount}/{totalCount})
                </span>
                <ChevronDownIcon className={`w-4 h-4 ml-1.5 transition-transform ${isOpen ? 'rotate-180' : ''}`} />
            </button>

            {isOpen && (
                <div className="absolute right-0 z-20 mt-2 w-56 origin-top-right rounded-lg bg-white shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none">
                    <div className="py-2 px-3 border-b border-gray-100">
                        <p className="text-xs font-medium text-gray-500 uppercase tracking-wide">
                            Toggle Columns
                        </p>
                    </div>
                    <div className="py-2 max-h-64 overflow-y-auto">
                        {columns.map((column) => {
                            const isChecked = visibleColumns[column.key] ?? true;
                            const isDisabled = column.alwaysVisible || false;

                            return (
                                <label
                                    key={column.key}
                                    className={`flex items-center px-3 py-2 text-sm cursor-pointer hover:bg-gray-50 ${
                                        isDisabled ? 'cursor-not-allowed opacity-60' : ''
                                    }`}
                                >
                                    <input
                                        type="checkbox"
                                        checked={isChecked}
                                        disabled={isDisabled}
                                        onChange={() => {
                                            if (!isDisabled) {
                                                onChange(column.key, !isChecked);
                                            }
                                        }}
                                        className="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500 disabled:opacity-50"
                                    />
                                    <span className="ml-2 text-gray-700">{column.label}</span>
                                    {isDisabled && (
                                        <span className="ml-auto text-xs text-gray-400">Required</span>
                                    )}
                                </label>
                            );
                        })}
                    </div>
                    <div className="py-2 px-3 border-t border-gray-100">
                        <button
                            type="button"
                            onClick={() => {
                                if (onBatchChange) {
                                    // Use batch update if available
                                    const updates = {};
                                    columns.forEach((column) => {
                                        updates[column.key] = true;
                                    });
                                    onBatchChange(updates);
                                } else {
                                    // Fallback to individual updates
                                    columns.forEach((column) => {
                                        if (!column.alwaysVisible) {
                                            onChange(column.key, true);
                                        }
                                    });
                                }
                            }}
                            className="text-xs text-blue-600 hover:text-blue-800"
                        >
                            Show all columns
                        </button>
                    </div>
                </div>
            )}
        </div>
    );
}
