<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Performs the decimal operations used by invoice reconciliation without binary floats.
 */
final class Einvoice_pro_decimal
{
    /**
     * Returns a decimal at the requested scale using half-up rounding.
     *
     * @param int|string $value Decimal value received from Perfex or a fixture.
     */
    public static function normalize($value, int $scale = 2): string
    {
        [$negative, $digits, $sourceScale] = self::parse($value);
        $digits = self::rescaleMagnitude($digits, $sourceScale, $scale);

        return self::format($negative, $digits, $scale);
    }

    /**
     * Adds two decimal values and returns a normalized result.
     *
     * @param int|string $left
     * @param int|string $right
     */
    public static function add($left, $right, int $scale = 2): string
    {
        $leftInteger = self::scaledInteger($left, $scale);
        $rightInteger = self::scaledInteger($right, $scale);

        return self::formatSignedInteger(self::addSigned($leftInteger, $rightInteger), $scale);
    }

    /**
     * Subtracts the second decimal value from the first.
     *
     * @param int|string $left
     * @param int|string $right
     */
    public static function subtract($left, $right, int $scale = 2): string
    {
        $rightInteger = self::scaledInteger($right, $scale);
        $rightInteger = $rightInteger[0] === '-'
            ? substr($rightInteger, 1)
            : ($rightInteger === '0' ? '0' : '-' . $rightInteger);

        return self::formatSignedInteger(
            self::addSigned(self::scaledInteger($left, $scale), $rightInteger),
            $scale
        );
    }

    /**
     * Multiplies two decimal values with one final rounding step.
     *
     * @param int|string $left
     * @param int|string $right
     */
    public static function multiply($left, $right, int $scale = 2): string
    {
        [$leftNegative, $leftDigits, $leftScale] = self::parse($left);
        [$rightNegative, $rightDigits, $rightScale] = self::parse($right);
        $digits = self::multiplyMagnitudes($leftDigits, $rightDigits);
        $digits = self::rescaleMagnitude($digits, $leftScale + $rightScale, $scale);

        return self::format($leftNegative !== $rightNegative, $digits, $scale);
    }

    /**
     * Calculates a percentage of a decimal amount.
     *
     * @param int|string $amount
     * @param int|string $rate
     */
    public static function percentage($amount, $rate, int $scale = 2): string
    {
        [$amountNegative, $amountDigits, $amountScale] = self::parse($amount);
        [$rateNegative, $rateDigits, $rateScale] = self::parse($rate);
        $digits = self::multiplyMagnitudes($amountDigits, $rateDigits);
        $digits = self::rescaleMagnitude($digits, $amountScale + $rateScale + 2, $scale);

        return self::format($amountNegative !== $rateNegative, $digits, $scale);
    }

    /**
     * Divides two decimal values with half-up rounding at the requested scale.
     *
     * @param int|string $dividend
     * @param int|string $divisor
     */
    public static function divide($dividend, $divisor, int $scale = 2): string
    {
        [$dividendNegative, $dividendDigits, $dividendScale] = self::parse($dividend);
        [$divisorNegative, $divisorDigits, $divisorScale] = self::parse($divisor);
        if ($divisorDigits === '0') {
            throw new Einvoice_pro_validation_exception('decimal.division', 'A decimal division has a zero divisor.');
        }

        $numerator = $dividendDigits . str_repeat('0', $divisorScale + $scale + 1);
        $denominator = $divisorDigits . str_repeat('0', $dividendScale);
        $quotient = self::divideMagnitudes($numerator, $denominator);
        $digits = self::rescaleMagnitude($quotient, 1, 0);

        return self::format($dividendNegative !== $divisorNegative, $digits, $scale);
    }

    /**
     * Compares two decimal values at a common scale.
     *
     * @param int|string $left
     * @param int|string $right
     */
    public static function compare($left, $right, int $scale = 6): int
    {
        $difference = self::scaledInteger(self::subtract($left, $right, $scale), $scale);

        if ($difference === '0') {
            return 0;
        }

        return $difference[0] === '-' ? -1 : 1;
    }

    /**
     * Reports whether a decimal is zero at the selected scale.
     *
     * @param int|string $value
     */
    public static function isZero($value, int $scale = 6): bool
    {
        return self::scaledInteger($value, $scale) === '0';
    }

