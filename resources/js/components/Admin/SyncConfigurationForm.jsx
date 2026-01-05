import { useForm } from '@inertiajs/react';

export default function SyncConfigurationForm({ syncConfiguration, syncStatus, timezones }) {
    const { data, setData, post, processing, errors } = useForm({
        business_hours_enabled: syncConfiguration?.business_hours_enabled ?? true,
        timezone: syncConfiguration?.timezone || 'America/Los_Angeles',
        start_hour: syncConfiguration?.start_hour ?? 9,
        end_hour: syncConfiguration?.end_hour ?? 17,
        weekdays_only: syncConfiguration?.weekdays_only ?? true,
        business_hours_interval: syncConfiguration?.business_hours_interval ?? 15,
        off_hours_interval: syncConfiguration?.off_hours_interval ?? 60,
        full_sync_time: syncConfiguration?.full_sync_time || '02:00',
    });

    const handleSubmit = (e) => {
        e.preventDefault();
        post('/admin/integrations/sync-configuration');
    };

    const hourOptions = Array.from({ length: 24 }, (_, i) => ({
        value: i,
        label: i === 0 ? '12:00 AM' : i === 12 ? '12:00 PM' : i < 12 ? `${i}:00 AM` : `${i - 12}:00 PM`,
    }));

    const endHourOptions = Array.from({ length: 23 }, (_, i) => ({
        value: i + 1,
        label: i + 1 === 12 ? '12:00 PM' : i + 1 < 12 ? `${i + 1}:00 AM` : `${i + 1 - 12}:00 PM`,
    }));

    return (
        <div className="card">
            <div className="card-header">
                <h3 className="text-lg font-medium text-gray-900">Sync Schedule Configuration</h3>
                <p className="mt-1 text-sm text-gray-500">
                    Configure how often data is synchronized from AppFolio.
                </p>
            </div>
            <div className="card-body">
                {/* Current Status */}
                {syncStatus && (
                    <div className="mb-6 p-4 bg-gray-50 rounded-lg">
                        <h4 className="text-sm font-medium text-gray-700 mb-2">Current Status</h4>
                        <div className="grid grid-cols-2 gap-4 text-sm">
                            <div>
                                <span className="text-gray-500">Mode:</span>{' '}
                                <span className={`font-medium ${syncStatus.current_mode === 'business_hours' ? 'text-green-600' : 'text-blue-600'}`}>
                                    {syncStatus.current_mode === 'business_hours' ? 'Business Hours' : 'Off-Hours'}
                                </span>
                            </div>
                            <div>
                                <span className="text-gray-500">Interval:</span>{' '}
                                <span className="font-medium">Every {syncStatus.current_interval} minutes</span>
                            </div>
                            <div className="col-span-2">
                                <span className="text-gray-500">Next sync:</span>{' '}
                                <span className="font-medium">{new Date(syncStatus.next_sync).toLocaleString()}</span>
                            </div>
                        </div>
                    </div>
                )}

                <form onSubmit={handleSubmit} className="space-y-6">
                    {/* Business Hours Toggle */}
                    <div className="flex items-center justify-between">
                        <div>
                            <label htmlFor="business_hours_enabled" className="label mb-0">
                                Business Hours Mode
                            </label>
                            <p className="text-sm text-gray-500">
                                Sync more frequently during business hours, less frequently off-hours
                            </p>
                        </div>
                        <button
                            type="button"
                            onClick={() => setData('business_hours_enabled', !data.business_hours_enabled)}
                            className={`relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 ${
                                data.business_hours_enabled ? 'bg-blue-600' : 'bg-gray-200'
                            }`}
                        >
                            <span
                                className={`pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out ${
                                    data.business_hours_enabled ? 'translate-x-5' : 'translate-x-0'
                                }`}
                            />
                        </button>
                    </div>

                    {data.business_hours_enabled && (
                        <>
                            {/* Timezone */}
                            <div>
                                <label htmlFor="timezone" className="label">
                                    Timezone
                                </label>
                                <select
                                    id="timezone"
                                    className="input"
                                    value={data.timezone}
                                    onChange={(e) => setData('timezone', e.target.value)}
                                >
                                    {Object.entries(timezones || {}).map(([value, label]) => (
                                        <option key={value} value={value}>
                                            {label}
                                        </option>
                                    ))}
                                </select>
                                {errors.timezone && (
                                    <p className="mt-1 text-sm text-red-600">{errors.timezone}</p>
                                )}
                            </div>

                            {/* Business Hours Range */}
                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <label htmlFor="start_hour" className="label">
                                        Business Hours Start
                                    </label>
                                    <select
                                        id="start_hour"
                                        className="input"
                                        value={data.start_hour}
                                        onChange={(e) => setData('start_hour', parseInt(e.target.value))}
                                    >
                                        {hourOptions.map((opt) => (
                                            <option key={opt.value} value={opt.value}>
                                                {opt.label}
                                            </option>
                                        ))}
                                    </select>
                                    {errors.start_hour && (
                                        <p className="mt-1 text-sm text-red-600">{errors.start_hour}</p>
                                    )}
                                </div>
                                <div>
                                    <label htmlFor="end_hour" className="label">
                                        Business Hours End
                                    </label>
                                    <select
                                        id="end_hour"
                                        className="input"
                                        value={data.end_hour}
                                        onChange={(e) => setData('end_hour', parseInt(e.target.value))}
                                    >
                                        {endHourOptions.filter((opt) => opt.value > data.start_hour).map((opt) => (
                                            <option key={opt.value} value={opt.value}>
                                                {opt.label}
                                            </option>
                                        ))}
                                    </select>
                                    {errors.end_hour && (
                                        <p className="mt-1 text-sm text-red-600">{errors.end_hour}</p>
                                    )}
                                </div>
                            </div>

                            {/* Weekdays Only */}
                            <div className="flex items-center">
                                <input
                                    type="checkbox"
                                    id="weekdays_only"
                                    className="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                    checked={data.weekdays_only}
                                    onChange={(e) => setData('weekdays_only', e.target.checked)}
                                />
                                <label htmlFor="weekdays_only" className="ml-2 text-sm text-gray-700">
                                    Weekdays only (Monday - Friday)
                                </label>
                            </div>

                            {/* Sync Intervals */}
                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <label htmlFor="business_hours_interval" className="label">
                                        Business Hours Interval
                                    </label>
                                    <div className="flex items-center">
                                        <select
                                            id="business_hours_interval"
                                            className="input"
                                            value={data.business_hours_interval}
                                            onChange={(e) => setData('business_hours_interval', parseInt(e.target.value))}
                                        >
                                            <option value={5}>5 minutes</option>
                                            <option value={10}>10 minutes</option>
                                            <option value={15}>15 minutes</option>
                                            <option value={30}>30 minutes</option>
                                            <option value={60}>60 minutes</option>
                                        </select>
                                    </div>
                                    {errors.business_hours_interval && (
                                        <p className="mt-1 text-sm text-red-600">{errors.business_hours_interval}</p>
                                    )}
                                </div>
                                <div>
                                    <label htmlFor="off_hours_interval" className="label">
                                        Off-Hours Interval
                                    </label>
                                    <div className="flex items-center">
                                        <select
                                            id="off_hours_interval"
                                            className="input"
                                            value={data.off_hours_interval}
                                            onChange={(e) => setData('off_hours_interval', parseInt(e.target.value))}
                                        >
                                            <option value={15}>15 minutes</option>
                                            <option value={30}>30 minutes</option>
                                            <option value={60}>60 minutes (1 hour)</option>
                                            <option value={120}>120 minutes (2 hours)</option>
                                            <option value={240}>240 minutes (4 hours)</option>
                                        </select>
                                    </div>
                                    {errors.off_hours_interval && (
                                        <p className="mt-1 text-sm text-red-600">{errors.off_hours_interval}</p>
                                    )}
                                </div>
                            </div>
                        </>
                    )}

                    {/* Full Sync Time */}
                    <div>
                        <label htmlFor="full_sync_time" className="label">
                            Daily Full Sync Time
                        </label>
                        <input
                            type="time"
                            id="full_sync_time"
                            className="input w-32"
                            value={data.full_sync_time}
                            onChange={(e) => setData('full_sync_time', e.target.value)}
                        />
                        <p className="mt-1 text-sm text-gray-500">
                            A complete sync runs daily at this time (in server timezone)
                        </p>
                        {errors.full_sync_time && (
                            <p className="mt-1 text-sm text-red-600">{errors.full_sync_time}</p>
                        )}
                    </div>

                    <div className="pt-4">
                        <button
                            type="submit"
                            disabled={processing}
                            className="btn-primary"
                        >
                            {processing ? 'Saving...' : 'Save Configuration'}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    );
}
