<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';
import Toast from 'primevue/toast';
import { useToast } from 'primevue/usetoast';
import DatePicker from 'primevue/datepicker';
import Select from 'primevue/select';
import InputText from 'primevue/inputtext';
import Button from 'primevue/button';
import Tag from 'primevue/tag';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Dialog from 'primevue/dialog';
import IftaLabel from 'primevue/iftalabel';

defineOptions({ layout: AuthenticatedLayout });

const props = defineProps({
    authEmployee: { type: Object, required: true },
    canCreateOvertime: { type: Boolean, default: true },
    isSupervisor: { type: Boolean, default: false },
    userLevel: { type: String, default: 'Supervisor' },
    overtimeTypes: { type: Object, default: () => ({}) },
    supervisorCandidates: { type: Array, default: () => [] },
    allEmployees: { type: Array, default: () => [] },
    recentOvertime: { type: Array, default: () => [] },
    pendingVerificationCount: { type: Number, default: 0 },
    initialTab: { type: String, default: 'form' },
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

const selectedDate = ref(null);
const selectedStartTime = ref(null);
const selectedEndTime = ref(null);

const form = useForm({
    applicant_nip: props.authEmployee?.nip || '',
    overtime_type: 1,
    start_date: '',
    start_time: '',
    end_time: '',
    supervisors: ['', '', ''],
    participants: [{ nip: '', task: '' }],
});

const overtimeTypeOptions = computed(() =>
    Object.entries(props.overtimeTypes).map(([id, label]) => ({
        value: parseInt(id, 10),
        label,
    }))
);

function formatDateLocal(val) {
    if (val instanceof Date && !isNaN(val.getTime())) {
        const y = val.getFullYear();
        const m = String(val.getMonth() + 1).padStart(2, '0');
        const d = String(val.getDate()).padStart(2, '0');
        return `${y}-${m}-${d}`;
    }
    if (typeof val === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(val.trim())) {
        return val.trim();
    }
    return '';
}

function formatTimeLocal(val) {
    if (val instanceof Date && !isNaN(val.getTime())) {
        const h = String(val.getHours()).padStart(2, '0');
        const m = String(val.getMinutes()).padStart(2, '0');
        return `${h}:${m}`;
    }
    if (typeof val === 'string') {
        const trimmed = val.trim();
        if (/^([01]\d|2[0-3]):[0-5]\d$/.test(trimmed)) {
            return trimmed;
        }
    }
    return '';
}

function onDateChange(val) {
    form.start_date = formatDateLocal(val);
}

function onStartTimeChange(val) {
    form.start_time = formatTimeLocal(val);
}

function onEndTimeChange(val) {
    form.end_time = formatTimeLocal(val);
}

const minDate = computed(() => {
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    return today;
});

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

function resetForm() {
    form.reset('overtime_type', 'start_date', 'start_time', 'end_time', 'participants');
    form.applicant_nip = props.authEmployee?.nip || '';
    form.supervisors = ['', '', ''];
    form.participants = [{ nip: '', task: '' }];
    form.clearErrors();
    selectedDate.value = null;
    selectedStartTime.value = null;
    selectedEndTime.value = null;
    toast.add({
        severity: 'info',
        summary: 'Formulir Direset',
        detail: 'Seluruh isian formulir SPL telah dikosongkan ke setelan awal.',
        life: 2500,
    });
}

function submitForm() {
    const normalizedDate = formatDateLocal(selectedDate.value) || form.start_date;
    const normalizedStartTime = formatTimeLocal(selectedStartTime.value) || form.start_time;
    const normalizedEndTime = formatTimeLocal(selectedEndTime.value) || form.end_time;

    form.start_date = normalizedDate;
    form.start_time = normalizedStartTime;
    form.end_time = normalizedEndTime;

    const filledSupervisors = form.supervisors.filter((s) => s && s.trim() !== '');

    if (filledSupervisors.length === 0) {
        toast.add({ severity: 'error', summary: 'ACC Wajib', detail: 'Minimal 1 pejabat penyetuju (ACC 1) wajib dipilih.', life: 4000 });
        return;
    }

    if (new Set(filledSupervisors).size !== filledSupervisors.length) {
        toast.add({ severity: 'error', summary: 'Duplikasi ACC', detail: 'Pejabat yang meng-ACC tidak boleh sama pada setiap level.', life: 4000 });
        return;
    }

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
            form.participants = [{ nip: '', task: '' }];
            selectedDate.value = null;
            selectedStartTime.value = null;
            selectedEndTime.value = null;
            activeTab.value = 'history';
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

function refreshHistory() {
    isRefreshingHistory.value = true;
    router.reload({
        only: ['recentOvertime', 'pendingVerificationCount'],
        onFinish: () => {
            isRefreshingHistory.value = false;
        },
    });
}

const historySearch = ref('');
const historyStatusFilter = ref('');
const responsivePage = ref(1);
const perPage = 10;

const historyStatusOptions = [
    { label: 'Semua Status SPL', value: '' },
    { label: 'Menunggu ACC', value: 'pending' },
    { label: 'Menunggu Verifikasi', value: 'need_verification' },
    { label: 'Sudah Verifikasi', value: 'verified' },
    { label: 'Ditolak', value: 'rejected' },
];

const filteredOvertime = computed(() => {
    const q = historySearch.value.trim().toLowerCase();
    const status = historyStatusFilter.value;

    return props.recentOvertime.filter((item) => {
        if (status === 'pending' && item.badge !== 'pending') return false;
        if (status === 'need_verification' && !item.can_verify) return false;
        if (status === 'verified' && !item.is_verified) return false;
        if (status === 'rejected' && item.approval_status !== 2) return false;

        if (!q) return true;

        return (
            (item.code || '').toLowerCase().includes(q) ||
            (item.legacy_id ? String(item.legacy_id).toLowerCase().includes(q) : false) ||
            (item.type_label || '').toLowerCase().includes(q) ||
            (item.start_date || '').toLowerCase().includes(q) ||
            (item.request_date || '').toLowerCase().includes(q) ||
            (item.status_label || '').toLowerCase().includes(q)
        );
    });
});

watch([historySearch, historyStatusFilter], () => {
    responsivePage.value = 1;
});

const responsiveTotalPages = computed(() => Math.max(1, Math.ceil(filteredOvertime.value.length / perPage)));
const paginatedCardOvertime = computed(() => {
    const start = (responsivePage.value - 1) * perPage;
    return filteredOvertime.value.slice(start, start + perPage);
});

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
</script>

<template>
    <Head title="Pengajuan Lembur (SPL) - eSDM PT Kimia Farma Plant Banjaran" />

    <div class="space-y-5 max-w-7xl mx-auto px-3 sm:px-6 pt-1 pb-6">
        <Toast position="top-right" />

        <!-- ================= TOP PAGE HEADER (FLAT ON CANVAS, SERAGAM DENGAN /izin/buat) ================= -->
        <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-3">
            <div class="space-y-1">
                <nav aria-label="Breadcrumb" class="flex items-center gap-2 text-xs font-medium text-slate-600">
                    <Link href="/absensi" class="hover:text-[#005BAC] flex items-center gap-1 py-0.5">
                        <i class="pi pi-calendar-check text-[11px]" aria-hidden="true" />
                        <span>Presensi</span>
                    </Link>
                    <span aria-hidden="true">/</span>
                    <span class="text-slate-900 font-semibold">Pengajuan Lembur (SPL)</span>
                </nav>
                <h1 class="text-xl sm:text-2xl font-bold text-slate-900 tracking-tight">
                    Surat Perintah Lembur (SPL)
                </h1>
                <p class="text-xs sm:text-sm text-slate-600">
                    Formulir pengajuan lembur resmi KFPB mencakup rencana lembur, peserta, dan persetujuan atasan.
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-2 shrink-0 self-start sm:self-auto">
                <span
                    v-if="pendingVerificationCount > 0"
                    class="inline-flex items-center gap-1.5 bg-amber-100 text-amber-900 text-xs font-semibold px-3 py-1.5 rounded-full border border-amber-300"
                >
                    <i class="pi pi-exclamation-triangle text-xs text-amber-800" aria-hidden="true" />
                    <span>{{ pendingVerificationCount }} SPL menunggu verifikasi</span>
                </span>
                <Link
                    href="/absensi"
                    class="inline-flex items-center justify-center gap-2 px-4 py-2.5 min-h-[44px] rounded-xl border border-slate-300 bg-white hover:bg-slate-50 text-slate-700 font-semibold text-xs sm:text-sm transition-colors shrink-0"
                >
                    <i class="pi pi-arrow-left text-xs" aria-hidden="true" />
                    <span>Kembali ke Presensi</span>
                </Link>
            </div>
        </div>

        <!-- ================= ALERTS & BANNER ================= -->
        <div
            v-if="pendingVerificationCount > 0"
            role="status"
            class="bg-amber-50 border border-amber-200 rounded-xl p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3"
        >
            <div class="flex items-start gap-3">
                <i class="pi pi-exclamation-triangle text-amber-700 text-lg mt-0.5 shrink-0" aria-hidden="true" />
                <div class="text-xs sm:text-sm text-amber-900">
                    <p class="font-semibold">
                        Anda memiliki {{ pendingVerificationCount }} SPL yang sudah disetujui dan menunggu verifikasi pelaksanaan.
                    </p>
                    <p class="text-xs mt-0.5 text-amber-800">
                        Selesaikan verifikasi SPL lama terlebih dahulu sebelum membuat pengajuan lembur baru.
                    </p>
                </div>
            </div>
            <Button
                type="button"
                label="Buka Tab Riwayat"
                icon="pi pi-history"
                size="small"
                severity="secondary"
                outlined
                class="min-h-[44px] !text-amber-950 !border-amber-300 !bg-white hover:!bg-amber-100 shrink-0 text-xs"
                @click="switchTab('history')"
            />
        </div>

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

        <div
            v-if="!canCreateOvertime"
            class="p-4 rounded-xl bg-blue-50 border border-blue-200 flex flex-col sm:flex-row sm:items-center justify-between gap-3"
        >
            <div class="flex items-start gap-3">
                <i class="pi pi-info-circle text-[#005BAC] text-base mt-0.5 shrink-0" aria-hidden="true" />
                <div class="space-y-0.5">
                    <h2 class="text-xs sm:text-sm font-bold text-slate-900">Informasi Kewenangan Pengajuan Lembur</h2>
                    <p class="text-xs text-slate-700 leading-relaxed">
                        Pengajuan Surat Perintah Lembur (SPL) merupakan wewenang atasan (minimal tingkat Supervisor / SPV). Sebagai tingkat Pelaksana, penugasan lembur Anda dicatat oleh atasan dan dapat dipantau pada tab Riwayat Penugasan SPL.
                    </p>
                </div>
            </div>
            <Tag value="Tingkat Pelaksana" severity="secondary" class="shrink-0 font-semibold text-xs px-3 py-1" />
        </div>

        <!-- ================= SEGMENTED TAB NAVIGATION (ARIA TABLIST) ================= -->
        <div
            role="tablist"
            aria-label="Navigasi Formulir dan Riwayat Lembur"
            class="grid grid-cols-2 p-1 bg-slate-200/80 rounded-xl max-w-lg w-full select-none"
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
                <i :class="canCreateOvertime ? 'pi pi-file-edit' : 'pi pi-lock'" class="text-xs shrink-0" aria-hidden="true" />
                <span>{{ canCreateOvertime ? 'Form Pengajuan' : 'Form (Khusus SPV)' }}</span>
            </button>

            <button
                id="tab-history"
                ref="historyTabButtonRef"
                type="button"
                role="tab"
                :aria-selected="String(activeTab === 'history')"
                aria-controls="panel-history"
                :tabindex="activeTab === 'history' ? 0 : -1"
                class="flex items-center justify-center gap-1.5 sm:gap-2 py-2 px-2.5 sm:px-3 min-h-[44px] rounded-lg font-bold text-xs sm:text-sm transition-colors cursor-pointer"
                :class="activeTab === 'history'
                    ? 'bg-white text-slate-900 shadow-xs'
                    : 'text-slate-700 hover:text-slate-900 hover:bg-white/60'"
                @click="switchTab('history')"
                @keydown="handleTabKeydown($event, 'history')"
            >
                <i class="pi pi-history text-xs shrink-0" aria-hidden="true" />
                <span>{{ canCreateOvertime ? 'Riwayat SPL' : 'Riwayat Penugasan' }}</span>
                <span
                    v-if="recentOvertime.length > 0"
                    class="px-1.5 py-0.5 rounded-full text-[11px] font-mono shrink-0"
                    :class="activeTab === 'history' ? 'bg-slate-200 text-slate-900' : 'bg-slate-300 text-slate-900'"
                >
                    {{ recentOvertime.length }}
                </span>
                <span
                    v-if="pendingVerificationCount > 0"
                    class="px-1.5 py-0.5 rounded-full text-[11px] font-bold bg-amber-100 text-amber-900 border border-amber-300 shrink-0"
                >
                    {{ pendingVerificationCount }} Verifikasi
                </span>
            </button>
        </div>

        <!-- ================= PANEL 1: FORM PENGAJUAN SPL ================= -->
        <div
            id="panel-form"
            role="tabpanel"
            aria-labelledby="tab-form"
            v-show="activeTab === 'form'"
        >
            <div
                v-if="!canCreateOvertime"
                class="bg-white rounded-2xl border border-slate-200 border-t-4 border-t-[#005BAC] shadow-sm p-6 sm:p-8 text-center space-y-4"
            >
                <div class="size-14 rounded-2xl bg-amber-50 border border-amber-200 flex items-center justify-center mx-auto text-amber-800">
                    <i class="pi pi-lock text-2xl" aria-hidden="true" />
                </div>
                <div class="max-w-md mx-auto space-y-1.5">
                    <h2 class="font-bold text-slate-900 text-base">Formulir Pengajuan Lembur Khusus Supervisor</h2>
                    <p class="text-xs sm:text-sm text-slate-600 leading-relaxed">
                        Akun Anda tercatat sebagai tingkat Pelaksana. Berdasarkan SOP eSDM KFPB, penyusunan Surat Perintah Lembur (SPL) dan pemilihan peserta diajukan oleh atasan langsung (Supervisor/SPV ke atas).
                    </p>
                </div>
                <div class="pt-1">
                    <Button
                        type="button"
                        label="Buka Riwayat Penugasan SPL"
                        icon="pi pi-history"
                        size="small"
                        class="!bg-[#005BAC] hover:!bg-[#004280] !border-[#005BAC] !text-white min-h-[44px] text-xs font-semibold"
                        @click="switchTab('history')"
                    />
                </div>
            </div>

            <div
                v-else
                class="bg-white rounded-2xl border border-slate-200 border-t-4 border-t-[#005BAC] shadow-sm overflow-hidden"
            >
                <div class="px-5 sm:px-7 py-4 border-b border-slate-200/80 flex items-center justify-between gap-3">
                    <div>
                        <h2 class="font-bold text-sm sm:text-base tracking-tight text-slate-900">
                            Form Pengajuan Lembur Baru
                        </h2>
                        <p class="text-xs text-slate-600 mt-0.5">
                            Rencana lembur harus diajukan sebelum pelaksanaan dan disetujui berjenjang.
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

                    <!-- 1. Identitas Pengaju Lembur -->
                    <section class="space-y-2">
                        <h3 class="text-xs font-bold uppercase tracking-wider text-slate-700">
                            Identitas Pengaju Lembur
                        </h3>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 p-3.5 rounded-xl bg-slate-50 border border-slate-200/80">
                            <div>
                                <span class="text-[11px] uppercase font-bold text-slate-600 tracking-wider">NPP / NIP Pengaju</span>
                                <p class="text-sm font-bold font-mono text-slate-900 mt-0.5">
                                    {{ authEmployee?.nip || '-' }}
                                </p>
                            </div>
                            <div>
                                <span class="text-[11px] uppercase font-bold text-slate-600 tracking-wider">Nama Pegawai</span>
                                <p class="text-sm font-bold text-slate-900 mt-0.5">
                                    {{ authEmployee?.name || '-' }}
                                </p>
                            </div>
                            <div class="sm:col-span-2 pt-2 border-t border-slate-200/70 flex items-center gap-2 text-xs text-slate-700">
                                <i class="pi pi-briefcase text-slate-600" aria-hidden="true" />
                                <span>
                                    {{ authEmployee?.division || 'PT Kimia Farma Plant Banjaran' }}
                                    <template v-if="authEmployee?.sub_division && authEmployee.sub_division !== '-'">
                                        &bull; {{ authEmployee.sub_division }}
                                    </template>
                                </span>
                            </div>
                        </div>
                    </section>

                    <!-- 2. Rencana Lembur -->
                    <section class="space-y-3.5">
                        <h3 class="text-xs font-bold uppercase tracking-wider text-slate-700">
                            Rencana Lembur
                        </h3>

                        <div class="bg-blue-50 border border-blue-200 rounded-xl p-4 text-xs text-slate-800 space-y-1.5">
                            <p class="font-bold text-[#004280] flex items-center gap-1.5">
                                <i class="pi pi-info-circle" aria-hidden="true" />
                                <span>Ketentuan Pengajuan Lembur KFPB:</span>
                            </p>
                            <ul class="list-disc list-inside space-y-1 pl-1 text-slate-700">
                                <li>ACC 1: Asman / Pgs. Asman (wajib diisi)</li>
                                <li>ACC 2: Manager / Pgs. Manager (wajib untuk lembur Sabtu/Minggu/Hari Libur)</li>
                                <li>ACC terakhir: Asman Pengendalian Proses Produksi (PPIC)</li>
                                <li>Pejabat ACC tidak boleh sama pada setiap level</li>
                                <li class="font-semibold text-slate-900">
                                    Tidak bisa membuat lembur backdate. Untuk lembur mendadak, ajukan Memo Lembur manual minimal dari Asman &amp; PPIC.
                                </li>
                            </ul>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                            <div class="space-y-1.5 sm:col-span-2">
                                <span id="overtime-type-label" class="text-xs font-semibold text-slate-700 block">
                                    Jenis Lembur <span class="text-rose-700">*</span>
                                </span>
                                <Select
                                    v-model="form.overtime_type"
                                    :options="overtimeTypeOptions"
                                    option-label="label"
                                    option-value="value"
                                    aria-labelledby="overtime-type-label"
                                    class="w-full"
                                    placeholder="Pilih Jenis Lembur"
                                    :invalid="!!form.errors.overtime_type"
                                />
                                <small v-if="form.errors.overtime_type" class="text-rose-700 font-medium block">
                                    {{ form.errors.overtime_type }}
                                </small>
                            </div>

                            <div class="sm:col-span-2">
                                <IftaLabel class="w-full">
                                    <DatePicker
                                        v-model="selectedDate"
                                        inputId="overtime_start_date"
                                        dateFormat="dd/mm/yy"
                                        :minDate="minDate"
                                        showIcon
                                        showButtonBar
                                        class="w-full"
                                        :pt="{
                                            dropdown: { 'aria-label': 'Pilih Tanggal Rencana Lembur' },
                                            panel: { 'aria-label': 'Kalender Tanggal Rencana Lembur' }
                                        }"
                                        :invalid="!!form.errors.start_date"
                                        @update:modelValue="onDateChange"
                                        @clear-click="() => { selectedDate = null; form.start_date = ''; }"
                                    />
                                    <label for="overtime_start_date">Tanggal Rencana Lembur *</label>
                                </IftaLabel>
                                <small v-if="form.errors.start_date" class="text-rose-700 font-medium block mt-1">
                                    {{ form.errors.start_date }}
                                </small>
                            </div>

                            <div>
                                <IftaLabel class="w-full">
                                    <DatePicker
                                        v-model="selectedStartTime"
                                        inputId="overtime_start_time"
                                        timeOnly
                                        hourFormat="24"
                                        showIcon
                                        class="w-full"
                                        :pt="{
                                            dropdown: { 'aria-label': 'Pilih Jam Mulai Rencana' },
                                            panel: { 'aria-label': 'Pemilih Jam Mulai Rencana' }
                                        }"
                                        :invalid="!!form.errors.start_time"
                                        @update:modelValue="onStartTimeChange"
                                    >
                                        <template #dropdownicon>
                                            <i class="pi pi-clock" aria-hidden="true" />
                                        </template>
                                    </DatePicker>
                                    <label for="overtime_start_time">Jam Mulai Rencana (24 Jam) *</label>
                                </IftaLabel>
                                <p class="text-xs text-slate-600 mt-1">Format 24 jam, contoh: 16:00</p>
                                <small v-if="form.errors.start_time" class="text-rose-700 font-medium block mt-1">
                                    {{ form.errors.start_time }}
                                </small>
                            </div>

                            <div>
                                <IftaLabel class="w-full">
                                    <DatePicker
                                        v-model="selectedEndTime"
                                        inputId="overtime_end_time"
                                        timeOnly
                                        hourFormat="24"
                                        showIcon
                                        class="w-full"
                                        :pt="{
                                            dropdown: { 'aria-label': 'Pilih Jam Selesai Rencana' },
                                            panel: { 'aria-label': 'Pemilih Jam Selesai Rencana' }
                                        }"
                                        :invalid="!!form.errors.end_time"
                                        @update:modelValue="onEndTimeChange"
                                    >
                                        <template #dropdownicon>
                                            <i class="pi pi-clock" aria-hidden="true" />
                                        </template>
                                    </DatePicker>
                                    <label for="overtime_end_time">Jam Selesai Rencana (24 Jam) *</label>
                                </IftaLabel>
                                <p class="text-xs text-slate-600 mt-1">Format 24 jam, contoh: 20:00</p>
                                <small v-if="form.errors.end_time" class="text-rose-700 font-medium block mt-1">
                                    {{ form.errors.end_time }}
                                </small>
                            </div>
                        </div>
                    </section>

                    <!-- 3. Pejabat Penyetuju (ACC Lembur) -->
                    <section class="space-y-3">
                        <h3 class="text-xs font-bold uppercase tracking-wider text-slate-700">
                            Pejabat Penyetuju (ACC Lembur)
                        </h3>
                        <small v-if="form.errors.supervisors" class="text-rose-700 font-medium block">
                            {{ form.errors.supervisors }}
                        </small>

                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3.5">
                            <div v-for="(_, idx) in [0, 1, 2]" :key="idx" class="space-y-1.5">
                                <span :id="`supervisor_label_${idx + 1}`" class="text-xs font-semibold text-slate-700 block">
                                    ACC {{ idx + 1 }}
                                    <span v-if="idx === 0" class="text-rose-700">*</span>
                                    <span v-else class="text-slate-600 font-normal">(opsional)</span>
                                </span>
                                <Select
                                    v-model="form.supervisors[idx]"
                                    :options="supervisorCandidates"
                                    option-label="label"
                                    option-value="nip"
                                    :aria-labelledby="`supervisor_label_${idx + 1}`"
                                    class="w-full"
                                    :placeholder="`Pilih ACC ${idx + 1}`"
                                    filter
                                    filterPlaceholder="Ketik Nama atau NIP..."
                                    show-clear
                                    :disabled="idx > 0 && !form.supervisors[idx - 1]"
                                />
                            </div>
                        </div>
                    </section>

                    <!-- 4. Daftar Peserta Lembur -->
                    <section class="space-y-3">
                        <div class="flex items-center justify-between gap-2">
                            <h3 class="text-xs font-bold uppercase tracking-wider text-slate-700">
                                Daftar Peserta Lembur
                                <span class="text-slate-600 font-normal normal-case ml-1.5">({{ form.participants.length }}/30)</span>
                            </h3>
                        </div>
                        <small v-if="form.errors.participants" class="text-rose-700 font-medium block">
                            {{ form.errors.participants }}
                        </small>

                        <div class="space-y-3">
                            <div
                                v-for="(participant, idx) in form.participants"
                                :key="idx"
                                class="bg-slate-50 border border-slate-200 rounded-xl p-4 space-y-3"
                            >
                                <div class="flex items-center justify-between gap-2">
                                    <span class="text-xs font-bold text-slate-700">Pegawai {{ idx + 1 }}</span>
                                    <button
                                        v-if="form.participants.length > 1"
                                        type="button"
                                        class="inline-flex items-center gap-1.5 px-2.5 min-h-[44px] -my-2 rounded-lg text-xs font-semibold text-rose-700 hover:text-rose-900 hover:bg-rose-50 transition-colors cursor-pointer"
                                        @click="removeParticipant(idx)"
                                    >
                                        <i class="pi pi-trash text-xs" aria-hidden="true" />
                                        <span>Hapus</span>
                                    </button>
                                </div>

                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                                    <div class="space-y-1.5">
                                        <span :id="`participant_nip_label_${idx}`" class="text-xs font-semibold text-slate-700 block">
                                            Pilih Pegawai <span class="text-rose-700">*</span>
                                        </span>
                                        <Select
                                            v-model="participant.nip"
                                            :options="allEmployees"
                                            option-label="label"
                                            option-value="nip"
                                            :aria-labelledby="`participant_nip_label_${idx}`"
                                            class="w-full"
                                            placeholder="Pilih / Ketik Nama Pegawai"
                                            filter
                                            filterPlaceholder="Ketik Nama atau NIP..."
                                            :invalid="!!form.errors[`participants.${idx}.nip`]"
                                        />
                                        <small v-if="form.errors[`participants.${idx}.nip`]" class="text-rose-700 font-medium block">
                                            {{ form.errors[`participants.${idx}.nip`] }}
                                        </small>
                                    </div>

                                    <div class="space-y-1.5">
                                        <label :for="`participant_task_${idx}`" class="text-xs font-semibold text-slate-700 block">
                                            Uraian Tugas Lembur <span class="text-rose-700">*</span>
                                        </label>
                                        <InputText
                                            :id="`participant_task_${idx}`"
                                            :name="`participant_task_${idx}`"
                                            v-model="participant.task"
                                            maxlength="255"
                                            class="w-full min-h-[44px] text-sm"
                                            placeholder="Contoh: Setup batch produksi, perawatan mesin..."
                                            :invalid="!!form.errors[`participants.${idx}.task`]"
                                        />
                                        <small v-if="form.errors[`participants.${idx}.task`]" class="text-rose-700 font-medium block">
                                            {{ form.errors[`participants.${idx}.task`] }}
                                        </small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div>
                            <Button
                                type="button"
                                label="Tambah Peserta Lembur"
                                icon="pi pi-plus"
                                severity="secondary"
                                outlined
                                size="small"
                                class="min-h-[44px] !text-slate-700 text-xs font-semibold"
                                :disabled="form.participants.length >= 30"
                                @click="addParticipant"
                            />
                        </div>
                    </section>

                    <!-- 5. Action Buttons -->
                    <div class="pt-4 border-t border-slate-200 flex flex-col sm:flex-row items-center justify-center gap-3">
                        <Button
                            type="submit"
                            label="Kirim Pengajuan SPL"
                            icon="pi pi-send"
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
        </div>

        <!-- ================= PANEL 2: RIWAYAT PENGAJUAN LEMBUR ================= -->
        <div
            id="panel-history"
            role="tabpanel"
            aria-labelledby="tab-history"
            v-show="activeTab === 'history'"
            class="space-y-4"
        >
            <div class="bg-white rounded-2xl border border-slate-200 border-t-4 border-t-[#005BAC] shadow-sm overflow-hidden">
                <div class="px-5 sm:px-6 py-4 border-b border-slate-200/80 flex flex-wrap items-center justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <div class="size-9 rounded-xl bg-blue-50 text-[#005BAC] flex items-center justify-center shrink-0 border border-blue-200/60">
                            <i class="pi pi-history text-base" aria-hidden="true" />
                        </div>
                        <div>
                            <h2 class="font-bold text-sm sm:text-base text-slate-900 tracking-tight">
                                {{ canCreateOvertime ? 'Riwayat Pengajuan Lembur Saya' : 'Riwayat Penugasan Lembur Saya' }}
                            </h2>
                            <p class="text-xs text-slate-600">
                                Menampilkan {{ recentOvertime.length }} pengajuan lembur terbaru
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
                <div v-if="recentOvertime.length > 0" class="px-5 sm:px-6 pt-4 pb-1 grid grid-cols-1 sm:grid-cols-12 gap-3">
                    <div class="sm:col-span-7">
                        <label for="overtime-history-search" class="sr-only">Cari riwayat berdasarkan kode SPL, jenis, atau tanggal</label>
                        <InputText
                            id="overtime-history-search"
                            v-model="historySearch"
                            placeholder="Cari kode SPL, jenis, atau tanggal lembur..."
                            class="w-full min-h-[44px] text-sm"
                        />
                    </div>
                    <div class="sm:col-span-5">
                        <span id="overtime-status-filter-label" class="sr-only">Filter status pengajuan lembur</span>
                        <Select
                            v-model="historyStatusFilter"
                            :options="historyStatusOptions"
                            optionLabel="label"
                            optionValue="value"
                            aria-labelledby="overtime-status-filter-label"
                            placeholder="Semua Status SPL"
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
                        <p class="text-sm font-medium text-slate-700">Memuat pembaruan riwayat lembur...</p>
                    </div>

                    <!-- Empty State Awal -->
                    <div v-else-if="recentOvertime.length === 0" class="text-center py-10 px-4 space-y-3">
                        <div class="size-12 rounded-2xl bg-slate-100 text-slate-600 flex items-center justify-center mx-auto">
                            <i class="pi pi-inbox text-xl" aria-hidden="true" />
                        </div>
                        <div class="space-y-1 max-w-md mx-auto">
                            <p class="text-sm font-bold text-slate-900">Belum Ada Riwayat Pengajuan Lembur</p>
                            <p class="text-xs text-slate-600">
                                {{ canCreateOvertime
                                    ? 'Surat Perintah Lembur (SPL) yang Anda ajukan akan langsung tampil di sini setelah tersimpan.'
                                    : 'Penugasan lembur yang didaftarkan oleh atasan unit kerja akan tampil pada daftar ini.' }}
                            </p>
                        </div>
                        <div v-if="canCreateOvertime" class="pt-1">
                            <Button
                                type="button"
                                label="Buat Pengajuan Lembur Baru"
                                icon="pi pi-plus"
                                size="small"
                                class="!bg-[#005BAC] hover:!bg-[#004280] !border-[#005BAC] !text-white min-h-[44px]"
                                @click="switchTab('form')"
                            />
                        </div>
                    </div>

                    <!-- Empty State Hasil Filter -->
                    <div v-else-if="filteredOvertime.length === 0" class="text-center py-8 px-4 space-y-3">
                        <p class="text-sm font-semibold text-slate-800">Tidak ada riwayat lembur yang cocok dengan filter pencarian.</p>
                        <Button
                            type="button"
                            label="Reset Filter"
                            icon="pi pi-filter-slash"
                            severity="secondary"
                            outlined
                            size="small"
                            class="min-h-[44px] !text-slate-700"
                            @click="historySearch = ''; historyStatusFilter = '';"
                        />
                    </div>

                    <!-- Data View -->
                    <template v-else>
                        <!-- Desktop DataTable (>= lg: 1024px) -->
                        <div class="hidden lg:block">
                            <DataTable
                                :value="filteredOvertime"
                                :rows="perPage"
                                paginator
                                sort-field="raw_start_date"
                                :sort-order="-1"
                                responsive-layout="scroll"
                                class="text-sm"
                                empty-message="Belum ada riwayat pengajuan lembur."
                                striped-rows
                                :pt="{ bodyCell: { class: '!py-2' } }"
                            >
                                <Column field="code" header="Kode SPL" sortable style="min-width: 120px">
                                    <template #body="{ data }">
                                        <span class="font-mono font-bold text-xs text-[#005BAC]">
                                            {{ data.code }}
                                        </span>
                                        <span v-if="data.legacy_id" class="block font-mono text-[11px] text-slate-600">
                                            #{{ data.legacy_id }}
                                        </span>
                                    </template>
                                </Column>
                                <Column field="request_date" header="Tgl Pengajuan" sortable style="min-width: 115px">
                                    <template #body="{ data }">
                                        <span class="font-mono text-xs text-slate-800 whitespace-nowrap">{{ data.request_date }}</span>
                                    </template>
                                </Column>
                                <Column field="type_label" header="Jenis Lembur" sortable style="min-width: 150px">
                                    <template #body="{ data }">
                                        <span class="font-semibold text-slate-900 text-xs sm:text-sm">{{ data.type_label }}</span>
                                    </template>
                                </Column>
                                <Column field="raw_start_date" header="Tgl Lembur" sortable style="min-width: 110px">
                                    <template #body="{ data }">
                                        <span class="font-mono text-xs text-slate-800 whitespace-nowrap">{{ data.start_date }}</span>
                                    </template>
                                </Column>
                                <Column header="Jam" style="min-width: 115px">
                                    <template #body="{ data }">
                                        <span class="font-mono text-xs text-slate-800 whitespace-nowrap">
                                            {{ data.start_time }} - {{ data.end_time }}
                                        </span>
                                    </template>
                                </Column>
                                <Column field="approval_status" header="Status" sortable style="min-width: 155px">
                                    <template #body="{ data }">
                                        <Tag
                                            :value="data.status_label"
                                            :severity="getStatusSeverity(data)"
                                            class="text-xs whitespace-nowrap"
                                        />
                                    </template>
                                </Column>
                                <Column header="Aksi" style="min-width: 135px; text-align: center">
                                    <template #body="{ data }">
                                        <div class="flex items-center justify-center gap-2">
                                            <Link
                                                v-if="data.can_verify"
                                                :href="`/lembur/verifikasi/${data.id}`"
                                                :aria-label="`Verifikasi SPL ${data.code} tanggal ${data.start_date}`"
                                                class="inline-flex items-center justify-center gap-1.5 px-3 py-1.5 min-h-[44px] rounded-lg bg-[#005BAC] hover:bg-[#004280] text-white text-xs font-semibold transition-colors whitespace-nowrap"
                                            >
                                                <i class="pi pi-file-edit text-xs" aria-hidden="true" />
                                                <span>Verifikasi</span>
                                            </Link>
                                            <Button
                                                type="button"
                                                label="Detail"
                                                icon="pi pi-eye"
                                                size="small"
                                                severity="secondary"
                                                outlined
                                                :aria-label="`Detail ${data.code} tanggal ${data.start_date}`"
                                                class="min-h-[44px] text-xs !text-slate-700"
                                                @click="showDetail(data.id)"
                                            />
                                        </div>
                                    </template>
                                </Column>
                            </DataTable>
                        </div>

                        <!-- Mobile & Tablet Responsive Cards (< lg: < 1024px) -->
                        <div class="lg:hidden space-y-3">
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <article
                                    v-for="req in paginatedCardOvertime"
                                    :key="req.id"
                                    class="p-4 rounded-xl border border-slate-200 bg-slate-50/60 flex flex-col justify-between gap-3"
                                >
                                    <div class="space-y-2">
                                        <div class="flex items-start justify-between gap-2">
                                            <div>
                                                <div class="flex items-center gap-1.5 flex-wrap">
                                                    <h3 class="font-mono font-bold text-sm text-[#005BAC]">{{ req.code }}</h3>
                                                    <span v-if="req.legacy_id" class="font-mono text-xs text-slate-600">
                                                        #{{ req.legacy_id }}
                                                    </span>
                                                </div>
                                                <p class="text-xs sm:text-sm font-bold text-slate-900 mt-0.5">
                                                    {{ req.type_label }}
                                                </p>
                                            </div>
                                            <Tag
                                                :value="req.status_label"
                                                :severity="getStatusSeverity(req)"
                                                class="text-xs shrink-0"
                                            />
                                        </div>

                                        <div class="space-y-0.5 text-xs text-slate-700">
                                            <p>
                                                <span class="font-semibold text-slate-800">Tgl Lembur:</span>
                                                <span class="font-mono ml-1">{{ req.start_date }}</span>
                                                <span class="mx-1.5 text-slate-600">&bull;</span>
                                                <span class="font-mono">{{ req.start_time }} - {{ req.end_time }}</span>
                                            </p>
                                            <p class="text-slate-600">
                                                <span>Diajukan {{ req.request_date }}</span>
                                                <span class="mx-1.5">&bull;</span>
                                                <span>{{ req.participant_count }} peserta</span>
                                            </p>
                                        </div>
                                    </div>

                                    <div class="pt-2 border-t border-slate-200/80 flex items-center justify-end gap-2 flex-wrap">
                                        <Link
                                            v-if="req.can_verify"
                                            :href="`/lembur/verifikasi/${req.id}`"
                                            :aria-label="`Verifikasi SPL ${req.code} tanggal ${req.start_date}`"
                                            class="inline-flex items-center justify-center gap-1.5 px-3 py-1.5 min-h-[44px] rounded-lg bg-[#005BAC] hover:bg-[#004280] text-white text-xs font-semibold transition-colors"
                                        >
                                            <i class="pi pi-file-edit text-xs" aria-hidden="true" />
                                            <span>Verifikasi</span>
                                        </Link>
                                        <Button
                                            type="button"
                                            label="Detail"
                                            icon="pi pi-eye"
                                            size="small"
                                            severity="secondary"
                                            outlined
                                            :aria-label="`Detail ${req.code} tanggal ${req.start_date}`"
                                            class="min-h-[44px] text-xs !text-slate-700"
                                            @click="showDetail(req.id)"
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
                                    type="button"
                                    label="Sebelumnya"
                                    icon="pi pi-chevron-left"
                                    size="small"
                                    severity="secondary"
                                    outlined
                                    :disabled="responsivePage <= 1"
                                    class="min-h-[44px] text-xs !text-slate-700"
                                    @click="responsivePage--"
                                />
                                <span class="text-xs font-mono text-slate-700">
                                    Hal {{ responsivePage }} / {{ responsiveTotalPages }}
                                </span>
                                <Button
                                    type="button"
                                    label="Berikutnya"
                                    icon="pi pi-chevron-right"
                                    iconPos="right"
                                    size="small"
                                    severity="secondary"
                                    outlined
                                    :disabled="responsivePage >= responsiveTotalPages"
                                    class="min-h-[44px] text-xs !text-slate-700"
                                    @click="responsivePage++"
                                />
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </div>

        <!-- ================= DETAIL DIALOG SURAT PERINTAH LEMBUR ================= -->
        <Dialog
            v-model:visible="detailDialogVisible"
            modal
            header="Detail Surat Perintah Lembur (SPL)"
            :style="{ width: '680px', maxWidth: '95vw' }"
            :closable="true"
        >
            <div v-if="loadingDetail" role="status" aria-live="polite" class="py-10 text-center text-slate-600">
                <i class="pi pi-spin motion-reduce:animate-none pi-spinner text-2xl text-[#005BAC]" aria-hidden="true" />
                <p class="text-sm mt-2">Memuat detail SPL...</p>
            </div>

            <div v-else-if="detailData" class="space-y-5 text-sm">
                <div class="grid grid-cols-2 sm:grid-cols-3 gap-x-4 gap-y-3 bg-slate-50/80 p-4 rounded-xl border border-slate-200">
                    <div>
                        <p class="text-[11px] text-slate-600 font-bold uppercase">Kode SPL</p>
                        <p class="font-mono font-bold text-[#005BAC] text-xs sm:text-sm mt-0.5">
                            {{ detailData.code }}
                            <span v-if="detailData.legacy_id" class="text-[11px] text-slate-600 font-normal ml-1">
                                (#{{ detailData.legacy_id }})
                            </span>
                        </p>
                    </div>
                    <div>
                        <p class="text-[11px] text-slate-600 font-bold uppercase">Jenis Lembur</p>
                        <p class="font-semibold text-slate-900 mt-0.5">{{ detailData.type_label }}</p>
                    </div>
                    <div>
                        <p class="text-[11px] text-slate-600 font-bold uppercase">Tanggal Lembur</p>
                        <p class="font-mono font-medium text-slate-900 mt-0.5">{{ detailData.start_date }}</p>
                    </div>
                    <div>
                        <p class="text-[11px] text-slate-600 font-bold uppercase">Tgl Pengajuan</p>
                        <p class="font-mono font-medium text-slate-900 mt-0.5">{{ detailData.request_date }}</p>
                    </div>
                    <div>
                        <p class="text-[11px] text-slate-600 font-bold uppercase">Jam Rencana</p>
                        <p class="font-mono font-medium text-slate-900 mt-0.5">{{ detailData.start_time }} - {{ detailData.end_time }}</p>
                    </div>
                    <div>
                        <p class="text-[11px] text-slate-600 font-bold uppercase">Status Pengajuan</p>
                        <div class="mt-1">
                            <Tag :value="detailData.status_label" :severity="getStatusSeverity(detailData.approval_status)" class="text-xs" />
                        </div>
                    </div>
                </div>

                <div>
                    <div class="flex items-center justify-between mb-2.5">
                        <p class="text-xs font-bold uppercase tracking-wider text-slate-700">
                            Alur Persetujuan Atasan (ACC 1 - 3)
                        </p>
                        <span class="text-xs text-slate-600 font-medium">
                            {{ detailData.supervisors?.length ?? 0 }} Tingkat Approval
                        </span>
                    </div>

                    <div
                        v-if="detailData.supervisors && detailData.supervisors.length > 0"
                        class="grid gap-3"
                        :class="detailData.supervisors.length === 1 ? 'grid-cols-1' : (detailData.supervisors.length === 2 ? 'grid-cols-1 sm:grid-cols-2' : 'grid-cols-1 sm:grid-cols-3')"
                    >
                        <div
                            v-for="s in detailData.supervisors"
                            :key="s.level"
                            class="p-3.5 rounded-xl border bg-white shadow-xs flex flex-col justify-between gap-2.5"
                            :class="s.status_label === 'Menunggu'
                                ? 'border-amber-300 ring-1 ring-amber-200 bg-amber-50/30'
                                : 'border-slate-200'"
                        >
                            <div class="flex items-center justify-between gap-1.5 pb-2 border-b border-slate-100">
                                <span
                                    class="text-[11px] font-bold uppercase tracking-wide flex items-center gap-1"
                                    :class="s.status === 1 ? 'text-emerald-800' : (s.status_label === 'Menunggu' ? 'text-amber-800' : 'text-slate-700')"
                                >
                                    <i class="pi pi-user-check text-[11px]" aria-hidden="true" />
                                    {{ s.level_label }}
                                </span>
                                <Tag
                                    :value="s.status_label"
                                    :severity="s.status === 1 ? 'success' : (s.status === 2 ? 'danger' : (s.status_label === 'Menunggu Antrean' ? 'secondary' : 'warn'))"
                                    class="text-[11px] px-1.5 py-0.5"
                                />
                            </div>

                            <div class="space-y-0.5">
                                <p class="font-bold text-slate-900 text-xs leading-snug line-clamp-1" :title="s.name">{{ s.name }}</p>
                                <p class="text-[11px] text-slate-600 font-mono">{{ s.nip }}</p>
                                <p class="text-[11px] text-slate-700 truncate" :title="s.position">{{ s.position }}</p>
                            </div>

                            <div v-if="s.approved_at" class="text-[11px] text-slate-600 pt-1.5 border-t border-slate-100 flex items-center gap-1">
                                <i class="pi pi-clock text-[10px]" aria-hidden="true" />
                                <span>{{ s.approved_at }}</span>
                            </div>
                        </div>
                    </div>

                    <div v-else class="text-xs text-slate-600 italic p-3 bg-slate-50 rounded-xl border border-slate-200">
                        Tidak ada data atasan penyetuju yang tercatat.
                    </div>
                </div>

                <div v-if="detailData.approval_notes" class="bg-amber-50 border border-amber-200 rounded-xl p-3.5">
                    <p class="text-xs font-bold text-amber-800 uppercase">Catatan Atasan</p>
                    <p class="text-xs sm:text-sm text-amber-900 mt-1">{{ detailData.approval_notes }}</p>
                </div>

                <div>
                    <p class="text-xs font-bold uppercase tracking-wider text-slate-700 mb-2.5">
                        Daftar Peserta Lembur ({{ detailData.participants?.length ?? 0 }} orang)
                    </p>
                    <div class="border border-slate-200 rounded-xl overflow-x-auto">
                        <table class="w-full text-xs min-w-[460px]">
                            <caption class="sr-only">Daftar Peserta Lembur untuk {{ detailData.code }}</caption>
                            <thead class="bg-slate-50 border-b border-slate-200">
                                <tr>
                                    <th scope="col" class="px-3 py-2 text-left font-semibold text-slate-700">NPP/NIP</th>
                                    <th scope="col" class="px-3 py-2 text-left font-semibold text-slate-700">Nama Pegawai</th>
                                    <th scope="col" class="px-3 py-2 text-left font-semibold text-slate-700">Uraian Tugas</th>
                                    <th scope="col" class="px-3 py-2 text-center font-semibold text-slate-700">Realisasi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr v-for="(p, i) in detailData.participants" :key="i" class="hover:bg-slate-50/60">
                                    <td class="px-3 py-2 font-mono text-slate-700 whitespace-nowrap">{{ p.nip }}</td>
                                    <td class="px-3 py-2 text-slate-900 font-medium">{{ p.name }}</td>
                                    <td class="px-3 py-2 text-slate-700">{{ p.task || '-' }}</td>
                                    <td class="px-3 py-2 text-center font-mono text-slate-700 whitespace-nowrap">{{ p.duration ?? '-' }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <template #footer>
                <Button
                    type="button"
                    label="Tutup"
                    severity="secondary"
                    outlined
                    class="min-h-[44px] !text-slate-700"
                    @click="detailDialogVisible = false"
                />
            </template>
        </Dialog>
    </div>
</template>
