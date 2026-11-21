<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';
import Toast from 'primevue/toast';
import { useToast } from 'primevue/usetoast';
import DatePicker from 'primevue/datepicker';
import Select from 'primevue/select';
import RadioButton from 'primevue/radiobutton';
import Textarea from 'primevue/textarea';
import InputText from 'primevue/inputtext';
import Button from 'primevue/button';
import Tag from 'primevue/tag';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Dialog from 'primevue/dialog';
import IftaLabel from 'primevue/iftalabel';

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
    initialTab: {
        type: String,
        default: 'form',
    },
});

const toast = useToast();

const activeTab = ref(props.initialTab === 'history' ? 'history' : 'form');
watch(
    () => props.initialTab,
    (nextTab) => {
        if (nextTab === 'form' || nextTab === 'history') {
            activeTab.value = nextTab;
        }
    }
);

const isRefreshingHistory = ref(false);
let removeStartListener = null;
let removeFinishListener = null;

onMounted(() => {
    removeStartListener = router.on('start', () => {
        if (activeTab.value === 'history') {
            isRefreshingHistory.value = true;
        }
    });
    removeFinishListener = router.on('finish', () => {
        isRefreshingHistory.value = false;
    });
});

onUnmounted(() => {
    if (typeof removeStartListener === 'function') removeStartListener();
    if (typeof removeFinishListener === 'function') removeFinishListener();
});

const formTabButtonRef = ref(null);
const historyTabButtonRef = ref(null);

function switchTab(tab) {
    activeTab.value = tab;
}

function handleTabKeydown(event, currentTab) {
    if (event.key === 'ArrowRight' || event.key === 'ArrowLeft') {
        event.preventDefault();
        const targetTab = currentTab === 'form' ? 'history' : 'form';
        activeTab.value = targetTab;
        if (targetTab === 'form') {
            formTabButtonRef.value?.focus();
        } else {
            historyTabButtonRef.value?.focus();
        }
    } else if (event.key === 'Home') {
        event.preventDefault();
        activeTab.value = 'form';
        formTabButtonRef.value?.focus();
    } else if (event.key === 'End') {
        event.preventDefault();
        activeTab.value = 'history';
        historyTabButtonRef.value?.focus();
    }
}

const fileInputRef = ref(null);
const filePreview = ref(null);
const selectedFileName = ref('');

const selectedStartDate = ref(null);
const selectedEndDate = ref(null);

const form = useForm({
    employee_nip: props.authEmployee?.nip || '',
    permission_type_id: 2,
    start_date: '',
    end_date: '',
    reason: '',
    attachment: null,
});

const estimatedDurationDays = computed(() => {
    if (!selectedStartDate.value || !selectedEndDate.value) return 0;
    const start = new Date(selectedStartDate.value);
    const end = new Date(selectedEndDate.value);
    if (isNaN(start.getTime()) || isNaN(end.getTime()) || end < start) return 0;
    const diffTime = Math.abs(end - start);
    return Math.ceil(diffTime / (1000 * 60 * 60 * 24)) + 1;
});

function onStartDateChange(val) {
    if (val && (!selectedEndDate.value || selectedEndDate.value < val)) {
        selectedEndDate.value = new Date(val);
    }
}

