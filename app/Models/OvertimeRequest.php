<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OvertimeRequest extends Model
{
    use HasFactory;

    protected $table = 'overtime_requests';

    protected $guarded = ['id'];

    // Label 4 jenis lembur KFPB sesuai master tbl_lembur sistem lama
    public const OVERTIME_TYPES = [
        1 => 'Lembur Hari Kerja',
        2 => 'Lembur Sabtu/Minggu',
        3 => 'Lembur Hari Besar/Raya',
        4 => 'Lembur Flextime',
    ];

    // Label status approval sesuai kd_approve sistem lama
    public const APPROVAL_STATUS_LABELS = [
        0 => 'Menunggu',
        1 => 'Disetujui',
        2 => 'Ditolak',
        3 => 'Menunggu ACC 1',
        4 => 'Menunggu ACC 2',
        5 => 'Menunggu ACC 3',
    ];

    protected $casts = [
        'request_date'            => 'date',
        'start_date'              => 'date',
        'end_date'                => 'date',
        'approval_status'         => 'integer',
        'overtime_type'           => 'integer',
        'supervisor_status_1'     => 'integer',
        'supervisor_status_2'     => 'integer',
        'supervisor_status_3'     => 'integer',
        'supervisor_approved_at_1' => 'datetime',
        'supervisor_approved_at_2' => 'datetime',
        'supervisor_approved_at_3' => 'datetime',
    ];

    public function applicant(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'applicant_nip', 'nip');
    }

    public function supervisor1(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'supervisor_title_1', 'nip');
    }

    public function supervisor2(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'supervisor_title_2', 'nip');
    }

    public function supervisor3(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'supervisor_title_3', 'nip');
    }

    public function participants(): HasMany
    {
        return $this->hasMany(OvertimeParticipant::class, 'overtime_request_id');
    }
}
