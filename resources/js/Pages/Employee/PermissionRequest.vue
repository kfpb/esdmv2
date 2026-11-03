<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import Toast from 'primevue/toast';
import { useToast } from 'primevue/usetoast';

defineOptions({ layout: AuthenticatedLayout });

const props = defineProps({
    authEmployee: {
        type: Object,
        required: true,
    },
    isAdmin: {
        type: Boolean,
        default: false,
    },
    employees: {
        type: Array,
        default: () => [],
    },
    permissionTypes: {
        type: Array,
        default: () => [],
    },
    recentRequests: {
        type: Array,
        default: () => [],
    },
});

const toast = useToast();

const fileInputRef = ref(null);
const filePreview = ref(null);
const selectedFileName = ref('');
const employeeSearchQuery = ref('');

// Inisialisasi formulir pengajuan (mengadopsi struktur inputijin.php)
const form = useForm({
    employee_nip: props.authEmployee?.nip || '',
    permission_type_id: 2, // Default: Sakit (id: 2 sesuai inputijin.php)
    start_date: '',
    end_date: '',
    reason: '',
    attachment: null,
});

// Filter pegawai untuk role Admin (searchable dropdown)
const filteredEmployees = computed(() => {
    if (!employeeSearchQuery.value) return props.employees;
    const q = employeeSearchQuery.value.toLowerCase();
    return props.employees.filter(
        emp => emp.full_name?.toLowerCase().includes(q) || emp.nip?.includes(q)
    );
});

// Menghitung estimasi durasi hari izin
const estimatedDurationDays = computed(() => {
    if (!form.start_date || !form.end_date) return 0;
    const start = new Date(form.start_date);
    const end = new Date(form.end_date);
    if (isNaN(start.getTime()) || isNaN(end.getTime()) || end < start) return 0;
    const diffTime = Math.abs(end - start);
    return Math.ceil(diffTime / (1000 * 60 * 60 * 24)) + 1;
});

// Format tanggal ke Bahasa Indonesia sederhana
function formatDateDisplay(dateStr) {
    if (!dateStr) return '-';
    try {
        const d = new Date(dateStr);
        return d.toLocaleDateString('id-ID', {
            day: 'numeric',
            month: 'short',
            year: 'numeric',
        });
    } catch {
        return dateStr;
    }
}

// Handler pemilihan berkas lampiran
function onFileChange(event) {
    const file = event.target.files[0];
    if (!file) {
        removeFile();
        return;
    }

    // Validasi ukuran sisi klien (maksimal 5MB)
    if (file.size > 5 * 1024 * 1024) {
        toast.add({
            severity: 'error',
            summary: 'Ukuran Berkas Terlalu Besar',
            detail: 'Ukuran lampiran maksimal 5MB.',
            life: 4000,
        });
        removeFile();
        return;
    }

    form.attachment = file;
    selectedFileName.value = `${file.name} (${(file.size / 1024).toFixed(1)} KB)`;

    if (file.type.startsWith('image/')) {
        const reader = new FileReader();
        reader.onload = (e) => {
            filePreview.value = e.target.result;
        };
        reader.readAsDataURL(file);
    } else {
        filePreview.value = null;
    }
}

function removeFile() {
    form.attachment = null;
    selectedFileName.value = '';
    filePreview.value = null;
    if (fileInputRef.value) {
        fileInputRef.value.value = '';
    }
}

// Otomatis samakan tanggal akhir jika tanggal awal baru diisi
function onStartDateChange() {
    if (form.start_date && (!form.end_date || form.end_date < form.start_date)) {
        form.end_date = form.start_date;
    }
}

// Submit permohonan
function submitForm() {
    form.post('/izin/simpan', {
        preserveScroll: true,
        onSuccess: () => {
            toast.add({
                severity: 'success',
                summary: 'Berhasil',
                detail: 'Permohonan lapor tidak masuk kerja berhasil disimpan.',
                life: 3000,
            });
        },
        onError: (errors) => {
            const firstError = Object.values(errors)[0];
            toast.add({
                severity: 'error',
                summary: 'Gagal Menyimpan Permohonan',
                detail: firstError || 'Periksa kembali isian formulir Anda.',
                life: 5000,
            });
        },
    });
}
</script>