    /**
     * Returns the positive representation of a decimal value.
     *
     * @param int|string $value
     */
    public static function absolute($value, int $scale = 2): string
    {
        $normalized = self::normalize($value, $scale);

        return $normalized[0] === '-' ? substr($normalized, 1) : $normalized;
    }

    /**
     * Parses a decimal into a sign, an unsigned magnitude, and its scale.
     *
     * @param int|string $value
     * @return array{0: bool, 1: string, 2: int}
     */
    private static function parse($value): array
    {
        if (!is_int($value) && !is_string($value)) {
            throw new Einvoice_pro_validation_exception(
                'decimal.invalid',
                'Decimal values must be strings or integers.'
            );
        }

        $value = trim((string) $value);
        if (!preg_match('/^-?(?:0|[1-9][0-9]{0,29})(?:\.[0-9]{1,12})?$/D', $value)) {
            throw new Einvoice_pro_validation_exception('decimal.invalid', 'A decimal value is invalid or too large.');
        }

        $negative = $value[0] === '-';
        if ($negative) {
            $value = substr($value, 1);
        }

        $parts = explode('.', $value, 2);
        $fraction = $parts[1] ?? '';
        $digits = ltrim($parts[0] . $fraction, '0');
        $digits = $digits === '' ? '0' : $digits;

        while ($fraction !== '' && substr($fraction, -1) === '0') {
            $fraction = substr($fraction, 0, -1);
            $digits = strlen($digits) > 1 ? substr($digits, 0, -1) : '0';
        }

        return [$negative && $digits !== '0', $digits, strlen($fraction)];
    }

    /**
     * Converts a decimal to a signed integer at the chosen scale.
     *
     * @param int|string $value
     */
    private static function scaledInteger($value, int $scale): string
    {
        [$negative, $digits, $sourceScale] = self::parse($value);
        $digits = self::rescaleMagnitude($digits, $sourceScale, $scale);

        return $negative && $digits !== '0' ? '-' . $digits : $digits;
    }

    /**
     * Changes an unsigned integer magnitude from one decimal scale to another.
     */
    private static function rescaleMagnitude(string $digits, int $sourceScale, int $targetScale): string
    {
        if ($targetScale < 0 || $targetScale > 12) {
            throw new InvalidArgumentException('Decimal scale is outside the supported range.');
        }

        if ($sourceScale <= $targetScale) {
            return self::trimMagnitude($digits . str_repeat('0', $targetScale - $sourceScale));
        }

        $drop = $sourceScale - $targetScale;
        $padded = str_pad($digits, $drop + 1, '0', STR_PAD_LEFT);
        $keepLength = strlen($padded) - $drop;
        $kept = substr($padded, 0, $keepLength);
        $firstDropped = (int) $padded[$keepLength];

        if ($firstDropped >= 5) {
            $kept = self::addMagnitudes($kept, '1');
        }

        return self::trimMagnitude($kept);
    }

    /**
     * Formats a magnitude as a fixed-scale decimal.
     */
    private static function format(bool $negative, string $digits, int $scale): string
    {
        $digits = self::trimMagnitude($digits);
        $negative = $negative && $digits !== '0';

        if ($scale === 0) {
            return ($negative ? '-' : '') . $digits;
        }

        $digits = str_pad($digits, $scale + 1, '0', STR_PAD_LEFT);
        $integer = substr($digits, 0, -$scale);
        $fraction = substr($digits, -$scale);

        return ($negative ? '-' : '') . $integer . '.' . $fraction;
    }

    /**
     * Formats a signed scaled integer as a decimal.
     */
    private static function formatSignedInteger(string $integer, int $scale): string
    {
        $negative = $integer[0] === '-';
        $digits = $negative ? substr($integer, 1) : $integer;

        return self::format($negative, $digits, $scale);
    }

