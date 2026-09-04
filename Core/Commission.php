<?php
namespace Core;

class Commission
{
    /**
     * Self-healing schema check: automatically creates sales_agents & commission tables if missing.
     */
    public static function ensureTables(\PDO $pdo): void
    {
        static $ensured = false;
        if ($ensured) return;
        $ensured = true;

        try {
            $pdo->query("SELECT 1 FROM sales_agents LIMIT 1");
        } catch (\PDOException $e) {
            if ($e->getCode() == '42S02' || str_contains($e->getMessage(), 'sales_agents') || str_contains($e->getMessage(), "doesn't exist")) {
                require_once __DIR__ . '/../migrate.php';
                run_migrations($pdo);
            }
        }
    }

    /**
     * Calculate commission amount for a given agent and invoice context.
     *
     * @param array $agent Agent row from database
     * @param array $invoice Invoice data containing 'total', 'subtotal', and optionally 'paid_amount'
     * @return float Calculated commission rounded to 2 decimal places
     */
    public static function calculate(array $agent, array $invoice): float
    {
        $total = max(0, (float)($invoice['total'] ?? 0));
        $subtotal = max(0, (float)($invoice['subtotal'] ?? 0));
        $paid = max(0, (float)($invoice['paid_amount'] ?? 0));

        // Determine calculation base
        $baseType = $agent['calculation_base'] ?? 'total';
        $baseAmount = match ($baseType) {
            'subtotal'    => $subtotal,
            'paid_amount' => $paid,
            default       => $total
        };

        if ($baseAmount <= 0) {
            return 0.00;
        }

        $type = $agent['commission_type'] ?? 'percentage';
        $rate = (float)($agent['rate'] ?? 0);

        switch ($type) {
            case 'percentage':
                return round($baseAmount * ($rate / 100), 2);

            case 'slab':
                $slabData = $agent['slab_json'] ?? [];
                if (is_string($slabData)) {
                    $slabData = json_decode($slabData, true) ?: [];
                }

                if (empty($slabData)) {
                    return round($baseAmount * ($rate / 100), 2);
                }

                // Progressive marginal tiered calculation
                $commission = 0.0;
                foreach ($slabData as $tier) {
                    $from = max(0, (float)($tier['from'] ?? 0));
                    $to = (isset($tier['to']) && $tier['to'] !== null && $tier['to'] !== '') 
                        ? max(0, (float)$tier['to']) 
                        : null;
                    $tierRate = (float)($tier['rate'] ?? 0);

                    if ($baseAmount > $from) {
                        $slice = ($to !== null) ? (min($baseAmount, $to) - $from) : ($baseAmount - $from);
                        if ($slice > 0) {
                            $commission += $slice * ($tierRate / 100);
                        }
                    }
                }
                return round(max(0, $commission), 2);

            case 'fixed':
                return round($rate, 2);

            case 'milestone':
                $target = max(0, (float)($agent['milestone_target'] ?? 0));
                $milestoneRate = (float)($agent['milestone_rate'] ?? 0);
                if ($target > 0 && $baseAmount >= $target) {
                    return round($baseAmount * ($milestoneRate / 100), 2);
                }
                return round($baseAmount * ($rate / 100), 2);

            case 'custom':
                $formula = trim($agent['custom_formula'] ?? '');
                if (empty($formula)) {
                    return round($baseAmount * ($rate / 100), 2);
                }
                return round(self::evaluateFormula($formula, [
                    'amount'      => $baseAmount,
                    'total'       => $total,
                    'subtotal'    => $subtotal,
                    'paid_amount' => $paid,
                    'rate'        => $rate
                ]), 2);

            default:
                return round($baseAmount * ($rate / 100), 2);
        }
    }

    /**
     * Safely evaluate a basic mathematical expression formula with variable replacements.
     */
    public static function evaluateFormula(string $formula, array $vars): float
    {
        // Replace tokens
        foreach ($vars as $k => $v) {
            $formula = str_ireplace('{' . $k . '}', (string)(float)$v, $formula);
        }

        // Clean formula: only allow digits, decimal points, spaces, and math operators + - * / ( )
        $sanitized = preg_replace('/[^0-9\.\+\-\*\/\(\)\s]/', '', $formula);
        if (trim($sanitized) === '') {
            return 0.0;
        }

        try {
            // Safe evaluation using isolated arithmetic tokens
            $calc = @eval("return max(0, (float)({$sanitized}));");
            return is_numeric($calc) ? (float)$calc : 0.0;
        } catch (\Throwable $e) {
            return 0.0;
        }
    }

    /**
     * Return a concise badge label for the commission rule.
     */
    public static function getBadge(array $agent): string
    {
        $type = $agent['commission_type'] ?? 'percentage';
        $base = match ($agent['calculation_base'] ?? 'total') {
            'subtotal'    => 'Subtotal',
            'paid_amount' => 'Collected',
            default       => 'Total'
        };

        switch ($type) {
            case 'percentage':
                $r = (float)($agent['rate'] ?? 0);
                return rtrim(rtrim(number_format($r, 2), '0'), '.') . "% • On $base";

            case 'slab':
                $slabs = $agent['slab_json'] ?? [];
                if (is_string($slabs)) $slabs = json_decode($slabs, true) ?: [];
                $rates = array_filter(array_column($slabs, 'rate'), 'is_numeric');
                if ($rates) {
                    $min = min($rates);
                    $max = max($rates);
                    $span = ($min == $max) ? "{$min}%" : "{$min}%-{$max}%";
                    return "Slab ($span) • On $base";
                }
                return "Tiered Slab • On $base";

            case 'fixed':
                $r = (float)($agent['rate'] ?? 0);
                return "Fixed " . number_format($r, 2) . " • Per Deal";

            case 'milestone':
                $target = (float)($agent['milestone_target'] ?? 0);
                $mRate = (float)($agent['milestone_rate'] ?? 0);
                return "Target " . number_format($target, 0) . " ({$mRate}%) • On $base";

            case 'custom':
                return "Custom Formula • On $base";

            default:
                return "Standard Rate";
        }
    }

    /**
     * Return a human-readable detailed explanation of how commission is calculated.
     */
    public static function getDetails(array $agent): string
    {
        $baseText = match ($agent['calculation_base'] ?? 'total') {
            'subtotal'    => 'Invoice Subtotal (excluding VAT/tax)',
            'paid_amount' => 'Paid Amount (only collected client payments)',
            default       => 'Invoice Total Gross Amount (inclusive of tax)'
        };

        $type = $agent['commission_type'] ?? 'percentage';

        return match ($type) {
            'percentage' => number_format((float)$agent['rate'], 2) . "% calculated on " . $baseText,
            'slab'       => "Progressive tiered slab bands calculated on " . $baseText,
            'fixed'      => "Fixed amount of " . number_format((float)$agent['rate'], 2) . " per invoice",
            'milestone'  => "Base " . number_format((float)$agent['rate'], 2) . "%, upgraded to " . number_format((float)$agent['milestone_rate'], 2) . "% when hitting milestone target of " . number_format((float)$agent['milestone_target'], 2),
            'custom'     => "Custom math formula: " . ($agent['custom_formula'] ?? '{amount} * rate'),
            default      => "Standard commission rate"
        };
    }
}
