<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';
import Toast from 'primevue/toast';
import { useToast } from 'primevue/usetoast';
import DatePicker from 'primevue/datepicker';
import Select from 'primevue/select';
import Button from 'primevue/button';
import Tag from 'primevue/tag';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Dialog from 'primevue/dialog';
import IftaLabel from 'primevue/iftalabel';

defineOptions({ layout: AuthenticatedLayout });

const props = defineProps({
    authEmployee: { type: Object, required: true },
    overtimeTypes: { type: Object, default: () => ({}) },
    supervisorCandidates: { type: Array, default: () => [] },
    allEmployees: { type: Array, default: () => [] },
    recentOvertime: { type: Array, default: () => [] },
    pendingVerificationCount: { type: Number, default: 0 },
});

const toast = useToast();

// --- STATE FORMULIR ---
const selectedDate = ref(null);

// Form data menggunakan Inertia useForm
const form = useForm({
    applicant_nip: props.authEmployee?.nip || '',
    overtime_type: 1,
    start_date: '',
    start_time: '',
    end_time: '',
    supervisors: ['', '', ''],     // Tiga slot ACC (ACC 1 wajib, ACC 2 & 3 opsional)
    participants: [{ nip: '', task: '' }],
});

// Jenis lembur sebagai array opsi untuk Select
const overtimeTypeOptions = computed(() =>
    Object.entries(props.overtimeTypes).map(([id, label]) => ({
        value: parseInt(id),
        label,
    }))
);

// Konversi tanggal PrimeVue DatePicker ke string YYYY-MM-DD
function onDateChange(val) {
    if (val instanceof Date && !isNaN(val.getTime())) {
        const y = val.getFullYear();
        const m = String(val.getMonth() + 1).padStart(2, '0');
        const d = String(val.getDate()).padStart(2, '0');
        form.start_date = `${y}-${m}-${d}`;
    } else {
        form.start_date = '';
    }
}

// Batas minimum tanggal lembur = hari ini (tidak boleh backdate)
const minDate = computed(() => {
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    return today;
});

// --- MANAJEMEN PESERTA DINAMIS ---
function addParticipant() {
    if (form.participants.length >= 30) {
        toast.add({ severity: 'warn', summary: 'Batas Peserta', detail: 'Maksimal 30 peserta lembur.', life: 3000 });
        return;
    }
    form.participants.push({ nip: '', task: '' });
}

function removeParticipant(index) {
    if (form.participants.length <= 1) {
        toast.add({ severity: 'warn', summary: 'Minimal 1 Peserta', detail: 'Minimal 1 peserta lembur harus ada.', life: 3000 });
        return;
    }
    form.participants.splice(index, 1);
}

// --- SUBMIT FORMULIR ---
function submitForm() {
    // Bersihkan slot supervisors yang kosong, kirim hanya yang terisi
    const filledSupervisors = form.supervisors.filter((s) => s && s.trim() !== '');

    if (filledSupervisors.length === 0) {
        toast.add({ severity: 'error', summary: 'ACC Wajib', detail: 'Minimal 1 pejabat penyetuju (ACC 1) wajib dipilih.', life: 4000 });
        return;
    }

    // Validasi ACC tidak boleh dobel
    if (new Set(filledSupervisors).size !== filledSupervisors.length) {
        toast.add({ severity: 'error', summary: 'Duplikasi ACC', detail: 'Pejabat yang meng-ACC tidak boleh sama pada setiap level.', life: 4000 });
        return;
    }

    // Validasi ACC harus berurutan (ACC 2 tidak boleh terisi jika ACC 1 kosong, dst)
    if (!form.supervisors[0] && (form.supervisors[1] || form.supervisors[2])) {
        toast.add({ severity: 'error', summary: 'Urutan ACC Salah', detail: 'ACC 1 wajib diisi sebelum mengisi ACC 2 atau ACC 3.', life: 4000 });
        return;
    }
    if (!form.supervisors[1] && form.supervisors[2]) {
        toast.add({ severity: 'error', summary: 'Urutan ACC Salah', detail: 'ACC 2 wajib diisi sebelum mengisi ACC 3.', life: 4000 });
        return;
    }

    form.supervisors = filledSupervisors;

    form.post('/lembur/simpan', {
        preserveScroll: true,
        onSuccess: () => {
            toast.add({
                severity: 'success',
                summary: 'SPL Berhasil Diajukan',
                detail: 'Surat Perintah Lembur berhasil disimpan. Informasikan ke Atasan untuk persetujuan.',
                life: 5000,
            });
            form.reset('overtime_type', 'start_date', 'start_time', 'end_time', 'participants');
            form.supervisors = ['', '', ''];
            selectedDate.value = null;
            form.participants = [{ nip: '', task: '' }];
        },
        onError: (errors) => {
            const firstError = Object.values(errors)[0];
            toast.add({
                severity: 'error',
                summary: 'Gagal Menyimpan SPL',
                detail: firstError || 'Periksa kembali isian formulir Anda.',
                life: 6000,
            });
        },
    });
}

