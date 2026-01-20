import { Head, Link, router } from '@inertiajs/react';
import React, { useState, useRef, useCallback, useEffect } from 'react';
import { Dialog, DialogBackdrop, DialogPanel, DialogTitle } from '@headlessui/react';
import Layout from '../../components/Layout';
import { useToast } from '../../components/Toast';
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
    ClockIcon,
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
    const [similarityThreshold, setSimilarityThreshold] = useState(0.6);
    const [resultLimit, setResultLimit] = useState(20);

    // Background analysis state
    const [analysisStatus, setAnalysisStatus] = useState(null); // pending, processing, completed, failed
    const [analysisProgress, setAnalysisProgress] = useState(null);
    const pollIntervalRef = useRef(null);

    // Toast notifications
    const toast = useToast();

    // Ref to store the element that triggered the modal for focus return
    const triggerRef = useRef(null);

    // Cleanup polling on unmount
    useEffect(() => {
        return () => {
            if (pollIntervalRef.current) {
                clearInterval(pollIntervalRef.current);
            }
        };
    }, []);

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

    const openLinkModal = useCallback((vendor, triggerElement = null) => {
        triggerRef.current = triggerElement || document.activeElement;
        setSelectedVendor(vendor);
        setSelectedCanonical('');
        setSearchCanonical('');
        setShowLinkModal(true);
    }, []);

    const closeLinkModal = useCallback(() => {
        setShowLinkModal(false);
        setSelectedVendor(null);
        setSelectedCanonical('');
        setSearchCanonical('');
        // Return focus to trigger element
        if (triggerRef.current && typeof triggerRef.current.focus === 'function') {
            setTimeout(() => triggerRef.current?.focus(), 0);
        }
    }, []);

    const handleLink = async () => {
        if (!selectedVendor || !selectedCanonical) return;

        setIsProcessing(true);
        try {
            const response = await fetch(`/api/vendors/${selectedVendor.id}/mark-duplicate`, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content'),
                },
                body: JSON.stringify({ canonical_vendor_id: selectedCanonical }),
            });

            if (response.ok) {
                closeLinkModal();
                toast.success('Vendor linked successfully');
                router.reload();
            } else {
                const data = await response.json();
                toast.error(data.message || 'Failed to link vendor');
            }
        } catch (error) {
            console.error('Error linking vendor:', error);
            toast.error('An error occurred while linking the vendor');
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
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content'),
                },
            });

            if (response.ok) {
                toast.success('Vendor unlinked successfully');
                router.reload();
            } else {
                const data = await response.json();
                toast.error(data.message || 'Failed to unlink vendor');
            }
        } catch (error) {
            console.error('Error unlinking vendor:', error);
            toast.error('An error occurred while unlinking the vendor');
        } finally {
            setIsProcessing(false);
        }
    };

    // Poll for analysis completion
    const pollAnalysisStatus = useCallback(async (id) => {
        try {
            const response = await fetch(`/api/vendors/duplicate-analysis/${id}`, {
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            if (response.ok) {
                const data = await response.json();
                const analysis = data.data;
                setAnalysisStatus(analysis.status);
                setAnalysisProgress(analysis);

                if (analysis.status === 'completed') {
                    // Stop polling
                    if (pollIntervalRef.current) {
                        clearInterval(pollIntervalRef.current);
                        pollIntervalRef.current = null;
                    }
                    setLoadingPotential(false);
                    setPotentialDuplicates(analysis.results || []);
                    setShowPotentialDuplicates(true);

                    if (analysis.duplicates_found === 0) {
                        toast.success('No potential duplicates found');
                    } else {
                        toast.success(`Found ${analysis.duplicates_found} potential duplicate pairs`);
                    }
                } else if (analysis.status === 'failed') {
                    // Stop polling
                    if (pollIntervalRef.current) {
                        clearInterval(pollIntervalRef.current);
                        pollIntervalRef.current = null;
                    }
                    setLoadingPotential(false);
                    toast.error(analysis.error_message || 'Analysis failed');
                }
            }
        } catch (error) {
            console.error('Error polling analysis status:', error);
        }
    }, [toast]);

    const loadPotentialDuplicates = async () => {
        // Clear any existing polling
        if (pollIntervalRef.current) {
            clearInterval(pollIntervalRef.current);
            pollIntervalRef.current = null;
        }

        setLoadingPotential(true);
        setAnalysisStatus('pending');
        setAnalysisProgress(null);

        try {
            // Start a background analysis
            const response = await fetch('/api/vendors/duplicate-analysis', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content'),
                },
                body: JSON.stringify({
                    threshold: similarityThreshold,
                    limit: resultLimit,
                }),
            });

            if (response.ok || response.status === 202) {
                const data = await response.json();
                const analysisId = data.data.id;
                setAnalysisStatus(data.data.status);
                toast.info('Analysis started - this may take a moment');

                // Start polling
                pollIntervalRef.current = setInterval(() => {
                    pollAnalysisStatus(analysisId);
                }, 2000); // Poll every 2 seconds
            } else if (response.status === 409) {
                // Analysis already in progress
                const data = await response.json();
                const analysisId = data.data.id;
                setAnalysisStatus(data.data.status);
                toast.warning('An analysis is already in progress');

                // Start polling the existing analysis
                pollIntervalRef.current = setInterval(() => {
                    pollAnalysisStatus(analysisId);
                }, 2000);
            } else {
                const data = await response.json();
                toast.error(data.message || 'Failed to start analysis');
                setLoadingPotential(false);
            }
        } catch (error) {
            console.error('Error starting duplicate analysis:', error);
            toast.error('An error occurred while starting the analysis');
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
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div>
                        <h1 className="text-xl sm:text-2xl font-semibold text-gray-900">Vendor Deduplication</h1>
                        <p className="mt-1 text-sm text-gray-500">
                            Manage duplicate vendor records and canonical groupings
                        </p>
                    </div>
                    <Link href={route('vendors.index')} className="btn-secondary min-h-[44px] sm:min-h-0 text-center">
                        Back to Vendors
                    </Link>
                </div>

                {/* Potential Duplicates Settings */}
                <div className="card">
                    <div className="card-body">
                        <div className="flex flex-col sm:flex-row sm:flex-wrap sm:items-end gap-4">
                            <div className="flex-1 min-w-0 sm:min-w-[200px]">
                                <label htmlFor="similarity-threshold" className="block text-sm font-medium text-gray-700 mb-1">
                                    Similarity Threshold: {Math.round(similarityThreshold * 100)}%
                                </label>
                                <input
                                    type="range"
                                    id="similarity-threshold"
                                    min="0.1"
                                    max="1.0"
                                    step="0.05"
                                    value={similarityThreshold}
                                    onChange={(e) => setSimilarityThreshold(parseFloat(e.target.value))}
                                    className="w-full h-2 bg-gray-200 rounded-lg appearance-none cursor-pointer accent-blue-600"
                                    disabled={loadingPotential}
                                />
                                <div className="flex justify-between text-xs text-gray-500 mt-1">
                                    <span>More results</span>
                                    <span>Higher accuracy</span>
                                </div>
                            </div>
                            <div className="flex gap-3 sm:gap-4">
                                <div className="w-28 sm:w-32">
                                    <label htmlFor="result-limit" className="block text-sm font-medium text-gray-700 mb-1">
                                        Max Results
                                    </label>
                                    <select
                                        id="result-limit"
                                        value={resultLimit}
                                        onChange={(e) => setResultLimit(parseInt(e.target.value))}
                                        className="input min-h-[44px] sm:min-h-0"
                                        disabled={loadingPotential}
                                    >
                                        <option value="10">10</option>
                                        <option value="20">20</option>
                                        <option value="50">50</option>
                                        <option value="100">100</option>
                                    </select>
                                </div>
                                <div className="flex items-end">
                                    <button
                                        type="button"
                                        onClick={loadPotentialDuplicates}
                                        disabled={loadingPotential}
                                        className="btn-primary flex items-center min-h-[44px] sm:min-h-0 whitespace-nowrap"
                                    >
                                        {loadingPotential ? (
                                            <ArrowPathIcon className="w-4 h-4 sm:mr-2 animate-spin" />
                                        ) : (
                                            <MagnifyingGlassIcon className="w-4 h-4 sm:mr-2" />
                                        )}
                                        <span className="hidden sm:inline">{loadingPotential ? 'Analyzing...' : 'Find Duplicates'}</span>
                                        <span className="sm:hidden">{loadingPotential ? '...' : 'Find'}</span>
                                    </button>
                                </div>
                            </div>
                        </div>

                        {/* Analysis Progress */}
                        {loadingPotential && analysisStatus && (
                            <div className="mt-4 p-4 bg-blue-50 rounded-lg">
                                <div className="flex items-center gap-3">
                                    <ClockIcon className="w-5 h-5 text-blue-600 animate-pulse" />
                                    <div className="flex-1">
                                        <p className="text-sm font-medium text-blue-900">
                                            {analysisStatus === 'pending' && 'Starting analysis...'}
                                            {analysisStatus === 'processing' && 'Comparing vendors for potential duplicates...'}
                                        </p>
                                        {analysisProgress?.total_vendors && (
                                            <p className="text-xs text-blue-700 mt-1">
                                                Analyzing {analysisProgress.total_vendors} vendors
                                            </p>
                                        )}
                                    </div>
                                </div>
                            </div>
                        )}
                    </div>
                </div>

                {/* Stats */}
                <div className="grid grid-cols-2 md:grid-cols-4 gap-3 md:gap-4">
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
                                potentialDuplicates.map((pair) => (
                                    <div key={`${pair.vendor1.id}-${pair.vendor2.id}`} className="p-4 hover:bg-gray-50">
                                        <div className="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3">
                                            <div className="flex-1 grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-4">
                                                <div>
                                                    <Link
                                                        href={route('vendors.show', pair.vendor1.id)}
                                                        className="font-medium text-gray-900 hover:text-blue-600"
                                                    >
                                                        {pair.vendor1.company_name}
                                                    </Link>
                                                    {pair.vendor1.contact_name && (
                                                        <p className="text-sm text-gray-500">{pair.vendor1.contact_name}</p>
                                                    )}
                                                    {pair.vendor1.email && (
                                                        <p className="text-xs text-gray-400 truncate">{pair.vendor1.email}</p>
                                                    )}
                                                </div>
                                                <div>
                                                    <Link
                                                        href={route('vendors.show', pair.vendor2.id)}
                                                        className="font-medium text-gray-900 hover:text-blue-600"
                                                    >
                                                        {pair.vendor2.company_name}
                                                    </Link>
                                                    {pair.vendor2.contact_name && (
                                                        <p className="text-sm text-gray-500">{pair.vendor2.contact_name}</p>
                                                    )}
                                                    {pair.vendor2.email && (
                                                        <p className="text-xs text-gray-400 truncate">{pair.vendor2.email}</p>
                                                    )}
                                                </div>
                                            </div>
                                            <div className="flex flex-row sm:flex-col items-center sm:items-end gap-2 sm:ml-4">
                                                <span className="inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-yellow-100 text-yellow-800">
                                                    {Math.round(pair.similarity * 100)}% match
                                                </span>
                                                <div className="flex flex-wrap gap-2">
                                                    <button
                                                        type="button"
                                                        onClick={(e) => openLinkModal(pair.vendor2, e.currentTarget)}
                                                        className="text-sm text-blue-600 hover:text-blue-700 min-h-[44px] sm:min-h-0 flex items-center"
                                                        title={`Mark ${pair.vendor2.company_name} as duplicate of ${pair.vendor1.company_name}`}
                                                    >
                                                        Link to {pair.vendor1.company_name?.substring(0, 15)}{pair.vendor1.company_name?.length > 15 ? '...' : ''}
                                                    </button>
                                                    <button
                                                        type="button"
                                                        onClick={(e) => openLinkModal(pair.vendor1, e.currentTarget)}
                                                        className="text-sm text-blue-600 hover:text-blue-700 min-h-[44px] sm:min-h-0 flex items-center"
                                                        title={`Mark ${pair.vendor1.company_name} as duplicate of ${pair.vendor2.company_name}`}
                                                    >
                                                        Link to {pair.vendor2.company_name?.substring(0, 15)}{pair.vendor2.company_name?.length > 15 ? '...' : ''}
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
                                                        href={route('vendors.show', group.id)}
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
                                                                    href={route('vendors.show', dup.id)}
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

            {/* Link Modal - Accessible dialog using headlessui */}
            <Dialog open={showLinkModal} onClose={closeLinkModal} className="relative z-50">
                {/* Backdrop */}
                <DialogBackdrop
                    transition
                    className="fixed inset-0 bg-black/50 transition-opacity data-[closed]:opacity-0 data-[enter]:duration-200 data-[leave]:duration-150"
                />

                {/* Modal container */}
                <div className="fixed inset-0 overflow-y-auto">
                    <div className="flex min-h-full items-center justify-center p-4">
                        <DialogPanel
                            transition
                            className="relative bg-white rounded-lg shadow-xl max-w-lg w-full transform transition-all data-[closed]:scale-95 data-[closed]:opacity-0 data-[enter]:duration-200 data-[leave]:duration-150"
                        >
                            <div className="p-6">
                                <DialogTitle className="text-lg font-semibold text-gray-900 mb-4">
                                    Link Vendor as Duplicate
                                </DialogTitle>
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
                                        autoFocus
                                    />
                                    <div
                                        className="max-h-60 overflow-y-auto border border-gray-200 rounded-lg"
                                        role="radiogroup"
                                        aria-label="Select canonical vendor"
                                    >
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
                                                            aria-describedby={`vendor-${vendor.id}-contact`}
                                                        />
                                                        <div>
                                                            <p className="font-medium text-gray-900">
                                                                {vendor.company_name}
                                                            </p>
                                                            {vendor.contact_name && (
                                                                <p
                                                                    id={`vendor-${vendor.id}-contact`}
                                                                    className="text-sm text-gray-500"
                                                                >
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
                        </DialogPanel>
                    </div>
                </div>
            </Dialog>
        </Layout>
    );
}
