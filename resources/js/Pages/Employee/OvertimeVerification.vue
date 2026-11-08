<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import Toast from 'primevue/toast';
import { useToast } from 'primevue/usetoast';
import Button from 'primevue/button';
import Tag from 'primevue/tag';
import Select from 'primevue/select';

defineOptions({ layout: AuthenticatedLayout });

const props = defineProps({
    overtime: {
        type: Object,
        required: true,
    },
});

const toast = useToast();

// Inisialisasi formulir verifikasi
const form = useForm({
    corrected_start_time: props.overtime.corrected_start_time || props.overtime.planned_start_time || '',
    participants: props.overtime.participants.map((p) => ({
        id: p.id,
        nip: p.nip,
        name: p.name,
        division: p.division,
        position: p.position,
        task_description: p.task_description,
        real_check_out_time: p.real_check_out_time,
        corrected_end_time: p.corrected_end_time || '',
        is_approved: p.is_approved !== false,
        notes: p.notes || '',
    })),
});

const statusOptions = [
    { label: 'Jadi Lembur', value: true },
    { label: '-- Tidak Jadi --', value: false },
];

function submitVerification() {
    // Validasi jam mulai
    if (!form.corrected_start_time) {
        toast.add({
            severity: 'error',
            summary: 'Validasi Gagal',
            detail: 'Koreksi Jam Mulai Lembur wajib diisi.',
            life: 4000,
        });
        return;
    }

    // Validasi bahwa peserta yang "Jadi Lembur" harus memiliki koreksi jam pulang
    for (let i = 0; i < form.participants.length; i++) {
        const p = form.participants[i];
        if (p.is_approved && !p.corrected_end_time) {
            toast.add({
                severity: 'error',
                summary: 'Validasi Gagal',
                detail: `Koreksi Jam Pulang untuk ${p.name} wajib diisi karena berstatus Jadi Lembur.`,
                life: 5000,
            });
            return;
        }
    }

    form.post(`/lembur/verifikasi/${props.overtime.id}`, {
        preserveScroll: true,
        onError: (errors) => {
            const firstError = Object.values(errors)[0];
            toast.add({
                severity: 'error',
                summary: 'Gagal Menyimpan Verifikasi',
                detail: firstError || 'Periksa kembali isian form Anda.',
                life: 5000,
            });
        },
    });
}
</script>

