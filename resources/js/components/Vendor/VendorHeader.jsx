import {
    WrenchScrewdriverIcon,
    PhoneIcon,
    EnvelopeIcon,
    MapPinIcon,
} from '@heroicons/react/24/outline';

/**
 * Vendor header with contact info, trades, and status badges
 */
export default function VendorHeader({ vendor }) {
    const trades = vendor.vendor_trades?.split(',').map(t => t.trim()) || [];

    return (
        <div className="card">
            <div className="card-body">
                <div className="flex items-start justify-between">
                    <div className="flex items-start gap-4">
                        <div className="w-16 h-16 bg-blue-100 rounded-lg flex items-center justify-center flex-shrink-0">
                            <WrenchScrewdriverIcon className="w-8 h-8 text-blue-600" />
                        </div>
                        <div>
                            <h1 className="text-2xl font-semibold text-gray-900">
                                {vendor.company_name}
                                {vendor.duplicate_vendors?.length > 0 && (
                                    <span className="ml-2 inline-flex items-center px-2 py-0.5 rounded text-sm font-medium bg-gray-100 text-gray-600">
                                        +{vendor.duplicate_vendors.length} linked
                                    </span>
                                )}
                            </h1>
                            {trades.length > 0 && (
                                <div className="flex flex-wrap gap-2 mt-2">
                                    {trades.map((trade, idx) => (
                                        <span
                                            key={idx}
                                            className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800"
                                        >
                                            {trade}
                                        </span>
                                    ))}
                                </div>
                            )}
                            <div className="mt-3 flex flex-wrap gap-4 text-sm text-gray-500">
                                {vendor.contact_name && (
                                    <span>{vendor.contact_name}</span>
                                )}
                                {vendor.phone && (
                                    <a href={`tel:${vendor.phone}`} className="flex items-center hover:text-gray-700">
                                        <PhoneIcon className="w-4 h-4 mr-1" />
                                        {vendor.phone}
                                    </a>
                                )}
                                {vendor.email && (
                                    <a href={`mailto:${vendor.email}`} className="flex items-center hover:text-gray-700">
                                        <EnvelopeIcon className="w-4 h-4 mr-1" />
                                        {vendor.email}
                                    </a>
                                )}
                            </div>
                            {(vendor.address_street || vendor.address_city) && (
                                <div className="mt-2 flex items-center text-sm text-gray-500">
                                    <MapPinIcon className="w-4 h-4 mr-1" />
                                    {[vendor.address_street, vendor.address_city, vendor.address_state, vendor.address_zip]
                                        .filter(Boolean)
                                        .join(', ')}
                                </div>
                            )}
                        </div>
                    </div>
                    <div className="flex items-center gap-2">
                        <span className={`inline-flex items-center px-3 py-1 rounded-full text-sm font-medium ${
                            vendor.is_active
                                ? 'bg-green-100 text-green-800'
                                : 'bg-red-100 text-red-800'
                        }`}>
                            {vendor.is_active ? 'Active' : 'Inactive'}
                        </span>
                        {vendor.do_not_use && (
                            <span className="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-red-100 text-red-800">
                                Do Not Use
                            </span>
                        )}
                    </div>
                </div>
            </div>
        </div>
    );
}
