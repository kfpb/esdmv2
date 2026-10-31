<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaveQuota extends Model
{
    use HasFactory;

    protected $table = 'leave_quotas';
    protected $guarded = ['id'];

    protected $casts = [
        'year' => 'integer',
        'initial_quota' => 'integer',
        'used_quota' => 'integer',
        'remaining_quota' => 'integer',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_nip', 'nip');
    }

    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class, 'leave_type_id');
    }
}
