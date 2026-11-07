<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExitPermit extends Model
{
    use HasFactory;

    protected $table = 'exit_permits';

    protected $guarded = ['id'];

    public const STATUS_PENDING   = 0; // Menunggu Persetujuan Atasan
    public const STATUS_APPROVED  = 1; // Disetujui Atasan (Tiket QR Aktif)
    public const STATUS_REJECTED  = 2; // Ditolak Atasan
    public const STATUS_OUT       = 3; // Sedang Keluar Kantor (Scan Satpam Keluar)
    public const STATUS_COMPLETED = 4; // Selesai (Scan Satpam Kembali / Pulang / Datang Terlambat)

    protected $casts = [
        'date'            => 'date',
        'approval_status' => 'integer',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_nip', 'nip');
    }

    /**
     * Kode unik tiket izin: IK-YYYYMMDD-XXX
     */
    public function getKodeIzinAttribute(): string
    {
        $dateStr = $this->date ? str_replace('-', '', $this->date->format('Y-m-d')) : date('Ymd');
        return 'IK-' . $dateStr . '-' . str_pad((string) $this->id, 3, '0', STR_PAD_LEFT);
    }

    /**
     * Ekstrak atasan NIP jika tercatat dalam reason
     */
    public function getApproverNipAttribute(): ?string
    {
        if (preg_match('/\[Atasan:\s*([^\s\]]+)/i', $this->reason ?? '', $m)) {
            return $m[1];
        }
        return null;
    }

    /**
     * Ekstrak atasan Nama jika tercatat dalam reason
     */
    public function getApproverNameAttribute(): ?string
    {
        if (preg_match('/\[Atasan:\s*[^-\]]+-\s*([^\]]+)\]/i', $this->reason ?? '', $m)) {
            return trim($m[1]);
        }
        return null;
    }

    /**
     * Ekstrak jam keluar aktual dari catatan satpam
     */
    public function getActualExitTimeAttribute(): ?string
    {
        if (preg_match('/\[ScanKeluar:\s*([0-9:]+)/i', $this->reason ?? '', $m)) {
            return $m[1];
        }
        return null;
    }

    /**
     * Ekstrak jam kembali aktual dari catatan satpam
     */
    public function getActualReturnTimeAttribute(): ?string
    {
        if (preg_match('/\[ScanKembali:\s*([0-9:]+)/i', $this->reason ?? '', $m)) {
            return $m[1];
        }
        return null;
    }
}
