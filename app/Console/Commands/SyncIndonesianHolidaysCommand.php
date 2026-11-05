<?php

namespace App\Console\Commands;

use App\Models\Holiday;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class SyncIndonesianHolidaysCommand extends Command
{
    protected $signature = 'holidays:sync {year? : Tahun yang ingin disinkronkan (default: tahun berjalan)}';

    protected $description = 'Sinkronisasi daftar hari libur nasional Indonesia dari API publik ke tabel holidays';

    public function handle(): int
    {
        $year = (int) ($this->argument('year') ?: date('Y'));
        $this->info("Sedang mengambil data libur nasional Indonesia untuk tahun {$year}...");

        $sources = [
            "https://dayoffapi.vercel.app/api?year={$year}",
            "https://api-harilibur.vercel.app/api?year={$year}",
        ];

        $data = null;
        foreach ($sources as $url) {
            try {
                $response = Http::timeout(10)->get($url);
                if ($response->successful()) {
                    $json = $response->json();
                    if (is_array($json) && count($json) > 0) {
                        $data = $json;
                        break;
                    }
                }
            } catch (\Throwable $e) {
                // Lanjut ke sumber alternatif
            }
        }

        if (!$data) {
            $this->warn("Tidak dapat terhubung ke API publik atau tidak ada data baru. Menggunakan data kalender yang telah tersedia di tabel holidays.");
            return Command::SUCCESS;
        }

        $inserted = 0;
        foreach ($data as $item) {
            $date = $item['tanggal'] ?? $item['holiday_date'] ?? $item['date'] ?? null;
            $name = $item['keterangan'] ?? $item['holiday_name'] ?? $item['name'] ?? null;
            $isCuti = !empty($item['is_cuti']) || !empty($item['is_collective_leave']);

            if ($date && $name) {
                Holiday::updateOrInsert(
                    ['date' => $date],
                    [
                        'name' => $name,
                        'is_collective_leave' => $isCuti,
                        'updated_at' => now(),
                    ]
                );
                $inserted++;
            }
        }

        $this->info("Berhasil menyinkronkan {$inserted} hari libur nasional untuk tahun {$year} ke tabel holidays.");
        return Command::SUCCESS;
    }
}
