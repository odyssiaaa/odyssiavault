<?php

declare(strict_types=1);

final class Pricing
{
    private array $pricingConfig;

    public function __construct(array $pricingConfig)
    {
        $this->pricingConfig = $pricingConfig;
    }

    public function sellPrice(array $service): int
    {
        return $this->sellPricePer1000($service);
    }

    public function sellPricePer1000(array $service): int
    {
        $buyPrice = (int)($service['price'] ?? 0);
        if ($buyPrice <= 0) {
            return 0;
        }

        $category = mb_strtolower((string)($service['category'] ?? ''));
        $defaultPercent = (float)($this->pricingConfig['default_markup_percent'] ?? 0);
        $categoryMap = (array)($this->pricingConfig['category_markup_percent'] ?? []);
        $fixed = (int)($this->pricingConfig['fixed_markup'] ?? 0);
        $roundTo = max(1, (int)($this->pricingConfig['round_to'] ?? 1));

        $percent = $defaultPercent;
        foreach ($categoryMap as $keyword => $value) {
            if (str_contains($category, mb_strtolower((string)$keyword))) {
                $percent = (float)$value;
                break;
            }
        }

        $raw = $buyPrice + ($buyPrice * $percent / 100) + $fixed;
        return (int)(ceil($raw / $roundTo) * $roundTo);
    }

    public function sellUnitPrice(array $service): float
    {
        $pricePer1000 = $this->sellPricePer1000($service);
        if ($pricePer1000 <= 0) {
            return 0.0;
        }

        return $pricePer1000 / 1000;
    }

    public function totalSell(int $pricePer1000, int $qty): int
    {
        $pricePer1000 = max(0, $pricePer1000);
        $qty = max(0, $qty);
        if ($pricePer1000 <= 0 || $qty <= 0) {
            return 0;
        }

        return (int)ceil(($pricePer1000 * $qty) / 1000);
    }
}
