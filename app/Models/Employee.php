<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Employee extends Model
{
    use HasFactory;

    protected $table = 'employees';

    protected $guarded = ['id'];

    protected $casts = [
        'birth_date' => 'date',
        'join_date' => 'date',
        'contract_end_date' => 'date',
        'decree_date' => 'date',
        'retirement_date' => 'date',
        'is_active' => 'boolean',
        'children_count' => 'integer',
    ];

    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class, 'division_id');
    }

    public function subDivision(): BelongsTo
    {
        return $this->belongsTo(SubDivision::class, 'sub_division_id');
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class, 'position_id');
    }

    public function grade(): BelongsTo
    {
        return $this->belongsTo(Grade::class, 'grade_code', 'code');
    }

    public function religion(): BelongsTo
    {
        return $this->belongsTo(Religion::class, 'religion_id');
    }

    public function user(): HasOne
    {
        return $this->hasOne(User::class, 'employee_nip', 'nip');
    }

    public function salaryStructure(): HasOne
    {
        return $this->hasOne(SalaryStructure::class, 'employee_nip', 'nip');
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class, 'employee_nip', 'nip');
    }

    public function leaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class, 'employee_nip', 'nip');
    }

    public function permissionRequests(): HasMany
    {
        return $this->hasMany(PermissionRequest::class, 'employee_nip', 'nip');
    }

    public function exitPermits(): HasMany
    {
        return $this->hasMany(ExitPermit::class, 'employee_nip', 'nip');
    }

    public function leaveQuotas(): HasMany
    {
        return $this->hasMany(LeaveQuota::class, 'employee_nip', 'nip');
    }

    public function overtimeApplications(): HasMany
    {
        return $this->hasMany(OvertimeRequest::class, 'applicant_nip', 'nip');
    }

    public function overtimeParticipations(): HasMany
    {
        return $this->hasMany(OvertimeParticipant::class, 'employee_nip', 'nip');
    }

    public function families(): HasMany
    {
        return $this->hasMany(EmployeeFamily::class, 'employee_nip', 'nip');
    }

    public function educations(): HasMany
    {
        return $this->hasMany(EmployeeEducation::class, 'employee_nip', 'nip');
    }

    public function trainings(): HasMany
    {
        return $this->hasMany(EmployeeTraining::class, 'employee_nip', 'nip');
    }

    public function experiences(): HasMany
    {
        return $this->hasMany(EmployeeExperience::class, 'employee_nip', 'nip');
    }

    public function awards(): HasMany
    {
        return $this->hasMany(EmployeeAward::class, 'employee_nip', 'nip');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(EmployeeDocument::class, 'employee_nip', 'nip');
    }

    public function positionHistories(): HasMany
    {
        return $this->hasMany(EmployeePositionHistory::class, 'employee_nip', 'nip');
    }
}
