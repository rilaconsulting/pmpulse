import { Head, Link, router } from '@inertiajs/react';
import React, { useState } from 'react';
import Layout from '../../components/Layout';
import {
    UsersIcon,
    LinkIcon,
    MagnifyingGlassIcon,
    XMarkIcon,
    ChevronDownIcon,
    ChevronRightIcon,
    ExclamationTriangleIcon,
    CheckCircleIcon,
    ArrowPathIcon,
} from '@heroicons/react/24/outline';

export default function VendorDeduplication({ canonicalGroups, allCanonicalVendors, stats }) {
    const [expandedGroups, setExpandedGroups] = useState(new Set());
    const [showLinkModal, setShowLinkModal] = useState(false);
    const [selectedVendor, setSelectedVendor] = useState(null);
    const [selectedCanonical, setSelectedCanonical] = useState('');
    const [searchCanonical, setSearchCanonical] = useState('');
    const [isProcessing, setIsProcessing] = useState(false);
    const [showPotentialDuplicates, setShowPotentialDuplicates] = useState(false);
    const [potentialDuplicates, setPotentialDuplicates] = useState([]);
    const [loadingPotential, setLoadingPotential] = useState(false);

    const toggleGroup = (vendorId) => {
        setExpandedGroups((prev) => {
            const next = new Set(prev);
            if (next.has(vendorId)) {
                next.delete(vendorId);
            } else {
                next.add(vendorId);
            }
            return next;
        });
    };

    const openLinkModal = (vendor) => {
        setSelectedVendor(vendor);
        setSelectedCanonical('');
        setSearchCanonical('');
        setShowLinkModal(true);
    };

    const closeLinkModal = () => {
        setShowLinkModal(false);
        setSelectedVendor(null);
        setSelectedCanonical('');
        setSearchCanonical('');
    };

    const handleLink = async () => {
        if (!selectedVendor || !selectedCanonical) return;

        setIsProcessing(true);
        try {
            const response = await fetch(`/api/vendors/${selectedVendor.id}/mark-duplicate`, {
                method: 'POST',
                credentials: 'include',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content'),
                },
                body: JSON.stringify({ canonical_vendor_id: selectedCanonical }),
            });

            if (response.ok) {
                closeLinkModal();
                router.reload();
            } else {
                const data = await response.json();
                alert(data.message || 'Failed to link vendor');
            }
        } catch (error) {
            console.error('Error linking vendor:', error);
            alert('An error occurred while linking the vendor');
        } finally {
            setIsProcessing(false);
        }
    };

    const handleUnlink = async (vendorId) => {
        if (!confirm('Are you sure you want to unlink this vendor?')) return;

        setIsProcessing(true);
        try {
            const response = await fetch(`/api/vendors/${vendorId}/mark-canonical`, {
                method: 'POST',
                credentials: 'include',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content'),
                },
            });

            if (response.ok) {
                router.reload();
            } else {
                const data = await response.json();
                alert(data.message || 'Failed to unlink vendor');
            }
        } catch (error) {
            console.error('Error unlinking vendor:', error);
            alert('An error occurred while unlinking the vendor');
        } finally {
            setIsProcessing(false);
        }
    };

    const loadPotentialDuplicates = async () => {
        setLoadingPotential(true);
        try {
            const response = await fetch('/api/vendors/potential-duplicates?threshold=0.5&limit=20', {
                credentials: 'include',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content'),
                },
            });

            if (response.ok) {
                const data = await response.json();
                setPotentialDuplicates(data.data || []);
                setShowPotentialDuplicates(true);
            } else {
                alert('Failed to load potential duplicates');
            }
        } catch (error) {
            console.error('Error loading potential duplicates:', error);
            alert('An error occurred while loading potential duplicates');
        } finally {
            setLoadingPotential(false);
        }
    };

    const filteredCanonicalVendors = allCanonicalVendors.filter((vendor) => {
        if (!searchCanonical) return true;
        const search = searchCanonical.toLowerCase();
        return (
            vendor.company_name?.toLowerCase().includes(search) ||
            vendor.contact_name?.toLowerCase().includes(search) ||
            vendor.vendor_trades?.toLowerCase().includes(search)
        );
    });

    return (
        <Layout>
            <Head title="Vendor Deduplication" />

            <div className="space-y-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold text-gray-900">Vendor Deduplication</h1>
                        <p className="mt-1 text-sm text-gray-500">
                            Manage duplicate vendor records and canonical groupings
                        </p>
                    </div>
                    <div className="flex items-center gap-2">
                        <button
                            type="button"
                            onClick={loadPotentialDuplicates}
                            disabled={loadingPotential}
                            className="btn-secondary flex items-center"
                        >
                            {loadingPotential ? (
                                <ArrowPathIcon className="w-4 h-4 mr-2 animate-spin" />
                            ) : (
                                <MagnifyingGlassIcon className="w-4 h-4 mr-2" />
                            )}
                            Find Potential Duplicates
                        </button>
                        <Link href="/vendors" className="btn-secondary">
                            Back to Vendors
                        </Link>
                    </div>
                </div>

                {/* Stats */}
                <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div className="card">
                        <div className="card-body">
                            <p className="text-sm font-medium text-gray-500">Total Vendors</p>
                            <p className="text-2xl font-semibold text-gray-900">{stats.total_vendors}</p>
                        </div>
                    </div>
                    <div className="card">
                        <div className="card-body">
                            <p className="text-sm font-medium text-gray-500">Canonical Vendors</p>
                            <p className="text-2xl font-semibold text-gray-900">{stats.canonical_vendors}</p>
                        </div>
                    </div>
                    <div className="card">
                        <div className="card-body">
                            <p className="text-sm font-medium text-gray-500">Linked Duplicates</p>
                            <p className="text-2xl font-semibold text-purple-600">{stats.duplicate_vendors}</p>
                        </div>
                    </div>
                    <div className="card">
                        <div className="card-body">
                            <p className="text-sm font-medium text-gray-500">Groups with Duplicates</p>
                            <p className="text-2xl font-semibold text-gray-900">{stats.canonical_with_duplicates}</p>
                        </div>
                    </div>
                </div>

                {/* Potential Duplicates Section */}
                {showPotentialDuplicates && (
                    <div className="card">
                        <div className="card-header flex items-center justify-between">
                            <div className="flex items-center gap-2">
                                <ExclamationTriangleIcon className="w-5 h-5 text-yellow-500" />
                                <h2 className="text-lg font-semibold">Potential Duplicates</h2>
                                <span className="text-sm text-gray-500">({potentialDuplicates.length} pairs found)</span>
                            </div>
                            <button
                                type="button"
                                onClick={() => setShowPotentialDuplicates(false)}
                                className="text-gray-400 hover:text-gray-600"
                            >
                                <XMarkIcon className="w-5 h-5" />
                            </button>
                        </div>
                        <div className="divide-y divide-gray-200">
                            {potentialDuplicates.length === 0 ? (
                                <div className="p-6 text-center text-gray-500">
                                    <CheckCircleIcon className="w-12 h-12 mx-auto text-green-400 mb-2" />
                                    No potential duplicates found
                                </div>
                            ) : (
                                potentialDuplicates.map((pair, index) => (
                                    <div key={index} className="p-4 hover:bg-gray-50">
                                        <div className="flex items-start justify-between">
                                            <div className="flex-1 grid grid-cols-2 gap-4">
                                                <div>
                                                    <Link
                                                        href={`/vendors/${pair.vendor1.id}`}
                                                        className="font-medium text-gray-900 hover:text-blue-600"
                                                    >
                                                        {pair.vendor1.company_name}
                                                    </Link>
                                                    {pair.vendor1.contact_name && (
                                                        <p className="text-sm text-gray-500">{pair.vendor1.contact_name}</p>
                                                    )}
                                                    {pair.vendor1.email && (
                                                        <p className="text-xs text-gray-400">{pair.vendor1.email}</p>
                                                    )}
                                                </div>
                                                <div>
                                                    <Link
                                                        href={`/vendors/${pair.vendor2.id}`}
                                                        className="font-medium text-gray-900 hover:text-blue-600"
                                                    >
                                                        {pair.vendor2.company_name}
                                                    </Link>
                                                    {pair.vendor2.contact_name && (
                                                        <p className="text-sm text-gray-500">{pair.vendor2.contact_name}</p>
                                                    )}
                                                    {pair.vendor2.email && (
                                                        <p className="text-xs text-gray-400">{pair.vendor2.email}</p>
                                                    )}
                                                </div>
                                            </div>
                                            <div className="ml-4 flex flex-col items-end gap-2">
                                                <span className="inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-yellow-100 text-yellow-800">
                                                    {Math.round(pair.similarity * 100)}% match
                                                </span>
                                                <div className="flex gap-2">
                                                    <button
                                                        type="button"
                                                        onClick={() => openLinkModal(pair.vendor2)}
                                                        className="text-sm text-blue-600 hover:text-blue-700"
                                                    >
                                                        Link to first
                                                    </button>
                                                    <button
                                                        type="button"
                                                        onClick={() => openLinkModal(pair.vendor1)}
                                                        className="text-sm text-blue-600 hover:text-blue-700"
                                                    >
                                                        Link to second
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                        {pair.match_reasons && pair.match_reasons.length > 0 && (
                                            <div className="mt-2 flex flex-wrap gap-1">
                                                {pair.match_reasons.map((reason, idx) => (
                                                    <span
                                                        key={idx}
                                                        className="inline-flex items-center px-2 py-0.5 rounded text-xs bg-gray-100 text-gray-600"
                                                    >
                                                        {reason}
                                                    </span>
                                                ))}
                                            </div>
                                        )}
                                    </div>
                                ))
                            )}
                        </div>
                    </div>
                )}

                {/* Canonical Groups */}
                <div className="card">
                    <div className="card-header">
                        <h2 className="text-lg font-semibold flex items-center gap-2">
                            <UsersIcon className="w-5 h-5 text-purple-600" />
                            Canonical Vendor Groups
                        </h2>
                    </div>
                    <div className="divide-y divide-gray-200">
                        {canonicalGroups.length === 0 ? (
                            <div className="p-6 text-center text-gray-500">
                                <UsersIcon className="w-12 h-12 mx-auto text-gray-300 mb-2" />
                                No vendor groups with duplicates
                            </div>
                        ) : (
                            canonicalGroups.map((group) => {
                                const isExpanded = expandedGroups.has(group.id);
                                return (
                                    <div key={group.id} className="p-4">
                                        <div className="flex items-center justify-between">
                                            <div className="flex items-center gap-3">
                                                <button
                                                    type="button"
                                                    onClick={() => toggleGroup(group.id)}
                                                    className="p-1 rounded hover:bg-gray-200"
                                                    aria-expanded={isExpanded}
                                                >
                                                    {isExpanded ? (
                                                        <ChevronDownIcon className="w-5 h-5 text-gray-500" />
                                                    ) : (
                                                        <ChevronRightIcon className="w-5 h-5 text-gray-500" />
                                                    )}
                                                </button>
                                                <div>
                                                    <Link
                                                        href={`/vendors/${group.id}`}
                                                        className="font-medium text-gray-900 hover:text-blue-600"
                                                    >
                                                        {group.company_name}
                                                    </Link>
                                                    {group.vendor_trades && (
                                                        <p className="text-sm text-gray-500">{group.vendor_trades}</p>
                                                    )}
                                                </div>
                                            </div>
                                            <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-700">
                                                <UsersIcon className="w-3.5 h-3.5 mr-1" />
                                                {group.duplicate_count} linked
                                            </span>
                                        </div>
                                        {isExpanded && (
                                            <div className="mt-3 ml-9 space-y-2">
                                                {group.duplicates.map((dup) => (
                                                    <div
                                                        key={dup.id}
                                                        className="flex items-center justify-between p-3 bg-purple-50 rounded-lg"
                                                    >
                                                        <div className="flex items-center gap-3">
                                                            <LinkIcon className="w-4 h-4 text-purple-500" />
                                                            <div>
                                                                <Link
                                                                    href={`/vendors/${dup.id}`}
                                                                    className="text-sm font-medium text-gray-700 hover:text-blue-600"
                                                                >
                                                                    {dup.company_name}
                                                                </Link>
                                                                {dup.contact_name && (
                                                                    <p className="text-xs text-gray-500">
                                                                        {dup.contact_name}
                                                                        {dup.phone && ` | ${dup.phone}`}
                                                                    </p>
                                                                )}
                                                            </div>
                                                        </div>
                                                        <button
                                                            type="button"
                                                            onClick={() => handleUnlink(dup.id)}
                                                            disabled={isProcessing}
                                                            className="text-sm text-red-600 hover:text-red-700 disabled:opacity-50"
                                                        >
                                                            Unlink
                                                        </button>
                                                    </div>
                                                ))}
                                            </div>
                                        )}
                                    </div>
                                );
                            })
                        )}
                    </div>
                </div>
            </div>

            {/* Link Modal */}
            {showLinkModal && (
                <div className="fixed inset-0 z-50 overflow-y-auto">
                    <div className="flex min-h-full items-center justify-center p-4">
                        <div className="fixed inset-0 bg-black/50" onClick={closeLinkModal} />
                        <div className="relative bg-white rounded-lg shadow-xl max-w-lg w-full">
                            <div className="p-6">
                                <h3 className="text-lg font-semibold text-gray-900 mb-4">
                                    Link Vendor as Duplicate
                                </h3>
                                <div className="mb-4 p-3 bg-gray-50 rounded-lg">
                                    <p className="text-sm text-gray-500 mb-1">Vendor to link:</p>
                                    <p className="font-medium text-gray-900">{selectedVendor?.company_name}</p>
                                    {selectedVendor?.contact_name && (
                                        <p className="text-sm text-gray-500">{selectedVendor.contact_name}</p>
                                    )}
                                </div>
                                <div className="mb-4">
                                    <label htmlFor="canonical-search" className="label">
                                        Link to Canonical Vendor
                                    </label>
                                    <input
                                        type="text"
                                        id="canonical-search"
                                        className="input mb-2"
                                        placeholder="Search vendors..."
                                        value={searchCanonical}
                                        onChange={(e) => setSearchCanonical(e.target.value)}
                                    />
                                    <div className="max-h-60 overflow-y-auto border border-gray-200 rounded-lg">
                                        {filteredCanonicalVendors.length === 0 ? (
                                            <p className="p-3 text-sm text-gray-500">No vendors found</p>
                                        ) : (
                                            filteredCanonicalVendors
                                                .filter((v) => v.id !== selectedVendor?.id)
                                                .map((vendor) => (
                                                    <label
                                                        key={vendor.id}
                                                        className={`flex items-center p-3 cursor-pointer hover:bg-gray-50 border-b last:border-b-0 ${
                                                            selectedCanonical === vendor.id ? 'bg-blue-50' : ''
                                                        }`}
                                                    >
                                                        <input
                                                            type="radio"
                                                            name="canonical"
                                                            value={vendor.id}
                                                            checked={selectedCanonical === vendor.id}
                                                            onChange={() => setSelectedCanonical(vendor.id)}
                                                            className="mr-3"
                                                        />
                                                        <div>
                                                            <p className="font-medium text-gray-900">
                                                                {vendor.company_name}
                                                            </p>
                                                            {vendor.contact_name && (
                                                                <p className="text-sm text-gray-500">
                                                                    {vendor.contact_name}
                                                                </p>
                                                            )}
                                                        </div>
                                                    </label>
                                                ))
                                        )}
                                    </div>
                                </div>
                                <div className="flex justify-end gap-3">
                                    <button
                                        type="button"
                                        onClick={closeLinkModal}
                                        className="btn-secondary"
                                        disabled={isProcessing}
                                    >
                                        Cancel
                                    </button>
                                    <button
                                        type="button"
                                        onClick={handleLink}
                                        disabled={!selectedCanonical || isProcessing}
                                        className="btn-primary disabled:opacity-50"
                                    >
                                        {isProcessing ? 'Linking...' : 'Link Vendor'}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            )}
        </Layout>
    );
}
