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

    protected $casts = [
        'request_date' => 'date',
        'start_date' => 'date',
        'end_date' => 'date',
        'approval_status' => 'integer',
        'supervisor_status_1' => 'integer',
        'supervisor_status_2' => 'integer',
        'supervisor_status_3' => 'integer',
        'supervisor_approved_at_1' => 'datetime',
        'supervisor_approved_at_2' => 'datetime',
        'supervisor_approved_at_3' => 'datetime',
    ];

    public function applicant(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'applicant_nip', 'nip');
    }

    public function participants(): HasMany
    {
        return $this->hasMany(OvertimeParticipant::class, 'overtime_request_id');
    }
}
