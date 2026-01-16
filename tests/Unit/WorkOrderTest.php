<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkOrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_days_open_returns_zero_when_opened_at_is_null(): void
    {
        // Use make() instead of create() since the database schema doesn't allow null opened_at
        // This tests the accessor logic for edge cases where data might be null
        $workOrder = WorkOrder::factory()->make([
            'opened_at' => null,
            'closed_at' => null,
        ]);

        $this->assertEquals(0, $workOrder->days_open);
    }

    public function test_days_open_calculates_correctly_for_open_work_order(): void
    {
        $workOrder = WorkOrder::factory()->create([
            'opened_at' => now()->subDays(5),
            'closed_at' => null,
        ]);

        $this->assertEquals(5, $workOrder->days_open);
    }

    public function test_days_open_calculates_correctly_for_closed_work_order(): void
    {
        $workOrder = WorkOrder::factory()->create([
            'opened_at' => now()->subDays(10),
            'closed_at' => now()->subDays(3),
        ]);

        $this->assertEquals(7, $workOrder->days_open);
    }
}
