<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OvertimeParticipant extends Model
{
    use HasFactory;

    protected $table = 'overtime_participants';

    protected $guarded = ['id'];

    protected $casts = [
        'overtime_amount' => 'decimal:2',
        'is_approved' => 'boolean',
    ];

    public function overtimeRequest(): BelongsTo
    {
        return $this->belongsTo(OvertimeRequest::class, 'overtime_request_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_nip', 'nip');
    }
}
