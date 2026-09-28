<?php

namespace Tests\Unit\Helpers;

use App\Http\Helpers\CalculationHelper;
use PHPUnit\Framework\TestCase;

class CalculationHelperTest extends TestCase
{
    /**
     * Test convertToNumber with currency string.
     *
     * @return void
     */
    public function test_convert_to_number_with_currency_string()
    {
        $result = CalculationHelper::convertToNumber('$1,234.56');
        $this->assertEquals(1234.56, $result);
    }

    /**
     * Test convertToNumber with comma separated number.
     *
     * @return void
     */
    public function test_convert_to_number_with_comma()
    {
        $result = CalculationHelper::convertToNumber('1,234');
        $this->assertEquals(1234, $result);
    }

    /**
     * Test convertToNumber with dollar sign only.
     *
     * @return void
     */
    public function test_convert_to_number_with_dollar_sign()
    {
        $result = CalculationHelper::convertToNumber('$100');
        $this->assertEquals(100, $result);
    }

    /**
     * Test convertToNumber with plain number.
     *
     * @return void
     */
    public function test_convert_to_number_with_plain_number()
    {
        $result = CalculationHelper::convertToNumber('123.45');
        $this->assertEquals(123.45, $result);
    }

    /**
     * Test convertToNumber with null value.
     *
     * @return void
     */
    public function test_convert_to_number_with_null()
    {
        $result = CalculationHelper::convertToNumber(null);
        $this->assertEquals(0, $result);
    }

    /**
     * Test convertToNumber with empty string.
     *
     * @return void
     */
    public function test_convert_to_number_with_empty_string()
    {
        $result = CalculationHelper::convertToNumber('');
        $this->assertEquals(0, $result);
    }

    /**
     * Test convertToNumber with zero.
     *
     * @return void
     */
    public function test_convert_to_number_with_zero()
    {
        $result = CalculationHelper::convertToNumber(0);
        $this->assertEquals(0, $result);
    }

    /**
     * Test calculateIVA with positive amount.
     *
     * @return void
     */
    public function test_calculate_iva_with_positive_amount()
    {
        $result = CalculationHelper::calculateIVA(100);
        $this->assertEquals(16.0, $result);
    }

    /**
     * Test calculateIVA with decimal amount.
     *
     * @return void
     */
    public function test_calculate_iva_with_decimal_amount()
    {
        $result = CalculationHelper::calculateIVA(100.50);
        $this->assertEquals(16.08, $result);
    }

    /**
     * Test calculateIVA with zero.
     *
     * @return void
     */
    public function test_calculate_iva_with_zero()
    {
        $result = CalculationHelper::calculateIVA(0);
        $this->assertEquals(0.0, $result);
    }

    /**
     * Test calculateIVA rounding.
     *
     * @return void
     */
    public function test_calculate_iva_rounding()
    {
        $result = CalculationHelper::calculateIVA(100.33);
        $this->assertEquals(16.05, $result);
    }

    /**
     * Test calculateIVA with large amount.
     *
     * @return void
     */
    public function test_calculate_iva_with_large_amount()
    {
        $result = CalculationHelper::calculateIVA(10000);
        $this->assertEquals(1600.0, $result);
    }
}