<template>
    <Head title="Form Verifikasi Lembur - eSDM PT Kimia Farma Plant Banjaran" />

    <div class="space-y-6 max-w-5xl mx-auto px-3 sm:px-6 py-2">
        <Toast position="top-right" />

        <!-- ============== BREADCRUMB & HEADER ============== -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm">
            <div class="space-y-1">
                <div class="flex items-center gap-2 text-xs font-medium text-slate-500">
                    <Link href="/absensi" class="hover:text-[#005BAC] flex items-center gap-1">
                        <i class="pi pi-calendar-check text-[11px]" />
                        <span>Presensi</span>
                    </Link>
                    <span>/</span>
                    <Link href="/lembur/ajukan" class="hover:text-[#005BAC]">
                        Pengajuan Lembur (SPL)
                    </Link>
                    <span>/</span>
                    <span class="text-slate-800 font-semibold">Form Verifikasi Lembur</span>
                </div>
                <h1 class="text-xl sm:text-2xl font-bold text-slate-900 tracking-tight">
                    Form Verifikasi Lembur
                </h1>
                <p class="text-xs sm:text-sm text-slate-500">
                    Verifikasi pelaksanaan jam lembur riil dan kehadiran peserta lembur sesuai rekaman absensi aktual.
                </p>
            </div>
            <div class="flex items-center gap-2 shrink-0">
                <Link href="/lembur/ajukan">
                    <Button label="Kembali ke Riwayat" icon="pi pi-arrow-left" severity="secondary" outlined size="small" />
                </Link>
            </div>
        </div>

        <!-- ============== STATUS VERIFIKASI ALERT ============== -->
        <div
            v-if="overtime.is_verified"
            class="bg-emerald-50 border border-emerald-200 rounded-2xl p-4 flex items-start gap-3"
        >
            <i class="pi pi-check-circle text-emerald-600 text-lg mt-0.5 shrink-0" />
            <div class="text-sm text-emerald-900">
                <p class="font-bold">SPL Ini Sudah Pernah Diverifikasi</p>
                <p class="text-xs mt-0.5 text-emerald-700">
                    Terakhir diverifikasi pada {{ overtime.verified_date }}. Anda tetap dapat memperbarui koreksi jam jika terdapat penyesuaian.
                </p>
            </div>
        </div>

        <!-- ============== INFORMASI SPL MASTER ============== -->
        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
            <div class="px-5 sm:px-6 py-4 bg-gradient-to-r from-[#005BAC] to-[#004280] text-white flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="size-10 rounded-xl bg-white/10 border border-white/20 flex items-center justify-center shrink-0">
                        <i class="pi pi-file-edit text-white text-lg" />
                    </div>
                    <div>
                        <h2 class="font-bold text-sm sm:text-base text-white tracking-tight">Data Surat Perintah Lembur</h2>
                        <p class="text-[11px] text-blue-100">PT Kimia Farma Plant Banjaran &bull; SPL ID #{{ overtime.id }}</p>
                    </div>
                </div>
                <Tag
                    :value="overtime.is_verified ? 'Sudah Verifikasi' : 'Menunggu Verifikasi'"
                    :severity="overtime.is_verified ? 'success' : 'warn'"
                    class="text-xs px-3 py-1"
                />
            </div>

            <div class="p-5 sm:p-6 grid grid-cols-2 sm:grid-cols-4 gap-4 bg-slate-50/60 border-b border-slate-100 text-xs sm:text-sm">
                <div>
                    <span class="text-slate-400 font-semibold block text-[11px] uppercase">Jenis Lembur</span>
                    <span class="font-bold text-slate-800">{{ overtime.type_label }}</span>
                </div>
                <div>
                    <span class="text-slate-400 font-semibold block text-[11px] uppercase">Tanggal Lembur</span>
                    <span class="font-bold text-slate-800">{{ overtime.start_date }}</span>
                </div>
                <div>
                    <span class="text-slate-400 font-semibold block text-[11px] uppercase">Rencana Jam Mulai</span>
                    <span class="font-mono font-bold text-slate-700">{{ overtime.planned_start_time }}</span>
                </div>
                <div>
                    <span class="text-slate-400 font-semibold block text-[11px] uppercase">Rencana Jam Selesai</span>
                    <span class="font-mono font-bold text-slate-700">{{ overtime.planned_end_time }}</span>
                </div>
            </div>

            <!-- FORMULIR VERIFIKASI -->
            <form @submit.prevent="submitVerification" class="p-5 sm:p-7 space-y-6">
                <!-- Koreksi Jam Mulai Lembur -->
                <div class="bg-blue-50/70 border border-blue-200/80 rounded-xl p-4 space-y-2">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                        <div>
                            <label class="text-xs sm:text-sm font-bold text-slate-800 flex items-center gap-1.5">
                                <i class="pi pi-clock text-[#005BAC]" />
                                <span>Koreksi Jam Mulai Lembur</span>
                                <span class="text-rose-500">*</span>
                            </label>
                            <p class="text-[11px] text-slate-500 mt-0.5">
                                Masukkan jam mulai aktual pekerjaan lembur (format 24 jam HH:MM, contoh: 16:00).
                            </p>
                        </div>
                        <div class="w-full sm:w-48 shrink-0">
                            <input
                                v-model="form.corrected_start_time"
                                type="time"
                                class="w-full bg-white border border-slate-300 rounded-lg px-3 py-2 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-[#005BAC]/30 focus:border-[#005BAC]"
                                required
                            />
                        </div>
                    </div>
                    <p v-if="form.errors.corrected_start_time" class="text-xs text-rose-600 font-medium">
                        {{ form.errors.corrected_start_time }}
                    </p>
                </div>

                <!-- Bagian Daftar Peserta Lembur Realisasi -->
                <div class="space-y-4">
                    <div class="border-b border-slate-100 pb-2 flex items-center justify-between">
                        <h3 class="text-sm font-bold uppercase tracking-wider text-slate-700">
                            Realisasi Peserta Lembur ({{ form.participants.length }} Orang)
                        </h3>
                        <span class="text-[11px] text-slate-400 font-normal">
                            Periksa jam scan pulang mesin presensi dan sesuaikan jam pulang aktual
                        </span>
                    </div>

                    <div class="space-y-4">
                        <div
                            v-for="(participant, idx) in form.participants"
                            :key="participant.id"
                            class="border rounded-xl p-4 transition-all"
                            :class="participant.is_approved ? 'bg-white border-slate-200' : 'bg-slate-50/80 border-slate-200/60 opacity-70'"
                        >
                            <!-- Baris Header Peserta -->
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 pb-3 border-b border-slate-100">
                                <div class="flex items-center gap-2.5">
                                    <span class="size-6 rounded-full bg-slate-100 text-slate-600 font-bold text-xs flex items-center justify-center shrink-0">
                                        {{ idx + 1 }}
                                    </span>
                                    <div>
                                        <p class="text-sm font-bold text-slate-900">{{ participant.name }}</p>
                                        <p class="text-[11px] text-slate-500 font-mono">{{ participant.nip }} &bull; {{ participant.division }}</p>
                                    </div>
                                </div>
                                <div class="w-full sm:w-48 shrink-0">
                                    <Select
                                        v-model="participant.is_approved"
                                        :options="statusOptions"
                                        option-label="label"
                                        option-value="value"
                                        class="w-full text-xs"
                                        size="small"
                                    />
                                </div>
                            </div>

                            <!-- Baris Detail & Input Jam -->
                            <div class="pt-3 grid grid-cols-1 sm:grid-cols-12 gap-3 text-xs sm:text-sm">
                                <!-- Tugas Rencana -->
                                <div class="sm:col-span-4 space-y-1">
                                    <span class="text-[11px] font-semibold text-slate-400 uppercase">Tugas Rencana</span>
                                    <div class="p-2 bg-slate-50 rounded-lg text-slate-700 text-xs border border-slate-100 min-h-[38px] flex items-center">
                                        {{ participant.task_description || '-' }}
                                    </div>
                                </div>

                                <!-- Jam Pulang Real Mesin Presensi -->
                                <div class="sm:col-span-2 space-y-1">
                                    <span class="text-[11px] font-semibold text-slate-400 uppercase">Jam Pulang Real</span>
                                    <div class="p-2 bg-slate-100/80 rounded-lg font-mono font-bold text-slate-800 text-xs border border-slate-200 text-center min-h-[38px] flex items-center justify-center">
                                        <i class="pi pi-map-marker text-[10px] mr-1 text-slate-400" />
                                        {{ participant.real_check_out_time }}
                                    </div>
                                </div>

                                <!-- Koreksi Jam Pulang Aktual -->
                                <div class="sm:col-span-3 space-y-1">
                                    <span class="text-[11px] font-semibold text-slate-700 uppercase flex items-center gap-1">
                                        Koreksi Jam Pulang
                                        <span v-if="participant.is_approved" class="text-rose-500">*</span>
                                    </span>
                                    <input
                                        v-model="participant.corrected_end_time"
                                        type="time"
                                        :disabled="!participant.is_approved"
                                        class="w-full border border-slate-300 rounded-lg px-2.5 py-1.5 text-xs font-mono focus:outline-none focus:ring-2 focus:ring-[#005BAC]/30 focus:border-[#005BAC] disabled:bg-slate-100 disabled:text-slate-400"
                                    />
                                    <p class="text-[10px] text-slate-400">Jam selesai riil (HH:MM)</p>
                                </div>

                                <!-- Hasil / Catatan Pekerjaan -->
                                <div class="sm:col-span-3 space-y-1">
                                    <span class="text-[11px] font-semibold text-slate-700 uppercase">Hasil / Realisasi</span>
                                    <input
                                        v-model="participant.notes"
                                        type="text"
                                        :disabled="!participant.is_approved"
                                        placeholder="Contoh: Batch selesai..."
                                        maxlength="255"
                                        class="w-full border border-slate-300 rounded-lg px-2.5 py-1.5 text-xs focus:outline-none focus:ring-2 focus:ring-[#005BAC]/30 focus:border-[#005BAC] disabled:bg-slate-100 disabled:text-slate-400"
                                    />
                                    <p class="text-[10px] text-slate-400">Uraian hasil pekerjaan</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tombol Submit Form -->
                <div class="pt-4 border-t border-slate-100 flex flex-col sm:flex-row justify-end gap-3">
                    <Link href="/lembur/ajukan">
                        <Button
                            type="button"
                            label="Batal"
                            severity="secondary"
                            outlined
                            class="w-full sm:w-auto"
                        />
                    </Link>
                    <Button
                        type="submit"
                        label="Simpan Verifikasi Lembur"
                        icon="pi pi-check"
                        :loading="form.processing"
                        class="bg-[#005BAC] hover:bg-[#004280] w-full sm:w-auto text-white"
                    />
                </div>
            </form>
        </div>
    </div>
</template>
