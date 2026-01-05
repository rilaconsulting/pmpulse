<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\PropertyAdjustment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class AdjustmentReportController extends Controller
{
    /**
     * Display the adjustments report page.
     */
    public function index(Request $request): InertiaResponse
    {
        // Only admins can access
        if (! $request->user()?->isAdmin()) {
            abort(403);
        }

        $query = PropertyAdjustment::query()
            ->with(['property:id,name,external_id', 'creator:id,name'])
            ->orderBy('created_at', 'desc');

        // Filter by status (active/historical/all)
        $status = $request->get('status', 'active');
        $today = Carbon::today();

        if ($status === 'active') {
            $query->activeOn($today);
        } elseif ($status === 'historical') {
            $query->where(function ($q) use ($today) {
                $q->where('effective_to', '<', $today);
            });
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

        // Get adjustments with pagination
        $adjustments = $query->paginate(25)->withQueryString();

        // Get filter options
        $creators = User::whereHas('createdAdjustments')
            ->orderBy('name')
            ->get(['id', 'name']);

        // Calculate summary stats
        $summaryQuery = PropertyAdjustment::query();
        if ($status === 'active') {
            $summaryQuery->activeOn($today);
        } elseif ($status === 'historical') {
            $summaryQuery->where('effective_to', '<', $today);
        }

        $summary = [
            'total' => $summaryQuery->count(),
            'by_field' => $summaryQuery->clone()
                ->selectRaw('field_name, COUNT(*) as count')
                ->groupBy('field_name')
                ->pluck('count', 'field_name'),
            'properties_affected' => $summaryQuery->clone()
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
    public function export(Request $request): Response
    {
        // Only admins can access
        if (! $request->user()?->isAdmin()) {
            abort(403);
        }

        $query = PropertyAdjustment::query()
            ->with(['property:id,name,external_id', 'creator:id,name'])
            ->orderBy('created_at', 'desc');

        // Apply same filters as index
        $status = $request->get('status', 'active');
        $today = Carbon::today();

        if ($status === 'active') {
            $query->activeOn($today);
        } elseif ($status === 'historical') {
            $query->where('effective_to', '<', $today);
        }

        if ($fieldName = $request->get('field')) {
            $query->forField($fieldName);
        }

        if ($creatorId = $request->get('creator')) {
            $query->where('created_by', $creatorId);
        }

        if ($from = $request->get('from')) {
            $query->where('effective_from', '>=', Carbon::parse($from));
        }
        if ($to = $request->get('to')) {
            $query->where('effective_from', '<=', Carbon::parse($to));
        }

        $adjustments = $query->get();

        // Build CSV
        $csv = "Property,Field,Original Value,Adjusted Value,Effective From,Effective To,Reason,Created By,Created At\n";

        foreach ($adjustments as $adjustment) {
            $csv .= implode(',', [
                '"'.str_replace('"', '""', $adjustment->property->name ?? '').'"',
                '"'.($adjustment->field_label ?? $adjustment->field_name).'"',
                '"'.$adjustment->original_value.'"',
                '"'.$adjustment->adjusted_value.'"',
                $adjustment->effective_from?->format('Y-m-d'),
                $adjustment->effective_to?->format('Y-m-d') ?? 'Permanent',
                '"'.str_replace('"', '""', $adjustment->reason ?? '').'"',
                '"'.str_replace('"', '""', $adjustment->creator->name ?? '').'"',
                $adjustment->created_at?->format('Y-m-d H:i:s'),
            ])."\n";
        }

        $filename = 'adjustments-'.Carbon::now()->format('Y-m-d').'.csv';

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}
