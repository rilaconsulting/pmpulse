import { ChevronLeftIcon, ChevronRightIcon } from '@heroicons/react/24/outline';

/**
 * Pagination - A responsive pagination component.
 *
 * @param {Object} props
 * @param {number} props.currentPage - Current page number (1-based)
 * @param {number} props.totalPages - Total number of pages
 * @param {Function} props.onPageChange - Callback when page changes
 * @param {number} props.from - Starting item number (for info text)
 * @param {number} props.to - Ending item number (for info text)
 * @param {number} props.total - Total item count (for info text)
 * @param {number} props.siblingCount - Number of page buttons on each side (default: 1)
 * @param {string} props.className - Additional classes
 */
export default function Pagination({
    currentPage,
    totalPages,
    onPageChange,
    from,
    to,
    total,
    siblingCount = 1,
    className = '',
}) {
    if (totalPages <= 1) return null;

    const isFirstPage = currentPage === 1;
    const isLastPage = currentPage === totalPages;

    // Generate page numbers to show
    const getPageNumbers = () => {
        const pages = [];
        const leftSibling = Math.max(currentPage - siblingCount, 1);
        const rightSibling = Math.min(currentPage + siblingCount, totalPages);

        // Always show first page
        if (leftSibling > 1) {
            pages.push(1);
            if (leftSibling > 2) {
                pages.push('...');
            }
        }

        // Show range around current page
        for (let i = leftSibling; i <= rightSibling; i++) {
            pages.push(i);
        }

        // Always show last page
        if (rightSibling < totalPages) {
            if (rightSibling < totalPages - 1) {
                pages.push('...');
            }
            pages.push(totalPages);
        }

        return pages;
    };

    const pageNumbers = getPageNumbers();

    return (
        <div className={`flex flex-col sm:flex-row items-center justify-between gap-4 ${className}`}>
            {/* Info text */}
            {from && to && total && (
                <div className="text-sm text-gray-500 text-center sm:text-left order-2 sm:order-1">
                    <span className="hidden sm:inline">
                        Showing {from} to {to} of {total} results
                    </span>
                    <span className="sm:hidden">
                        {from}-{to} of {total}
                    </span>
                </div>
            )}

            {/* Pagination controls */}
            <div className="flex items-center gap-1 order-1 sm:order-2">
                {/* Previous button */}
                <button
                    type="button"
                    onClick={() => onPageChange(currentPage - 1)}
                    disabled={isFirstPage}
                    className={`flex items-center justify-center min-w-[44px] min-h-[44px] sm:min-w-0 sm:min-h-0 sm:px-3 sm:py-2 rounded-lg text-sm font-medium transition-colors ${
                        isFirstPage
                            ? 'text-gray-300 cursor-not-allowed'
                            : 'text-gray-700 hover:bg-gray-100 active:bg-gray-200'
                    }`}
                    aria-label="Previous page"
                >
                    <ChevronLeftIcon className="w-5 h-5" />
                    <span className="hidden sm:inline ml-1">Prev</span>
                </button>

                {/* Page numbers - desktop only */}
                <div className="hidden md:flex items-center gap-1">
                    {pageNumbers.map((page, index) => (
                        page === '...' ? (
                            <span key={`ellipsis-${index}`} className="px-2 py-1 text-gray-400">
                                ...
                            </span>
                        ) : (
                            <button
                                key={page}
                                type="button"
                                onClick={() => onPageChange(page)}
                                className={`min-w-[36px] px-3 py-2 rounded-lg text-sm font-medium transition-colors ${
                                    page === currentPage
                                        ? 'bg-blue-600 text-white'
                                        : 'text-gray-700 hover:bg-gray-100'
                                }`}
                            >
                                {page}
                            </button>
                        )
                    ))}
                </div>

                {/* Mobile page indicator */}
                <div className="md:hidden px-4 py-2 text-sm font-medium text-gray-700">
                    {currentPage} / {totalPages}
                </div>

                {/* Next button */}
                <button
                    type="button"
                    onClick={() => onPageChange(currentPage + 1)}
                    disabled={isLastPage}
                    className={`flex items-center justify-center min-w-[44px] min-h-[44px] sm:min-w-0 sm:min-h-0 sm:px-3 sm:py-2 rounded-lg text-sm font-medium transition-colors ${
                        isLastPage
                            ? 'text-gray-300 cursor-not-allowed'
                            : 'text-gray-700 hover:bg-gray-100 active:bg-gray-200'
                    }`}
                    aria-label="Next page"
                >
                    <span className="hidden sm:inline mr-1">Next</span>
                    <ChevronRightIcon className="w-5 h-5" />
                </button>
            </div>
        </div>
    );
}

/**
 * Helper to convert Laravel pagination to component props
 */
export function fromLaravelPagination(pagination) {
    return {
        currentPage: pagination.current_page,
        totalPages: pagination.last_page,
        from: pagination.from,
        to: pagination.to,
        total: pagination.total,
    };
}
