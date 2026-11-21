<?php

namespace App\Services;

use App\Models\SalaryStructure;

class OvertimeCalculationService
{
    /**
     * Normalisasi string waktu ke format HH:MM:SS atau null jika kosong / placeholder native.
     */
    public function normalizeTime(?string $time): ?string
    {
        if ($time === null) {
            return null;
        }

        $trimmed = trim($time);
        if ($trimmed === '' || in_array($trimmed, ['00:00', '00:00:00', '00:00:01'], true)) {
            return null;
        }

        if (preg_match('/^\d{2}:\d{2}$/', $trimmed) === 1) {
            $trimmed .= ':00';
        }

        if (preg_match('/^\d{2}:\d{2}:\d{2}$/', $trimmed) !== 1) {
            return null;
        }

        if (strtotime('2000-01-01 ' . $trimmed) === false) {
            return null;
        }

        return $trimmed;
    }

    /**
     * Hitung total jam rencana pada SPL (replikasi hitungJamAjuan di eSDM native).
     */
    public function calculatePlannedHours(?string $startDate, ?string $startTime, ?string $endTime): float
    {
        $startNorm = $this->normalizeTime($startTime);
        $endNorm   = $this->normalizeTime($endTime);

        if (empty($startDate) || $startNorm === null || $endNorm === null) {
            return 0.0;
        }

        $startTs = strtotime("{$startDate} {$startNorm}");
        if ($startTs === false) {
            return 0.0;
        }

        $endDate = (strtotime("2000-01-01 {$endNorm}") < strtotime("2000-01-01 {$startNorm}"))
            ? date('Y-m-d', strtotime("{$startDate} +1 day"))
            : $startDate;

        $endTs = strtotime("{$endDate} {$endNorm}");
        if ($endTs === false || $endTs <= $startTs) {
            return 0.0;
        }

        return round(($endTs - $startTs) / 3600, 2);
    }

    /**
     * Hitung durasi realisasi lembur dalam detik (replikasi hitungMenitRealisasi & $sum_dur_expr eSDM native).
     */
    public function calculateRealizationSeconds(
        ?string $date,
        ?string $jamIn,
        ?string $jamOut,
        ?string $jamIn2 = null,
        ?string $jamOut2 = null
    ): int {
        $effectiveIn  = $this->normalizeTime($jamIn2) ?? $this->normalizeTime($jamIn);
        $effectiveOut = $this->normalizeTime($jamOut2) ?? $this->normalizeTime($jamOut);

        if (empty($date) || $effectiveIn === null || $effectiveOut === null) {
            return 0;
        }

        $awal = strtotime("{$date} {$effectiveIn}");
        if ($awal === false) {
            return 0;
        }

        $endDate = (strtotime("2000-01-01 {$effectiveOut}") < strtotime("2000-01-01 {$effectiveIn}"))
            ? date('Y-m-d', strtotime("{$date} +1 day"))
            : $date;

        $akhir = strtotime("{$endDate} {$effectiveOut}");
        if ($akhir === false) {
            return 0;
        }

        return max(0, $akhir - $awal);
    }

    /**
     * Potongan istirahat 1 jam untuk durasi realisasi > 4 jam (> 14.400 detik).
     */
    public function calculateAfterBreakSeconds(int $seconds): int
    {
        return $seconds > 14400 ? ($seconds - 3600) : $seconds;
    }

    /**
     * Cek apakah jabatan termasuk Atasan pada aturan lembur native (id_jab = 2 atau 3).
     */
    public function isAtasanPosition(?int $positionLegacyId): bool
    {
        return in_array((int) $positionLegacyId, [2, 3], true);
    }

    /**
     * Hitung Jam Real (After) sesuai pemotongan istirahat native.
     */
    public function calculateAfterBreakHours(float $realHours, bool $isAtasan): float
    {
        $threshold = $isAtasan ? 4.0 : 3.99;

        return $realHours > $threshold ? round($realHours - 1, 2) : $realHours;
    }

    /**
     * Adapter tunggal untuk membaca tarif lembur dari tabel gaji ketika modul Master Data Gaji tersedia.
     *
     * @return array{hourly_rate: float, minute_rate: float, has_rate: bool}
     */
    public function resolveEmployeeOvertimeRates(?SalaryStructure $salary): array
    {
        if (! $salary) {
            return [
                'hourly_rate' => 0.0,
                'minute_rate' => 0.0,
                'has_rate'    => false,
            ];
        }

        $attributes = $salary->getAttributes();

        $hourlyRate = (float) ($attributes['overtime_hourly_rate'] ?? $attributes['lembur_perjam'] ?? 0);
        $minuteRate = (float) ($attributes['overtime_minute_rate'] ?? $attributes['lembur_permenit'] ?? 0);

        return [
            'hourly_rate' => $hourlyRate,
            'minute_rate' => $minuteRate,
            'has_rate'    => ($hourlyRate > 0 || $minuteRate > 0),
        ];
    }