// --- DETAIL RIWAYAT (DIALOG) ---
const detailDialogVisible = ref(false);
const detailData = ref(null);
const loadingDetail = ref(false);

async function showDetail(id) {
    loadingDetail.value = true;
    detailDialogVisible.value = true;
    detailData.value = null;
    try {
        const res = await fetch(`/lembur/${id}`);
        if (!res.ok) throw new Error('Gagal memuat data');
        detailData.value = await res.json();
    } catch {
        toast.add({ severity: 'error', summary: 'Gagal Memuat', detail: 'Gagal memuat detail pengajuan lembur.', life: 4000 });
        detailDialogVisible.value = false;
    } finally {
        loadingDetail.value = false;
    }
}

// --- BADGE STATUS ---
function getStatusSeverity(row) {
    if (typeof row === 'object' && row !== null) {
        if (row.is_verified) return 'success';
        if (row.can_verify) return 'warn';
        if (row.approval_status === 1) return 'success';
        if (row.approval_status === 2) return 'danger';
        return 'warn';
    }
    if (row === 1) return 'success';
    if (row === 2) return 'danger';
    return 'warn';
}

function getStatusLabel(status) {
    const labels = { 0: 'Menunggu', 1: 'Disetujui', 2: 'Ditolak', 3: 'Menunggu ACC 1', 4: 'Menunggu ACC 2', 5: 'Menunggu ACC 3' };
    return labels[status] ?? 'Menunggu';
}

function getTypeLabel(type) {
    return props.overtimeTypes[type] ?? 'Lembur';
}
</script>

