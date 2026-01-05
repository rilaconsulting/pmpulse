import { GoogleMap, LoadScript, Marker, InfoWindow } from '@react-google-maps/api';
import { Link } from '@inertiajs/react';
import { useMemo, useState, useCallback } from 'react';

const containerStyle = {
    width: '100%',
    height: '600px',
};

export default function PropertyMap({ properties, apiKey }) {
    const [selectedProperty, setSelectedProperty] = useState(null);

    // Filter to only properties with coordinates
    const propertiesWithCoords = useMemo(() =>
        properties.filter(p => p.latitude && p.longitude),
        [properties]
    );

    // Calculate center and zoom
    const { center, zoom } = useMemo(() => {
        if (propertiesWithCoords.length === 0) {
            // Default to San Francisco
            return { center: { lat: 37.7749, lng: -122.4194 }, zoom: 12 };
        }

        if (propertiesWithCoords.length === 1) {
            return {
                center: {
                    lat: parseFloat(propertiesWithCoords[0].latitude),
                    lng: parseFloat(propertiesWithCoords[0].longitude),
                },
                zoom: 15,
            };
        }

        // Calculate bounds center
        const lats = propertiesWithCoords.map(p => parseFloat(p.latitude));
        const lngs = propertiesWithCoords.map(p => parseFloat(p.longitude));

        const minLat = Math.min(...lats);
        const maxLat = Math.max(...lats);
        const minLng = Math.min(...lngs);
        const maxLng = Math.max(...lngs);

        return {
            center: {
                lat: (minLat + maxLat) / 2,
                lng: (minLng + maxLng) / 2,
            },
            zoom: 11,
        };
    }, [propertiesWithCoords]);

    const onMarkerClick = useCallback((property) => {
        setSelectedProperty(property);
    }, []);

    const onInfoWindowClose = useCallback(() => {
        setSelectedProperty(null);
    }, []);

    if (!apiKey) {
        return (
            <div className="bg-yellow-50 border border-yellow-200 rounded-lg p-8 text-center">
                <p className="text-yellow-800">
                    Google Maps API key is not configured.
                </p>
                <p className="text-sm text-yellow-600 mt-2">
                    Add your API key in Admin &rarr; Settings &rarr; Google Maps.
                </p>
            </div>
        );
    }

    if (propertiesWithCoords.length === 0) {
        return (
            <div className="bg-gray-100 rounded-lg p-8 text-center">
                <p className="text-gray-500">
                    No properties with location data available.
                </p>
                <p className="text-sm text-gray-400 mt-2">
                    Run <code className="bg-gray-200 px-1 rounded">php artisan properties:geocode</code> to populate coordinates.
                </p>
            </div>
        );
    }

    return (
        <div className="rounded-lg overflow-hidden border border-gray-200">
            <LoadScript googleMapsApiKey={apiKey}>
                <GoogleMap
                    mapContainerStyle={containerStyle}
                    center={center}
                    zoom={zoom}
                    options={{
                        streetViewControl: false,
                        mapTypeControl: false,
                        fullscreenControl: true,
                    }}
                >
                    {propertiesWithCoords.map((property) => (
                        <Marker
                            key={property.id}
                            position={{
                                lat: parseFloat(property.latitude),
                                lng: parseFloat(property.longitude),
                            }}
                            onClick={() => onMarkerClick(property)}
                            title={property.name}
                        />
                    ))}

                    {selectedProperty && (
                        <InfoWindow
                            position={{
                                lat: parseFloat(selectedProperty.latitude),
                                lng: parseFloat(selectedProperty.longitude),
                            }}
                            onCloseClick={onInfoWindowClose}
                        >
                            <div className="min-w-48 p-1">
                                <Link
                                    href={`/properties/${selectedProperty.id}`}
                                    className="font-medium text-blue-600 hover:text-blue-800"
                                >
                                    {selectedProperty.name}
                                </Link>
                                {selectedProperty.address_line1 && (
                                    <p className="text-sm text-gray-600 mt-1">
                                        {selectedProperty.address_line1}
                                    </p>
                                )}
                                {selectedProperty.city && (
                                    <p className="text-sm text-gray-500">
                                        {selectedProperty.city}, {selectedProperty.state} {selectedProperty.zip}
                                    </p>
                                )}
                                <div className="mt-2 flex flex-wrap gap-2 text-xs">
                                    {(selectedProperty.units_count ?? selectedProperty.unit_count) > 0 && (
                                        <span className="bg-gray-100 px-2 py-0.5 rounded">
                                            {selectedProperty.units_count ?? selectedProperty.unit_count} units
                                        </span>
                                    )}
                                    {selectedProperty.occupancy_rate !== null && (
                                        <span className={`px-2 py-0.5 rounded ${
                                            selectedProperty.occupancy_rate >= 90
                                                ? 'bg-green-100 text-green-800'
                                                : selectedProperty.occupancy_rate >= 70
                                                    ? 'bg-yellow-100 text-yellow-800'
                                                    : 'bg-red-100 text-red-800'
                                        }`}>
                                            {selectedProperty.occupancy_rate}% occupied
                                        </span>
                                    )}
                                </div>
                            </div>
                        </InfoWindow>
                    )}
                </GoogleMap>
            </LoadScript>
        </div>
    );
}
