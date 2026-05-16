<?php

namespace App\Services;

use App\Models\Setting;

class CurrencyFormatterService
{
    protected function resolveCurrency($currency): object
    {
        return (object) [
            'symbol'            => $currency?->symbol ?? Setting::getValue('currency_symbol'),
            'symbol_position'   => $currency?->symbol_position ?? Setting::getValue('currency_symbol_position'),
            'decimal_places'    => $currency?->decimal_places ?? Setting::getValue('decimal_places'),
            'thousand_separator'=> $currency?->thousand_separator ?? Setting::getValue('thousand_separator'),
            'decimal_separator' => $currency?->decimal_separator ?? Setting::getValue('decimal_separator'),
        ];
    }

    public function formatPrice($amount, $currency = null): string
    {
        $currency = $this->resolveCurrency($currency);

        $number = number_format(
            $amount,
            $currency->decimal_places,
            $currency->decimal_separator,
            $currency->thousand_separator
        );

        $position = strtolower((string) $currency->symbol_position);

        return $position === 'right'
            ? $number. ' ' .$currency->symbol
            : $currency->symbol. ' ' .$number;
    }

    public function formatSalaryRange($min, $max, $currency = null): ?string
    {
        if (! $min && ! $max) {
            return null;
        }

        if ($min && ! $max) {
            return __('From'). ' ' .$this->formatPrice($min, $currency);
        }

        if (! $min && $max) {
            return __('Upto'). ' ' .$this->formatPrice($max, $currency);
        }

        return
            $this->formatPrice($min, $currency)
            . ' - '
            . $this->formatPrice($max, $currency);
    }
}
