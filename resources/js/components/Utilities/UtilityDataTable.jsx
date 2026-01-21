import { useState, useMemo, useCallback, useRef, useEffect } from 'react';
import { Link, router } from '@inertiajs/react';
import { ArrowDownTrayIcon, ChevronUpIcon, ChevronDownIcon, ChatBubbleLeftIcon, PlusIcon, XMarkIcon } from '@heroicons/react/24/outline';
import ColumnVisibilityDropdown from './ColumnVisibilityDropdown';
import NoteModal from './NoteModal';
import Tooltip from '../Tooltip';
import { findUtilityType, getIconComponent, getColorScheme, formatCurrency, getHeatMapStyle, calculateHeatMapStats } from './constants';

// Column definitions
const COLUMNS = [
    { key: 'property_name', label: 'Property', alwaysVisible: true, sortable: true, align: 'left' },
    { key: 'property_type', label: 'Type', sortable: true, align: 'left' },
    { key: 'unit_count', label: 'Units', sortable: true, align: 'right', format: 'number' },
    { key: 'total_sqft', label: 'Sq Ft', sortable: true, align: 'right', format: 'number' },
    { key: 'current_month', label: 'Current Mo', sortable: true, align: 'right', format: 'currency' },
    { key: 'prev_month', label: 'Prev Mo', sortable: true, align: 'right', format: 'currency' },
    { key: 'prev_3_months', label: '3 Mo Avg', sortable: true, align: 'right', format: 'currency' },
    { key: 'prev_12_months', label: '12 Mo Avg', sortable: true, align: 'right', format: 'currency' },
    { key: 'avg_per_unit', label: '$/Unit', sortable: true, align: 'right', format: 'currency_decimal' },
    { key: 'avg_per_sqft', label: '$/Sq Ft', sortable: true, align: 'right', format: 'currency_sqft' },
    { key: 'note', label: 'Notes', sortable: false, align: 'left' },
];

