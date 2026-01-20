import { useState } from 'react';
import { Disclosure, Transition } from '@headlessui/react';
import {
    FunnelIcon,
    XMarkIcon,
    ChevronDownIcon,
} from '@heroicons/react/24/outline';

/**
 * FilterBar - A responsive filter bar that collapses on mobile.
 *
 * @param {Object} props
 * @param {Array<{name: string, component: React.ReactNode}>} props.filters - Filter inputs
 * @param {React.ReactNode} props.search - Search input component (always visible)
 * @param {Function} props.onClear - Handler for clearing all filters
 * @param {number} props.activeCount - Number of active filters
 * @param {React.ReactNode} props.actions - Additional action buttons (right side)
 * @param {string} props.className - Additional classes
 */
export default function FilterBar({
    filters = [],
    search,
    onClear,
    activeCount = 0,
    actions,
    className = '',
}) {
    return (
        <div className={`space-y-4 ${className}`}>
            {/* Desktop view */}
            <div className="hidden md:flex md:items-center md:gap-4">
                {/* Search */}
                {search && <div className="flex-1 max-w-xs">{search}</div>}

                {/* Filters */}
                {filters.map((filter, index) => (
                    <div key={filter.name || index} className="flex-shrink-0">
                        {filter.component}
                    </div>
                ))}

                {/* Clear button */}
                {activeCount > 0 && onClear && (
                    <button
                        type="button"
                        onClick={onClear}
                        className="text-sm text-gray-500 hover:text-gray-700"
                    >
                        Clear filters
                    </button>
                )}

                {/* Actions */}
                {actions && <div className="ml-auto flex items-center gap-2">{actions}</div>}
            </div>

            {/* Mobile view */}
            <div className="md:hidden space-y-3">
                {/* Search - always visible */}
                {search && <div>{search}</div>}

                {/* Collapsible filters */}
                {filters.length > 0 && (
                    <Disclosure>
                        {({ open }) => (
                            <>
                                <div className="flex items-center gap-2">
                                    <Disclosure.Button className="flex-1 flex items-center justify-between px-4 py-3 bg-white border border-gray-200 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50 active:bg-gray-100">
                                        <span className="flex items-center gap-2">
                                            <FunnelIcon className="w-5 h-5" />
                                            Filters
                                            {activeCount > 0 && (
                                                <span className="inline-flex items-center justify-center w-5 h-5 text-xs font-semibold bg-blue-100 text-blue-700 rounded-full">
                                                    {activeCount}
                                                </span>
                                            )}
                                        </span>
                                        <ChevronDownIcon
                                            className={`w-5 h-5 text-gray-400 transition-transform ${
                                                open ? 'rotate-180' : ''
                                            }`}
                                        />
                                    </Disclosure.Button>

                                    {/* Clear button - always visible when filters active */}
                                    {activeCount > 0 && onClear && (
                                        <button
                                            type="button"
                                            onClick={onClear}
                                            className="p-3 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-lg"
                                            aria-label="Clear filters"
                                        >
                                            <XMarkIcon className="w-5 h-5" />
                                        </button>
                                    )}
                                </div>

                                <Transition
                                    enter="transition duration-150 ease-out"
                                    enterFrom="transform -translate-y-2 opacity-0"
                                    enterTo="transform translate-y-0 opacity-100"
                                    leave="transition duration-100 ease-in"
                                    leaveFrom="transform translate-y-0 opacity-100"
                                    leaveTo="transform -translate-y-2 opacity-0"
                                >
                                    <Disclosure.Panel className="bg-white border border-gray-200 rounded-lg p-4 space-y-4">
                                        {filters.map((filter, index) => (
                                            <div key={filter.name || index}>
                                                {filter.label && (
                                                    <label className="block text-sm font-medium text-gray-700 mb-1">
                                                        {filter.label}
                                                    </label>
                                                )}
                                                {filter.component}
                                            </div>
                                        ))}
                                    </Disclosure.Panel>
                                </Transition>
                            </>
                        )}
                    </Disclosure>
                )}

                {/* Actions - full width on mobile */}
                {actions && (
                    <div className="flex items-center gap-2">
                        {actions}
                    </div>
                )}
            </div>
        </div>
    );
}

/**
 * Simple search input component for use with FilterBar
 */
export function SearchInput({ value, onChange, placeholder = 'Search...', className = '' }) {
    return (
        <input
            type="text"
            value={value}
            onChange={(e) => onChange(e.target.value)}
            placeholder={placeholder}
            className={`input ${className}`}
        />
    );
}
