import { useState } from 'react';
import { router } from '@inertiajs/react';

const DATE_RANGE_PRESETS = [
    { value: '', label: 'Default (1 year)' },
    { value: '6_months', label: '6 Months' },
    { value: '1_year', label: '1 Year' },
    { value: '2_years', label: '2 Years' },
    { value: 'all_time', label: 'All Time' },
    { value: 'custom', label: 'Custom...' },
];

export default function SyncControls({ hasConnection }) {
    const [mode, setMode] = useState('full');
    const [dateRangePreset, setDateRangePreset] = useState('');
    const [fromDate, setFromDate] = useState('');
    const [toDate, setToDate] = useState(new Date().toISOString().split('T')[0]);
    const [isSubmitting, setIsSubmitting] = useState(false);

    const handleSubmit = (e) => {
        e.preventDefault();

        const data = {
            mode,
            date_range_preset: dateRangePreset || null,
        };

        if (dateRangePreset === 'custom') {
            data.from_date = fromDate;
            data.to_date = toDate;
        }

        setIsSubmitting(true);
        router.post('/admin/sync/trigger', data, {
            onFinish: () => setIsSubmitting(false),
        });
    };

    const showCustomDateInputs = dateRangePreset === 'custom';

    return (
        <div className="card">
            <div className="card-header">
                <h3 className="text-lg font-medium text-gray-900">Run Sync</h3>
                <p className="mt-1 text-sm text-gray-500">
                    Trigger a manual sync with optional custom date range
                </p>
            </div>
            <form onSubmit={handleSubmit} className="p-6 border-t border-gray-200 space-y-4">
                {!hasConnection && (
                    <div className="rounded-md bg-yellow-50 p-4">
                        <div className="flex">
                            <div className="ml-3">
                                <h3 className="text-sm font-medium text-yellow-800">
                                    No Connection
                                </h3>
                                <div className="mt-2 text-sm text-yellow-700">
                                    Please configure your AppFolio connection in the Integrations tab before running a sync.
                                </div>
                            </div>
                        </div>
                    </div>
                )}

                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    {/* Mode Selection */}
                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">
                            Sync Mode
                        </label>
                        <div className="flex rounded-md shadow-sm">
                            <button
                                type="button"
                                onClick={() => setMode('incremental')}
                                className={`flex-1 px-4 py-2 text-sm font-medium rounded-l-md border ${
                                    mode === 'incremental'
                                        ? 'bg-blue-50 border-blue-500 text-blue-700 z-10'
                                        : 'bg-white border-gray-300 text-gray-700 hover:bg-gray-50'
                                }`}
                            >
                                Incremental
                            </button>
                            <button
                                type="button"
                                onClick={() => setMode('full')}
                                className={`flex-1 px-4 py-2 text-sm font-medium rounded-r-md border-l-0 border ${
                                    mode === 'full'
                                        ? 'bg-blue-50 border-blue-500 text-blue-700 z-10'
                                        : 'bg-white border-gray-300 text-gray-700 hover:bg-gray-50'
                                }`}
                            >
                                Full
                            </button>
                        </div>
                        <p className="mt-1 text-xs text-gray-500">
                            {mode === 'incremental'
                                ? 'Sync recent changes only (faster)'
                                : 'Sync all data within date range'}
                        </p>
                    </div>

                    {/* Date Range Preset */}
                    <div>
                        <label htmlFor="dateRangePreset" className="block text-sm font-medium text-gray-700 mb-1">
                            Date Range
                        </label>
                        <select
                            id="dateRangePreset"
                            value={dateRangePreset}
                            onChange={(e) => setDateRangePreset(e.target.value)}
                            className="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                        >
                            {DATE_RANGE_PRESETS.map((preset) => (
                                <option key={preset.value} value={preset.value}>
                                    {preset.label}
                                </option>
                            ))}
                        </select>
                        <p className="mt-1 text-xs text-gray-500">
                            For bill details and work orders
                        </p>
                    </div>

                    {/* Custom From Date */}
                    {showCustomDateInputs && (
                        <div>
                            <label htmlFor="fromDate" className="block text-sm font-medium text-gray-700 mb-1">
                                From Date
                            </label>
                            <input
                                type="date"
                                id="fromDate"
                                value={fromDate}
                                onChange={(e) => setFromDate(e.target.value)}
                                max={toDate}
                                required={showCustomDateInputs}
                                className="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                            />
                        </div>
                    )}

                    {/* Custom To Date */}
                    {showCustomDateInputs && (
                        <div>
                            <label htmlFor="toDate" className="block text-sm font-medium text-gray-700 mb-1">
                                To Date
                            </label>
                            <input
                                type="date"
                                id="toDate"
                                value={toDate}
                                onChange={(e) => setToDate(e.target.value)}
                                min={fromDate}
                                max={new Date().toISOString().split('T')[0]}
                                required={showCustomDateInputs}
                                className="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                            />
                        </div>
                    )}
                </div>

                {/* Submit Button */}
                <div className="flex justify-end pt-4">
                    <button
                        type="submit"
                        disabled={isSubmitting || !hasConnection || (showCustomDateInputs && !fromDate)}
                        className="btn-primary"
                    >
                        {isSubmitting ? 'Starting Sync...' : 'Run Sync'}
                    </button>
                </div>
            </form>
        </div>
    );
}
