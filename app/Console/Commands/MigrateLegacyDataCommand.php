<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class MigrateLegacyDataCommand extends Command
{
    protected $signature = 'sdm:migrate-legacy 
                            {--file= : Path ke file dump SQL lama} 
                            {--tables= : Tabel spesifik, pisahkan dengan koma (contoh: --tables=user,absensi)} 
                            {--dry-run : Uji coba parsing tanpa menyimpan ke database}';

    protected $description = 'Migrasi data dari database SQL dump lama (sql_sdm) ke skema modern Laravel eSDMv2 secara presisi';

    protected array $maritalStatusMap = [
        1 => 'Single',   // Lajang
        2 => 'Married',  // Menikah
        3 => 'Widowed',  // Duda
        4 => 'Widowed',  // Janda
    ];

    protected array $roleMap = [
        1 => 'admin',
        2 => 'director',
        3 => 'hrd',
        4 => 'supervisor',
        5 => 'employee',
    ];

    protected array $idToNip = [];
    protected array $nipToName = [];

    public function handle(): int
    {
        $filePath = $this->option('file') ?: 'e:\\DATABASE - KFPB SERVER\\sdm_merged_final.sql';
        $dryRun = (bool) $this->option('dry-run');
        $rawTables = $this->option('tables');
        $tablesFilter = $rawTables ? array_map('trim', explode(',', strtolower($rawTables))) : null;

        if (!File::exists($filePath)) {
            $this->error("File SQL dump tidak ditemukan di: {$filePath}");
            return Command::FAILURE;
        }

        $this->info("=== MEMULAI PROSES ETL MIGRASI DATABASE LEGACY ===");
        $this->info("Sumber: {$filePath}");
        $this->info("Ukuran: " . round(File::size($filePath) / (1024 * 1024), 2) . " MB");
        if ($tablesFilter) {
            $this->info("Filter tabel aktif: " . implode(', ', $tablesFilter));
        }
        if ($dryRun) {
            $this->warn("[DRY-RUN MODE] Simulasi aktif. Data tidak disimpan ke database.");
        }

        // Matikan query log agar tidak memakan RAM
        DB::disableQueryLog();

        if (!$dryRun) {
            $driver = DB::connection()->getDriverName();
            if ($driver === 'sqlite') {
                DB::statement('PRAGMA foreign_keys = OFF;');
            } elseif ($driver === 'mysql') {
                DB::statement('SET FOREIGN_KEY_CHECKS=0;');
            }

            // Cache data pegawai
            $this->idToNip = DB::table('employees')->pluck('nip', 'id')->toArray();
            $this->nipToName = DB::table('employees')->pluck('full_name', 'nip')->toArray();
        }

        $handle = fopen($filePath, 'r');
        if (!$handle) {
            $this->error("Gagal membuka file stream.");
            return Command::FAILURE;
        }

        $lineCount = 0;
        $totalImported = 0;
        $tableCounts = [];
        $attendanceBatch = [];
        $batchSize = 1000;

        $this->line("Sedang membaca dan memetakan data secara streaming...");

        while (($line = fgets($handle)) !== false) {
            $lineCount++;

            if (str_starts_with($line, 'INSERT INTO `')) {
                if (preg_match('/^INSERT INTO `([^`]+)` VALUES \s*(.*);$/', trim($line), $matches)) {
                    $legacyTable = $matches[1];
                    $lowerTbl = strtolower($legacyTable);

                    // Lewati tabel arsip lama sesuai arahan user
                    if (preg_match('/(?:2021|2022|_old|_old2|_test|_test2|_trial|februari|kfest|tmp_)/i', $lowerTbl)) {
                        continue;
                    }

                    // Alias filter user -> tbl_user
                    $checkFilter = $lowerTbl;
                    if ($tablesFilter) {
                        $isAllowed = in_array($lowerTbl, $tablesFilter) 
                            || ($lowerTbl === 'tbl_user' && in_array('user', $tablesFilter))
                            || ($lowerTbl === 'tbl_user' && in_array('users', $tablesFilter))
                            || ($lowerTbl === 'izin_keluar' && in_array('exit_permits', $tablesFilter))
                            || ($lowerTbl === 'izin_keluar' && in_array('izin-keluar', $tablesFilter));
                        if (!$isAllowed) {
                            continue;
                        }
                    }

                    $valuesBlock = $matches[2];
                    $tuples = explode('), (', $valuesBlock);

                    foreach ($tuples as $rawTuple) {
                        $cleanTuple = trim($rawTuple, "(); \t\n\r\0\x0B");
                        $row = $this->parseSqlValues($cleanTuple);

                        if ($legacyTable === 'absensi') {
                            $mapped = $this->mapAttendanceRow($row);
                            if ($mapped) {
                                $attendanceBatch[] = $mapped;
                                if (count($attendanceBatch) >= $batchSize) {
                                    if (!$dryRun) {
                                        DB::table('attendances')->insert($attendanceBatch);
                                    }
                                    $attendanceBatch = [];
                                }
                            }
                        } else {
                            $this->mapAndInsertRow($legacyTable, $row, $dryRun);
                        }

                        $totalImported++;
                        $tableCounts[$legacyTable] = ($tableCounts[$legacyTable] ?? 0) + 1;
                    }
                }
            }

            if ($lineCount % 1000 === 0) {
                $this->output->write(".");
            }
        }

        // Flush sisa batch attendance
        if (!empty($attendanceBatch) && !$dryRun) {
            DB::table('attendances')->insert($attendanceBatch);
            $attendanceBatch = [];
        }

        fclose($handle);

        if (!$dryRun) {
            $driver = DB::connection()->getDriverName();
            if ($driver === 'sqlite') {
                DB::statement('PRAGMA foreign_keys = ON;');
            } elseif ($driver === 'mysql') {
                DB::statement('SET FOREIGN_KEY_CHECKS=1;');
            }
        }

        $this->newLine(2);
        $this->info("=== PROSES ETL SELESAI DENGAN SUKSES ===");
        $this->info("Total record lama diproses: " . number_format($totalImported));
        if (!empty($tableCounts)) {
            $this->table(['Tabel Legacy', 'Jumlah Record Diproses'], array_map(fn($t, $c) => [$t, number_format($c)], array_keys($tableCounts), $tableCounts));
        }

        return Command::SUCCESS;
    }

    /**
     * Map baris absensi untuk batch insert berkecepatan tinggi
     */
    protected function mapAttendanceRow(array $values): ?array
    {
        if (!isset($values[0], $values[12])) {
            return null;
        }

        $nip = (string) $values[12];
        $tgl = $this->cleanDate($values[4] ?? null);

        if (!$nip || !$tgl) {
            return null;
        }

        return [
            'legacy_id' => (int) $values[0],
            'employee_nip' => $nip,
            'attendance_date' => $tgl,
            'work_shift_id' => !empty($values[1]) && is_numeric($values[1]) ? (int) $values[1] : null,
            'permission_type_id' => !empty($values[2]) && is_numeric($values[2]) ? (int) $values[2] : null,
            'overtime_request_id' => !empty($values[3]) && is_numeric($values[3]) ? (int) $values[3] : null,
            'check_in_time' => $this->cleanTime($values[5] ?? null),
            'check_out_time' => $this->cleanTime($values[6] ?? null),
            'is_present_in' => ($values[7] ?? 'N') === 'Y',
            'is_present_out' => ($values[8] ?? 'N') === 'Y',
            'is_late' => ($values[10] ?? 'N') === 'Y',
            'is_early_leave' => ($values[11] ?? 'N') === 'Y',
            'check_in_latitude' => !empty($values[14]) && $values[14] !== 'NULL' ? $values[14] : null,
            'check_in_longitude' => !empty($values[15]) && $values[15] !== 'NULL' ? $values[15] : null,
            'check_out_latitude' => !empty($values[16]) && $values[16] !== 'NULL' ? $values[16] : null,
            'check_out_longitude' => !empty($values[17]) && $values[17] !== 'NULL' ? $values[17] : null,
            'is_overtime' => ($values[18] ?? 'N') === 'Y',
            'is_overtime_verified' => ($values[20] ?? 'N') === 'Y',
            'overtime_duration' => !empty($values[26]) && $values[26] !== 'NULL' ? $values[26] : null,
            'overtime_amount' => (float) preg_replace('/[^0-9.]/', '', $values[29] ?? '0'),
            'deduction_amount' => (float) preg_replace('/[^0-9.]/', '', $values[30] ?? '0'),
            'work_mode' => !empty($values[19]) && $values[19] !== 'NULL' ? $values[19] : 'WFO',
            'notes' => !empty($values[9]) && $values[9] !== 'NULL' && $values[9] !== 'NA' ? $values[9] : null,
            'created_at' => $this->cleanDateTime($values[13] ?? null) ?: now(),
            'updated_at' => now(),
        ];
    }

    /**
     * Map data baris dari tabel legacy ke skema modern Standard English secara presisi
     */
    protected function mapAndInsertRow(string $table, array $values, bool $dryRun): void
    {
        if ($dryRun) {
            return;
        }

        try {
            switch ($table) {
                // User Accounts (tbl_user -> users)
                case 'tbl_user':
                    if (isset($values[0], $values[1], $values[2])) {
                        $username = (string) $values[1];
                        $nip = !empty($values[5]) && $values[5] !== 'NULL' ? (string) $values[5] : null;
                        $email = !empty($values[4]) && $values[4] !== 'NULL' ? (string) $values[4] : null;
                        $name = ($nip && isset($this->nipToName[$nip])) ? $this->nipToName[$nip] : $username;

                        $level = !empty($values[3]) ? (int) $values[3] : 5;
                        $role = $this->roleMap[$level] ?? 'employee';

                        DB::table('users')->updateOrInsert(
                            ['username' => $username],
                            [
                                'employee_nip' => $nip,
                                'username' => $username,
                                'name' => $name,
                                'email' => $email,
                                'password' => $values[2], // Asli Bcrypt hash dari sistem lama!
                                'role' => $role,
                                'is_active' => ($values[6] ?? '1') === '1',
                                'photo_url' => !empty($values[10]) && $values[10] !== 'NULL' ? $values[10] : null,
                                'last_login_at' => $this->cleanDateTime($values[7] ?? null),
                                'created_at' => $this->cleanDateTime($values[9] ?? null) ?: now(),
                                'updated_at' => now(),
                            ]
                        );
                    }
                    break;

                // Geography: prov, kabkot, kec
                case 'prov':
                    if (isset($values[0], $values[1])) {
                        DB::table('provinces')->updateOrInsert(
                            ['id' => (string) $values[0]],
                            ['id' => (string) $values[0], 'name' => $values[1], 'created_at' => now(), 'updated_at' => now()]
                        );
                    }
                    break;

                case 'kabkot':
                    if (isset($values[0], $values[1], $values[2])) {
                        DB::table('regencies')->updateOrInsert(
                            ['id' => (string) $values[0]],
                            ['id' => (string) $values[0], 'province_id' => (string) $values[1], 'name' => $values[2], 'created_at' => now(), 'updated_at' => now()]
                        );
                    }
                    break;

                case 'kec':
                    if (isset($values[0], $values[1], $values[2])) {
                        DB::table('districts')->updateOrInsert(
                            ['id' => (string) $values[0]],
                            ['id' => (string) $values[0], 'regency_id' => (string) $values[1], 'name' => $values[2], 'created_at' => now(), 'updated_at' => now()]
                        );
                    }
                    break;

                // Izin Keluar Kantor (izin_keluar -> exit_permits)
                case 'izin_keluar':
                    if (isset($values[0], $values[3], $values[5])) {
                        $id = (int) $values[0];
                        $nip = trim((string) $values[3]);
                        $tgl = $this->cleanDate($values[5] ?? null);

                        if ($nip && $tgl) {
                            $legacyStatus = strtolower(trim($values[21] ?? 'pending'));
                            $kdApprove = isset($values[13]) && is_numeric($values[13]) ? (int) $values[13] : 0;

                            $approvalStatus = 0;
                            if ($legacyStatus === 'selesai' || $legacyStatus === 'kembali') {
                                $approvalStatus = 4;
                            } elseif ($legacyStatus === 'keluar') {
                                $approvalStatus = 3;
                            } elseif ($legacyStatus === 'rejected' || $kdApprove === 2) {
                                $approvalStatus = 2;
                            } elseif ($legacyStatus === 'approved' || $kdApprove === 1) {
                                $approvalStatus = 1;
                            } else {
                                $approvalStatus = 0;
                            }

                            // Format reason & metadata historis
                            $metaNotes = [];
                            if (!empty($values[8]) && $values[8] !== 'NULL') {
                                $metaNotes[] = "[Tipe: " . trim($values[8]) . "]";
                            }
                            if (!empty($values[10]) && $values[10] === 'N') {
                                $metaNotes[] = "[Langsung Pulang]";
                            }
                            if (!empty($values[11]) && $values[11] !== 'NULL') {
                                $atasanStr = trim($values[11]) . (!empty($values[12]) && $values[12] !== 'NULL' ? " - " . trim($values[12]) : "");
                                $metaNotes[] = "[Atasan: $atasanStr]";
                            }
                            if (!empty($values[14]) && $values[14] !== 'NULL') {
                                $metaNotes[] = "[Catatan Atasan: " . trim($values[14]) . "]";
                            }
                            if (!empty($values[17]) && $values[17] !== 'NULL') {
                                $satpamKeluar = (!empty($values[19]) && $values[19] !== 'NULL') ? " by " . trim($values[19]) : "";
                                $metaNotes[] = "[ScanKeluar: " . trim($values[17]) . $satpamKeluar . "]";
                            }
                            if (!empty($values[18]) && $values[18] !== 'NULL') {
                                $satpamKembali = (!empty($values[20]) && $values[20] !== 'NULL') ? " by " . trim($values[20]) : "";
                                $metaNotes[] = "[ScanKembali: " . trim($values[18]) . $satpamKembali . "]";
                            }

                            $fullReason = trim($values[9] ?? '');
                            if (!empty($metaNotes)) {
                                $fullReason .= "\n\n" . implode(' ', $metaNotes);
                            }

                            $createdAt = $this->cleanDateTime($values[22] ?? null) ?: now();
                            $updatedAt = $this->cleanDateTime($values[23] ?? null) ?: $createdAt;

                            DB::table('exit_permits')->updateOrInsert(
                                ['id' => $id],
                                [
                                    'id'              => $id,
                                    'employee_nip'    => $nip,
                                    'date'            => $tgl,
                                    'exit_time'       => $this->cleanTime($values[6] ?? null) ?: '00:00:00',
                                    'return_time'     => $this->cleanTime($values[7] ?? null),
                                    'reason'          => $fullReason,
                                    'approval_status' => $approvalStatus,
                                    'created_at'      => $createdAt,
                                    'updated_at'      => $updatedAt,
                                ]
                            );
                        }
                    }
                    break;

                default:
                    break;
            }
        } catch (\Throwable $e) {
            // Lanjutkan jika ada duplicate
        }
    }

    protected function cleanDate(?string $val): ?string
    {
        if (!$val || $val === '0000-00-00' || $val === 'NULL' || $val === "''") {
            return null;
        }
        $val = trim($val, "'\" ");
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $val)) {
            return null;
        }
        return $val;
    }

    protected function cleanTime(?string $val): ?string
    {
        if (!$val || $val === '00:00:00' || $val === 'NULL' || $val === "''") {
            return null;
        }
        $val = trim($val, "'\" ");
        return $val;
    }

    protected function cleanDateTime(?string $val): ?string
    {
        if (!$val || str_starts_with($val, '0000-00-00') || $val === 'NULL' || $val === "''") {
            return null;
        }
        $val = trim($val, "'\" ");
        return $val;
    }

    protected function parseSqlValues(string $tuple): array
    {
        $values = [];
        $len = strlen($tuple);
        $current = '';
        $inQuote = false;
        $quoteChar = '';
        $escaped = false;

        for ($i = 0; $i < $len; $i++) {
            $char = $tuple[$i];

            if ($escaped) {
                $current .= $char;
                $escaped = false;
                continue;
            }

            if ($char === '\\') {
                $escaped = true;
                continue;
            }

            if (!$inQuote && ($char === "'" || $char === '"')) {
                $inQuote = true;
                $quoteChar = $char;
                continue;
            }

            if ($inQuote && $char === $quoteChar) {
                $inQuote = false;
                continue;
            }

            if (!$inQuote && $char === ',') {
                $val = trim($current);
                $values[] = ($val === 'NULL') ? null : $val;
                $current = '';
                continue;
            }

            $current .= $char;
        }

        $val = trim($current);
        $values[] = ($val === 'NULL') ? null : $val;

        return $values;
    }
}
