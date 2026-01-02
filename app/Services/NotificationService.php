<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AlertRule;
use App\Models\DailyKpi;
use App\Models\Setting;
use App\Models\WorkOrder;
use App\Notifications\AlertNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * Notification Service
 *
 * This service evaluates alert rules and sends notifications
 * when thresholds are exceeded.
 */
class NotificationService
{
    /**
     * Evaluate all enabled alert rules.
     */
    public function evaluateAlerts(): void
    {
        if (! Setting::isFeatureEnabled('notifications', true)) {
            Log::info('Notifications are disabled via feature flag');

            return;
        }

        $latestKpis = DailyKpi::latest('date')->first();

        if (! $latestKpis) {
            Log::warning('No KPI data available for alert evaluation');

            return;
        }

        $rules = AlertRule::enabled()->get();

        foreach ($rules as $rule) {
            try {
                $this->evaluateRule($rule, $latestKpis);
            } catch (\Exception $e) {
                Log::error('Failed to evaluate alert rule', [
                    'rule_id' => $rule->id,
                    'rule_name' => $rule->name,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Evaluate a single alert rule.
     */
    private function evaluateRule(AlertRule $rule, DailyKpi $kpis): void
    {
        $currentValue = $this->getMetricValue($rule->metric, $kpis);

        if ($currentValue === null) {
            Log::warning('Could not get metric value for rule', [
                'rule' => $rule->name,
                'metric' => $rule->metric,
            ]);

            return;
        }

        if ($rule->evaluate($currentValue)) {
            $this->sendAlert($rule, $currentValue);
        }
    }

    /**
     * Get the current value for a metric.
     */
    private function getMetricValue(string $metric, DailyKpi $kpis): ?float
    {
        return match ($metric) {
            'vacancy_count' => (float) $kpis->vacancy_count,
            'delinquency_amount' => (float) $kpis->delinquency_amount,
            'occupancy_rate' => (float) $kpis->occupancy_rate,
            'open_work_orders' => (float) $kpis->open_work_orders,
            'work_order_days_open' => $this->getMaxWorkOrderDaysOpen(),
            default => null,
        };
    }

    /**
     * Get the maximum days any work order has been open.
     */
    private function getMaxWorkOrderDaysOpen(): float
    {
        $oldestOpenWorkOrder = WorkOrder::open()
            ->orderBy('opened_at')
            ->first();

        if (! $oldestOpenWorkOrder) {
            return 0;
        }

        return (float) $oldestOpenWorkOrder->days_open;
    }

    /**
     * Send an alert notification.
     */
    private function sendAlert(AlertRule $rule, float $currentValue): void
    {
        Log::info('Alert triggered', [
            'rule' => $rule->name,
            'metric' => $rule->metric,
            'threshold' => $rule->threshold,
            'current_value' => $currentValue,
        ]);

        $recipients = $rule->recipients;

        if (empty($recipients)) {
            Log::warning('No recipients configured for alert rule', ['rule' => $rule->name]);

            return;
        }

        // Build the alert message
        $message = $this->buildAlertMessage($rule, $currentValue);

        // Send notification
        Notification::route('mail', $recipients)
            ->notify(new AlertNotification($rule, $currentValue, $message));

        // Mark the rule as triggered
        $rule->markAsTriggered();
    }

    /**
     * Build a friendly alert message.
     *
     * Note: The message style avoids hyphens as per requirements.
     */
    private function buildAlertMessage(AlertRule $rule, float $currentValue): string
    {
        $formattedValue = $this->formatMetricValue($rule->metric, $currentValue);
        $formattedThreshold = $this->formatMetricValue($rule->metric, $rule->threshold);
        $comparison = $this->getComparisonText($rule->operator);

        $messages = [
            'vacancy_count' => "Your portfolio currently has {$formattedValue} vacant units, which is {$comparison} your threshold of {$formattedThreshold}. Consider reviewing your marketing strategy to fill these vacancies.",

            'delinquency_amount' => "The total delinquency amount is now {$formattedValue}, which is {$comparison} your threshold of {$formattedThreshold}. You may want to follow up with tenants who have outstanding balances.",

            'occupancy_rate' => "Your occupancy rate is currently at {$formattedValue}, which is {$comparison} your target of {$formattedThreshold}. This might be a good time to review your leasing strategy.",

            'open_work_orders' => "There are currently {$formattedValue} open work orders, which is {$comparison} your threshold of {$formattedThreshold}. Consider allocating additional resources to maintenance.",

            'work_order_days_open' => "You have work orders that have been open for {$formattedValue} days, which is {$comparison} your threshold of {$formattedThreshold} days. Prioritizing older work orders can help improve tenant satisfaction.",
        ];

        return $messages[$rule->metric] ?? "Alert: {$rule->name} has been triggered. Current value: {$formattedValue}, Threshold: {$formattedThreshold}.";
    }

    /**
     * Format a metric value for display.
     */
    private function formatMetricValue(string $metric, float $value): string
    {
        return match ($metric) {
            'delinquency_amount' => '$'.number_format($value, 2),
            'occupancy_rate' => number_format($value, 1).'%',
            'work_order_days_open' => (int) $value.' days',
            default => (string) (int) $value,
        };
    }

    /**
     * Get comparison text for an operator.
     */
    private function getComparisonText(string $operator): string
    {
        return match ($operator) {
            'gt' => 'greater than',
            'gte' => 'at or above',
            'lt' => 'less than',
            'lte' => 'at or below',
            'eq' => 'equal to',
            default => 'compared to',
        };
    }

    /**
     * Send a test notification to verify email configuration.
     */
    public function sendTestNotification(string $email): void
    {
        $testRule = new AlertRule([
            'name' => 'Test Alert',
            'metric' => 'vacancy_count',
            'operator' => 'gt',
            'threshold' => 0,
            'recipients' => [$email],
        ]);

        Notification::route('mail', [$email])
            ->notify(new AlertNotification(
                $testRule,
                0,
                'This is a test notification from PMPulse. If you received this email, your notification system is working correctly.'
            ));

        Log::info('Test notification sent', ['email' => $email]);
    }
}