<template>
    <Head title="Pengajuan Lembur (SPL) - eSDM PT Kimia Farma Plant Banjaran" />

    <div class="space-y-6 max-w-5xl mx-auto px-3 sm:px-6 py-2">
        <Toast position="top-right" />

        <!-- ============== HEADER BREADCRUMB ============== -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm">
            <div class="space-y-1">
                <div class="flex items-center gap-2 text-xs font-medium text-slate-500">
                    <Link href="/absensi" class="hover:text-[#005BAC] flex items-center gap-1">
                        <i class="pi pi-calendar-check text-[11px]" />
                        <span>Presensi</span>
                    </Link>
                    <span>/</span>
                    <span class="text-slate-800 font-semibold">Pengajuan Lembur (SPL)</span>
                </div>
                <h1 class="text-xl sm:text-2xl font-bold text-slate-900 tracking-tight">
                    Surat Perintah Lembur (SPL)
                </h1>
                <p class="text-xs sm:text-sm text-slate-500">
                    Formulir pengajuan lembur resmi KFPB — rencana lembur beserta daftar peserta dan persetujuan atasan.
                </p>
            </div>
            <div class="flex items-center gap-2 shrink-0">
                <!-- Badge SPL menunggu verifikasi -->
                <span
                    v-if="pendingVerificationCount > 0"
                    class="inline-flex items-center gap-1 bg-amber-100 text-amber-700 text-[11px] font-semibold px-2.5 py-1 rounded-full border border-amber-200"
                >
                    <i class="pi pi-exclamation-triangle text-[10px]" />
                    {{ pendingVerificationCount }} SPL menunggu verifikasi
                </span>
                <Link href="/absensi">
                    <Button label="Kembali" icon="pi pi-arrow-left" severity="secondary" outlined size="small" />
                </Link>
            </div>
        </div>

        <!-- ============== ALERT PERINGATAN PENDING VERIFIKASI ============== -->
        <div
            v-if="pendingVerificationCount > 0"
            class="bg-amber-50 border border-amber-200 rounded-2xl p-4 flex items-start gap-3"
        >
            <i class="pi pi-exclamation-triangle text-amber-500 text-lg mt-0.5 shrink-0" />
            <div class="text-sm text-amber-800">
                <p class="font-semibold">Anda memiliki {{ pendingVerificationCount }} SPL yang sudah disetujui dan menunggu verifikasi pelaksanaan.</p>
                <p class="text-xs mt-1 text-amber-700">
                    Verifikasi SPL lama terlebih dahulu sebelum membuat pengajuan baru. Lihat di tab Riwayat di bawah.
                </p>
            </div>
        </div>

        <!-- ============== ALERT FLASH SUCCESS ============== -->
        <div v-if="$page.props.flash?.success" class="bg-emerald-50 border border-emerald-200 rounded-2xl p-4 flex items-start gap-3">
            <i class="pi pi-check-circle text-emerald-500 text-lg mt-0.5 shrink-0" />
            <p class="text-sm text-emerald-800 font-medium">{{ $page.props.flash.success }}</p>
        </div>

        <!-- ============== FORMULIR PENGAJUAN SPL ============== -->
        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
            <!-- Header Card -->
            <div class="px-5 sm:px-6 py-4 bg-gradient-to-r from-[#005BAC] to-[#004280] text-white flex items-center gap-3">
                <div class="size-10 rounded-xl bg-white/10 border border-white/20 flex items-center justify-center shrink-0">
                    <i class="pi pi-clock text-white text-lg" />
                </div>
                <div>
                    <h2 class="font-bold text-sm sm:text-base tracking-tight text-white">Form Pengajuan Lembur Baru</h2>
                    <p class="text-[11px] text-blue-100">PT Kimia Farma Plant Banjaran &bull; Rencana lembur harus diajukan sebelum pelaksanaan</p>
                </div>
            </div>

            <form @submit.prevent="submitForm" class="p-5 sm:p-7 space-y-7">
                <!-- Alert Validasi Global -->
                <div
                    v-if="Object.keys(form.errors).length > 0"
                    class="p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-xs sm:text-sm space-y-1"
                >
                    <div class="font-bold flex items-center gap-2 text-rose-900">
                        <i class="pi pi-exclamation-triangle" />
                        <span>Mohon Perbaiki Isian Formulir:</span>
                    </div>
                    <ul class="list-disc list-inside space-y-0.5 text-rose-700 pl-1">
                        <li v-for="(errMsg, errField) in form.errors" :key="errField">{{ errMsg }}</li>
                    </ul>
                </div>

                <!-- BAGIAN 1: Data Pengaju -->
                <section class="space-y-3">
                    <h3 class="text-sm font-bold uppercase tracking-wider text-slate-700 border-b border-slate-100 pb-2">
                        Data Pengaju Lembur
                    </h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="space-y-1">
                            <label class="text-xs font-semibold text-slate-600">NPP / NIP Pengaju</label>
                            <div class="bg-slate-50 border border-slate-200 rounded-lg px-3 py-2.5 text-sm font-mono text-slate-700 select-none">
                                {{ authEmployee.nip }}
                            </div>
                        </div>
                        <div class="space-y-1">
                            <label class="text-xs font-semibold text-slate-600">Nama Pegawai</label>
                            <div class="bg-slate-50 border border-slate-200 rounded-lg px-3 py-2.5 text-sm text-slate-700 select-none">
                                {{ authEmployee.name }}
                            </div>
                        </div>
                        <div class="space-y-1 sm:col-span-2">
                            <label class="text-xs font-semibold text-slate-600">Unit Kerja / Bagian</label>
                            <div class="bg-slate-50 border border-slate-200 rounded-lg px-3 py-2.5 text-sm text-slate-700 select-none">
                                {{ authEmployee.division }} {{ authEmployee.sub_division !== '-' ? `— ${authEmployee.sub_division}` : '' }}
                            </div>
                        </div>
                    </div>
                </section>

                <!-- BAGIAN 2: Rencana Lembur -->
                <section class="space-y-4">
                    <h3 class="text-sm font-bold uppercase tracking-wider text-slate-700 border-b border-slate-100 pb-2">
                        Rencana Lembur
                    </h3>

                    <!-- Peringatan Bisnis Kunci -->
                    <div class="bg-blue-50 border border-blue-200 rounded-xl p-4 text-xs text-blue-800 space-y-1.5">
                        <p class="font-semibold text-blue-900 flex items-center gap-1.5"><i class="pi pi-info-circle" /> Ketentuan Pengajuan Lembur KFPB:</p>
                        <ul class="list-disc list-inside space-y-1 pl-2 text-blue-700">
                            <li>ACC 1: Asman / Pgs. Asman (wajib diisi)</li>
                            <li>ACC 2: Manager / Pgs. Manager (wajib untuk lembur Sabtu/Minggu/Hari Libur)</li>
                            <li>ACC terakhir: Asman Pengendalian Proses Produksi (PPIC)</li>
                            <li>Pejabat ACC tidak boleh sama pada setiap level</li>
                            <li class="font-semibold text-blue-800">Tidak bisa membuat lembur backdate. Jika ada lembur mendadak, ajukan Memo Lembur manual minimal dari Asman &amp; PPIC</li>
                        </ul>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <!-- Jenis Lembur -->
                        <div class="space-y-1.5 sm:col-span-2">
                            <label class="text-xs font-semibold text-slate-700">Jenis Lembur <span class="text-rose-500">*</span></label>
                            <IftaLabel class="w-full">
                                <Select
                                    v-model="form.overtime_type"
                                    input-id="overtime_type"
                                    :options="overtimeTypeOptions"
                                    option-label="label"
                                    option-value="value"
                                    class="w-full"
                                    placeholder="-- Pilih Jenis Lembur --"
                                />
                            </IftaLabel>
                            <p v-if="form.errors.overtime_type" class="text-xs text-rose-600">{{ form.errors.overtime_type }}</p>
                        </div>

                        <!-- Tanggal Rencana Lembur -->
                        <div class="space-y-1.5">
                            <label class="text-xs font-semibold text-slate-700">Tanggal Rencana Lembur <span class="text-rose-500">*</span></label>
                            <DatePicker
                                v-model="selectedDate"
                                input-id="start_date"
                                date-format="yy-mm-dd"
                                :min-date="minDate"
                                show-button-bar
                                class="w-full"
                                placeholder="YYYY-MM-DD"
                                @date-select="onDateChange"
                                @clear-click="() => { selectedDate = null; form.start_date = ''; }"
                            />
                            <p v-if="form.errors.start_date" class="text-xs text-rose-600">{{ form.errors.start_date }}</p>
                        </div>

                        <!-- Jam Mulai -->
                        <div class="space-y-1.5">
                            <label class="text-xs font-semibold text-slate-700">Jam Mulai Rencana <span class="text-rose-500">*</span></label>
                            <input
                                v-model="form.start_time"
                                type="time"
                                class="w-full border border-slate-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#005BAC]/30 focus:border-[#005BAC]"
                            />
                            <p class="text-[11px] text-slate-400">Format 24 jam, contoh: 16:00</p>
                            <p v-if="form.errors.start_time" class="text-xs text-rose-600">{{ form.errors.start_time }}</p>
                        </div>

                        <!-- Jam Selesai -->
                        <div class="space-y-1.5">
                            <label class="text-xs font-semibold text-slate-700">Jam Selesai Rencana <span class="text-rose-500">*</span></label>
                            <input
                                v-model="form.end_time"
                                type="time"
                                class="w-full border border-slate-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#005BAC]/30 focus:border-[#005BAC]"
                            />
                            <p class="text-[11px] text-slate-400">Format 24 jam, contoh: 20:00</p>
                            <p v-if="form.errors.end_time" class="text-xs text-rose-600">{{ form.errors.end_time }}</p>
                        </div>
                    </div>
                </section>

                <!-- BAGIAN 3: Pejabat Penyetuju (ACC) -->
                <section class="space-y-4">
                    <h3 class="text-sm font-bold uppercase tracking-wider text-slate-700 border-b border-slate-100 pb-2">
                        Pejabat Penyetuju (ACC Lembur)
                    </h3>
                    <p v-if="form.errors.supervisors" class="text-xs text-rose-600">{{ form.errors.supervisors }}</p>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div v-for="(_, idx) in [0, 1, 2]" :key="idx" class="space-y-1.5">
                            <label class="text-xs font-semibold text-slate-700">
                                ACC {{ idx + 1 }}
                                <span v-if="idx === 0" class="text-rose-500">*</span>
                                <span v-else class="text-slate-400 font-normal">(opsional)</span>
                            </label>
                            <IftaLabel class="w-full">
                                <Select
                                    v-model="form.supervisors[idx]"
                                    :input-id="`supervisor_${idx + 1}`"
                                    :options="supervisorCandidates"
                                    option-label="label"
                                    option-value="nip"
                                    class="w-full"
                                    :placeholder="`-- Pilih Pejabat ACC ${idx + 1} --`"
                                    filter
                                    show-clear
                                    :disabled="idx > 0 && !form.supervisors[idx - 1]"
                                />
                            </IftaLabel>
                        </div>
                    </div>
                </section>

                <!-- BAGIAN 4: Daftar Peserta Lembur -->
                <section class="space-y-4">
                    <div class="flex items-center justify-between">
                        <h3 class="text-sm font-bold uppercase tracking-wider text-slate-700 border-b border-slate-100 pb-2 grow">
                            Daftar Peserta Lembur
                            <span class="text-slate-400 font-normal text-xs normal-case ml-2">({{ form.participants.length }}/30)</span>
                        </h3>
                    </div>
                    <p v-if="form.errors.participants" class="text-xs text-rose-600">{{ form.errors.participants }}</p>

                    <div class="space-y-3">
                        <div
                            v-for="(participant, idx) in form.participants"
                            :key="idx"
                            class="bg-slate-50 border border-slate-200 rounded-xl p-4 space-y-3"
                        >
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-bold text-slate-600">Pegawai {{ idx + 1 }}</span>
                                <button
                                    v-if="form.participants.length > 1"
                                    type="button"
                                    class="text-rose-400 hover:text-rose-600 text-xs flex items-center gap-1 transition-colors"
                                    @click="removeParticipant(idx)"
                                >
                                    <i class="pi pi-trash text-[11px]" />
                                    Hapus
                                </button>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <!-- Pilih Pegawai -->
                                <div class="space-y-1">
                                    <label class="text-xs font-semibold text-slate-600">Pilih Pegawai <span class="text-rose-500">*</span></label>
                                    <Select
                                        v-model="participant.nip"
                                        :options="allEmployees"
                                        option-label="label"
                                        option-value="nip"
                                        class="w-full"
                                        placeholder="-- Pilih / Ketik Nama --"
                                        filter
                                    />
                                    <p v-if="form.errors[`participants.${idx}.nip`]" class="text-xs text-rose-600">
                                        {{ form.errors[`participants.${idx}.nip`] }}
                                    </p>
                                </div>

                                <!-- Uraian Tugas -->
                                <div class="space-y-1">
                                    <label class="text-xs font-semibold text-slate-600">Uraian Tugas Lembur <span class="text-rose-500">*</span></label>
                                    <input
                                        v-model="participant.task"
                                        type="text"
                                        maxlength="255"
                                        class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#005BAC]/30 focus:border-[#005BAC]"
                                        placeholder="Contoh: Perbaikan mesin fette, Setup batch produksi..."
                                    />
                                    <p v-if="form.errors[`participants.${idx}.task`]" class="text-xs text-rose-600">
                                        {{ form.errors[`participants.${idx}.task`] }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <Button
                        type="button"
                        label="+ Tambah Peserta Lembur"
                        severity="secondary"
                        outlined
                        size="small"
                        icon="pi pi-plus"
                        :disabled="form.participants.length >= 30"
                        @click="addParticipant"
                    />
                </section>

                <!-- TOMBOL SUBMIT -->
                <div class="pt-2 border-t border-slate-100 flex flex-col sm:flex-row justify-end gap-3">
                    <Button
                        type="button"
                        label="Batal / Reset"
                        severity="secondary"
                        outlined
                        @click="() => { form.reset(); form.supervisors = ['', '', '']; form.participants = [{ nip: '', task: '' }]; selectedDate = null; }"
                    />
                    <Button
                        type="submit"
                        label="Kirim Pengajuan SPL"
                        icon="pi pi-send"
                        :loading="form.processing"
                        class="bg-[#005BAC] hover:bg-[#004280]"
                    />
                </div>
            </form>
        </div>

        <!-- ============== RIWAYAT PENGAJUAN LEMBUR ============== -->
        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
            <div class="px-5 sm:px-6 py-4 border-b border-slate-100 flex items-center gap-3">
                <i class="pi pi-history text-[#005BAC] text-lg" />
                <div>
                    <h2 class="font-bold text-sm text-slate-900">Riwayat Pengajuan Lembur Saya</h2>
                    <p class="text-[11px] text-slate-500">15 pengajuan terbaru — tanggal terbaru paling atas</p>
                </div>
            </div>

            <div class="p-5 sm:p-6">
                <DataTable
                    :value="recentOvertime"
                    :rows="10"
                    paginator
                    sort-field="raw_start_date"
                    :sort-order="-1"
                    responsive-layout="scroll"
                    class="text-sm"
                    empty-message="Belum ada riwayat pengajuan lembur."
                    striped-rows
                >
                    <Column field="request_date" header="Tgl Pengajuan" sortable style="min-width: 110px" />
                    <Column field="type_label" header="Jenis Lembur" sortable style="min-width: 150px" />
                    <Column field="start_date" header="Tgl Lembur" sortable style="min-width: 100px" />
                    <Column header="Jam" style="min-width: 110px">
                        <template #body="{ data }">
                            <span class="font-mono text-xs">{{ data.start_time }} – {{ data.end_time }}</span>
                        </template>
                    </Column>
                    <Column field="participant_count" header="Peserta" sortable style="min-width: 80px; text-align: center">
                        <template #body="{ data }">
                            <span class="inline-flex items-center justify-center min-w-6 h-5 bg-slate-100 rounded-full text-[11px] font-bold text-slate-600 px-1.5">
                                {{ data.participant_count }}
                            </span>
                        </template>
                    </Column>
                    <Column field="approval_status" header="Status" sortable style="min-width: 170px">
                        <template #body="{ data }">
                            <Tag
                                :value="data.status_label"
                                :severity="getStatusSeverity(data)"
                                class="text-[11px]"
                            />
                        </template>
                    </Column>
                    <Column header="Aksi" style="min-width: 130px; text-align: center">
                        <template #body="{ data }">
                            <div class="flex items-center justify-center gap-1.5">
                                <!-- Tombol Verifikasi Resmi KFPB -->
                                <Link
                                    v-if="data.can_verify"
                                    :href="`/lembur/verifikasi/${data.id}`"
                                >
                                    <Button
                                        label="Verifikasi"
                                        icon="pi pi-file-edit"
                                        size="small"
                                        class="bg-[#005BAC] hover:bg-[#004280] text-white text-[11px] px-2.5 py-1 h-7"
                                        v-tooltip="'Isi Form Verifikasi Lembur'"
                                    />
                                </Link>

                                <!-- Tombol Lihat Detail -->
                                <Button
                                    icon="pi pi-eye"
                                    size="small"
                                    severity="secondary"
                                    outlined
                                    class="h-7 w-7 p-0"
                                    v-tooltip="'Lihat Detail'"
                                    @click="showDetail(data.id)"
                                />
                            </div>
                        </template>
                    </Column>
                </DataTable>
            </div>
        </div>
    </div>

    <!-- ============== DIALOG DETAIL SPL ============== -->
    <Dialog
        v-model:visible="detailDialogVisible"
        modal
        header="Detail Surat Perintah Lembur (SPL)"
        :style="{ width: '640px', maxWidth: '95vw' }"
        :closable="true"
    >
        <div v-if="loadingDetail" class="py-10 text-center text-slate-400">
            <i class="pi pi-spin pi-spinner text-2xl" />
            <p class="text-sm mt-2">Memuat data...</p>
        </div>

        <div v-else-if="detailData" class="space-y-5 text-sm">
            <!-- Info Utama -->
            <div class="grid grid-cols-2 gap-x-6 gap-y-3">
                <div>
                    <p class="text-xs text-slate-500 font-semibold uppercase">Jenis Lembur</p>
                    <p class="font-medium text-slate-800">{{ detailData.type_label }}</p>
                </div>
                <div>
                    <p class="text-xs text-slate-500 font-semibold uppercase">Tanggal Lembur</p>
                    <p class="font-medium text-slate-800">{{ detailData.start_date }}</p>
                </div>
                <div>
                    <p class="text-xs text-slate-500 font-semibold uppercase">Jam Rencana</p>
                    <p class="font-mono font-medium text-slate-800">{{ detailData.start_time }} – {{ detailData.end_time }}</p>
                </div>
                <div>
                    <p class="text-xs text-slate-500 font-semibold uppercase">Status</p>
                    <Tag :value="detailData.status_label" :severity="getStatusSeverity(detailData.approval_status)" class="text-[11px]" />
                </div>
                <div>
                    <p class="text-xs text-slate-500 font-semibold uppercase">ACC 1</p>
                    <p class="font-medium text-slate-800">{{ detailData.supervisor_1 || '-' }}</p>
                </div>
                <div>
                    <p class="text-xs text-slate-500 font-semibold uppercase">Tgl Pengajuan</p>
                    <p class="font-medium text-slate-800">{{ detailData.request_date }}</p>
                </div>
            </div>

            <div v-if="detailData.approval_notes" class="bg-amber-50 border border-amber-200 rounded-xl p-3">
                <p class="text-xs font-semibold text-amber-700 uppercase">Catatan Atasan</p>
                <p class="text-sm text-amber-900 mt-1">{{ detailData.approval_notes }}</p>
            </div>

            <!-- Daftar Peserta -->
            <div>
                <p class="text-xs font-bold uppercase tracking-wider text-slate-700 mb-3">
                    Daftar Peserta Lembur ({{ detailData.participants?.length ?? 0 }} orang)
                </p>
                <div class="border border-slate-200 rounded-xl overflow-hidden">
                    <table class="w-full text-xs">
                        <thead class="bg-slate-50 border-b border-slate-200">
                            <tr>
                                <th class="px-3 py-2 text-left font-semibold text-slate-600">NPP/NIP</th>
                                <th class="px-3 py-2 text-left font-semibold text-slate-600">Nama Pegawai</th>
                                <th class="px-3 py-2 text-left font-semibold text-slate-600">Uraian Tugas</th>
                                <th class="px-3 py-2 text-center font-semibold text-slate-600">Realisasi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="(p, i) in detailData.participants" :key="i" class="hover:bg-slate-50/50">
                                <td class="px-3 py-2 font-mono text-slate-600">{{ p.nip }}</td>
                                <td class="px-3 py-2 text-slate-800 font-medium">{{ p.name }}</td>
                                <td class="px-3 py-2 text-slate-600">{{ p.task || '-' }}</td>
                                <td class="px-3 py-2 text-center font-mono text-slate-500">{{ p.duration ?? '—' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <template #footer>
            <Button label="Tutup" severity="secondary" outlined @click="detailDialogVisible = false" />
        </template>
    </Dialog>
</template>
