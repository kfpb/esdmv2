<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\ExitPermit;
use App\Models\OvertimeRequest;
use App\Models\User;
use Carbon\Carbon;

class NotificationService
{
    /**
     * Ambil seluruh notifikasi aktif untuk pengguna saat ini.
     * Kategori:
     * 1. Verifikasi Realisasi Lembur (bagi pembuat SPL yang telah di-ACC tapi belum diverifikasi)
     * 2. ACC Pengajuan / Tugas Atasan (SPL Lembur, Izin Keluar Kantor yang menunggu persetujuan)
     * 3. Pengingat Masa Kontrak Habis (bagi pegawai sendiri & bagi atasan/admin)
     */
    public function getForUser(?User $user): array
    {
        if (! $user) {
            return ['count' => 0, 'items' => []];
        }

        $nip   = $user->employee_nip;
        $items = collect();

        // 1. Notifikasi Verifikasi Lembur (SPL Milik User yang sudah di-ACC tapi belum diverifikasi)
        if ($nip) {
            $unverifiedOvertimes = OvertimeRequest::where('applicant_nip', $nip)
                ->where('approval_status', 1)
                ->whereNull('end_date')
                ->orderByDesc('start_date')
                ->take(5)
                ->get();

            foreach ($unverifiedOvertimes as $ot) {
                $dateStr = $ot->start_date ? $ot->start_date->format('d/m/Y') : '-';
                $items->push([
                    'id'         => 'ot-ver-' . $ot->id,
                    'category'   => 'verifikasi',
                    'title'      => 'SPL Perlu Verifikasi Realisasi',
                    'message'    => "Lembur tgl {$dateStr} telah di-ACC. Segera lengkapi Form Verifikasi Lembur.",
                    'time'       => $ot->updated_at ? $ot->updated_at->diffForHumans() : 'Baru saja',
                    'badge'      => 'Perlu Verifikasi',
                    'badgeClass' => 'bg-emerald-100 text-emerald-800',
                    'icon'       => 'pi pi-file-edit text-emerald-600 bg-emerald-50',
                    'url'        => route('lembur.verify', $ot->id),
                ]);
            }
        }

        // 2. Notifikasi Tugas Atasan: SPL Lembur yang Menunggu Persetujuan (ACC 1, ACC 2, atau ACC 3)
        if ($nip) {
            $pendingApprovals = OvertimeRequest::with('applicant')
                ->where(function ($q) use ($nip) {
                    $q->where(function ($sub) use ($nip) {
                        $sub->where('supervisor_title_1', $nip)->where('approval_status', 3);
                    })->orWhere(function ($sub) use ($nip) {
                        $sub->where('supervisor_title_2', $nip)->where('approval_status', 4);
                    })->orWhere(function ($sub) use ($nip) {
                        $sub->where('supervisor_title_3', $nip)->where('approval_status', 5);
                    });
                })
                ->orderByDesc('request_date')
                ->take(5)
                ->get();

            foreach ($pendingApprovals as $ot) {
                $applicantName = $ot->applicant?->full_name ?? $ot->applicant_nip;
                $dateStr       = $ot->start_date ? $ot->start_date->format('d/m/Y') : '-';
                $level         = match ($ot->approval_status) {
                    3       => 'ACC 1',
                    4       => 'ACC 2',
                    5       => 'ACC 3',
                    default => 'ACC',
                };

                $items->push([
                    'id'         => 'ot-app-' . $ot->id,
                    'category'   => 'approval',
                    'title'      => "Persetujuan Lembur ({$level})",
                    'message'    => "Pengajuan lembur dari {$applicantName} (Tgl {$dateStr}) menunggu persetujuan Anda.",
                    'time'       => $ot->created_at ? $ot->created_at->diffForHumans() : 'Baru saja',
                    'badge'      => 'Tugas Atasan',
                    'badgeClass' => 'bg-amber-100 text-amber-800',
                    'icon'       => 'pi pi-clock text-amber-600 bg-amber-50',
                    'url'        => route('lembur.approvals'),
                ]);
            }
        }

        // 3. Notifikasi Tugas Atasan: Izin Keluar Kantor yang Menunggu Approval
        if ($nip) {
            $pendingExitPermits = ExitPermit::with('employee')
                ->where('approval_status', ExitPermit::STATUS_PENDING)
                ->where('reason', 'like', "%[Atasan: {$nip}]%")
                ->orderByDesc('created_at')
                ->take(5)
                ->get();

            foreach ($pendingExitPermits as $ik) {
                $empName = $ik->employee?->full_name ?? $ik->employee_nip;
                $items->push([
                    'id'         => 'ik-app-' . $ik->id,
                    'category'   => 'approval',
                    'title'      => 'Izin Keluar Perlu Persetujuan',
                    'message'    => "Permohonan izin keluar dari {$empName} menunggu persetujuan Anda.",
                    'time'       => $ik->created_at ? $ik->created_at->diffForHumans() : 'Baru saja',
                    'badge'      => 'Izin Keluar',
                    'badgeClass' => 'bg-orange-100 text-orange-800',
                    'icon'       => 'pi pi-sign-out text-orange-600 bg-orange-50',
                    'url'        => route('izin-keluar.index'),
                ]);
            }
        }

        // 4. Notifikasi Kontrak Habis
        // a. Untuk Pegawai Sendiri (Masa kontrak akan habis dalam <= 30 hari atau sudah habis)
        if ($nip) {
            $employee = Employee::where('nip', $nip)->first();
            if ($employee && $employee->contract_end_date) {
                $contractDate = Carbon::parse($employee->contract_end_date)->startOfDay();
                $today        = Carbon::today();
                $diffDays     = $today->diffInDays($contractDate, false);

                if ($diffDays <= 30) {
                    $dateFormatted = $contractDate->translatedFormat('d F Y');
                    if ($diffDays < 0) {
                        $absDays = abs($diffDays);
                        $items->push([
                            'id'         => 'contract-self-expired',
                            'category'   => 'kontrak',
                            'title'      => 'Masa Kontrak Berakhir',
                            'message'    => "Masa kontrak Anda telah berakhir sejak {$absDays} hari lalu ({$dateFormatted}). Harap koordinasi dengan SDM.",
                            'time'       => 'Pengingat Personalia',
                            'badge'      => 'Kontrak Habis',
                            'badgeClass' => 'bg-rose-100 text-rose-800',
                            'icon'       => 'pi pi-calendar-times text-rose-600 bg-rose-50',
                            'url'        => '#',
                        ]);
                    } else {
                        $items->push([
                            'id'         => 'contract-self-warning',
                            'category'   => 'kontrak',
                            'title'      => 'Pengingat Masa Kontrak',
                            'message'    => "Masa kontrak Anda akan berakhir dalam {$diffDays} hari lagi ({$dateFormatted}).",
                            'time'       => 'Pengingat Personalia',
                            'badge'      => 'Sisa ' . $diffDays . ' Hari',
                            'badgeClass' => 'bg-amber-100 text-amber-800',
                            'icon'       => 'pi pi-exclamation-triangle text-amber-600 bg-amber-50',
                            'url'        => '#',
                        ]);
                    }
                }
            }
        }

        // b. Untuk Admin / HRD: Pantau seluruh pegawai yang kontraknya segera habis (<= 14 hari)
        if ($user->hasAdminAccess()) {
            $today = Carbon::today()->toDateString();
            $next14Days = Carbon::today()->addDays(14)->toDateString();

            $totalExpiring = Employee::where('is_active', true)
                ->whereNotNull('contract_end_date')
                ->whereBetween('contract_end_date', [$today, $next14Days])
                ->count();

            if ($totalExpiring > 0) {
                $items->push([
                    'id'         => 'admin-contracts-summary',
                    'category'   => 'kontrak_admin',
                    'title'      => 'Monitoring Kontrak Pegawai (HRD)',
                    'message'    => "Terdapat {$totalExpiring} pegawai aktif yang kontrak kerjanya akan habis dalam 14 hari ke depan.",
                    'time'       => 'Perhatian HRD',
                    'badge'      => "{$totalExpiring} Pegawai",
                    'badgeClass' => 'bg-purple-100 text-purple-800',
                    'icon'       => 'pi pi-users text-purple-600 bg-purple-50',
                    'url'        => '/datatable',
                ]);
            }
        }

        return [
            'count' => $items->count(),
            'items' => $items->values()->all(),
        ];
    }
}
