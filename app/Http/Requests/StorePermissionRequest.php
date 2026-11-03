<?php

namespace App\Http\Requests;

use App\Models\Attendance;
use App\Models\PermissionRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Validator;

class StorePermissionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Auth::check();
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $user = Auth::user();

        // Jika bukan admin/supervisor yang memiliki akses delegasi, NIP dipaksa menggunakan NIP login sendiri
        if (! $user->hasAdminAccess() || empty($this->employee_nip)) {
            $this->merge([
                'employee_nip' => $user->employee_nip,
            ]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'employee_nip'        => ['required', 'string', 'exists:employees,nip'],
            'permission_type_id'  => ['required', 'exists:permission_types,id'],
            'start_date'          => ['required', 'date_format:Y-m-d'],
            'end_date'            => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'reason'              => ['nullable', 'string', 'max:1000'],
            'attachment'          => ['nullable', 'file', 'mimes:jpeg,jpg,png,pdf', 'max:5120'], // Max 5MB
        ];
    }

    /**
     * Custom validation messages.
     */
    public function messages(): array
    {
        return [
            'employee_nip.required'       => 'NIP pegawai wajib diisi.',
            'employee_nip.exists'         => 'Data pegawai dengan NIP tersebut tidak ditemukan.',
            'permission_type_id.required' => 'Pilih salah satu jenis izin / alasan ketidakhadiran.',
            'permission_type_id.exists'   => 'Jenis izin yang dipilih tidak valid.',
            'start_date.required'         => 'Tanggal awal izin wajib diisi.',
            'start_date.date_format'      => 'Format tanggal awal harus YYYY-MM-DD.',
            'end_date.required'           => 'Tanggal akhir izin wajib diisi.',
            'end_date.date_format'        => 'Format tanggal akhir harus YYYY-MM-DD.',
            'end_date.after_or_equal'     => 'Tanggal akhir tidak boleh lebih kecil dari tanggal awal.',
            'attachment.mimes'            => 'Lampiran bukti harus berupa berkas gambar (JPG, JPEG, PNG) atau PDF.',
            'attachment.max'              => 'Ukuran berkas lampiran maksimal 5MB.',
        ];
    }

    /**
     * Validasi bisnis lanjutan: deteksi bentrok absensi kehadiran dan tumpang tindih izin.
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $nip       = $this->input('employee_nip');
                $startDate = $this->input('start_date');
                $endDate   = $this->input('end_date');

                // 1. Cek bentrok dengan rekaman absensi hadir pada rentang tanggal tersebut (sesuai aturan inputijin.php)
                $hasPresentAttendance = Attendance::where('employee_nip', $nip)
                    ->whereBetween('attendance_date', [$startDate, $endDate])
                    ->where(function ($query) {
                        $query->where('is_present_in', true)
                            ->orWhereNotNull('check_in_time');
                    })
                    ->exists();

                if ($hasPresentAttendance) {
                    $validator->errors()->add(
                        'start_date',
                        "Pegawai dengan NIP {$nip} sudah melakukan absensi masuk pada rentang tanggal {$startDate} s/d {$endDate}."
                    );
                    return;
                }

                // 2. Cek tumpang tindih dengan permohonan izin lain yang sudah tercatat
                $hasOverlappingPermission = PermissionRequest::where('employee_nip', $nip)
                    ->where(function ($query) use ($startDate, $endDate) {
                        $query->whereBetween('start_date', [$startDate, $endDate])
                            ->orWhereBetween('end_date', [$startDate, $endDate])
                            ->orWhere(function ($sub) use ($startDate, $endDate) {
                                $sub->where('start_date', '<=', $startDate)
                                    ->where('end_date', '>=', $endDate);
                            });
                    })
                    ->exists();

                if ($hasOverlappingPermission) {
                    $validator->errors()->add(
                        'start_date',
                        "Pegawai dengan NIP {$nip} sudah memiliki permohonan izin yang aktif pada rentang tanggal tersebut."
                    );
                }
            },
        ];
    }
}

