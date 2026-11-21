<?php

namespace Tests\Unit;

use App\Services\OvertimeCalculationService;
use PHPUnit\Framework\TestCase;

class OvertimeCalculationServiceTest extends TestCase
{
    private OvertimeCalculationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new OvertimeCalculationService();
    }

    public function test_calculate_planned_hours_handles_daytime_overnight_and_empty_inputs(): void
    {
        $this->assertSame(3.5, $this->service->calculatePlannedHours('2026-11-18', '16:00:00', '19:30:00'));
        $this->assertSame(4.0, $this->service->calculatePlannedHours('2026-11-18', '22:00:00', '02:00:00'));
        $this->assertSame(0.0, $this->service->calculatePlannedHours('2026-11-18', '00:00:00', '17:00:00'));
        $this->assertSame(0.0, $this->service->calculatePlannedHours('2026-11-18', '16:00:00', '16:00:00'));
        $this->assertSame(0.0, $this->service->calculatePlannedHours(null, '16:00:00', '19:00:00'));
    }

    public function test_calculate_realization_seconds_prefers_override_and_handles_overnight(): void
    {
        $this->assertSame(
            9000,
            $this->service->calculateRealizationSeconds('2026-11-18', '07:30:00', '19:00:00', '16:00:00', '18:30:00')
        );

        $this->assertSame(
            14400,
            $this->service->calculateRealizationSeconds('2026-11-18', '08:00:00', '12:00:00', null, null)
        );

        $this->assertSame(
            16200,
            $this->service->calculateRealizationSeconds('2026-11-18', null, null, '22:00:00', '02:30:00')
        );

        $this->assertSame(
            0,
            $this->service->calculateRealizationSeconds('2026-11-18', '08:00:00', '00:00:00', null, null)
        );
    }

    public function test_unverified_overtime_returns_zero_values(): void
    {
        $result = $this->service->calculateVerifiedOvertime([
            'is_verified'     => false,
            'overtime_type'   => 1,
            'attendance_date' => '2026-11-18',
            'jam_in2'         => '16:00:00',
            'jam_out2'        => '19:00:00',
        ]);

        $this->assertSame(0, $result['raw_minutes']);
        $this->assertSame(0.0, $result['counted_hours']);
        $this->assertSame(0.0, $result['amount']);
    }

    public function test_type_1_workday_overtime_matches_native_formula(): void
    {
        $cases = [
            ['16:45:00', 0.0],
            ['17:00:00', 1.5],
            ['18:00:00', 3.5],
            ['19:59:00', 7.47],
            ['20:00:00', 5.5],
            ['21:00:00', 7.5],
        ];

        foreach ($cases as [$endTime, $expectedCountedHours]) {
            $result = $this->service->calculateVerifiedOvertime([
                'is_verified'        => true,
                'overtime_type'      => 1,
                'attendance_date'    => '2026-11-18',
                'jam_in2'            => '16:00:00',
                'jam_out2'           => $endTime,
                'position_legacy_id' => 5,
            ]);

            $this->assertSame($expectedCountedHours, $result['counted_hours'], "Failed at end time {$endTime}");
        }
    }

    public function test_type_2_weekend_overtime_matches_native_formula(): void
    {
        $cases = [
            ['11:00:00', 6.0],
            ['13:00:00', 8.0],
            ['16:30:00', 15.5],
            ['17:30:00', 19.0],
            ['18:30:00', 23.0],
        ];

        foreach ($cases as [$endTime, $expectedCountedHours]) {
            $result = $this->service->calculateVerifiedOvertime([
                'is_verified'        => true,
                'overtime_type'      => 2,
                'attendance_date'    => '2026-11-15',
                'jam_in2'            => '08:00:00',
                'jam_out2'           => $endTime,
                'position_legacy_id' => 5,
            ]);

            $this->assertSame($expectedCountedHours, $result['counted_hours'], "Failed at end time {$endTime}");
        }
    }

    public function test_type_3_holiday_overtime_matches_native_formula(): void
    {
        $cases = [
            ['11:00:00', 6.0],
            ['13:00:00', 8.0],
            ['16:00:00', 19.0],
            ['17:00:00', 23.0],
        ];

        foreach ($cases as [$endTime, $expectedCountedHours]) {
            $result = $this->service->calculateVerifiedOvertime([
                'is_verified'        => true,
                'overtime_type'      => 3,
                'attendance_date'    => '2026-11-17',
                'jam_in2'            => '08:00:00',
                'jam_out2'           => $endTime,
                'position_legacy_id' => 5,
            ]);

            $this->assertSame($expectedCountedHours, $result['counted_hours'], "Failed at end time {$endTime}");
        }
    }

    public function test_type_4_flextime_overtime_matches_native_formula(): void
    {
        $cases = [
            ['16:45:00', 0.0],
            ['17:00:00', 1.5],
            ['20:00:00', 7.5],
            ['20:01:00', 5.53],
        ];

        foreach ($cases as [$endTime, $expectedCountedHours]) {
            $result = $this->service->calculateVerifiedOvertime([
                'is_verified'        => true,
                'overtime_type'      => 4,
                'attendance_date'    => '2026-11-18',
                'jam_in2'            => '16:00:00',
                'jam_out2'           => $endTime,
                'position_legacy_id' => 5,
            ]);

            $this->assertSame($expectedCountedHours, $result['counted_hours'], "Failed at end time {$endTime}");
        }
    }

    public function test_atasan_positions_2_and_3_use_real_hours_after_break_and_hourly_rate(): void
    {
        foreach ([2, 3] as $posId) {
            $underFour = $this->service->calculateVerifiedOvertime([
                'is_verified'        => true,
                'overtime_type'      => 1,
                'attendance_date'    => '2026-11-18',
                'jam_in2'            => '16:00:00',
                'jam_out2'           => '19:00:00',
                'position_legacy_id' => $posId,
                'hourly_rate'        => 50000,
                'minute_rate'        => 833.33,
            ]);
            $this->assertSame(3.0, $underFour['counted_hours']);
            $this->assertSame(150000.0, $underFour['amount']);

            $overFour = $this->service->calculateVerifiedOvertime([
                'is_verified'        => true,
                'overtime_type'      => 1,
                'attendance_date'    => '2026-11-18',
                'jam_in2'            => '16:00:00',
                'jam_out2'           => '21:00:00',
                'position_legacy_id' => $posId,
                'hourly_rate'        => 50000,
                'minute_rate'        => 833.33,
            ]);
            $this->assertSame(4.0, $overFour['counted_hours']);
            $this->assertSame(200000.0, $overFour['amount']);
        }
    }

    public function test_special_shift_or_weekend_uses_jam_in_when_later_than_jam_in2(): void
    {
        $weekendLaterCheckIn = $this->service->calculateVerifiedOvertime([
            'is_verified'        => true,
            'overtime_type'      => 2,
            'attendance_date'    => '2026-11-15',
            'jam_in'             => '08:30:00',
            'jam_in2'            => '08:00:00',
            'jam_out2'           => '11:30:00',
            'position_legacy_id' => 5,
        ]);
        $this->assertSame(6.0, $weekendLaterCheckIn['counted_hours']);

        $shift13LaterCheckIn = $this->service->calculateVerifiedOvertime([
            'is_verified'          => true,
            'overtime_type'        => 1,
            'work_shift_legacy_id' => 13,
            'attendance_date'      => '2026-11-18',
            'jam_in'               => '16:30:00',
            'jam_in2'              => '16:00:00',
            'jam_out2'             => '17:30:00',
            'position_legacy_id'   => 5,
        ]);
        $this->assertSame(1.5, $shift13LaterCheckIn['counted_hours']);
    }

    public function test_pelaksana_nominal_multiplies_counted_minutes_by_minute_rate(): void
    {
        $result = $this->service->calculateVerifiedOvertime([
            'is_verified'        => true,
            'overtime_type'      => 1,
            'attendance_date'    => '2026-11-18',
            'jam_in2'            => '16:00:00',
            'jam_out2'           => '18:00:00',
            'position_legacy_id' => 5,
            'minute_rate'        => 500.0,
        ]);

        $this->assertSame(210.0, $result['counted_minutes']);
        $this->assertSame(3.5, $result['counted_hours']);
        $this->assertSame(105000.0, $result['amount']);
    }
}
