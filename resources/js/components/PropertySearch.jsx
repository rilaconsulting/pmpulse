import { useState, useEffect, useRef, useCallback } from 'react';
import { router } from '@inertiajs/react';
import {
    MagnifyingGlassIcon,
    BuildingOfficeIcon,
    XMarkIcon,
} from '@heroicons/react/24/outline';

export default function PropertySearch() {
    const [query, setQuery] = useState('');
    const [results, setResults] = useState([]);
    const [isOpen, setIsOpen] = useState(false);
    const [isLoading, setIsLoading] = useState(false);
    const [selectedIndex, setSelectedIndex] = useState(-1);
    const [recentSearches, setRecentSearches] = useState([]);
    const inputRef = useRef(null);
    const containerRef = useRef(null);
    const debounceRef = useRef(null);

    // Load recent searches from localStorage
    useEffect(() => {
        const stored = localStorage.getItem('propertySearchHistory');
        if (stored) {
            try {
                setRecentSearches(JSON.parse(stored));
            } catch (e) {
                // Ignore parsing errors
            }
        }
    }, []);

    // Save to recent searches
    const addToRecentSearches = (property) => {
        const updated = [
            property,
            ...recentSearches.filter(p => p.id !== property.id),
        ].slice(0, 5);
        setRecentSearches(updated);
        localStorage.setItem('propertySearchHistory', JSON.stringify(updated));
    };

    // Debounced search
    const search = useCallback(async (searchQuery) => {
        if (searchQuery.length < 2) {
            setResults([]);
            return;
        }

        setIsLoading(true);
        try {
            const response = await fetch(`/properties/search?q=${encodeURIComponent(searchQuery)}`);
            const data = await response.json();
            setResults(data);
            setSelectedIndex(-1);
        } catch (error) {
            console.error('Search error:', error);
            setResults([]);
        } finally {
            setIsLoading(false);
        }
    }, []);

    // Handle input change with debounce
    const handleInputChange = (e) => {
        const value = e.target.value;
        setQuery(value);
        setIsOpen(true);

        if (debounceRef.current) {
            clearTimeout(debounceRef.current);
        }

        debounceRef.current = setTimeout(() => {
            search(value);
        }, 300);
    };

    // Navigate to property
    const navigateToProperty = (property) => {
        addToRecentSearches(property);
        setQuery('');
        setResults([]);
        setIsOpen(false);
        router.visit(`/properties/${property.id}`);
    };

    // Handle keyboard navigation
    const handleKeyDown = (e) => {
        const items = query.length >= 2 ? results : recentSearches;

        switch (e.key) {
            case 'ArrowDown':
                e.preventDefault();
                setSelectedIndex(prev =>
                    prev < items.length - 1 ? prev + 1 : prev
                );
                break;
            case 'ArrowUp':
                e.preventDefault();
                setSelectedIndex(prev => prev > 0 ? prev - 1 : -1);
                break;
            case 'Enter':
                e.preventDefault();
                if (selectedIndex >= 0 && items[selectedIndex]) {
                    navigateToProperty(items[selectedIndex]);
                }
                break;
            case 'Escape':
                setIsOpen(false);
                setQuery('');
                inputRef.current?.blur();
                break;
        }
    };

    // Close dropdown when clicking outside
    useEffect(() => {
        const handleClickOutside = (e) => {
            if (containerRef.current && !containerRef.current.contains(e.target)) {
                setIsOpen(false);
            }
        };

        document.addEventListener('mousedown', handleClickOutside);
        return () => document.removeEventListener('mousedown', handleClickOutside);
    }, []);

    // Clean up debounce on unmount
    useEffect(() => {
        return () => {
            if (debounceRef.current) {
                clearTimeout(debounceRef.current);
            }
        };
    }, []);

    const showDropdown = isOpen && (query.length >= 2 || recentSearches.length > 0);
    const displayItems = query.length >= 2 ? results : recentSearches;

    return (
        <div ref={containerRef} className="relative">
            <div className="relative">
                <input
                    ref={inputRef}
                    type="text"
                    className="w-full pl-10 pr-8 py-2 text-sm border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent bg-gray-50 focus:bg-white"
                    placeholder="Search properties..."
                    value={query}
                    onChange={handleInputChange}
                    onFocus={() => setIsOpen(true)}
                    onKeyDown={handleKeyDown}
                />
                <MagnifyingGlassIcon className="w-5 h-5 text-gray-400 absolute left-3 top-1/2 -translate-y-1/2" />
                {query && (
                    <button
                        type="button"
                        onClick={() => {
                            setQuery('');
                            setResults([]);
                            inputRef.current?.focus();
                        }}
                        className="absolute right-2 top-1/2 -translate-y-1/2 p-1 text-gray-400 hover:text-gray-600"
                    >
                        <XMarkIcon className="w-4 h-4" />
                    </button>
                )}
            </div>

            {showDropdown && (
                <div className="absolute top-full left-0 right-0 mt-1 bg-white border border-gray-200 rounded-lg shadow-lg z-50 max-h-80 overflow-y-auto">
                    {isLoading && (
                        <div className="px-4 py-3 text-sm text-gray-500">
                            Searching...
                        </div>
                    )}

                    {!isLoading && query.length >= 2 && results.length === 0 && (
                        <div className="px-4 py-3 text-sm text-gray-500">
                            No properties found
                        </div>
                    )}

                    {!isLoading && query.length < 2 && recentSearches.length > 0 && (
                        <div className="px-3 py-2 text-xs font-medium text-gray-500 uppercase tracking-wider bg-gray-50">
                            Recent Searches
                        </div>
                    )}

                    {!isLoading && displayItems.map((property, index) => (
                        <button
                            key={property.id}
                            type="button"
                            className={`w-full px-4 py-3 text-left flex items-center hover:bg-gray-50 ${
                                index === selectedIndex ? 'bg-blue-50' : ''
                            }`}
                            onClick={() => navigateToProperty(property)}
                            onMouseEnter={() => setSelectedIndex(index)}
                        >
                            <div className="w-8 h-8 bg-blue-100 rounded-lg flex items-center justify-center flex-shrink-0">
                                <BuildingOfficeIcon className="w-4 h-4 text-blue-600" />
                            </div>
                            <div className="ml-3 overflow-hidden">
                                <div className="text-sm font-medium text-gray-900 truncate">
                                    {property.name}
                                </div>
                                {property.address && (
                                    <div className="text-xs text-gray-500 truncate">
                                        {property.address}
                                    </div>
                                )}
                            </div>
                        </button>
                    ))}

                    {!isLoading && displayItems.length > 0 && (
                        <div className="px-3 py-2 text-xs text-gray-400 bg-gray-50 border-t border-gray-100">
                            Press <kbd className="px-1 py-0.5 bg-gray-200 rounded text-gray-600">↑</kbd> <kbd className="px-1 py-0.5 bg-gray-200 rounded text-gray-600">↓</kbd> to navigate, <kbd className="px-1 py-0.5 bg-gray-200 rounded text-gray-600">Enter</kbd> to select
                        </div>
                    )}
                </div>
            )}
        </div>
    );
}
