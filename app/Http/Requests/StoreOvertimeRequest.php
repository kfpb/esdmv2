<?php

namespace App\Http\Requests;

use App\Models\OvertimeRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Validator;

class StoreOvertimeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check();
    }

    protected function prepareForValidation(): void
    {
        $user = Auth::user();

        // NIP pengaju dipaksa NIP sendiri jika bukan admin yang mendelegasikan
        if (! $user->hasAdminAccess() || empty($this->applicant_nip)) {
            $this->merge([
                'applicant_nip' => $user->employee_nip,
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'applicant_nip'    => ['required', 'string', 'exists:employees,nip'],
            'overtime_type'    => ['required', 'integer', 'in:1,2,3,4'],
            'start_date'       => ['required', 'date_format:Y-m-d'],
            'start_time'       => ['required', 'date_format:H:i'],
            'end_time'         => ['required', 'date_format:H:i'],
            'supervisors'      => ['required', 'array', 'min:1', 'max:3'],
            'supervisors.*'    => ['required', 'string', 'exists:employees,nip'],
            'participants'     => ['required', 'array', 'min:1', 'max:30'],
            'participants.*.nip'  => ['required', 'string', 'exists:employees,nip'],
            'participants.*.task' => ['required', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'applicant_nip.required'       => 'Data NPP/NIP pengaju wajib ada.',
            'applicant_nip.exists'          => 'Pegawai pengaju tidak ditemukan dalam sistem.',
            'overtime_type.required'        => 'Jenis lembur wajib dipilih.',
            'overtime_type.in'              => 'Jenis lembur tidak valid.',
            'start_date.required'           => 'Tanggal rencana lembur wajib diisi.',
            'start_date.date_format'        => 'Format tanggal lembur harus YYYY-MM-DD.',
            'start_time.required'           => 'Jam mulai rencana lembur wajib diisi.',
            'start_time.date_format'        => 'Format jam mulai harus HH:MM.',
            'end_time.required'             => 'Jam selesai rencana lembur wajib diisi.',
            'end_time.date_format'          => 'Format jam selesai harus HH:MM.',
            'supervisors.required'          => 'Minimal 1 pejabat penyetuju (ACC) wajib dipilih.',
            'supervisors.min'               => 'Minimal 1 pejabat penyetuju (ACC) wajib dipilih.',
            'supervisors.max'               => 'Maksimal 3 pejabat penyetuju (ACC) yang dapat dipilih.',
            'supervisors.*.exists'          => 'Pejabat yang dipilih sebagai ACC tidak ditemukan dalam sistem.',
            'participants.required'         => 'Daftar peserta lembur wajib diisi minimal 1 orang.',
            'participants.min'              => 'Minimal 1 peserta lembur wajib ditambahkan.',
            'participants.max'              => 'Maksimal 30 peserta lembur yang dapat ditambahkan.',
            'participants.*.nip.required'   => 'NPP/NIP peserta lembur wajib dipilih.',
            'participants.*.nip.exists'     => 'Salah satu peserta lembur tidak ditemukan dalam sistem.',
            'participants.*.task.required'  => 'Deskripsi tugas setiap peserta lembur wajib diisi.',
            'participants.*.task.max'       => 'Deskripsi tugas maksimal 255 karakter.',
        ];
    }

    /**
     * Validasi bisnis KFPB lanjutan setelah rule dasar lolos.
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $nip       = $this->input('applicant_nip');
                $startDate = $this->input('start_date');
                $today     = now()->toDateString();

                // Business Rule #1: Larangan lembur backdate
                if ($startDate < $today) {
                    $validator->errors()->add(
                        'start_date',
                        'Tidak dapat membuat pengajuan lembur backdate. Jika ada lembur mendadak, ajukan Memo Lembur manual minimal dari Asman & PPIC.'
                    );
                    return;
                }

                // Business Rule #2: Pejabat ACC tidak boleh dobel
                $supervisors = array_values(array_filter((array) $this->input('supervisors', [])));
                if (count($supervisors) !== count(array_unique($supervisors))) {
                    $validator->errors()->add(
                        'supervisors',
                        'Pejabat yang meng-ACC tidak boleh sama (dobel). Setiap level ACC harus pejabat yang berbeda.'
                    );
                    return;
                }

                // Business Rule #3: Blokir jika ada SPL approved yang belum diverifikasi
                // (kd_approve=1 dan tgl_akhir masih NULL / belum terisi, mapping: approval_status=1 & end_date = start_date bawaan migration lama)
                $hasUnverifiedApproved = OvertimeRequest::where('applicant_nip', $nip)
                    ->where('approval_status', 1)
                    ->whereNull('end_date')
                    ->exists();

                if ($hasUnverifiedApproved) {
                    $validator->errors()->add(
                        'applicant_nip',
                        'Anda memiliki SPL lembur yang sudah disetujui namun belum diverifikasi pelaksanaannya. Selesaikan verifikasi lembur sebelumnya terlebih dahulu.'
                    );
                }
            },
        ];
    }
}
