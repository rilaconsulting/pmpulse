import { useState, useRef, useEffect } from 'react';
import { router } from '@inertiajs/react';
import { FunnelIcon, XMarkIcon, ChevronDownIcon } from '@heroicons/react/24/outline';
import { findUtilityType, getIconComponent, getColorScheme } from './constants';

export default function UtilityFiltersBar({
    filters = {},
    utilityTypes = {},
    selectedUtilityType,
    propertyTypeOptions = {},
}) {
    const [unitCountMin, setUnitCountMin] = useState(filters?.unit_count_min ?? '');
    const [unitCountMax, setUnitCountMax] = useState(filters?.unit_count_max ?? '');
    const [selectedPropertyTypes, setSelectedPropertyTypes] = useState(filters?.property_types ?? []);
    const [propertyTypeDropdownOpen, setPropertyTypeDropdownOpen] = useState(false);
    const propertyTypeRef = useRef(null);

    // Sync local state when filters prop changes
    useEffect(() => {
        setUnitCountMin(filters?.unit_count_min ?? '');
        setUnitCountMax(filters?.unit_count_max ?? '');
        setSelectedPropertyTypes(filters?.property_types ?? []);
    }, [filters]);

    // Close property type dropdown when clicking outside
    useEffect(() => {
        const handleClickOutside = (event) => {
            if (propertyTypeRef.current && !propertyTypeRef.current.contains(event.target)) {
                setPropertyTypeDropdownOpen(false);
            }
        };

        if (propertyTypeDropdownOpen) {
            document.addEventListener('mousedown', handleClickOutside);
        }

        return () => {
            document.removeEventListener('mousedown', handleClickOutside);
        };
    }, [propertyTypeDropdownOpen]);

    // Close property type dropdown on Escape key
    useEffect(() => {
        const handleEscape = (event) => {
            if (event.key === 'Escape') {
                setPropertyTypeDropdownOpen(false);
            }
        };

        if (propertyTypeDropdownOpen) {
            document.addEventListener('keydown', handleEscape);
        }

        return () => {
            document.removeEventListener('keydown', handleEscape);
        };
    }, [propertyTypeDropdownOpen]);

    // Calculate active filter count
    const activeFilterCount = [
        filters?.unit_count_min != null,
        filters?.unit_count_max != null,
        (filters?.property_types?.length ?? 0) > 0,
    ].filter(Boolean).length;

    const applyFilters = () => {
        const newFilters = {
            utility_type: selectedUtilityType,
        };

        const parsedMin = Number(unitCountMin);
        if (unitCountMin !== '' && unitCountMin != null && !Number.isNaN(parsedMin)) {
            newFilters.unit_count_min = parsedMin;
        }
        const parsedMax = Number(unitCountMax);
        if (unitCountMax !== '' && unitCountMax != null && !Number.isNaN(parsedMax)) {
            newFilters.unit_count_max = parsedMax;
        }
        if (selectedPropertyTypes.length > 0) {
            newFilters.property_types = selectedPropertyTypes;
        }

        router.get(route('utilities.data'), newFilters, { preserveState: true });
    };

    const clearFilters = () => {
        setUnitCountMin('');
        setUnitCountMax('');
        setSelectedPropertyTypes([]);

        router.get(route('utilities.data'), { utility_type: selectedUtilityType }, { preserveState: true });
    };

    const handleUtilityTypeChange = (newType) => {
        const newFilters = {
            utility_type: newType,
        };

        const parsedMin = Number(unitCountMin);
        if (unitCountMin !== '' && unitCountMin != null && !Number.isNaN(parsedMin)) {
            newFilters.unit_count_min = parsedMin;
        }
        const parsedMax = Number(unitCountMax);
        if (unitCountMax !== '' && unitCountMax != null && !Number.isNaN(parsedMax)) {
            newFilters.unit_count_max = parsedMax;
        }
        if (selectedPropertyTypes.length > 0) {
            newFilters.property_types = selectedPropertyTypes;
        }

        router.get(route('utilities.data'), newFilters, { preserveState: true });
    };

    const togglePropertyType = (type) => {
        setSelectedPropertyTypes((prev) => {
            if (prev.includes(type)) {
                return prev.filter((t) => t !== type);
            }
            return [...prev, type];
        });
    };

    const selectedType = findUtilityType(utilityTypes, selectedUtilityType);
    const Icon = getIconComponent(selectedType?.icon);
    const colors = getColorScheme(selectedType?.color_scheme);

    return (
        <div className="card overflow-visible">
            <div className="card-body overflow-visible">
                <div className="flex flex-wrap items-center gap-4">
                    {/* Filter Icon & Label */}
                    <div className="flex items-center text-gray-600">
                        <FunnelIcon className="w-5 h-5 mr-2" />
                        <span className="font-medium text-sm">Filters</span>
                        {activeFilterCount > 0 && (
                            <span className="ml-2 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                {activeFilterCount} active
                            </span>
                        )}
                    </div>

                    {/* Utility Type Selector */}
                    <div className="flex items-center space-x-2">
                        <label className="text-sm text-gray-500">Utility:</label>
                        <div className="relative">
                            <select
                                value={selectedUtilityType}
                                onChange={(e) => handleUtilityTypeChange(e.target.value)}
                                className="input py-1.5 pl-9 pr-8 text-sm"
                            >
                                {Array.isArray(utilityTypes) && utilityTypes.map((type) => (
                                    <option key={type.key} value={type.key}>{type.label}</option>
                                ))}
                            </select>
                            <div className={`absolute left-2.5 top-1/2 -translate-y-1/2 ${colors.text}`}>
                                <Icon className="w-4 h-4" />
                            </div>
                        </div>
                    </div>

                    {/* Unit Count Range */}
                    <div className="flex items-center space-x-2">
                        <label className="text-sm text-gray-500">Units:</label>
                        <input
                            type="number"
                            min="0"
                            placeholder="Min"
                            value={unitCountMin}
                            onChange={(e) => setUnitCountMin(e.target.value)}
                            className="input py-1.5 w-20 text-sm"
                        />
                        <span className="text-gray-400">-</span>
                        <input
                            type="number"
                            min="0"
                            placeholder="Max"
                            value={unitCountMax}
                            onChange={(e) => setUnitCountMax(e.target.value)}
                            className="input py-1.5 w-20 text-sm"
                        />
                    </div>

                    {/* Property Type Multiselect */}
                    <div className="relative" ref={propertyTypeRef}>
                        <button
                            type="button"
                            onClick={() => setPropertyTypeDropdownOpen(!propertyTypeDropdownOpen)}
                            className="input py-1.5 pr-8 text-sm text-left min-w-[160px] flex items-center justify-between"
                            aria-expanded={propertyTypeDropdownOpen}
                            aria-haspopup="listbox"
                        >
                            <span className={selectedPropertyTypes.length > 0 ? 'text-gray-900' : 'text-gray-500'}>
                                {selectedPropertyTypes.length > 0
                                    ? `${selectedPropertyTypes.length} type${selectedPropertyTypes.length > 1 ? 's' : ''} selected`
                                    : 'Property types'
                                }
                            </span>
                            <ChevronDownIcon className={`w-4 h-4 text-gray-400 transition-transform ${propertyTypeDropdownOpen ? 'rotate-180' : ''}`} />
                        </button>

                        {propertyTypeDropdownOpen && (
                            <div className="absolute z-20 mt-1 w-64 origin-top-left rounded-lg bg-white shadow-lg ring-1 ring-black ring-opacity-5">
                                <div className="p-2 max-h-60 overflow-y-auto">
                                    {Object.entries(propertyTypeOptions || {}).length === 0 ? (
                                        <div className="px-3 py-2 text-sm text-gray-500">
                                            No property types available
                                        </div>
                                    ) : (
                                        Object.entries(propertyTypeOptions).map(([type, count]) => (
                                            <label
                                                key={type}
                                                className="flex items-center px-3 py-2 text-sm cursor-pointer hover:bg-gray-50 rounded"
                                            >
                                                <input
                                                    type="checkbox"
                                                    checked={selectedPropertyTypes.includes(type)}
                                                    onChange={() => togglePropertyType(type)}
                                                    className="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                                />
                                                <span className="ml-2 text-gray-700">{type}</span>
                                                <span className="ml-auto text-xs text-gray-400">{count}</span>
                                            </label>
                                        ))
                                    )}
                                </div>
                                {selectedPropertyTypes.length > 0 && (
                                    <div className="py-2 px-3 border-t border-gray-100">
                                        <button
                                            type="button"
                                            onClick={() => setSelectedPropertyTypes([])}
                                            className="text-xs text-blue-600 hover:text-blue-800"
                                        >
                                            Clear selection
                                        </button>
                                    </div>
                                )}
                            </div>
                        )}
                    </div>

                    {/* Action Buttons */}
                    <div className="flex items-center space-x-2 ml-auto">
                        <button
                            type="button"
                            onClick={applyFilters}
                            className="btn-primary text-sm py-1.5"
                        >
                            Apply Filters
                        </button>
                        {activeFilterCount > 0 && (
                            <button
                                type="button"
                                onClick={clearFilters}
                                className="btn-secondary text-sm py-1.5 flex items-center"
                            >
                                <XMarkIcon className="w-4 h-4 mr-1" />
                                Clear
                            </button>
                        )}
                    </div>
                </div>
            </div>
        </div>
    );
}
