<?php

namespace App\Http\Helpers;

/**
 * Helper class for calculation-related utilities.
 *
 * Provides methods for currency conversion and tax calculations.
 */
class CalculationHelper
{
    /**
     * Convert currency string to numeric value.
     *
     * Removes currency symbols and commas from a string and converts to float.
     * Returns 0 if the value is empty or null.
     *
     * @param mixed $value Currency value (e.g., "$1,234.56")
     * @return float Numeric value
     */
    public static function convertToNumber($value)
    {
        if (!$value) {
            return 0;
        }
        return floatval(str_replace(['$', ','], '', $value));
    }

    /**
     * Calculate VAT (IVA) based on 16% assumption.
     *
     * Calculates the Mexican VAT (IVA) at 16% rate for a given amount.
     * Rounds the result to 2 decimal places.
     *
     * @param float $amount The base amount to calculate IVA on
     * @return float IVA amount rounded to 2 decimal places
     */
    public static function calculateIVA($amount)
    {
        return round($amount * 0.16, 2);
    }
}
