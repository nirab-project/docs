<?php

namespace Modules\ServiceManagement\Services;

use Carbon\CarbonInterface;
use Illuminate\Validation\ValidationException;
use Modules\ServiceManagement\DTOs\PricingBreakdown;
use Modules\ServiceManagement\Entities\PricingRule;
use Modules\ServiceManagement\Entities\Service;
use Modules\ServiceManagement\Entities\Variation;

class PricingEngine
{
    public function calculate(
        Service $service,
        Variation $variation,
        int $quantity,
        string $zoneId,
        ?string $providerId,
        ?string $areaId,
        CarbonInterface $at,
        float $discount = 0,
        array $fieldAnswers = [],
    ): PricingBreakdown {
        if ($service->pricing_type !== 'fixed') {
            throw ValidationException::withMessages(['service_id' => 'service_requires_quotation']);
        }

        if ($variation->service_id !== $service->id) {
            throw ValidationException::withMessages(['variant_key' => 'option_does_not_belong_to_service']);
        }

        $variation = app(ServicePriceResolver::class)->resolve($service->id, $variation->variant_key, $zoneId);
        if (!$service->is_active || $service->deleted_at
            || !app(DirectServicePolicy::class)->catalog($zoneId)->whereKey($service->id)->exists()) {
            throw ValidationException::withMessages(['service_id' => 'service_price_unavailable']);
        }
        $requirements = app(ServiceRequirements::class)->evaluate($service, $variation, $quantity, $fieldAnswers);
        $basePrice = round((float) $variation->price * $quantity, 3);
        $running = round($basePrice + $requirements['price_adjustment'], 3);
        if ($running <= 0) throw ValidationException::withMessages(['service_id' => 'service_price_unavailable']);
        $adjustments = [];
        if ($requirements['price_adjustment'] != 0) $adjustments[] = ['scope' => 'options', 'amount' => $requirements['price_adjustment'], 'subtotal' => $running];

        $rules = PricingRule::applicable($service->id, $zoneId, $providerId, $areaId, $at)->get();
        foreach (['branch', 'neighborhood', 'peak'] as $scope) {
            foreach ($rules->where('scope', $scope) as $rule) {
                if (!$rule->appliesAt($at)) {
                    continue;
                }

                $amount = $rule->adjustment_type === 'percentage'
                    ? round($running * ((float) $rule->adjustment_value / 100), 3)
                    : round((float) $rule->adjustment_value, 3);
                $running = max(0, round($running + $amount, 3));
                $adjustments[] = [
                    'rule_id' => $rule->id,
                    'name' => $rule->name,
                    'scope' => $rule->scope,
                    'type' => $rule->adjustment_type,
                    'value' => (float) $rule->adjustment_value,
                    'amount' => $amount,
                    'subtotal' => $running,
                ];
            }
        }

        if ($running <= 0) throw ValidationException::withMessages(['service_id' => 'service_price_unavailable']);
        $discount = min(max(0, round($discount, 3)), $running);
        $afterDiscount = round($running - $discount, 3);
        $tax = round($afterDiscount * ((float) $service->tax / 100), 3);

        return new PricingBreakdown(
            basePrice: $basePrice,
            adjustments: $adjustments,
            adjustedPrice: $running,
            discount: $discount,
            tax: $tax,
            total: round($afterDiscount + $tax, 3),
            currency: (string) (business_config('currency_code', 'business_information')?->live_values ?? 'SAR'),
            execution: $requirements,
            priceSource: ['variation_id' => $variation->id, 'scope' => $variation->zone_id === null ? 'global' : 'zone', 'zone_id' => $variation->zone_id],
        );
    }
}
