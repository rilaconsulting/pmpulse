import { GoogleMap, useJsApiLoader, Marker, InfoWindow } from '@react-google-maps/api';
import { Link } from '@inertiajs/react';
import { useMemo, useState, useCallback, useRef } from 'react';

const containerStyle = {
    width: '100%',
    height: '600px',
};

// Default center (San Francisco) when no properties
const DEFAULT_CENTER = { lat: 37.7749, lng: -122.4194 };
const DEFAULT_ZOOM = 12;
const SINGLE_PROPERTY_ZOOM = 15;

export default function PropertyMap({ properties, apiKey }) {
    const [selectedProperty, setSelectedProperty] = useState(null);
    const mapRef = useRef(null);

    // Use the hook instead of LoadScript component (prevents multiple script loads)
    const { isLoaded, loadError } = useJsApiLoader({
        googleMapsApiKey: apiKey || '',
    });

    // Filter to only properties with coordinates
    const propertiesWithCoords = useMemo(() =>
        properties.filter(p => p.latitude && p.longitude),
        [properties]
    );

    // Calculate initial center (used before fitBounds adjusts)
    const initialCenter = useMemo(() => {
        if (propertiesWithCoords.length === 0) {
            return DEFAULT_CENTER;
        }
        // Use first property as initial center
        return {
            lat: parseFloat(propertiesWithCoords[0].latitude),
            lng: parseFloat(propertiesWithCoords[0].longitude),
        };
    }, [propertiesWithCoords]);

    // Fit bounds to show all markers when map loads
    const onMapLoad = useCallback((map) => {
        mapRef.current = map;

        if (propertiesWithCoords.length === 0) {
            return;
        }

        if (propertiesWithCoords.length === 1) {
            // Single property: center on it with a reasonable zoom
            map.setCenter({
                lat: parseFloat(propertiesWithCoords[0].latitude),
                lng: parseFloat(propertiesWithCoords[0].longitude),
            });
            map.setZoom(SINGLE_PROPERTY_ZOOM);
            return;
        }

        // Multiple properties: use fitBounds to show all markers
        const bounds = new window.google.maps.LatLngBounds();
        propertiesWithCoords.forEach(property => {
            bounds.extend({
                lat: parseFloat(property.latitude),
                lng: parseFloat(property.longitude),
            });
        });
        map.fitBounds(bounds);
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

    if (loadError) {
        return (
            <div className="bg-red-50 border border-red-200 rounded-lg p-8 text-center">
                <p className="text-red-800">
                    Error loading Google Maps.
                </p>
                <p className="text-sm text-red-600 mt-2">
                    Please check your API key configuration.
                </p>
            </div>
        );
    }

    if (!isLoaded) {
        return (
            <div className="bg-gray-100 rounded-lg p-8 text-center">
                <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600 mx-auto"></div>
                <p className="mt-2 text-gray-500">Loading map...</p>
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
            <GoogleMap
                mapContainerStyle={containerStyle}
                center={initialCenter}
                zoom={DEFAULT_ZOOM}
                onLoad={onMapLoad}
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
        </div>
    );
}