function onFileChange(event) {
    const file = event.target.files[0];
    if (!file) {
        removeFile();
        return;
    }

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

function resetForm() {
    form.reset('permission_type_id', 'start_date', 'end_date', 'reason', 'attachment');
    form.employee_nip = props.authEmployee?.nip || '';
    form.clearErrors();
    selectedStartDate.value = null;
    selectedEndDate.value = null;
    removeFile();
    toast.add({
        severity: 'info',
        summary: 'Formulir Direset',
        detail: 'Seluruh isian formulir telah dikosongkan ke setelan awal.',
        life: 2500,
    });
}

function formatDateLocal(val) {
    if (val instanceof Date && !isNaN(val.getTime())) {
        const y = val.getFullYear();
        const m = String(val.getMonth() + 1).padStart(2, '0');
        const d = String(val.getDate()).padStart(2, '0');
        return `${y}-${m}-${d}`;
    }
    return typeof val === 'string' && val ? val : '';
}

function submitForm() {
    form.start_date = formatDateLocal(selectedStartDate.value);
    form.end_date = formatDateLocal(selectedEndDate.value);
    const isPersonalSubmission = form.employee_nip === (props.authEmployee?.nip || '');

    form.post('/izin/simpan', {
        preserveScroll: true,
        onSuccess: () => {
            toast.add({
                severity: 'success',
                summary: 'Berhasil Disimpan',
                detail: 'Laporan izin tidak masuk kerja berhasil disimpan.',
                life: 3500,
            });
            form.reset('reason', 'attachment');
            selectedStartDate.value = null;
            selectedEndDate.value = null;
            removeFile();
            if (isPersonalSubmission) {
                activeTab.value = 'history';
            }
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

function refreshHistory() {
    isRefreshingHistory.value = true;
    router.reload({
        only: ['recentRequests'],
        onFinish: () => {
            isRefreshingHistory.value = false;
        },
    });
}

const historySearch = ref('');
const historyTypeFilter = ref('');
const responsivePage = ref(1);
const perPage = 10;

const historyTypeOptions = computed(() => [
    { label: 'Semua Jenis Izin', value: '' },
    ...props.permissionTypes.map((pt) => ({
        label: pt.name,
        value: pt.name,
    })),
]);

const filteredRequests = computed(() => {
    const q = historySearch.value.trim().toLowerCase();
    const type = historyTypeFilter.value;

    return props.recentRequests.filter((item) => {
        const matchType = !type || item.type_name === type;
        if (!matchType) return false;
        if (!q) return true;

        return (
            (item.type_name || '').toLowerCase().includes(q) ||
            (item.start_date || '').toLowerCase().includes(q) ||
            (item.end_date || '').toLowerCase().includes(q) ||
            (item.reason || '').toLowerCase().includes(q)
        );
    });
});

watch([historySearch, historyTypeFilter], () => {
    responsivePage.value = 1;
});

const responsiveTotalPages = computed(() => Math.max(1, Math.ceil(filteredRequests.value.length / perPage)));
const paginatedCardRequests = computed(() => {
    const start = (responsivePage.value - 1) * perPage;
    return filteredRequests.value.slice(start, start + perPage);
});

function getStatusLabel(status) {
    if (status === 1) return 'Tercatat';
    if (status === 2) return 'Ditolak';
    return 'Pending';
}

function getStatusSeverity(status) {
    if (status === 1) return 'success';
    if (status === 2) return 'danger';
    return 'warn';
}

const detailDialogVisible = ref(false);
const activeDetail = ref(null);

function openDetail(item) {
    activeDetail.value = item;
    detailDialogVisible.value = true;
}

function permissionHint(id) {
    if (id === 2) return 'Lampirkan surat dokter';
    if (id === 3) return 'Potong hak cuti tahunan';
    if (id === 5) return 'Tugas luar kota / dinas';
    return '';
}
</script>

<template>
    <Head title="Lapor Tidak Masuk Kerja - eSDM PT Kimia Farma Plant Banjaran" />

    <div class="space-y-5 max-w-7xl mx-auto px-3 sm:px-6 pt-1 pb-6">
        <Toast position="top-right" />

        <!-- ================= TOP PAGE HEADER (FLAT ON CANVAS, CLEAR HIERARCHY) ================= -->
        <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-3">
            <div class="space-y-1">
                <nav aria-label="Breadcrumb" class="flex items-center gap-2 text-xs font-medium text-slate-600">
                    <Link href="/absensi" class="hover:text-[#005BAC] flex items-center gap-1 py-0.5">
                        <i class="pi pi-calendar-check text-[11px]" aria-hidden="true" />
                        <span>Presensi</span>
                    </Link>
                    <span aria-hidden="true">/</span>
                    <span class="text-slate-900 font-semibold">Lapor Tidak Masuk Kerja</span>
                </nav>
                <h1 class="text-xl sm:text-2xl font-bold text-slate-900 tracking-tight">
                    Formulir & Riwayat Izin Ketidakhadiran
                </h1>
                <p class="text-xs sm:text-sm text-slate-600">
                    Pelaporan ketidakhadiran (Sakit, Cuti, Dinas Luar, atau Dispensasi Kerja) serta daftar pengajuan tercatat.
                </p>
            </div>

            <Link
                href="/absensi"
                class="inline-flex items-center justify-center gap-2 px-4 py-2.5 min-h-[44px] rounded-xl border border-slate-300 bg-white hover:bg-slate-50 text-slate-700 font-semibold text-xs sm:text-sm transition-colors shrink-0 self-start sm:self-auto"
            >
                <i class="pi pi-arrow-left text-xs" aria-hidden="true" />
                <span>Kembali ke Presensi</span>
            </Link>
        </div>

        <!-- ================= FLASH MESSAGES ================= -->
        <div
            v-if="$page.props.flash?.success"
            role="status"
            class="bg-emerald-50 border border-emerald-200 rounded-xl p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3"
        >
            <div class="flex items-start gap-3">
                <i class="pi pi-check-circle text-emerald-700 text-lg mt-0.5 shrink-0" aria-hidden="true" />
                <p class="text-xs sm:text-sm text-emerald-900 font-medium">{{ $page.props.flash.success }}</p>
            </div>
            <Link
                href="/absensi"
                class="inline-flex items-center justify-center gap-1.5 px-3 py-2 min-h-[44px] rounded-xl bg-white border border-emerald-300 text-emerald-900 hover:bg-emerald-100 text-xs font-semibold transition-colors shrink-0"
            >
                <span>Lihat Beranda Presensi</span>
                <i class="pi pi-arrow-right text-[11px]" aria-hidden="true" />
            </Link>
        </div>

        <div
            v-if="$page.props.flash?.error"
            role="alert"
            class="bg-rose-50 border border-rose-200 rounded-xl p-4 flex items-start gap-3"
        >
            <i class="pi pi-times-circle text-rose-700 text-lg mt-0.5 shrink-0" aria-hidden="true" />
            <p class="text-xs sm:text-sm text-rose-900 font-medium">{{ $page.props.flash.error }}</p>
        </div>

        <!-- ================= SEGMENTED TAB NAVIGATION (OPSI A) ================= -->
        <div
            role="tablist"
            aria-label="Navigasi Formulir dan Riwayat Izin"
            class="grid grid-cols-2 p-1 bg-slate-200/80 rounded-xl max-w-md w-full select-none"
        >
            <button
                id="tab-form"
                ref="formTabButtonRef"
                type="button"
                role="tab"
                :aria-selected="String(activeTab === 'form')"
                aria-controls="panel-form"
                :tabindex="activeTab === 'form' ? 0 : -1"
                class="flex items-center justify-center gap-2 py-2 px-3 min-h-[44px] rounded-lg font-bold text-xs sm:text-sm transition-colors cursor-pointer"
                :class="activeTab === 'form'
                    ? 'bg-white text-slate-900 shadow-xs'
                    : 'text-slate-700 hover:text-slate-900 hover:bg-white/60'"
                @click="switchTab('form')"
                @keydown="handleTabKeydown($event, 'form')"
            >
                <i class="pi pi-file-edit text-xs" aria-hidden="true" />
                <span>Form Laporan Izin</span>
            </button>

            <button
                id="tab-history"
                ref="historyTabButtonRef"
                type="button"
                role="tab"
                :aria-selected="String(activeTab === 'history')"
                aria-controls="panel-history"
                :tabindex="activeTab === 'history' ? 0 : -1"
                class="flex items-center justify-center gap-2 py-2 px-3 min-h-[44px] rounded-lg font-bold text-xs sm:text-sm transition-colors cursor-pointer"
                :class="activeTab === 'history'
                    ? 'bg-white text-slate-900 shadow-xs'
                    : 'text-slate-700 hover:text-slate-900 hover:bg-white/60'"
                @click="switchTab('history')"
                @keydown="handleTabKeydown($event, 'history')"
            >
                <i class="pi pi-history text-xs" aria-hidden="true" />
                <span>{{ isAdmin ? 'Riwayat Saya' : 'Riwayat Izin Saya' }}</span>
                <span
                    v-if="recentRequests.length > 0"
                    class="px-1.5 py-0.5 rounded-full text-[11px] font-mono"
                    :class="activeTab === 'history' ? 'bg-slate-200 text-slate-900' : 'bg-slate-300 text-slate-900'"
                >
                    {{ recentRequests.length }}
                </span>
            </button>
        </div>

        <!-- ================= PANEL 1: FORM INPUT IZIN ================= -->
        <div
            id="panel-form"
            role="tabpanel"
            aria-labelledby="tab-form"
            v-show="activeTab === 'form'"
            class="bg-white rounded-2xl border border-slate-200 border-t-4 border-t-[#005BAC] shadow-sm overflow-hidden"
        >
            <div class="px-5 sm:px-7 py-4 border-b border-slate-200/80 flex items-center justify-between gap-3">
                <div>
                    <h2 class="font-bold text-sm sm:text-base tracking-tight text-slate-900">
                        Input Laporan Izin Tidak Hadir
                    </h2>
                    <p class="text-xs text-slate-600 mt-0.5">
                        Pengajuan yang disimpan langsung tersinkronisasi ke rekap kehadiran harian.
                    </p>
                </div>
            </div>

            <form @submit.prevent="submitForm" class="p-5 sm:p-7 flex flex-col gap-6">
                <!-- Alert Validasi Global -->
                <div
                    v-if="Object.keys(form.errors).length > 0"
                    role="alert"
                    class="p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-900 text-xs sm:text-sm space-y-1"
                >
                    <div class="font-bold flex items-center gap-2">
                        <i class="pi pi-exclamation-triangle text-rose-700" aria-hidden="true" />
                        <span>Mohon Perbaiki Isian Formulir:</span>
                    </div>
                    <ul class="list-disc list-inside space-y-0.5 text-rose-800 pl-1">
                        <li v-for="(errMsg, errField) in form.errors" :key="errField">
                            {{ errMsg }}
                        </li>
                    </ul>
                </div>

                <!-- 1. Identitas Pegawai -->
                <div class="space-y-2">
                    <p id="heading-applicant" class="text-xs font-bold uppercase tracking-wider text-slate-700">
                        Pegawai yang Mengajukan
                    </p>

                    <div v-if="isAdmin" class="space-y-1.5">
                        <span id="employee-nip-label" class="sr-only">Pegawai KFPB yang Diajukan</span>
                        <Select
                            v-model="form.employee_nip"
                            :options="employees"
                            optionLabel="label"
                            optionValue="nip"
                            filter
                            filterPlaceholder="Ketik Nama atau NIP..."
                            placeholder="Pilih Pegawai KFPB yang Diajukan *"
                            aria-labelledby="employee-nip-label"
                            class="w-full"
                            :invalid="!!form.errors.employee_nip"
                        />
                        <small v-if="form.errors.employee_nip" class="text-rose-700 font-medium block">
                            {{ form.errors.employee_nip }}
                        </small>
                        <p class="text-xs text-slate-600">
                            Sebagai Admin / HRD, Anda dapat mendaftarkan izin atas nama pegawai lain.
                        </p>
                    </div>

                    <div v-else class="grid grid-cols-1 sm:grid-cols-2 gap-3 p-3.5 rounded-xl bg-slate-50 border border-slate-200/80">
                        <div>
                            <span class="text-[11px] uppercase font-bold text-slate-600 tracking-wider">NPP / NIP Pegawai</span>
                            <p class="text-sm font-bold font-mono text-slate-900 mt-0.5">
                                {{ authEmployee?.nip || '-' }}
                            </p>
                        </div>
                        <div>
                            <span class="text-[11px] uppercase font-bold text-slate-600 tracking-wider">Nama Lengkap</span>
                            <p class="text-sm font-bold text-slate-900 mt-0.5">
                                {{ authEmployee?.name || '-' }}
                            </p>
                        </div>
                        <div class="sm:col-span-2 pt-2 border-t border-slate-200/70 flex items-center gap-2 text-xs text-slate-700">
                            <i class="pi pi-briefcase text-slate-600" aria-hidden="true" />
                            <span>{{ authEmployee?.position || 'Staf' }} &bull; {{ authEmployee?.division || 'Plant Banjaran' }}</span>
                        </div>
                    </div>
                </div>

                <!-- 2. Rentang Tanggal Izin -->
                <fieldset class="min-w-0 space-y-2 border-0 p-0">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <legend class="text-xs font-bold uppercase tracking-wider text-slate-700 p-0">
                            Periode Tanggal Izin <span class="text-rose-700">*</span>
                        </legend>
                        <span
                            v-if="estimatedDurationDays > 0"
                            class="inline-flex items-center px-2.5 py-0.5 rounded-full bg-blue-50 text-[#005BAC] text-xs font-bold border border-blue-200/80"
                        >
                            Estimasi: {{ estimatedDurationDays }} Hari
                        </span>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                        <div>
                            <IftaLabel class="w-full">
                                <DatePicker
                                    inputId="start_date"
                                    v-model="selectedStartDate"
                                    class="w-full"
                                    dateFormat="dd/mm/yy"
                                    showIcon
                                    :pt="{
                                        dropdown: { 'aria-label': 'Pilih Tanggal Awal Izin' },
                                        panel: { 'aria-label': 'Kalender Tanggal Awal Izin' }
                                    }"
                                    :invalid="!!form.errors.start_date"
                                    @update:modelValue="onStartDateChange"
                                />
                                <label for="start_date">Tanggal Awal Izin *</label>
                            </IftaLabel>
                            <small v-if="form.errors.start_date" class="text-rose-700 font-medium block mt-1">
                                {{ form.errors.start_date }}
                            </small>
                        </div>

                        <div>
                            <IftaLabel class="w-full">
                                <DatePicker
                                    inputId="end_date"
                                    v-model="selectedEndDate"
                                    class="w-full"
                                    dateFormat="dd/mm/yy"
                                    :minDate="selectedStartDate || undefined"
                                    showIcon
                                    :pt="{
                                        dropdown: { 'aria-label': 'Pilih Tanggal Akhir Izin' },
                                        panel: { 'aria-label': 'Kalender Tanggal Akhir Izin' }
                                    }"
                                    :invalid="!!form.errors.end_date"
                                />
                                <label for="end_date">Tanggal Akhir Izin *</label>
                            </IftaLabel>
                            <small v-if="form.errors.end_date" class="text-rose-700 font-medium block mt-1">
                                {{ form.errors.end_date }}
                            </small>
                        </div>
                    </div>
                </fieldset>

                <!-- 3. Jenis Izin (Grid Simetris 2x4 Desktop) -->
                <fieldset class="min-w-0 space-y-2 border-0 p-0">
                    <legend class="text-xs font-bold uppercase tracking-wider text-slate-700 p-0">
                        Jenis Izin / Kategori Ketidakhadiran <span class="text-rose-700">*</span>
                    </legend>

                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                        <label
                            v-for="pt in permissionTypes"
                            :key="pt.id"
                            :for="'pt_' + pt.id"
                            class="relative flex items-start gap-2.5 p-3 min-h-[44px] rounded-xl border cursor-pointer transition-colors select-none"
                            :class="[
                                form.permission_type_id === pt.id
                                    ? 'bg-blue-50/80 border-[#005BAC] ring-1 ring-[#005BAC]'
                                    : 'bg-white border-slate-200 hover:border-slate-300 hover:bg-slate-50/60'
                            ]"
                        >
                            <RadioButton
                                v-model="form.permission_type_id"
                                :inputId="'pt_' + pt.id"
                                :value="pt.id"
                                class="mt-0.5 shrink-0"
                            />
                            <div class="flex-1 min-w-0">
                                <p class="text-xs sm:text-sm font-bold text-slate-900 leading-snug">
                                    {{ pt.name }}
                                </p>
                                <span v-if="permissionHint(pt.id)" class="text-xs text-slate-600 block mt-0.5 leading-snug">
                                    {{ permissionHint(pt.id) }}
                                </span>
                            </div>
                        </label>
                    </div>
                    <small v-if="form.errors.permission_type_id" class="text-rose-700 font-medium block mt-1">
                        {{ form.errors.permission_type_id }}
                    </small>
                </fieldset>

                <!-- 4. Keterangan / Alasan -->
                <div class="space-y-1">
                    <IftaLabel class="w-full">
                        <Textarea
                            id="reason"
                            v-model="form.reason"
                            rows="3"
                            placeholder=" "
                            class="w-full"
                            :invalid="!!form.errors.reason"
                        />
                        <label for="reason">Keterangan Tambahan / Alasan</label>
                    </IftaLabel>
                    <small v-if="form.errors.reason" class="text-rose-700 font-medium block mt-1">
                        {{ form.errors.reason }}
                    </small>
                </div>

                <!-- 5. Lampiran Bukti Dokumen -->
                <div class="space-y-2">
                    <p class="text-xs font-bold uppercase tracking-wider text-slate-700">
                        Lampiran Bukti Dokumen (Surat Dokter / Form Cuti / Surat Tugas)
                    </p>

                    <div class="flex flex-col sm:flex-row items-start sm:items-center gap-3">
                        <label
                            for="attachment_file"
                            class="inline-flex items-center gap-2 px-4 py-2.5 min-h-[44px] rounded-xl border border-slate-300 bg-slate-50 hover:bg-slate-100 text-slate-800 text-xs sm:text-sm font-semibold cursor-pointer transition-colors focus-within:ring-2 focus-within:ring-[#005BAC] focus-within:ring-offset-2"
                        >
                            <i class="pi pi-upload text-sm" aria-hidden="true" />
                            <span>Pilih Berkas Lampiran</span>
                            <input
                                id="attachment_file"
                                ref="fileInputRef"
                                type="file"
                                accept="image/*,application/pdf"
                                class="sr-only"
                                @change="onFileChange"
                            />
                        </label>

                        <div
                            v-if="selectedFileName"
                            class="flex items-center gap-2 text-xs font-medium text-slate-800 bg-slate-100 px-3 py-1 rounded-lg border border-slate-200 max-w-full"
                        >
                            <i class="pi pi-file text-[#005BAC] shrink-0" aria-hidden="true" />
                            <span class="truncate max-w-[200px] sm:max-w-xs">{{ selectedFileName }}</span>
                            <button
                                type="button"
                                aria-label="Hapus berkas lampiran"
                                class="inline-flex items-center justify-center min-w-[44px] min-h-[44px] -my-1 -mr-2 rounded-lg text-rose-700 hover:text-rose-900 hover:bg-rose-50 transition-colors cursor-pointer"
                                @click="removeFile"
                            >
                                <i class="pi pi-times text-xs" aria-hidden="true" />
                            </button>
                        </div>
                        <span v-else class="text-xs text-slate-600">
                            Format berkas: JPG, PNG, atau PDF (maksimal 5MB)
                        </span>
                    </div>

                    <div v-if="filePreview" class="pt-2">
                        <div class="inline-block relative rounded-xl border border-slate-200 overflow-hidden shadow-xs max-w-xs">
                            <img :src="filePreview" alt="Pratinjau Bukti Izin" class="max-h-48 w-auto object-cover rounded-xl" />
                        </div>
                    </div>

                    <small v-if="form.errors.attachment" class="text-rose-700 font-medium block mt-1">
                        {{ form.errors.attachment }}
                    </small>
                </div>

                <!-- 6. Action Buttons -->
                <div class="pt-4 border-t border-slate-200 flex flex-col sm:flex-row items-center justify-center gap-3">
                    <Button
                        label="Simpan Permohonan"
                        icon="pi pi-check"
                        type="submit"
                        :disabled="form.processing"
                        :loading="form.processing"
                        class="!bg-[#005BAC] hover:!bg-[#004280] !border-[#005BAC] !text-white w-full sm:w-auto min-h-[44px] font-semibold"
                    />

                    <Button
                        type="button"
                        label="Reset Formulir"
                        icon="pi pi-refresh"
                        severity="secondary"
                        outlined
                        :disabled="form.processing"
                        class="w-full sm:w-auto min-h-[44px] !text-slate-700"
                        @click="resetForm"
                    />
                </div>
            </form>
        </div>

        <!-- ================= PANEL 2: RIWAYAT LAPORAN IZIN SAYA ================= -->
        <div
            id="panel-history"
            role="tabpanel"
            aria-labelledby="tab-history"
            v-show="activeTab === 'history'"
            class="space-y-4"
        >
            <!-- Banner Konteks Admin -->
            <div
                v-if="isAdmin"
                class="p-4 rounded-xl bg-blue-50 border border-blue-200 flex flex-col sm:flex-row sm:items-center justify-between gap-3"
            >
                <div class="flex items-start gap-3">
                    <i class="pi pi-info-circle text-[#005BAC] text-base mt-0.5 shrink-0" aria-hidden="true" />
                    <p class="text-xs sm:text-sm text-slate-800">
                        Tab ini menampilkan riwayat laporan izin pribadi Anda. Untuk melihat dan memfilter izin seluruh pegawai KFPB, buka halaman rekap admin.
                    </p>
                </div>
                <Link
                    href="/admin/izin"
                    class="inline-flex items-center justify-center gap-1.5 px-3.5 py-2 min-h-[44px] rounded-xl bg-[#005BAC] hover:bg-[#004280] text-white font-semibold text-xs transition-colors shrink-0"
                >
                    <span>Rekap Izin Pegawai</span>
                    <i class="pi pi-arrow-right text-[11px]" aria-hidden="true" />
                </Link>
            </div>

            <div class="bg-white rounded-2xl border border-slate-200 border-t-4 border-t-[#005BAC] shadow-sm overflow-hidden">
                <div class="px-5 sm:px-6 py-4 border-b border-slate-200/80 flex flex-wrap items-center justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <div class="size-9 rounded-xl bg-blue-50 text-[#005BAC] flex items-center justify-center shrink-0 border border-blue-200/60">
                            <i class="pi pi-history text-base" aria-hidden="true" />
                        </div>
                        <div>
                            <h2 class="font-bold text-sm sm:text-base text-slate-900 tracking-tight">
                                {{ isAdmin ? 'Riwayat Laporan Izin Pribadi' : 'Riwayat Laporan Izin Saya' }}
                            </h2>
                            <p class="text-xs text-slate-600">
                                Menampilkan {{ recentRequests.length }} permohonan terbaru milik Anda
                            </p>
                        </div>
                    </div>

                    <Button
                        type="button"
                        label="Muat Ulang"
                        icon="pi pi-refresh"
                        size="small"
                        severity="secondary"
                        outlined
                        :loading="isRefreshingHistory"
                        :disabled="isRefreshingHistory"
                        class="min-h-[44px] text-xs !text-slate-700"
                        @click="refreshHistory"
                    />
                </div>

                <!-- Bar Filter Pencarian (Jika ada data) -->
                <div v-if="recentRequests.length > 0" class="px-5 sm:px-6 pt-4 pb-1 grid grid-cols-1 sm:grid-cols-12 gap-3">
                    <div class="sm:col-span-7">
                        <label for="history-search" class="sr-only">Cari riwayat berdasarkan jenis, tanggal, atau keterangan</label>
                        <InputText
                            id="history-search"
                            v-model="historySearch"
                            placeholder="Cari tanggal atau keterangan izin..."
                            class="w-full min-h-[44px] text-sm"
                        />
                    </div>
                    <div class="sm:col-span-5">
                        <span id="history-type-label" class="sr-only">Filter kategori jenis izin</span>
                        <Select
                            v-model="historyTypeFilter"
                            :options="historyTypeOptions"
                            optionLabel="label"
                            optionValue="value"
                            aria-labelledby="history-type-label"
                            placeholder="Semua Jenis Izin"
                            class="w-full"
                        />
                    </div>
                </div>

                <div class="p-5 sm:p-6">
                    <!-- Loading State Eksplisit -->
                    <div
                        v-if="isRefreshingHistory"
                        role="status"
                        aria-live="polite"
                        class="py-8 px-4 text-center space-y-3"
                    >
                        <i class="pi pi-spin motion-reduce:animate-none pi-spinner text-2xl text-[#005BAC]" aria-hidden="true" />
                        <p class="text-sm font-medium text-slate-700">Memuat pembaruan riwayat laporan izin...</p>
                    </div>

                    <!-- Empty State Awal -->
                    <div v-else-if="recentRequests.length === 0" class="text-center py-10 px-4 space-y-3">
                        <div class="size-12 rounded-2xl bg-slate-100 text-slate-600 flex items-center justify-center mx-auto">
                            <i class="pi pi-inbox text-xl" aria-hidden="true" />
                        </div>
                        <div class="space-y-1 max-w-md mx-auto">
                            <p class="text-sm font-bold text-slate-900">Belum Ada Riwayat Laporan Izin</p>
                            <p class="text-xs text-slate-600">
                                Pengajuan sakit, cuti, dinas luar, atau dispensasi yang Anda laporkan akan langsung tampil di sini setelah tersimpan.
                            </p>
                        </div>
                        <div class="pt-1">
                            <Button
                                label="Buat Laporan Izin"
                                icon="pi pi-plus"
                                size="small"
                                class="!bg-[#005BAC] hover:!bg-[#004280] !border-[#005BAC] !text-white min-h-[44px]"
                                @click="switchTab('form')"
                            />
                        </div>
                    </div>

                    <!-- Empty State Hasil Filter -->
                    <div v-else-if="filteredRequests.length === 0" class="text-center py-8 px-4 space-y-3">
                        <p class="text-sm font-semibold text-slate-800">Tidak ada riwayat yang cocok dengan filter pencarian.</p>
                        <Button
                            label="Reset Filter"
                            icon="pi pi-filter-slash"
                            severity="secondary"
                            outlined
                            size="small"
                            class="min-h-[44px]"
                            @click="historySearch = ''; historyTypeFilter = '';"
                        />
                    </div>

                    <!-- Data View -->
                    <template v-else>
                        <!-- Desktop DataTable (>= lg: 1024px) -->
                        <div class="hidden lg:block">
                            <DataTable
                                :value="filteredRequests"
                                :rows="perPage"
                                paginator
                                striped-rows
                                responsive-layout="scroll"
                                class="text-sm"
                                empty-message="Belum ada data riwayat."
                                :pt="{ bodyCell: { class: '!py-2' } }"
                            >
                                <Column field="type_name" header="Jenis Izin" sortable style="min-width: 140px">
                                    <template #body="{ data }">
                                        <span class="font-bold text-slate-900">{{ data.type_name }}</span>
                                    </template>
                                </Column>
                                <Column field="raw_start_date" header="Periode Tanggal" sortable style="min-width: 175px">
                                    <template #body="{ data }">
                                        <div class="font-mono text-xs text-slate-800">
                                            {{ data.start_date }}
                                            <span v-if="data.start_date !== data.end_date">s/d {{ data.end_date }}</span>
                                        </div>
                                        <span class="text-[11px] font-medium text-slate-600">
                                            {{ data.duration_days }} hari
                                        </span>
                                    </template>
                                </Column>
                                <Column field="reason" header="Keterangan" style="min-width: 220px">
                                    <template #body="{ data }">
                                        <p class="text-xs text-slate-700 line-clamp-2 max-w-md">
                                            {{ data.reason }}
                                        </p>
                                    </template>
                                </Column>
                                <Column header="Lampiran" style="min-width: 105px">
                                    <template #body="{ data }">
                                        <a
                                            v-if="data.attachment_url"
                                            :href="data.attachment_url"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            class="inline-flex items-center gap-1.5 min-h-[44px] px-2 text-xs text-[#005BAC] hover:underline font-semibold"
                                        >
                                            <i class="pi pi-paperclip text-xs" aria-hidden="true" />
                                            <span>Lihat</span>
                                        </a>
                                        <span v-else class="text-xs text-slate-600">-</span>
                                    </template>
                                </Column>
                                <Column field="approval_status" header="Status" sortable style="min-width: 115px">
                                    <template #body="{ data }">
                                        <Tag
                                            :value="getStatusLabel(data.approval_status)"
                                            :severity="getStatusSeverity(data.approval_status)"
                                            class="text-xs"
                                        />
                                    </template>
                                </Column>
                                <Column header="Aksi" style="min-width: 105px; text-align: center">
                                    <template #body="{ data }">
                                        <Button
                                            label="Detail"
                                            icon="pi pi-eye"
                                            size="small"
                                            severity="secondary"
                                            outlined
                                            :aria-label="'Detail ' + data.type_name + ' ' + data.start_date"
                                            class="min-h-[44px] text-xs !text-slate-700"
                                            @click="openDetail(data)"
                                        />
                                    </template>
                                </Column>
                            </DataTable>
                        </div>

                        <!-- Mobile & Tablet Responsive Cards (< lg: < 1024px) -->
                        <div class="lg:hidden space-y-3">
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <article
                                    v-for="req in paginatedCardRequests"
                                    :key="req.id"
                                    class="p-4 rounded-xl border border-slate-200 bg-slate-50/60 flex flex-col justify-between gap-2.5"
                                >
                                    <div class="space-y-2">
                                        <div class="flex items-start justify-between gap-2">
                                            <div>
                                                <h3 class="text-sm font-bold text-slate-900">{{ req.type_name }}</h3>
                                                <p class="font-mono text-xs text-slate-700 mt-0.5">
                                                    {{ req.start_date }}
                                                    <span v-if="req.start_date !== req.end_date">s/d {{ req.end_date }}</span>
                                                    <span class="font-sans text-slate-600"> &bull; {{ req.duration_days }} hari</span>
                                                </p>
                                            </div>
                                            <Tag
                                                :value="getStatusLabel(req.approval_status)"
                                                :severity="getStatusSeverity(req.approval_status)"
                                                class="text-xs shrink-0"
                                            />
                                        </div>

                                        <p class="text-xs text-slate-700 line-clamp-2">
                                            {{ req.reason !== '-' ? req.reason : 'Tidak ada keterangan tambahan.' }}
                                        </p>
                                    </div>

                                    <div class="pt-2 border-t border-slate-200/80 flex items-center justify-between gap-2">
                                        <a
                                            v-if="req.attachment_url"
                                            :href="req.attachment_url"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            class="inline-flex items-center gap-1.5 min-h-[44px] px-2.5 rounded-lg text-xs text-[#005BAC] font-semibold hover:bg-blue-50 transition-colors"
                                        >
                                            <i class="pi pi-paperclip text-xs" aria-hidden="true" />
                                            <span>Buka Lampiran</span>
                                        </a>
                                        <span v-else class="text-xs text-slate-600">Tanpa lampiran</span>

                                        <Button
                                            label="Detail"
                                            icon="pi pi-eye"
                                            size="small"
                                            severity="secondary"
                                            outlined
                                            :aria-label="'Detail ' + req.type_name + ' ' + req.start_date"
                                            class="min-h-[44px] text-xs !text-slate-700"
                                            @click="openDetail(req)"
                                        />
                                    </div>
                                </article>
                            </div>

                            <!-- Mobile & Tablet Pagination -->
                            <div
                                v-if="responsiveTotalPages > 1"
                                class="pt-2 flex items-center justify-between gap-2 border-t border-slate-200"
                            >
                                <Button
                                    label="Sebelumnya"
                                    icon="pi pi-chevron-left"
                                    size="small"
                                    severity="secondary"
                                    outlined
                                    :disabled="responsivePage <= 1"
                                    class="min-h-[44px] text-xs"
                                    @click="responsivePage--"
                                />
                                <span class="text-xs font-mono text-slate-700">
                                    Hal {{ responsivePage }} / {{ responsiveTotalPages }}
                                </span>
                                <Button
                                    label="Berikutnya"
                                    icon="pi pi-chevron-right"
                                    iconPos="right"
                                    size="small"
                                    severity="secondary"
                                    outlined
                                    :disabled="responsivePage >= responsiveTotalPages"
                                    class="min-h-[44px] text-xs"
                                    @click="responsivePage++"
                                />
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </div>

        <!-- ================= DETAIL DIALOG RIWAYAT IZIN ================= -->
        <Dialog
            v-model:visible="detailDialogVisible"
            modal
            header="Detail Laporan Izin Tidak Hadir"
            :style="{ width: '560px', maxWidth: '95vw' }"
            :closable="true"
        >
            <div v-if="activeDetail" class="space-y-4 text-sm">
                <div class="grid grid-cols-2 gap-3 p-3.5 rounded-xl bg-slate-50 border border-slate-200">
                    <div>
                        <p class="text-[11px] uppercase font-bold text-slate-600">Jenis Izin</p>
                        <p class="font-bold text-slate-900 mt-0.5">{{ activeDetail.type_name }}</p>
                    </div>
                    <div>
                        <p class="text-[11px] uppercase font-bold text-slate-600">Status</p>
                        <div class="mt-0.5">
                            <Tag
                                :value="getStatusLabel(activeDetail.approval_status)"
                                :severity="getStatusSeverity(activeDetail.approval_status)"
                                class="text-xs"
                            />
                        </div>
                    </div>
                    <div>
                        <p class="text-[11px] uppercase font-bold text-slate-600">Periode Tanggal</p>
                        <p class="font-mono text-xs text-slate-900 mt-0.5">
                            {{ activeDetail.start_date }}
                            <span v-if="activeDetail.start_date !== activeDetail.end_date">s/d {{ activeDetail.end_date }}</span>
                        </p>
                    </div>
                    <div>
                        <p class="text-[11px] uppercase font-bold text-slate-600">Durasi & Dicatat</p>
                        <p class="text-xs text-slate-800 mt-0.5">
                            <span class="font-bold text-[#005BAC]">{{ activeDetail.duration_days }} hari</span>
                            <span v-if="activeDetail.created_at" class="text-slate-600"> &bull; {{ activeDetail.created_at }}</span>
                        </p>
                    </div>
                </div>

                <div class="space-y-1">
                    <p class="text-xs font-bold uppercase tracking-wider text-slate-700">Keterangan / Alasan</p>
                    <div class="p-3 rounded-xl border border-slate-200 bg-white text-xs sm:text-sm text-slate-800 whitespace-pre-wrap">
                        {{ activeDetail.reason !== '-' ? activeDetail.reason : 'Tidak ada keterangan tambahan.' }}
                    </div>
                </div>

                <div class="space-y-2">
                    <div class="flex items-center justify-between gap-2">
                        <p class="text-xs font-bold uppercase tracking-wider text-slate-700">Lampiran Bukti Dokumen</p>
                        <a
                            v-if="activeDetail.attachment_url"
                            :href="activeDetail.attachment_url"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="inline-flex items-center gap-1.5 min-h-[44px] px-2 text-xs text-[#005BAC] hover:underline font-semibold"
                        >
                            <i class="pi pi-external-link text-xs" aria-hidden="true" />
                            <span>Buka Berkas Asli</span>
                        </a>
                    </div>

                    <div v-if="activeDetail.attachment_url && !activeDetail.is_pdf" class="border border-slate-200 rounded-xl overflow-hidden bg-slate-100 flex items-center justify-center p-2">
                        <img
                            :src="activeDetail.attachment_url"
                            alt="Pratinjau berkas lampiran izin"
                            class="max-h-72 w-auto object-contain rounded-lg"
                        />
                    </div>
                    <div v-else-if="activeDetail.attachment_url && activeDetail.is_pdf" class="p-4 rounded-xl border border-slate-200 bg-slate-50 flex items-center justify-between gap-3">
                        <div class="flex items-center gap-2.5 text-xs text-slate-800">
                            <i class="pi pi-file-pdf text-rose-700 text-lg" aria-hidden="true" />
                            <span>Dokumen lampiran berformat PDF.</span>
                        </div>
                        <a
                            :href="activeDetail.attachment_url"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="inline-flex items-center justify-center px-3 py-2 min-h-[44px] rounded-lg bg-[#005BAC] text-white text-xs font-semibold hover:bg-[#004280]"
                        >
                            Buka PDF
                        </a>
                    </div>
                    <div v-else class="p-4 rounded-xl border border-slate-200 bg-slate-50 text-center text-xs text-slate-600">
                        Tidak ada berkas lampiran yang disertakan pada pengajuan ini.
                    </div>
                </div>

                <div class="pt-2 flex justify-end">
                    <Button
                        label="Tutup"
                        severity="secondary"
                        outlined
                        class="min-h-[44px]"
                        @click="detailDialogVisible = false"
                    />
                </div>
            </div>
        </Dialog>
    </div>
</template>