export default function UtilityDataTable({ data, utilityTypes = {}, selectedType, filters = {}, propertyTypeOptions = {} }) {
    const [sortField, setSortField] = useState('property_name');
    const [sortDirection, setSortDirection] = useState('asc');
    const [visibleColumns, setVisibleColumns] = useState(() => {
        // Initialize all columns as visible
        const initial = {};
        COLUMNS.forEach((col) => {
            initial[col.key] = true;
        });
        return initial;
    });

    // Note modal state
    const [noteModalOpen, setNoteModalOpen] = useState(false);
    const [selectedProperty, setSelectedProperty] = useState(null);
    // Local notes state to track updates without page reload
    const [localNotes, setLocalNotes] = useState({});

    // Filter state
    const [unitCountMin, setUnitCountMin] = useState(filters?.unit_count_min ?? '');
    const [unitCountMax, setUnitCountMax] = useState(filters?.unit_count_max ?? '');
    const [selectedPropertyTypes, setSelectedPropertyTypes] = useState(filters?.property_types ?? []);
    const [propertyTypeDropdownOpen, setPropertyTypeDropdownOpen] = useState(false);
    const propertyTypeRef = useRef(null);

    // Sync filter state when filters prop changes
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

    const selectedUtilityType = findUtilityType(utilityTypes, selectedType);
    const Icon = getIconComponent(selectedUtilityType?.icon);
    const colors = getColorScheme(selectedUtilityType?.color_scheme);

    // Filter visible columns
    const activeColumns = useMemo(
        () => COLUMNS.filter((col) => visibleColumns[col.key]),
        [visibleColumns]
    );

    // Calculate heat map stats for $/Unit and $/Sq Ft columns
    const heatMapStats = useMemo(() => {
        if (!data?.properties) return { avg_per_unit: null, avg_per_sqft: null };

        const unitValues = data.properties
            .map((p) => p.avg_per_unit)
            .filter((v) => v !== null && v !== undefined);
        const sqftValues = data.properties
            .map((p) => p.avg_per_sqft)
            .filter((v) => v !== null && v !== undefined);

        return {
            avg_per_unit: calculateHeatMapStats(unitValues),
            avg_per_sqft: calculateHeatMapStats(sqftValues),
        };
    }, [data?.properties]);

    // Calculate true averages (total spend / total units, not average of averages)
    const trueAverages = useMemo(() => {
        if (!data?.properties) return { avg_per_unit: null, avg_per_sqft: null };

        let totalSpend = 0;
        let totalUnits = 0;
        let totalSqft = 0;

        data.properties.forEach((p) => {
            // Use 12-month average spend as the representative spend value
            const spend = p.prev_12_months || 0;
            const units = p.unit_count || 0;
            const sqft = p.total_sqft || 0;

            totalSpend += spend;
            totalUnits += units;
            totalSqft += sqft;
        });

        return {
            avg_per_unit: totalUnits > 0 ? totalSpend / totalUnits : null,
            avg_per_sqft: totalSqft > 0 ? totalSpend / totalSqft : null,
        };
    }, [data?.properties]);

    // Sort properties
    const sortedProperties = useMemo(() => {
        if (!data?.properties) return [];

        return [...data.properties].sort((a, b) => {
            let aVal, bVal;

            if (sortField === 'property_name') {
                aVal = (a.property_name ?? '').toLowerCase();
                bVal = (b.property_name ?? '').toLowerCase();
            } else if (sortField === 'property_type') {
                aVal = (a.property_type ?? '').toLowerCase();
                bVal = (b.property_type ?? '').toLowerCase();
            } else {
                aVal = a[sortField] ?? -Infinity;
                bVal = b[sortField] ?? -Infinity;
            }

            if (aVal < bVal) return sortDirection === 'asc' ? -1 : 1;
            if (aVal > bVal) return sortDirection === 'asc' ? 1 : -1;
            return 0;
        });
    }, [data?.properties, sortField, sortDirection]);

    const handleSort = (field) => {
        if (sortField === field) {
            setSortDirection((prev) => (prev === 'asc' ? 'desc' : 'asc'));
        } else {
            setSortField(field);
            setSortDirection('asc');
        }
    };

    const handleColumnVisibilityChange = (columnKey, isVisible) => {
        setVisibleColumns((prev) => ({
            ...prev,
            [columnKey]: isVisible,
        }));
    };

    const handleBatchColumnVisibilityChange = (updates) => {
        setVisibleColumns((prev) => ({
            ...prev,
            ...updates,
        }));
    };

    // Note modal handlers (memoized to prevent unnecessary re-renders)
    const openNoteModal = useCallback((property) => {
        setSelectedProperty(property);
        setNoteModalOpen(true);
    }, []);

    const closeNoteModal = useCallback(() => {
        setNoteModalOpen(false);
        setSelectedProperty(null);
    }, []);

    const handleNoteSave = useCallback((savedNote) => {
        setSelectedProperty((currentProperty) => {
            if (currentProperty) {
                setLocalNotes((prev) => ({
                    ...prev,
                    [currentProperty.property_id]: savedNote,
                }));
            }
            return currentProperty;
        });
    }, []);

    const handleNoteDelete = useCallback(() => {
        setSelectedProperty((currentProperty) => {
            if (currentProperty) {
                setLocalNotes((prev) => ({
                    ...prev,
                    [currentProperty.property_id]: null,
                }));
            }
            return currentProperty;
        });
    }, []);

    // Get the effective note for a property (local updates take precedence)
    const getPropertyNote = (property) => {
        if (localNotes.hasOwnProperty(property.property_id)) {
            return localNotes[property.property_id];
        }
        return property.note;
    };

    // Truncate note text for display
    const truncateNote = (text, maxLength = 30) => {
        if (!text || text.length <= maxLength) return text;
        return text.substring(0, maxLength) + '...';
    };

    // Filter handlers
    const applyFilters = () => {
        const newFilters = {
            utility_type: selectedType,
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

        router.get(route('utilities.data'), { utility_type: selectedType }, { preserveState: true });
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

    const formatValue = (value, format) => {
        if (value === null || value === undefined) return '-';

        switch (format) {
            case 'currency':
                return formatCurrency(value);
            case 'currency_decimal':
                return new Intl.NumberFormat('en-US', {
                    style: 'currency',
                    currency: 'USD',
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2,
                }).format(value);
            case 'currency_sqft':
                return `$${value.toFixed(4)}`;
            case 'number':
                return new Intl.NumberFormat('en-US').format(value);
            default:
                return value;
        }
    };

    const escapeCsvCell = (value) => {
        if (value === null || value === undefined) return '';
        const str = String(value);
        if (str.includes(',') || str.includes('"') || str.includes('\n')) {
            return `"${str.replace(/"/g, '""')}"`;
        }
        return str;
    };

    const exportToCsv = () => {
        const headers = activeColumns.map((col) => col.label);
        const rows = sortedProperties.map((p) =>
            activeColumns.map((col) => {
                const value = p[col.key];
                return value ?? '';
            })
        );

        const csvContent = [
            headers.map(escapeCsvCell).join(','),
            ...rows.map((row) => row.map(escapeCsvCell).join(',')),
        ].join('\n');

        const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;

        const safeSelectedType = String(selectedType ?? 'unknown')
            .toLowerCase()
            .replace(/[^a-z0-9-_]+/g, '-')
            .replace(/^-+|-+$/g, '') || 'unknown';
        link.download = `utility-data-${safeSelectedType}-${new Date().toISOString().split('T')[0]}.csv`;

        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        URL.revokeObjectURL(url);
    };

    if (!data || !data.properties || data.properties.length === 0) {
        return (
            <div className="card flex-1 flex flex-col">
                <div className="card-body border-b border-gray-200 py-3">
                    <div className="flex items-center space-x-3">
                        <div className={`p-2 rounded-lg ${colors.bg} ${colors.text}`}>
                            <Icon className="w-5 h-5" />
                        </div>
                        <h3 className="text-base font-medium text-gray-900">
                            {selectedUtilityType?.label || selectedType} Data
                        </h3>
                    </div>
                </div>
                <div className="flex-1 flex items-center justify-center">
                    <div className="py-12 text-center text-gray-500">
                        No property data available for the selected filters
                    </div>
                </div>
            </div>
        );
    }

    return (
        <div className="card overflow-visible flex-1 flex flex-col min-h-0">
            {/* Compact single-row header with filters */}
            <div className="flex-shrink-0 card-body border-b border-gray-200 py-3">
                <div className="flex flex-wrap items-center gap-4">
                    {/* Title Section */}
                    <div className="flex items-center space-x-3">
                        <div className={`p-2 rounded-lg ${colors.bg} ${colors.text}`}>
                            <Icon className="w-5 h-5" />
                        </div>
                        <div>
                            <h3 className="text-base font-medium text-gray-900">
                                {selectedUtilityType?.label || selectedType} Data
                            </h3>
                            <p className="text-xs text-gray-500">
                                {data.property_count} properties
                            </p>
                        </div>
                    </div>

                    {/* Divider */}
                    <div className="h-8 w-px bg-gray-200" />

                    {/* Utility Type Selector */}
                    <div className="flex items-center space-x-2">
                        <label className="text-sm text-gray-500">Utility:</label>
                        <div className="relative">
                            <select
                                value={selectedType}
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
                            className="input py-1.5 w-16 text-sm"
                        />
                        <span className="text-gray-400">-</span>
                        <input
                            type="number"
                            min="0"
                            placeholder="Max"
                            value={unitCountMax}
                            onChange={(e) => setUnitCountMax(e.target.value)}
                            className="input py-1.5 w-16 text-sm"
                        />
                    </div>

                    {/* Property Type Multiselect */}
                    <div className="relative" ref={propertyTypeRef}>
                        <button
                            type="button"
                            onClick={() => setPropertyTypeDropdownOpen(!propertyTypeDropdownOpen)}
                            className="input py-1.5 pr-8 text-sm text-left min-w-[140px] flex items-center justify-between"
                            aria-expanded={propertyTypeDropdownOpen}
                            aria-haspopup="listbox"
                        >
                            <span className={selectedPropertyTypes.length > 0 ? 'text-gray-900' : 'text-gray-500'}>
                                {selectedPropertyTypes.length > 0
                                    ? `${selectedPropertyTypes.length} type${selectedPropertyTypes.length > 1 ? 's' : ''}`
                                    : 'Property types'
                                }
                            </span>
                            <ChevronDownIcon className={`w-4 h-4 text-gray-400 transition-transform ${propertyTypeDropdownOpen ? 'rotate-180' : ''}`} />
                        </button>

                        {propertyTypeDropdownOpen && (
                            <div className="absolute z-40 mt-1 w-64 origin-top-left rounded-lg bg-white shadow-lg ring-1 ring-black ring-opacity-5">
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

                    {/* Apply/Clear Filters */}
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

                    {/* Spacer */}
                    <div className="flex-1" />

                    {/* Column Visibility & Export */}
                    <ColumnVisibilityDropdown
                        columns={COLUMNS}
                        visibleColumns={visibleColumns}
                        onChange={handleColumnVisibilityChange}
                        onBatchChange={handleBatchColumnVisibilityChange}
                    />
                    <button onClick={exportToCsv} className="btn-secondary text-sm py-1.5">
                        <ArrowDownTrayIcon className="w-4 h-4 mr-1" />
                        Export CSV
                    </button>
                </div>
            </div>

            {/* Table with scrollable body */}
            <div className="flex-1 overflow-auto min-h-0">
                <table className="min-w-full divide-y divide-gray-200">
                    <thead className="bg-gray-50 sticky top-0 z-20">
                        <tr>
                            {activeColumns.map((column, index) => (
                                <th
                                    key={column.key}
                                    onClick={() => column.sortable && handleSort(column.key)}
                                    className={`px-4 py-3 text-xs font-medium text-gray-500 uppercase tracking-wider ${
                                        column.align === 'right' ? 'text-right' : 'text-left'
                                    } ${column.sortable ? 'cursor-pointer hover:bg-gray-100 select-none' : ''} ${
                                        index === 0 ? 'sticky left-0 z-30 bg-gray-50 shadow-[2px_0_5px_-2px_rgba(0,0,0,0.1)]' : ''
                                    }`}
                                >
                                    <div className={`flex items-center ${column.align === 'right' ? 'justify-end' : ''}`}>
                                        <span>{column.label}</span>
                                        {column.sortable && sortField === column.key && (
                                            <span className="ml-1">
                                                {sortDirection === 'asc' ? (
                                                    <ChevronUpIcon className="w-3 h-3" />
                                                ) : (
                                                    <ChevronDownIcon className="w-3 h-3" />
                                                )}
                                            </span>
                                        )}
                                    </div>
                                </th>
                            ))}
                        </tr>
                        {/* Summary row with true averages */}
                        <tr className="bg-blue-50 border-b border-blue-200">
                            {activeColumns.map((column, index) => (
                                <td
                                    key={column.key}
                                    className={`px-4 py-2 text-xs font-medium ${
                                        column.align === 'right' ? 'text-right' : 'text-left'
                                    } ${index === 0 ? 'sticky left-0 z-30 bg-blue-50 text-blue-800 shadow-[2px_0_5px_-2px_rgba(0,0,0,0.1)]' : 'text-blue-700'}`}
                                >
                                    {index === 0 ? (
                                        'Portfolio Average'
                                    ) : column.key === 'avg_per_unit' && trueAverages.avg_per_unit !== null ? (
                                        formatValue(trueAverages.avg_per_unit, 'currency_decimal')
                                    ) : column.key === 'avg_per_sqft' && trueAverages.avg_per_sqft !== null ? (
                                        formatValue(trueAverages.avg_per_sqft, 'currency_sqft')
                                    ) : (
                                        ''
                                    )}
                                </td>
                            ))}
                        </tr>
                    </thead>
                    <tbody className="bg-white divide-y divide-gray-200">
                        {sortedProperties.map((property) => (
                            <tr key={property.property_id} className="hover:bg-gray-50 group">
                                {activeColumns.map((column, index) => {
                                    const value = property[column.key];

                                    // Get conditional formatting from backend (for current_month, prev_month, prev_3_months)
                                    const backendFormatting = property.formatting?.[column.key];

                                    // Get heat map styling for $/Unit and $/Sq Ft columns
                                    let cellStyle = {};
                                    let hasFormatting = false;

                                    if (backendFormatting) {
                                        // Apply backend-provided conditional formatting
                                        cellStyle = {
                                            color: backendFormatting.color,
                                            backgroundColor: backendFormatting.background_color,
                                        };
                                        hasFormatting = true;
                                    } else if (['avg_per_unit', 'avg_per_sqft'].includes(column.key)) {
                                        // Apply heat map coloring for $/Unit and $/Sq Ft columns
                                        const stats = heatMapStats[column.key];
                                        if (stats?.average !== null && stats?.stdDev !== null) {
                                            cellStyle = getHeatMapStyle(value, stats.average, stats.stdDev);
                                            hasFormatting = Object.keys(cellStyle).length > 0;
                                        }
                                    }

                                    // Render cell content based on column type
                                    const renderCellContent = () => {
                                        if (column.key === 'property_name') {
                                            return (
                                                <Link
                                                    href={route('properties.show', property.property_id) + '?tab=utilities'}
                                                    className="font-medium text-blue-600 hover:text-blue-800"
                                                >
                                                    {value}
                                                </Link>
                                            );
                                        }

                                        if (column.key === 'property_type') {
                                            return <span className="text-gray-500">{value || '-'}</span>;
                                        }

                                        if (column.key === 'unit_count' && property.unit_count_adjusted) {
                                            return (
                                                <Tooltip content="Adjusted value (manual override)">
                                                    <span className="text-gray-900">
                                                        {formatValue(value, column.format)}
                                                        <span className="text-blue-500 ml-0.5">*</span>
                                                    </span>
                                                </Tooltip>
                                            );
                                        }

                                        if (column.key === 'total_sqft' && property.sqft_adjusted) {
                                            return (
                                                <Tooltip content="Adjusted value (manual override)">
                                                    <span className="text-gray-900">
                                                        {formatValue(value, column.format)}
                                                        <span className="text-blue-500 ml-0.5">*</span>
                                                    </span>
                                                </Tooltip>
                                            );
                                        }

                                        if (column.key === 'note') {
                                            const note = getPropertyNote(property);
                                            return (
                                                <button
                                                    type="button"
                                                    onClick={() => openNoteModal(property)}
                                                    className={`inline-flex items-center text-sm ${
                                                        note
                                                            ? 'text-gray-700 hover:text-gray-900'
                                                            : 'text-gray-400 hover:text-gray-600'
                                                    }`}
                                                >
                                                    {note ? (
                                                        <>
                                                            <ChatBubbleLeftIcon className="w-4 h-4 mr-1 flex-shrink-0" />
                                                            <span className="truncate max-w-[150px]">
                                                                {truncateNote(note.note)}
                                                            </span>
                                                        </>
                                                    ) : (
                                                        <>
                                                            <PlusIcon className="w-4 h-4 mr-1" />
                                                            <span>Add note</span>
                                                        </>
                                                    )}
                                                </button>
                                            );
                                        }

                                        // Wrap formatted cells with tooltip
                                        if (backendFormatting) {
                                            return (
                                                <Tooltip
                                                    content={
                                                        <div>
                                                            <div className="font-medium">{backendFormatting.rule_name}</div>
                                                            <div className="text-gray-400 mt-1">
                                                                {backendFormatting.operator === 'increase_percent' ? '≥' : '≤'} {backendFormatting.threshold}% {backendFormatting.operator === 'increase_percent' ? 'above' : 'below'} 12-mo avg
                                                            </div>
                                                        </div>
                                                    }
                                                >
                                                    <span>{formatValue(value, column.format)}</span>
                                                </Tooltip>
                                            );
                                        }

                                        return (
                                            <span className={hasFormatting ? '' : 'text-gray-900'}>
                                                {formatValue(value, column.format)}
                                            </span>
                                        );
                                    };

                                    return (
                                        <td
                                            key={column.key}
                                            className={`px-4 py-3 whitespace-nowrap text-sm ${
                                                column.align === 'right' ? 'text-right' : 'text-left'
                                            } ${index === 0 ? 'sticky left-0 z-10 bg-white group-hover:bg-gray-50 shadow-[2px_0_5px_-2px_rgba(0,0,0,0.1)]' : ''}`}
                                            style={index === 0 ? {} : cellStyle}
                                        >
                                            {renderCellContent()}
                                        </td>
                                    );
                                })}
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            {/* Note Modal */}
            <NoteModal
                isOpen={noteModalOpen}
                onClose={closeNoteModal}
                propertyId={selectedProperty?.property_id}
                propertyName={selectedProperty?.property_name}
                utilityType={selectedUtilityType}
                existingNote={selectedProperty ? getPropertyNote(selectedProperty) : null}
                onSave={handleNoteSave}
                onDelete={handleNoteDelete}
            />
        </div>
    );
}