<template>
    <Head title="Lapor Tidak Masuk Kerja - eSDM PT Kimia Farma Plant Banjaran" />

    <div class="space-y-6 max-w-5xl mx-auto px-3 sm:px-6 py-2">
        <Toast position="top-right" />

        <!-- ================= TOP HEADER BREADCRUMB & TITLE ================= -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm">
            <div class="space-y-1">
                <div class="flex items-center gap-2 text-xs font-medium text-slate-500">
                    <Link href="/absensi" class="hover:text-[#005BAC] flex items-center gap-1">
                        <i class="pi pi-calendar-check text-[11px]" />
                        <span>Presensi</span>
                    </Link>
                    <span>/</span>
                    <span class="text-slate-800 font-semibold">Lapor Tidak Masuk Kerja</span>
                </div>
                <h1 class="text-xl sm:text-2xl font-bold text-slate-900 tracking-tight">
                    Formulir Izin Ketidakhadiran
                </h1>
                <p class="text-xs sm:text-sm text-slate-500">
                    Pelaporan ketidakhadiran mandiri: Sakit, Cuti Tahunan, Dinas Luar, atau Dispensasi Kerja.
                </p>
            </div>

            <Link
                href="/absensi"
                class="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl border border-slate-200 text-slate-700 bg-slate-50 hover:bg-slate-100 font-semibold text-xs sm:text-sm transition-colors shrink-0 self-start sm:self-auto"
            >
                <i class="pi pi-arrow-left text-xs" />
                <span>Kembali ke Presensi</span>
            </Link>
        </div>

        <!-- ================= MAIN FORM CARD ================= -->
        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
            <!-- Header Card KFPB -->
            <div class="px-5 sm:px-6 py-4 bg-gradient-to-r from-[#005BAC] to-[#004280] text-white flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="size-10 rounded-xl bg-white/10 border border-white/20 flex items-center justify-center shrink-0">
                        <i class="pi pi-file-edit text-white text-lg" />
                    </div>
                    <div>
                        <h2 class="font-bold text-sm sm:text-base tracking-tight text-white">
                            Form Input Izin Tidak Hadir
                        </h2>
                        <p class="text-[11px] text-blue-100">
                            PT Kimia Farma Plant Banjaran • Data langsung ke rekap Absensi
                        </p>
                    </div>
                </div>
            </div>

            <!-- Form Content -->
            <form @submit.prevent="submitForm" class="p-5 sm:p-7 space-y-6">

                <!-- Alert Validasi Global (Jika ada error dari server) -->
                <div
                    v-if="Object.keys(form.errors).length > 0"
                    class="p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-xs sm:text-sm space-y-1"
                >
                    <div class="font-bold flex items-center gap-2 text-rose-900">
                        <i class="pi pi-exclamation-triangle" />
                        <span>Mohon Perbaiki Isian Formulir:</span>
                    </div>
                    <ul class="list-disc list-inside space-y-0.5 text-rose-700 pl-1">
                        <li v-for="(errMsg, errField) in form.errors" :key="errField">
                            {{ errMsg }}
                        </li>
                    </ul>
                </div>

                <!-- 1. Identitas Pegawai (Admin vs Pegawai Biasa) -->
                <div class="space-y-2">
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700">
                        Pegawai yang Mengajukan
                    </label>

                    <!-- JIKA ADMIN (sesuai sesi_level == 1 di inputijin.php) -->
                    <div v-if="isAdmin" class="space-y-2">
                        <div class="relative">
                            <select
                                v-model="form.employee_nip"
                                class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 text-xs sm:text-sm text-slate-800 bg-white focus:outline-none focus:ring-2 focus:ring-[#005BAC]/30 focus:border-[#005BAC] transition-all"
                                :class="{ 'border-rose-400': form.errors.employee_nip }"
                            >
                                <option value="" disabled>-- Pilih Pegawai KFPB --</option>
                                <option
                                    v-for="emp in employees"
                                    :key="emp.nip"
                                    :value="emp.nip"
                                >
                                    {{ emp.full_name }} (NIP: {{ emp.nip }})
                                </option>
                            </select>
                        </div>
                        <p class="text-[11px] text-slate-500">
                            Sebagai Admin / HRD, Anda dapat mendaftarkan izin atas nama pegawai lain.
                        </p>
                    </div>

                    <!-- JIKA PEGAWAI BIASA (Readonly Sesuai inputijin.php) -->
                    <div v-else class="grid grid-cols-1 sm:grid-cols-2 gap-3 p-3.5 rounded-xl bg-slate-50 border border-slate-200/70">
                        <div>
                            <span class="text-[10px] uppercase font-bold text-slate-400 tracking-wider">NPP / NIP Pegawai</span>
                            <p class="text-sm font-bold font-mono text-slate-800 mt-0.5">
                                {{ authEmployee?.nip || '-' }}
                            </p>
                        </div>
                        <div>
                            <span class="text-[10px] uppercase font-bold text-slate-400 tracking-wider">Nama Lengkap</span>
                            <p class="text-sm font-bold text-slate-800 mt-0.5">
                                {{ authEmployee?.name || '-' }}
                            </p>
                        </div>
                        <div class="sm:col-span-2 pt-1 border-t border-slate-200/60 flex items-center gap-2 text-xs text-slate-600">
                            <i class="pi pi-briefcase text-slate-400" />
                            <span>{{ authEmployee?.position || 'Staf' }} • {{ authEmployee?.division || 'Plant Banjaran' }}</span>
                        </div>
                    </div>
                </div>

                <!-- 2. Rentang Tanggal Izin -->
                <div class="space-y-2">
                    <div class="flex items-center justify-between">
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-700">
                            Periode Tanggal Izin <span class="text-rose-500">*</span>
                        </label>
                        <span
                            v-if="estimatedDurationDays > 0"
                            class="inline-flex items-center px-2 py-0.5 rounded-md bg-blue-50 text-[#005BAC] text-xs font-bold border border-blue-200/60"
                        >
                            Estimasi: {{ estimatedDurationDays }} Hari Kerja
                        </span>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                        <!-- Tanggal Awal -->
                        <div>
                            <span class="block text-[11px] font-semibold text-slate-600 mb-1">Tanggal Awal Izin</span>
                            <div class="relative">
                                <input
                                    v-model="form.start_date"
                                    type="date"
                                    class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 text-xs sm:text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-[#005BAC]/30 focus:border-[#005BAC] transition-all"
                                    :class="{ 'border-rose-400': form.errors.start_date }"
                                    @change="onStartDateChange"
                                />
                            </div>
                            <span v-if="form.errors.start_date" class="block text-[11px] text-rose-600 mt-1">
                                {{ form.errors.start_date }}
                            </span>
                        </div>

                        <!-- Tanggal Akhir -->
                        <div>
                            <span class="block text-[11px] font-semibold text-slate-600 mb-1">Tanggal Akhir Izin</span>
                            <div class="relative">
                                <input
                                    v-model="form.end_date"
                                    type="date"
                                    :min="form.start_date || undefined"
                                    class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 text-xs sm:text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-[#005BAC]/30 focus:border-[#005BAC] transition-all"
                                    :class="{ 'border-rose-400': form.errors.end_date }"
                                />
                            </div>
                            <span v-if="form.errors.end_date" class="block text-[11px] text-rose-600 mt-1">
                                {{ form.errors.end_date }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- 3. Jenis Izin (Sakit, Cuti, Dinas Luar, dsb) -->
                <div class="space-y-2">
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700">
                        Jenis Izin / Kategori Ketidakhadiran <span class="text-rose-500">*</span>
                    </label>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <label
                            v-for="pt in permissionTypes"
                            :key="pt.id"
                            class="relative flex items-center gap-3 p-3.5 rounded-xl border cursor-pointer transition-all select-none"
                            :class="[
                                form.permission_type_id === pt.id
                                    ? 'bg-blue-50/70 border-[#005BAC] ring-1 ring-[#005BAC] shadow-sm'
                                    : 'bg-white border-slate-200 hover:border-slate-300 hover:bg-slate-50/50'
                            ]"
                        >
                            <input
                                v-model="form.permission_type_id"
                                type="radio"
                                :value="pt.id"
                                class="text-[#005BAC] focus:ring-[#005BAC]"
                            />
                            <div class="flex-1">
                                <p class="text-xs sm:text-sm font-bold text-slate-800">
                                    {{ pt.name }}
                                </p>
                                <span v-if="pt.id === 2" class="text-[10px] text-slate-500 block">
                                    Lampirkan surat keterangan dokter
                                </span>
                                <span v-else-if="pt.id === 3" class="text-[10px] text-slate-500 block">
                                    Potong jatah hak cuti tahunan
                                </span>
                                <span v-else-if="pt.id === 5" class="text-[10px] text-slate-500 block">
                                    Tugas luar kota / kantor dinas
                                </span>
                            </div>
                        </label>
                    </div>
                    <span v-if="form.errors.permission_type_id" class="block text-[11px] text-rose-600 mt-1">
                        {{ form.errors.permission_type_id }}
                    </span>
                </div>

                <!-- 4. Keterangan / Alasan -->
                <div class="space-y-1.5">
                    <label for="reason" class="block text-xs font-bold uppercase tracking-wider text-slate-700">
                        Keterangan Tambahan / Alasan
                    </label>
                    <textarea
                        id="reason"
                        v-model="form.reason"
                        rows="3"
                        placeholder="Tuliskan keterangan contoh: Rawat inap RS, Cuti keperluan keluarga mendadak, Dinas keluar"
                        class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 text-xs sm:text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-[#005BAC]/30 focus:border-[#005BAC] transition-all resize-y"
                        :class="{ 'border-rose-400': form.errors.reason }"
                    />
                    <span v-if="form.errors.reason" class="block text-[11px] text-rose-600">
                        {{ form.errors.reason }}
                    </span>
                </div>

                <!-- 5. Lampiran Bukti Foto / Dokumen -->
                <div class="space-y-2">
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700">
                        Lampiran Bukti Dokumen (Surat Dokter / Form Cuti / Surat Tugas)
                    </label>

                    <div class="flex flex-col sm:flex-row items-start sm:items-center gap-3">
                        <label
                            class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl border border-slate-300 bg-slate-50 hover:bg-slate-100 text-slate-700 text-xs sm:text-sm font-semibold cursor-pointer transition-colors shadow-sm"
                        >
                            <i class="pi pi-upload text-sm" />
                            <span>Pilih Berkas Lampiran</span>
                            <input
                                ref="fileInputRef"
                                type="file"
                                accept="image/*,application/pdf"
                                class="hidden"
                                @change="onFileChange"
                            />
                        </label>

                        <div v-if="selectedFileName" class="flex items-center gap-2 text-xs font-medium text-slate-700 bg-slate-100 px-3 py-1.5 rounded-lg border border-slate-200">
                            <i class="pi pi-file text-blue-600" />
                            <span class="truncate max-w-xs">{{ selectedFileName }}</span>
                            <button
                                type="button"
                                class="text-rose-600 hover:text-rose-800 p-0.5 ml-1"
                                title="Hapus berkas"
                                @click="removeFile"
                            >
                                <i class="pi pi-times text-xs" />
                            </button>
                        </div>
                        <span v-else class="text-xs text-slate-400">
                            Format berkas: JPG, PNG, atau PDF (maksimal 5MB)
                        </span>
                    </div>

                    <!-- Preview Gambar jika ada -->
                    <div v-if="filePreview" class="pt-2">
                        <div class="inline-block relative rounded-xl border border-slate-200 overflow-hidden shadow-sm max-w-xs">
                            <img :src="filePreview" alt="Pratinjau Bukti Izin" class="max-h-48 object-cover rounded-xl" />
                        </div>
                    </div>

                    <span v-if="form.errors.attachment" class="block text-[11px] text-rose-600">
                        {{ form.errors.attachment }}
                    </span>
                </div>

                <!-- 6. Action Buttons -->
                <div class="pt-4 border-t border-slate-200 flex flex-col sm:flex-row items-center justify-center gap-3">
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-6 py-3 rounded-xl bg-[#005BAC] text-white hover:bg-[#004785] font-bold text-xs sm:text-sm shadow-sm transition-all disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        <i v-if="form.processing" class="pi pi-spin pi-spinner text-sm" />
                        <i v-else class="pi pi-check text-sm" />
                        <span>{{ form.processing ? 'Menyimpan Permohonan...' : 'Simpan' }}</span>
                    </button>

                    <Link
                        href="/absensi"
                        class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-5 py-3 rounded-xl border border-slate-300 text-slate-700 bg-white hover:bg-slate-50 font-semibold text-xs sm:text-sm transition-colors text-center"
                    >
                        <i class="pi pi-times text-xs" />
                        <span>Batal</span>
                    </Link>
                </div>
            </form>
        </div>

        <!-- ================= RIWAYAT 5 PENGAJUAN TERAKHIR SAYA ================= -->
        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-5 sm:p-6 space-y-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2.5">
                    <i class="pi pi-history text-[#005BAC]" />
                    <h3 class="text-sm sm:text-base font-bold text-slate-900 tracking-tight">
                        Riwayat Laporan Izin Terakhir Saya
                    </h3>
                </div>
                <span class="text-xs text-slate-400">
                    Menampilkan maksimal 5 permohonan terkini
                </span>
            </div>

            <!-- Empty State -->
            <div v-if="recentRequests.length === 0" class="text-center py-8 text-slate-400 text-xs">
                <i class="pi pi-inbox text-3xl mb-2 block text-slate-300" />
                <span>Belum ada histori permohonan izin atau lapor tidak masuk kerja yang tercatat.</span>
            </div>

            <!-- List Card / Table -->
            <div v-else class="overflow-x-auto">
                <table class="w-full text-left text-xs text-slate-700">
                    <thead>
                        <tr class="border-b border-slate-200 text-slate-400 font-bold uppercase text-[10px] tracking-wider">
                            <th class="py-2.5 px-3">Jenis Izin</th>
                            <th class="py-2.5 px-3">Periode Tanggal</th>
                            <th class="py-2.5 px-3">Keterangan</th>
                            <th class="py-2.5 px-3">Lampiran</th>
                            <th class="py-2.5 px-3 text-right">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="req in recentRequests" :key="req.id" class="hover:bg-slate-50/70 transition-colors">
                            <td class="py-3 px-3 font-bold text-slate-800">
                                {{ req.type_name }}
                            </td>
                            <td class="py-3 px-3 font-mono text-slate-600">
                                {{ req.start_date }} <span v-if="req.start_date !== req.end_date">s/d {{ req.end_date }}</span>
                            </td>
                            <td class="py-3 px-3 max-w-xs truncate text-slate-600">
                                {{ req.reason }}
                            </td>
                            <td class="py-3 px-3">
                                <a
                                    v-if="req.attachment_url"
                                    :href="req.attachment_url"
                                    target="_blank"
                                    class="inline-flex items-center gap-1 text-[#005BAC] hover:underline font-semibold"
                                >
                                    <i class="pi pi-paperclip text-[11px]" />
                                    <span>Lihat</span>
                                </a>
                                <span v-else class="text-slate-400">-</span>
                            </td>
                            <td class="py-3 px-3 text-right">
                                <span
                                    v-if="req.approval_status === 1"
                                    class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200"
                                >
                                    Tercatat
                                </span>
                                <span
                                    v-else-if="req.approval_status === 2"
                                    class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-rose-50 text-rose-700 border border-rose-200"
                                >
                                    Ditolak
                                </span>
                                <span
                                    v-else
                                    class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-50 text-amber-700 border border-amber-200"
                                >
                                    Pending
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</template>

