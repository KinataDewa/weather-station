<?php

namespace App\Services;

class ReadingProcessor
{
    private const MM_PER_TIP = 0.2;

    public function calculateRainMm(int $prev, int $current): float
    {
        $tips = $current < $prev ? $current : $current - $prev;

        return $tips * self::MM_PER_TIP;
    }

    public function validateReading(float $value, float $min, float $max): array
    {
        if ($value === -999.0) {
            return [
                'quality_flag' => false,
                'quality_note' => 'sensor_error_code',
            ];
        }

        if ($value < $min || $value > $max) {
            return [
                'quality_flag' => false,
                'quality_note' => 'out_of_range',
            ];
        }

        return [
            'quality_flag' => true,
            'quality_note' => null,
        ];
    }

    public function applyCalibration(float $rawValue, float $offset, float $scale): float
    {
        return ($rawValue * $scale) + $offset;
    }
}
