<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\AdjustmentReportRequest;
use App\Models\PropertyAdjustment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdjustmentReportController extends Controller
{
    /**
     * Display the adjustments report page.
     */
    public function index(AdjustmentReportRequest $request): InertiaResponse
    {
        $today = Carbon::today();
        $status = $request->get('status', 'active');

        $query = $this->buildFilteredQuery($request, $today, $status);

        // Get adjustments with pagination
        $adjustments = $query->paginate(25)->withQueryString();

        // Get filter options
        $creators = User::whereHas('createdAdjustments')
            ->orderBy('name')
            ->get(['id', 'name']);

        // Calculate summary stats
        $summaryQuery = $this->buildSummaryQuery($status, $today);
        $byFieldQuery = clone $summaryQuery;
        $propertiesQuery = clone $summaryQuery;

        $summary = [
            'total' => $summaryQuery->count(),
            'by_field' => $byFieldQuery
                ->selectRaw('field_name, COUNT(*) as count')
                ->groupBy('field_name')
                ->pluck('count', 'field_name'),
            'properties_affected' => $propertiesQuery
                ->distinct('property_id')
                ->count('property_id'),
        ];

        return Inertia::render('Admin/AdjustmentsReport', [
            'adjustments' => $adjustments,
            'adjustableFields' => PropertyAdjustment::ADJUSTABLE_FIELDS,
            'creators' => $creators,
            'summary' => $summary,
            'filters' => [
                'status' => $status,
                'field' => $request->get('field', ''),
                'creator' => $request->get('creator', ''),
                'from' => $request->get('from', ''),
                'to' => $request->get('to', ''),
            ],
        ]);
    }

    /**
     * Export adjustments to CSV.
     */
    public function export(AdjustmentReportRequest $request): StreamedResponse
    {
        $today = Carbon::today();
        $status = $request->get('status', 'active');

        $query = $this->buildFilteredQuery($request, $today, $status);

        $filename = 'adjustments-'.Carbon::now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($query): void {
            $handle = fopen('php://output', 'w');

            // Header row
            fputcsv($handle, [
                'Property',
                'Field',
                'Original Value',
                'Adjusted Value',
                'Effective From',
                'Effective To',
                'Reason',
                'Created By',
                'Created At',
            ]);

            // Stream adjustments using cursor to minimize memory usage
            foreach ($query->cursor() as $adjustment) {
                fputcsv($handle, [
                    $this->sanitizeCsvField($adjustment->property->name ?? ''),
                    $this->sanitizeCsvField($adjustment->field_label ?? $adjustment->field_name),
                    $this->sanitizeCsvField((string) $adjustment->original_value),
                    $this->sanitizeCsvField((string) $adjustment->adjusted_value),
                    $adjustment->effective_from?->format('Y-m-d') ?? '',
                    $adjustment->effective_to?->format('Y-m-d') ?? 'Permanent',
                    $this->sanitizeCsvField($adjustment->reason ?? ''),
                    $this->sanitizeCsvField($adjustment->creator->name ?? ''),
                    $adjustment->created_at?->format('Y-m-d H:i:s') ?? '',
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }

    /**
     * Build the filtered query for adjustments.
     *
     * @return Builder<PropertyAdjustment>
     */
    private function buildFilteredQuery(Request $request, Carbon $today, string $status): Builder
    {
        $query = PropertyAdjustment::query()
            ->with(['property:id,name,external_id', 'creator:id,name'])
            ->orderBy('created_at', 'desc');

        // Filter by status (active/historical/all)
        if ($status === 'active') {
            $query->activeOn($today);
        } elseif ($status === 'historical') {
            $query->where('effective_to', '<', $today);
        }

        // Filter by field name
        if ($fieldName = $request->get('field')) {
            $query->forField($fieldName);
        }

        // Filter by creator
        if ($creatorId = $request->get('creator')) {
            $query->where('created_by', $creatorId);
        }

        // Filter by date range
        if ($from = $request->get('from')) {
            $query->where('effective_from', '>=', Carbon::parse($from));
        }
        if ($to = $request->get('to')) {
            $query->where('effective_from', '<=', Carbon::parse($to));
        }

        return $query;
    }

    /**
     * Build the summary query for adjustments.
     *
     * @return Builder<PropertyAdjustment>
     */
    private function buildSummaryQuery(string $status, Carbon $today): Builder
    {
        $query = PropertyAdjustment::query();

        if ($status === 'active') {
            $query->activeOn($today);
        } elseif ($status === 'historical') {
            $query->where('effective_to', '<', $today);
        }

        return $query;
    }

    /**
     * Sanitize a field value for CSV export to prevent formula injection.
     *
     * Spreadsheet applications may execute formulas starting with =, +, -, or @.
     * This method prefixes such values with a single quote to neutralize them.
     */
    private function sanitizeCsvField(string $value): string
    {
        if ($value !== '' && in_array($value[0], ['=', '+', '-', '@'], true)) {
            return "'".$value;
        }

        return $value;
    }
}