    /**
     * Hitung jam hitung & nominal lembur terverifikasi sesuai 100% rumus eSDM native
     * (lap_lemburrekapbulancsv.php & adminlembur_detail.data.php).
     *
     * @param  array{
     *     is_verified?: bool,
     *     overtime_type?: int,
     *     work_shift_legacy_id?: int|null,
     *     attendance_date?: string|null,
     *     jam_in?: string|null,
     *     jam_in2?: string|null,
     *     jam_out2?: string|null,
     *     position_legacy_id?: int|null,
     *     hourly_rate?: float,
     *     minute_rate?: float,
     * } $input
     * @return array{
     *     raw_minutes: int,
     *     real_hours: float,
     *     after_break_hours: float,
     *     counted_minutes: float,
     *     counted_hours: float,
     *     amount: float,
     * }
     */
    public function calculateVerifiedOvertime(array $input): array
    {
        $zero = [
            'raw_minutes'       => 0,
            'real_hours'        => 0.0,
            'after_break_hours' => 0.0,
            'counted_minutes'   => 0.0,
            'counted_hours'     => 0.0,
            'amount'            => 0.0,
        ];

        if (empty($input['is_verified'])) {
            return $zero;
        }

        $jin   = $this->normalizeTime($input['jam_in'] ?? null);
        $jin2  = $this->normalizeTime($input['jam_in2'] ?? null);
        $jout2 = $this->normalizeTime($input['jam_out2'] ?? null);

        if ($jout2 === null || ($jin2 === null && $jin === null)) {
            return $zero;
        }

        $idLembur = (int) ($input['overtime_type'] ?? 0);
        $idAbs    = (int) ($input['work_shift_legacy_id'] ?? 0);

        if ($idAbs === 13 || $idAbs === 14 || $idLembur === 2 || $idLembur === 3) {
            $startStr = ($jin !== null && ($jin2 === null || $jin > $jin2)) ? $jin : $jin2;
        } else {
            $startStr = $jin2 ?? $jin;
        }

        if ($startStr === null) {
            return $zero;
        }

        $baseDate = ! empty($input['attendance_date']) ? $input['attendance_date'] : '2000-01-01';
        $awal     = strtotime("{$baseDate} {$startStr}");
        $endDate  = (strtotime("2000-01-01 {$jout2}") < strtotime("2000-01-01 {$startStr}"))
            ? date('Y-m-d', strtotime("{$baseDate} +1 day"))
            : $baseDate;
        $akhir    = strtotime("{$endDate} {$jout2}");

        if ($awal === false || $akhir === false) {
            return $zero;
        }

        $diff = max(0, $akhir - $awal);
        $waktu = (int) floor($diff / 60);
        $waktuJam = round($waktu / 60, 2);

        $waktu3 = $this->computeCountedMinutes($idLembur, $waktu);

        $hourlyRate = (float) ($input['hourly_rate'] ?? 0.0);
        $minuteRate = (float) ($input['minute_rate'] ?? 0.0);
        $isAtasan   = $this->isAtasanPosition($input['position_legacy_id'] ?? null);
        $afterBreakHours = $this->calculateAfterBreakHours($waktuJam, $isAtasan);

        if ($isAtasan) {
            if ($waktuJam > 4) {
                $countedHours = round($waktuJam - 1, 2);
                $amount       = $countedHours * $hourlyRate;
            } else {
                $countedHours = $waktuJam;
                $amount       = $waktuJam * $hourlyRate;
            }
        } else {
            $countedHours = round($waktu3 / 60, 2);
            $amount       = $waktu3 * $minuteRate;
        }

        return [
            'raw_minutes'       => $waktu,
            'real_hours'        => $waktuJam,
            'after_break_hours' => $afterBreakHours,
            'counted_minutes'   => (float) $waktu3,
            'counted_hours'     => $countedHours,
            'amount'            => round($amount, 2),
        ];
    }

    /**
     * Hitung menit indeks lembur ($waktu3) per jenis lembur sesuai eSDM native.
     */
    private function computeCountedMinutes(int $idLembur, int $waktu): float
    {
        if ($idLembur === 1) {
            if ($waktu > 60) {
                $waktu1 = ($waktu > 239) ? ($waktu - 120) : ($waktu - 60);

                return ($waktu1 * 2) + 90;
            }
            if ($waktu < 60) {
                return 0.0;
            }

            return $waktu * 1.5;
        }

        if ($idLembur === 2) {
            if ($waktu > 239) {
                if ($waktu < 480) {
                    return ($waktu - 60) * 2;
                }
                if ($waktu < 540) {
                    return (($waktu - 480) * 3) + 840;
                }
                if ($waktu < 600) {
                    return (($waktu - 540) * 4) + 1020;
                }

                return (($waktu - 600) * 4) + 1260;
            }

            return $waktu * 2;
        }

        if ($idLembur === 3) {
            if ($waktu > 239) {
                if ($waktu < 480) {
                    return ($waktu - 60) * 2;
                }

                return (($waktu - 480) * 4) + 1140;
            }

            return $waktu * 2;
        }

        if ($idLembur === 4) {
            if ($waktu > 60) {
                $waktu1 = ($waktu > 240) ? ($waktu - 120) : ($waktu - 60);

                return ($waktu1 * 2) + 90;
            }
            if ($waktu < 60) {
                return 0.0;
            }

            return $waktu * 1.5;
        }

        return 0.0;
    }
}
