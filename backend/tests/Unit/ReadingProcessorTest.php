<?php

namespace Tests\Unit;

use App\Services\ReadingProcessor;
use PHPUnit\Framework\TestCase;

class ReadingProcessorTest extends TestCase
{
    private ReadingProcessor $processor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->processor = new ReadingProcessor();
    }

    public function test_rain_counter_naik_menghasilkan_mm_yang_benar(): void
    {
        $mm = $this->processor->calculateRainMm(1040, 1043);

        $this->assertEqualsWithDelta(0.6, $mm, 0.0001);
    }

    public function test_rain_counter_turun_restart_menghitung_dari_nol(): void
    {
        $mm = $this->processor->calculateRainMm(1043, 5);

        $this->assertEquals(1.0, $mm);
    }

    public function test_value_minus_999_menghasilkan_sensor_error_code(): void
    {
        $result = $this->processor->validateReading(-999, -40, 80);

        $this->assertFalse($result['quality_flag']);
        $this->assertEquals('sensor_error_code', $result['quality_note']);
    }

    public function test_humidity_diluar_batas_maksimum_menghasilkan_out_of_range(): void
    {
        $result = $this->processor->validateReading(150, 0, 100);

        $this->assertFalse($result['quality_flag']);
        $this->assertEquals('out_of_range', $result['quality_note']);
    }

    public function test_temp_air_dalam_rentang_valid_menghasilkan_quality_flag_true(): void
    {
        $result = $this->processor->validateReading(27.4, -40, 80);

        $this->assertTrue($result['quality_flag']);
        $this->assertNull($result['quality_note']);
    }

    public function test_kalibrasi_dengan_offset_positif(): void
    {
        $value = $this->processor->applyCalibration(25.0, 1.5, 1.0);

        $this->assertEquals(26.5, $value);
    }

    public function test_kalibrasi_dengan_scale_lebih_dari_satu(): void
    {
        $value = $this->processor->applyCalibration(25.0, 0.0, 1.1);

        $this->assertEqualsWithDelta(27.5, $value, 0.0001);
    }
}