    /**
     * Adds two signed integer strings.
     */
    private static function addSigned(string $left, string $right): string
    {
        $leftNegative = $left[0] === '-';
        $rightNegative = $right[0] === '-';
        $leftMagnitude = $leftNegative ? substr($left, 1) : $left;
        $rightMagnitude = $rightNegative ? substr($right, 1) : $right;

        if ($leftNegative === $rightNegative) {
            $sum = self::addMagnitudes($leftMagnitude, $rightMagnitude);

            return $leftNegative && $sum !== '0' ? '-' . $sum : $sum;
        }

        $comparison = self::compareMagnitudes($leftMagnitude, $rightMagnitude);
        if ($comparison === 0) {
            return '0';
        }

        if ($comparison > 0) {
            $difference = self::subtractMagnitudes($leftMagnitude, $rightMagnitude);

            return $leftNegative ? '-' . $difference : $difference;
        }

        $difference = self::subtractMagnitudes($rightMagnitude, $leftMagnitude);

        return $rightNegative ? '-' . $difference : $difference;
    }

    /**
     * Compares two unsigned integer magnitudes.
     */
    private static function compareMagnitudes(string $left, string $right): int
    {
        $left = self::trimMagnitude($left);
        $right = self::trimMagnitude($right);

        if (strlen($left) !== strlen($right)) {
            return strlen($left) <=> strlen($right);
        }

        return strcmp($left, $right) <=> 0;
    }

    /**
     * Adds two unsigned integer magnitudes.
     */
    private static function addMagnitudes(string $left, string $right): string
    {
        $carry = 0;
        $result = '';
        $leftIndex = strlen($left) - 1;
        $rightIndex = strlen($right) - 1;

        while ($leftIndex >= 0 || $rightIndex >= 0 || $carry > 0) {
            $sum = $carry;
            $sum += $leftIndex >= 0 ? (int) $left[$leftIndex--] : 0;
            $sum += $rightIndex >= 0 ? (int) $right[$rightIndex--] : 0;
            $result = (string) ($sum % 10) . $result;
            $carry = intdiv($sum, 10);
        }

        return self::trimMagnitude($result);
    }

    /**
     * Subtracts a smaller unsigned magnitude from a larger one.
     */
    private static function subtractMagnitudes(string $larger, string $smaller): string
    {
        $borrow = 0;
        $result = '';
        $largeIndex = strlen($larger) - 1;
        $smallIndex = strlen($smaller) - 1;

        while ($largeIndex >= 0) {
            $digit = (int) $larger[$largeIndex--] - $borrow;
            $subtract = $smallIndex >= 0 ? (int) $smaller[$smallIndex--] : 0;
            if ($digit < $subtract) {
                $digit += 10;
                $borrow = 1;
            } else {
                $borrow = 0;
            }

            $result = (string) ($digit - $subtract) . $result;
        }

        return self::trimMagnitude($result);
    }

    /**
     * Multiplies two unsigned integer magnitudes.
     */
    private static function multiplyMagnitudes(string $left, string $right): string
    {
        if ($left === '0' || $right === '0') {
            return '0';
        }

        $result = array_fill(0, strlen($left) + strlen($right), 0);
        for ($leftIndex = strlen($left) - 1; $leftIndex >= 0; $leftIndex--) {
            for ($rightIndex = strlen($right) - 1; $rightIndex >= 0; $rightIndex--) {
                $position = $leftIndex + $rightIndex + 1;
                $product = ((int) $left[$leftIndex] * (int) $right[$rightIndex]) + $result[$position];
                $result[$position] = $product % 10;
                $result[$position - 1] += intdiv($product, 10);
            }
        }

        return self::trimMagnitude(implode('', $result));
    }

    /**
     * Performs long division on two unsigned integer magnitudes.
     */
    private static function divideMagnitudes(string $dividend, string $divisor): string
    {
        $divisor = self::trimMagnitude($divisor);
        $remainder = '0';
        $quotient = '';

        for ($index = 0, $length = strlen($dividend); $index < $length; $index++) {
            $remainder = self::trimMagnitude($remainder . $dividend[$index]);
            $digit = 0;
            while (self::compareMagnitudes($remainder, $divisor) >= 0) {
                $remainder = self::subtractMagnitudes($remainder, $divisor);
                $digit++;
            }
            $quotient .= (string) $digit;
        }

        return self::trimMagnitude($quotient);
    }

    /**
     * Removes insignificant leading zeroes without returning an empty string.
     */
    private static function trimMagnitude(string $digits): string
    {
        $digits = ltrim($digits, '0');

        return $digits === '' ? '0' : $digits;
    }
}
