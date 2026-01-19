import { useState, useMemo } from 'react';
import { Link } from '@inertiajs/react';
import { ArrowDownTrayIcon, ChevronUpIcon, ChevronDownIcon, ChatBubbleLeftIcon, PlusIcon } from '@heroicons/react/24/outline';
import ColumnVisibilityDropdown from './ColumnVisibilityDropdown';
import NoteModal from './NoteModal';
import { UtilityIcons, UtilityColors, formatCurrency, getHeatMapStyle, calculateHeatMapStats } from './constants';

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

export default function UtilityDataTable({ data, utilityTypes = {}, selectedType }) {
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

    const Icon = UtilityIcons[selectedType];
    const colors = UtilityColors[selectedType] || UtilityColors.other;

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

    // Note modal handlers
    const openNoteModal = (property) => {
        setSelectedProperty(property);
        setNoteModalOpen(true);
    };

    const closeNoteModal = () => {
        setNoteModalOpen(false);
        setSelectedProperty(null);
    };

    const handleNoteSave = (savedNote) => {
        if (selectedProperty) {
            setLocalNotes((prev) => ({
                ...prev,
                [selectedProperty.property_id]: savedNote,
            }));
        }
    };

    const handleNoteDelete = () => {
        if (selectedProperty) {
            setLocalNotes((prev) => ({
                ...prev,
                [selectedProperty.property_id]: null,
            }));
        }
    };

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
            <div className="card">
                <div className="card-header">
                    <h3 className="text-lg font-medium text-gray-900">Property Utility Data</h3>
                </div>
                <div className="card-body">
                    <div className="py-12 text-center text-gray-500">
                        No property data available for the selected filters
                    </div>
                </div>
            </div>
        );
    }

    return (
        <div className="card">
            {/* Header */}
            <div className="card-header flex items-center justify-between">
                <div className="flex items-center space-x-3">
                    {Icon && (
                        <div className={`p-2 rounded-lg ${colors.bg} ${colors.text}`}>
                            <Icon className="w-5 h-5" />
                        </div>
                    )}
                    <div>
                        <h3 className="text-lg font-medium text-gray-900">
                            {utilityTypes[selectedType]} Data
                        </h3>
                        <p className="text-sm text-gray-500">
                            {data.property_count} properties
                        </p>
                    </div>
                </div>
                <div className="flex items-center space-x-3">
                    <ColumnVisibilityDropdown
                        columns={COLUMNS}
                        visibleColumns={visibleColumns}
                        onChange={handleColumnVisibilityChange}
                        onBatchChange={handleBatchColumnVisibilityChange}
                    />
                    <button onClick={exportToCsv} className="btn-secondary text-sm">
                        <ArrowDownTrayIcon className="w-4 h-4 mr-1" />
                        Export CSV
                    </button>
                </div>
            </div>

            {/* Table */}
            <div className="overflow-x-auto">
                <table className="min-w-full divide-y divide-gray-200">
                    <thead className="bg-gray-50 sticky top-0 z-10">
                        <tr>
                            {activeColumns.map((column) => (
                                <th
                                    key={column.key}
                                    onClick={() => column.sortable && handleSort(column.key)}
                                    className={`px-4 py-3 text-xs font-medium text-gray-500 uppercase tracking-wider ${
                                        column.align === 'right' ? 'text-right' : 'text-left'
                                    } ${column.sortable ? 'cursor-pointer hover:bg-gray-100 select-none' : ''}`}
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
                    </thead>
                    <tbody className="bg-white divide-y divide-gray-200">
                        {sortedProperties.map((property) => (
                            <tr key={property.property_id} className="hover:bg-gray-50">
                                {activeColumns.map((column) => {
                                    const value = property[column.key];

                                    // Get conditional formatting from backend (for current_month, prev_month, prev_3_months)
                                    const backendFormatting = property[`${column.key}_formatting`];

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
                                                    href={route('utilities.show', property.property_id)}
                                                    className="font-medium text-blue-600 hover:text-blue-800"
                                                >
                                                    {value}
                                                </Link>
                                            );
                                        }

                                        if (column.key === 'property_type') {
                                            return <span className="text-gray-500">{value || '-'}</span>;
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
                                            }`}
                                            style={cellStyle}
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

            {/* Footer with totals */}
            <div className="px-6 py-4 border-t border-gray-200 bg-gray-50">
                <div className="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                    <div>
                        <span className="text-gray-500">Current Month Total:</span>
                        <span className="ml-2 font-medium text-gray-900">
                            {formatCurrency(data.totals?.current_month)}
                        </span>
                    </div>
                    <div>
                        <span className="text-gray-500">Prev Month Total:</span>
                        <span className="ml-2 font-medium text-gray-900">
                            {formatCurrency(data.totals?.prev_month)}
                        </span>
                    </div>
                    <div>
                        <span className="text-gray-500">Avg per Property (3 mo):</span>
                        <span className="ml-2 font-medium text-gray-900">
                            {formatCurrency(data.averages?.prev_3_months)}
                        </span>
                    </div>
                    <div>
                        <span className="text-gray-500">Avg per Property (12 mo):</span>
                        <span className="ml-2 font-medium text-gray-900">
                            {formatCurrency(data.averages?.prev_12_months)}
                        </span>
                    </div>
                </div>
            </div>

            {/* Note Modal */}
            <NoteModal
                isOpen={noteModalOpen}
                onClose={closeNoteModal}
                propertyId={selectedProperty?.property_id}
                propertyName={selectedProperty?.property_name}
                utilityType={selectedType}
                utilityTypeName={utilityTypes[selectedType]}
                existingNote={selectedProperty ? getPropertyNote(selectedProperty) : null}
                onSave={handleNoteSave}
                onDelete={handleNoteDelete}
            />
        </div>
    );
}
