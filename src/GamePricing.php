<?php

declare(strict_types=1);

if (!function_exists('mb_strtolower')) {
    function mb_strtolower($string, $encoding = null): string
    {
        return strtolower((string)$string);
    }
}

final class GamePricing
{
    private float $defaultMarkupPercent;
    private int $fixedMarkup;
    private int $roundTo;
    /** @var array<string,float> */
    private array $categoryMarkupPercent;

    public function __construct(array $config)
    {
        $this->defaultMarkupPercent = (float)($config['default_markup_percent'] ?? 0);
        $this->fixedMarkup = max(0, (int)($config['fixed_markup'] ?? 0));
        $this->roundTo = max(0, (int)($config['round_to'] ?? 100));

        $rawCategoryMarkup = (array)($config['category_markup_percent'] ?? []);
        $normalized = [];
        foreach ($rawCategoryMarkup as $category => $percent) {
            $key = trim((string)$category);
            if ($key === '') {
                continue;
            }
            $normalized[mb_strtolower($key)] = (float)$percent;
        }
        $this->categoryMarkupPercent = $normalized;
    }

    public function sellPrice(int $buyPrice, array $service = []): int
    {
        $buyPrice = max(0, $buyPrice);
        if ($buyPrice <= 0) {
            return 0;
        }

        $markupPercent = $this->resolveMarkupPercent($service);
        $sell = (float)$buyPrice;
        if ($markupPercent !== 0.0) {
            $sell *= (100 + $markupPercent) / 100;
        }
        $sell += (float)$this->fixedMarkup;
        $sell = max(1.0, $sell);

        if ($this->roundTo > 0) {
            $sell = ceil($sell / $this->roundTo) * $this->roundTo;
        }

        return (int)round($sell);
    }

    public function totalSell(int $sellPrice, int $qty = 1): int
    {
        $sellPrice = max(0, $sellPrice);
        $qty = max(1, $qty);
        return $sellPrice * $qty;
    }

    private function resolveMarkupPercent(array $service): float
    {
        $category = trim((string)($service['category'] ?? $service['kategori'] ?? ''));
        if ($category !== '') {
            $key = mb_strtolower($category);
            if (array_key_exists($key, $this->categoryMarkupPercent)) {
                return (float)$this->categoryMarkupPercent[$key];
            }
        }

        return $this->defaultMarkupPercent;
    }
}

