<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';
import axios from 'axios';
import Toast from 'primevue/toast';
import { useToast } from 'primevue/usetoast';
import Button from 'primevue/button';
import Tag from 'primevue/tag';
import Dialog from 'primevue/dialog';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import DatePicker from 'primevue/datepicker';
import InputText from 'primevue/inputtext';
import IconField from 'primevue/iconfield';
import InputIcon from 'primevue/inputicon';

defineOptions({ layout: AuthenticatedLayout });

const props = defineProps({
    pendingApprovals: {
        type: Array,
        default: () => [],
    },
    historyApprovals: {
        type: Array,
        default: () => [],
    },
    isAdmin: {
        type: Boolean,
        default: false,
    },
    canApproveOvertime: {
        type: Boolean,
        default: true,
    },
    isSupervisor: {
        type: Boolean,
        default: false,
    },
    userLevel: {
        type: String,
        default: 'Supervisor',
    },
    initialTab: {
        type: String,
        default: 'pending',
    },
});

const toast = useToast();

const activeTab = ref(props.initialTab === 'history' ? 'history' : 'pending');
watch(
    () => props.initialTab,
    (nextTab) => {
        if (nextTab === 'pending' || nextTab === 'history') {
            activeTab.value = nextTab;
        }
    }
);

const pendingTabButtonRef = ref(null);
const historyTabButtonRef = ref(null);

function switchTab(tab) {
    activeTab.value = tab;
}

function handleTabKeydown(event, currentTab) {
    if (event.key === 'ArrowRight' || event.key === 'ArrowLeft') {
        event.preventDefault();
        const targetTab = currentTab === 'pending' ? 'history' : 'pending';
        activeTab.value = targetTab;
        if (targetTab === 'pending') {
            pendingTabButtonRef.value?.focus();
        } else {
            historyTabButtonRef.value?.focus();
        }
    } else if (event.key === 'Home') {
        event.preventDefault();
        activeTab.value = 'pending';
        pendingTabButtonRef.value?.focus();
    } else if (event.key === 'End') {
        event.preventDefault();
        activeTab.value = 'history';
        historyTabButtonRef.value?.focus();
    }
}

const isRefreshing = ref(false);
let removeStartListener = null;
let removeFinishListener = null;

onMounted(() => {
    removeStartListener = router.on('start', (event) => {
        const visitUrl = event?.detail?.visit?.url?.pathname || '';
        if (visitUrl.includes('/lembur/persetujuan')) {
            isRefreshing.value = true;
        }
    });
    removeFinishListener = router.on('finish', () => {
        isRefreshing.value = false;
    });
});

onUnmounted(() => {
    if (typeof removeStartListener === 'function') removeStartListener();
    if (typeof removeFinishListener === 'function') removeFinishListener();
});

function refreshApprovals() {
    isRefreshing.value = true;
    router.reload({
        only: ['pendingApprovals', 'historyApprovals'],
        onFinish: () => {
            isRefreshing.value = false;
        },
    });
}

function formatDateLocal(val) {
    if (!val) return '';
    if (typeof val === 'string') return val.trim();
    if (!(val instanceof Date) || Number.isNaN(val.getTime())) return '';
    const y = val.getFullYear();
    const m = String(val.getMonth() + 1).padStart(2, '0');
    const d = String(val.getDate()).padStart(2, '0');
    return `${y}-${m}-${d}`;
}

const searchQuery = ref('');
const selectedStartDate = ref(null);
const selectedEndDate = ref(null);

const filterStartDate = computed(() => formatDateLocal(selectedStartDate.value));
const filterEndDate = computed(() => formatDateLocal(selectedEndDate.value));

const isFilterActive = computed(() => {
    return !!(searchQuery.value.trim() || filterStartDate.value || filterEndDate.value);
});

function resetDateFilter() {
    searchQuery.value = '';
    selectedStartDate.value = null;
    selectedEndDate.value = null;
}

const quickFilterActive = computed(() => {
    const today = new Date();
    const y = today.getFullYear();
    const m = String(today.getMonth() + 1).padStart(2, '0');
    const d = String(today.getDate()).padStart(2, '0');
    const todayStr = `${y}-${m}-${d}`;

    if (!filterStartDate.value && !filterEndDate.value) return 'all';
    if (filterStartDate.value === todayStr && filterEndDate.value === todayStr) return 'today';

    const past7 = new Date();
    past7.setDate(past7.getDate() - 7);
    const past7Str = `${past7.getFullYear()}-${String(past7.getMonth() + 1).padStart(2, '0')}-${String(past7.getDate()).padStart(2, '0')}`;
    if (filterStartDate.value === past7Str && filterEndDate.value === todayStr) return '7days';

    const lastDay = new Date(y, today.getMonth() + 1, 0).getDate();
    const startMonth = `${y}-${m}-01`;
    const endMonth = `${y}-${m}-${String(lastDay).padStart(2, '0')}`;
    if (filterStartDate.value === startMonth && filterEndDate.value === endMonth) return 'thisMonth';

    return 'custom';
});

function setQuickFilter(period) {
    const today = new Date();
    const y = today.getFullYear();
    const m = today.getMonth();
    const d = today.getDate();

    if (period === 'today') {
        selectedStartDate.value = new Date(y, m, d);
        selectedEndDate.value = new Date(y, m, d);
    } else if (period === '7days') {
        const past7 = new Date(y, m, d - 7);
        selectedStartDate.value = past7;
        selectedEndDate.value = new Date(y, m, d);
    } else if (period === 'thisMonth') {
        const lastDay = new Date(y, m + 1, 0).getDate();
        selectedStartDate.value = new Date(y, m, 1);
        selectedEndDate.value = new Date(y, m, lastDay);
    } else if (period === 'all') {
        selectedStartDate.value = null;
        selectedEndDate.value = null;
    }
}

const filteredPending = computed(() => {
    const q = searchQuery.value.toLowerCase().trim();
    const start = filterStartDate.value;
    const end = filterEndDate.value;

    return props.pendingApprovals.filter((item) => {
        const itemDate = item.raw_start_date;
        if (start && itemDate && itemDate < start) return false;
        if (end && itemDate && itemDate > end) return false;

        if (q) {
            const matchSearch =
                (item.code && item.code.toLowerCase().includes(q)) ||
                (item.legacy_id && String(item.legacy_id).includes(q)) ||
                (item.applicant_name && item.applicant_name.toLowerCase().includes(q)) ||
                (item.applicant_nip && item.applicant_nip.toLowerCase().includes(q)) ||
                (item.applicant_division && item.applicant_division.toLowerCase().includes(q)) ||
                (item.type_label && item.type_label.toLowerCase().includes(q));
            if (!matchSearch) return false;
        }

        return true;
    });
});

const filteredHistory = computed(() => {
    const q = searchQuery.value.toLowerCase().trim();
    const start = filterStartDate.value;
    const end = filterEndDate.value;

    return props.historyApprovals.filter((item) => {
        const itemDate = item.raw_start_date;
        if (start && itemDate && itemDate < start) return false;
        if (end && itemDate && itemDate > end) return false;

        if (q) {
            const matchSearch =
                (item.code && item.code.toLowerCase().includes(q)) ||
                (item.legacy_id && String(item.legacy_id).includes(q)) ||
                (item.applicant_name && item.applicant_name.toLowerCase().includes(q)) ||
                (item.applicant_nip && item.applicant_nip.toLowerCase().includes(q)) ||
                (item.applicant_division && item.applicant_division.toLowerCase().includes(q)) ||
                (item.type_label && item.type_label.toLowerCase().includes(q)) ||
                (item.status_label && item.status_label.toLowerCase().includes(q));
            if (!matchSearch) return false;
        }

        return true;
    });
});

const perPage = ref(10);
const pendingTableFirst = ref(0);
const historyTableFirst = ref(0);
const pendingResponsivePage = ref(1);
const historyResponsivePage = ref(1);

const pendingResponsiveTotalPages = computed(() =>
    Math.max(1, Math.ceil(filteredPending.value.length / perPage.value))
);
const paginatedPendingCards = computed(() => {
    const start = (pendingResponsivePage.value - 1) * perPage.value;
    return filteredPending.value.slice(start, start + perPage.value);
});

const historyResponsiveTotalPages = computed(() =>
    Math.max(1, Math.ceil(filteredHistory.value.length / perPage.value))
);
const paginatedHistoryCards = computed(() => {
    const start = (historyResponsivePage.value - 1) * perPage.value;
    return filteredHistory.value.slice(start, start + perPage.value);
});

watch([searchQuery, filterStartDate, filterEndDate, () => props.pendingApprovals, () => props.historyApprovals], () => {
    pendingTableFirst.value = 0;
    historyTableFirst.value = 0;
    pendingResponsivePage.value = 1;
    historyResponsivePage.value = 1;
});

function getStatusSeverity(status) {
    switch (status) {
        case 1:
            return 'success';
        case 2:
            return 'danger';
        case 3:
        case 4:
        case 5:
            return 'warn';
        default:
            return 'secondary';
    }
}

const approvalDialogVisible = ref(false);
const selectedSpl = ref(null);
const approveOptionRef = ref(null);
const rejectOptionRef = ref(null);

const approvalForm = useForm({
    decision: 'approve',
    notes: '',
});

function openApprovalModal(item) {
    selectedSpl.value = item;
    approvalForm.reset();
    approvalForm.decision = 'approve';
    approvalForm.notes = '';
    approvalDialogVisible.value = true;
}

function handleDecisionKeydown(event, currentDecision) {
    if (
        event.key === 'ArrowRight' ||
        event.key === 'ArrowDown' ||
        event.key === 'ArrowLeft' ||
        event.key === 'ArrowUp'
    ) {
        event.preventDefault();
        const nextDecision = currentDecision === 'approve' ? 'reject' : 'approve';
        approvalForm.decision = nextDecision;
        if (nextDecision === 'approve') {
            approveOptionRef.value?.focus();
        } else {
            rejectOptionRef.value?.focus();
        }
    }
}

function submitApproval() {
    if (!selectedSpl.value) return;

    if (approvalForm.decision === 'reject' && !approvalForm.notes.trim()) {
        toast.add({
            severity: 'warn',
            summary: 'Alasan Penolakan Wajib Diisi',
            detail: 'Silakan berikan catatan alasan mengapa pengajuan lembur ini ditolak.',
            life: 5000,
        });
        return;
    }

    const splCode = selectedSpl.value.code;
    approvalForm.post(`/lembur/${selectedSpl.value.id}/approval`, {
        preserveScroll: true,
        onSuccess: () => {
            approvalDialogVisible.value = false;
            toast.add({
                severity: 'success',
                summary: 'Berhasil Diproses',
                detail: `Keputusan untuk ${splCode} berhasil disimpan.`,
                life: 4000,
            });
            selectedSpl.value = null;
        },
        onError: (err) => {
            const firstErr = Object.values(err)[0];
            toast.add({
                severity: 'error',
                summary: 'Gagal Memproses',
                detail: firstErr || 'Terjadi kesalahan saat memproses keputusan.',
                life: 5000,
            });
        },
    });
}

const selectedIds = ref([]);
const batchApprovalDialogVisible = ref(false);

const batchForm = useForm({
    ids: [],
    notes: '',
});

const selectablePendingItems = computed(() => {
    return filteredPending.value.filter((item) => item.can_approve);
});

watch(selectablePendingItems, (items) => {
    const validIds = new Set(items.map((item) => item.id));
    const filtered = selectedIds.value.filter((id) => validIds.has(id));
    if (filtered.length !== selectedIds.value.length) {
        selectedIds.value = filtered;
    }
});

const isAllSelected = computed(() => {
    return (
        selectablePendingItems.value.length > 0 &&
        selectablePendingItems.value.every((item) => selectedIds.value.includes(item.id))
    );
});

const isIndeterminate = computed(() => {
    const selectedCountInSelectable = selectablePendingItems.value.filter((item) =>
        selectedIds.value.includes(item.id)
    ).length;
    return selectedCountInSelectable > 0 && selectedCountInSelectable < selectablePendingItems.value.length;
});

function toggleSelectAll(event) {
    if (event.target.checked) {
        const currentIds = new Set(selectedIds.value);
        selectablePendingItems.value.forEach((item) => currentIds.add(item.id));
        selectedIds.value = Array.from(currentIds);
    } else {
        const selectableIdSet = new Set(selectablePendingItems.value.map((i) => i.id));
        selectedIds.value = selectedIds.value.filter((id) => !selectableIdSet.has(id));
    }
}

const selectedSplList = computed(() => {
    return props.pendingApprovals.filter((i) => selectedIds.value.includes(i.id));
});

const selectedParticipantsTotal = computed(() => {
    return selectedSplList.value.reduce((total, spl) => total + (spl.participant_count || 0), 0);
});

function openBatchApprovalModal() {
    if (selectedIds.value.length === 0) return;
    batchForm.ids = [...selectedIds.value];
    batchForm.notes = '';
    batchApprovalDialogVisible.value = true;
}

function submitBatchApproval() {
    if (batchForm.ids.length === 0) return;

    batchForm.post('/lembur/persetujuan-massal', {
        preserveScroll: true,
        onSuccess: () => {
            batchApprovalDialogVisible.value = false;
            selectedIds.value = [];
            toast.add({
                severity: 'success',
                summary: 'Persetujuan Massal Berhasil',
                detail: 'Seluruh berkas SPL yang dipilih berhasil disetujui.',
                life: 4000,
            });
        },
        onError: (err) => {
            const firstErr = Object.values(err)[0];
            toast.add({
                severity: 'error',
                summary: 'Gagal Memproses Massal',
                detail: firstErr || 'Terjadi kesalahan saat memproses persetujuan massal.',
                life: 5000,
            });
        },
    });
}

const detailDialogVisible = ref(false);
const detailLoading = ref(false);
const detailData = ref(null);

async function openDetailModal(item) {
    detailDialogVisible.value = true;
    detailLoading.value = true;
    detailData.value = null;

    try {
        const res = await axios.get(`/lembur/${item.id}`);
        detailData.value = res.data;
    } catch {
        toast.add({
            severity: 'error',
            summary: 'Gagal Memuat Detail',
            detail: 'Tidak dapat mengambil rincian data SPL dari server.',
            life: 4000,
        });
        detailDialogVisible.value = false;
    } finally {
        detailLoading.value = false;
    }
}
</script>

<template>
    <Head title="Pusat Persetujuan Lembur (Atasan) - eSDM PT Kimia Farma Plant Banjaran" />

    <div class="space-y-5 max-w-7xl mx-auto px-3 sm:px-6 pt-1 pb-6">
        <Toast position="top-right" />

        <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-3">
            <div class="space-y-1">
                <nav aria-label="Breadcrumb" class="flex items-center gap-2 text-xs font-medium text-slate-600">
                    <Link href="/absensi" class="hover:text-[#005BAC] flex items-center gap-1 py-0.5">
                        <i class="pi pi-calendar-check text-[11px]" aria-hidden="true" />
                        <span>Presensi</span>
                    </Link>
                    <span aria-hidden="true">/</span>
                    <span class="text-slate-900 font-semibold">Persetujuan Lembur (Atasan)</span>
                </nav>
                <h1 class="text-xl sm:text-2xl font-bold text-slate-900 tracking-tight flex flex-wrap items-center gap-2.5">
                    <span>Pusat Persetujuan Surat Perintah Lembur</span>
                    <span
                        v-if="isAdmin"
                        class="text-[11px] font-bold px-2.5 py-0.5 rounded-full bg-blue-100 text-[#005BAC] border border-blue-200"
                    >
                        Mode Administrator
                    </span>
                </h1>
                <p class="text-xs sm:text-sm text-slate-600 max-w-3xl">
                    Tinjau, setujui, atau tolak permohonan Surat Perintah Lembur (SPL) bawahan secara berjenjang (ACC 1 - 3) sesuai otoritas struktural PT Kimia Farma Plant Banjaran.
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-2.5 shrink-0 self-start sm:self-auto">
                <div class="px-3.5 py-2 rounded-xl border flex items-center gap-2.5 bg-white border-slate-200 shadow-2xs">
                    <div
                        class="size-9 rounded-lg flex items-center justify-center shrink-0"
                        :class="pendingApprovals.length > 0 ? 'bg-amber-100 text-amber-800' : 'bg-emerald-100 text-emerald-800'"
                    >
                        <i :class="pendingApprovals.length > 0 ? 'pi pi-clock text-base' : 'pi pi-check-circle text-base'" aria-hidden="true" />
                    </div>
                    <div>
                        <p class="text-[11px] font-bold text-slate-600 uppercase tracking-wider">Perlu Persetujuan</p>
                        <p class="text-base font-bold text-slate-900 leading-tight">
                            {{ pendingApprovals.length }} <span class="text-xs font-medium text-slate-600">SPL</span>
                        </p>
                    </div>
                </div>

                <Link
                    href="/absensi"
                    class="inline-flex items-center justify-center gap-2 px-4 py-2.5 min-h-[44px] rounded-xl border border-slate-300 bg-white hover:bg-slate-50 text-slate-700 font-semibold text-xs sm:text-sm transition-colors shrink-0"
                >
                    <i class="pi pi-arrow-left text-xs" aria-hidden="true" />
                    <span>Kembali ke Presensi</span>
                </Link>
            </div>
        </div>

        <div
            v-if="!canApproveOvertime"
            class="bg-blue-50 border border-blue-200 rounded-2xl p-4 sm:p-5 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4"
        >
            <div class="flex items-start gap-3.5">
                <div class="size-10 rounded-xl bg-blue-100 border border-blue-200 flex items-center justify-center shrink-0 mt-0.5 text-[#005BAC]">
                    <i class="pi pi-shield text-lg" aria-hidden="true" />
                </div>
                <div>
                    <h2 class="text-sm font-bold text-slate-900">Informasi Akses Halaman Persetujuan Lembur</h2>
                    <p class="text-xs text-slate-700 mt-0.5 leading-relaxed">
                        Halaman ini merupakan halaman peninjauan dan persetujuan Surat Perintah Lembur (SPL) bawahan secara berjenjang (ACC 1 - 3) khusus bagi pejabat struktural (minimal tingkat <strong>Supervisor / SPV ke atas</strong>).
                        Akun Anda saat ini tercatat sebagai tingkat <strong>Pelaksana</strong> sehingga tidak memiliki kewenangan approval struktural.
                    </p>
                </div>
            </div>
            <Tag value="Tingkat Pelaksana" severity="secondary" class="shrink-0 font-semibold text-xs px-3 py-1" />
        </div>

        <div
            role="tablist"
            aria-label="Navigasi Antrean dan Riwayat Persetujuan Lembur"
            class="grid grid-cols-2 p-1 bg-slate-200/80 rounded-xl max-w-lg w-full select-none"
        >
            <button
                id="tab-pending"
                ref="pendingTabButtonRef"
                type="button"
                role="tab"
                :aria-selected="String(activeTab === 'pending')"
                aria-controls="panel-pending"
                :tabindex="activeTab === 'pending' ? 0 : -1"
                class="flex items-center justify-center gap-2 py-2 px-3 min-h-[44px] rounded-lg font-bold text-xs sm:text-sm transition-colors cursor-pointer"
                :class="activeTab === 'pending'
                    ? 'bg-white text-slate-900 shadow-xs'
                    : 'text-slate-700 hover:text-slate-900 hover:bg-white/60'"
                @click="switchTab('pending')"
                @keydown="handleTabKeydown($event, 'pending')"
            >
                <i class="pi pi-hourglass text-xs shrink-0" aria-hidden="true" />
                <span>Perlu Persetujuan</span>
                <span
                    v-if="pendingApprovals.length > 0"
                    class="px-1.5 py-0.5 rounded-full text-[11px] font-mono shrink-0"
                    :class="activeTab === 'pending' ? 'bg-amber-100 text-amber-900 border border-amber-300' : 'bg-amber-100 text-amber-900'"
                >
                    {{ pendingApprovals.length }}
                </span>
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
                <i class="pi pi-history text-xs shrink-0" aria-hidden="true" />
                <span>Riwayat Persetujuan</span>
                <span
                    class="px-1.5 py-0.5 rounded-full text-[11px] font-mono shrink-0"
                    :class="activeTab === 'history' ? 'bg-slate-200 text-slate-900' : 'bg-slate-300 text-slate-900'"
                >
                    {{ historyApprovals.length }}
                </span>
            </button>
        </div>

        <section
            aria-label="Daftar Persetujuan dan Riwayat SPL"
            class="bg-white rounded-2xl border border-slate-200 border-t-4 border-t-[#005BAC] shadow-sm overflow-hidden"
        >
            <div class="px-5 sm:px-6 py-4 border-b border-slate-200/80 flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <div class="size-9 rounded-xl bg-blue-50 text-[#005BAC] flex items-center justify-center shrink-0 border border-blue-200/60">
                        <i :class="activeTab === 'pending' ? 'pi pi-check-square text-base' : 'pi pi-history text-base'" aria-hidden="true" />
                    </div>
                    <div>
                        <h2 class="font-bold text-sm sm:text-base text-slate-900 tracking-tight">
                            {{ activeTab === 'pending' ? 'Antrean SPL Menunggu Persetujuan' : 'Riwayat Keputusan Persetujuan SPL' }}
                        </h2>
                        <p class="text-xs text-slate-600">
                            Menampilkan {{ activeTab === 'pending' ? filteredPending.length : filteredHistory.length }} dari
                            {{ activeTab === 'pending' ? pendingApprovals.length : historyApprovals.length }} pengajuan
                        </p>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <Button
                        v-if="isFilterActive"
                        type="button"
                        label="Reset Filter"
                        icon="pi pi-filter-slash"
                        size="small"
                        severity="danger"
                        outlined
                        class="min-h-[44px] text-xs !text-rose-700 !border-rose-300 hover:!bg-rose-50"
                        @click="resetDateFilter"
                    />
                    <Button
                        type="button"
                        label="Muat Ulang"
                        icon="pi pi-refresh"
                        size="small"
                        severity="secondary"
                        outlined
                        :loading="isRefreshing"
                        :disabled="isRefreshing"
                        class="min-h-[44px] text-xs !text-slate-700"
                        @click="refreshApprovals"
                    />
                </div>
            </div>

            <div class="px-5 sm:px-6 pt-4 pb-2 space-y-3 bg-slate-50/70 border-b border-slate-200/80">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2.5">
                    <div class="flex items-center gap-2 flex-wrap" role="group" aria-label="Preset rentang tanggal cepat">
                        <span class="text-xs font-bold text-slate-700 mr-1 flex items-center gap-1.5">
                            <i class="pi pi-calendar text-xs text-slate-600" aria-hidden="true" />
                            <span>Periode:</span>
                        </span>
                        <button
                            type="button"
                            :aria-pressed="String(quickFilterActive === 'all')"
                            class="px-3.5 min-h-[44px] rounded-xl text-xs font-semibold transition-colors cursor-pointer"
                            :class="quickFilterActive === 'all'
                                ? 'bg-[#005BAC] text-white'
                                : 'bg-white text-slate-700 border border-slate-300 hover:bg-slate-100'"
                            @click="setQuickFilter('all')"
                        >
                            Semua Tanggal
                        </button>
                        <button
                            type="button"
                            :aria-pressed="String(quickFilterActive === 'today')"
                            class="px-3.5 min-h-[44px] rounded-xl text-xs font-semibold transition-colors cursor-pointer"
                            :class="quickFilterActive === 'today'
                                ? 'bg-[#005BAC] text-white'
                                : 'bg-white text-slate-700 border border-slate-300 hover:bg-slate-100'"
                            @click="setQuickFilter('today')"
                        >
                            Hari Ini
                        </button>
                        <button
                            type="button"
                            :aria-pressed="String(quickFilterActive === '7days')"
                            class="px-3.5 min-h-[44px] rounded-xl text-xs font-semibold transition-colors cursor-pointer"
                            :class="quickFilterActive === '7days'
                                ? 'bg-[#005BAC] text-white'
                                : 'bg-white text-slate-700 border border-slate-300 hover:bg-slate-100'"
                            @click="setQuickFilter('7days')"
                        >
                            7 Hari Terakhir
                        </button>
                        <button
                            type="button"
                            :aria-pressed="String(quickFilterActive === 'thisMonth')"
                            class="px-3.5 min-h-[44px] rounded-xl text-xs font-semibold transition-colors cursor-pointer"
                            :class="quickFilterActive === 'thisMonth'
                                ? 'bg-[#005BAC] text-white'
                                : 'bg-white text-slate-700 border border-slate-300 hover:bg-slate-100'"
                            @click="setQuickFilter('thisMonth')"
                        >
                            Bulan Ini
                        </button>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 pb-2 pt-2 border-t border-slate-200/80">
                    <div class="min-w-0">
                        <label for="approval-filter-start-date" class="block text-xs font-bold text-slate-700 mb-1.5">
                            Tanggal Lembur Dari
                        </label>
                        <DatePicker
                            v-model="selectedStartDate"
                            inputId="approval-filter-start-date"
                            dateFormat="dd/mm/yy"
                            placeholder="dd/mm/yyyy"
                            showIcon
                            showButtonBar
                            fluid
                            :manualInput="false"
                            :pt="{
                                pcInputText: { root: { class: 'min-h-[44px] text-sm', name: 'filter_start_date', autocomplete: 'off' } },
                                dropdown: { class: 'min-h-[44px]', 'aria-label': 'Buka kalender tanggal lembur dari' },
                                panel: { 'aria-label': 'Pilih tanggal lembur dari' },
                            }"
                            @clear-click="selectedStartDate = null"
                        />
                    </div>

                    <div class="min-w-0">
                        <label for="approval-filter-end-date" class="block text-xs font-bold text-slate-700 mb-1.5">
                            Tanggal Lembur Sampai
                        </label>
                        <DatePicker
                            v-model="selectedEndDate"
                            inputId="approval-filter-end-date"
                            dateFormat="dd/mm/yy"
                            placeholder="dd/mm/yyyy"
                            showIcon
                            showButtonBar
                            fluid
                            :manualInput="false"
                            :pt="{
                                pcInputText: { root: { class: 'min-h-[44px] text-sm', name: 'filter_end_date', autocomplete: 'off' } },
                                dropdown: { class: 'min-h-[44px]', 'aria-label': 'Buka kalender tanggal lembur sampai' },
                                panel: { 'aria-label': 'Pilih tanggal lembur sampai' },
                            }"
                            @clear-click="selectedEndDate = null"
                        />
                    </div>

                    <div class="sm:col-span-2 min-w-0">
                        <label for="approval-search-query" class="block text-xs font-bold text-slate-700 mb-1.5">
                            Pencarian Kata Kunci
                        </label>
                        <IconField class="w-full">
                            <InputIcon class="pi pi-search text-slate-600" aria-hidden="true" />
                            <InputText
                                id="approval-search-query"
                                v-model="searchQuery"
                                name="search_query"
                                placeholder="Cari kode SPL, nama pemohon, NIP, divisi..."
                                class="w-full min-h-[44px] text-sm"
                            />
                        </IconField>
                    </div>
                </div>
            </div>

            <div class="p-5 sm:p-6">
                <div
                    v-if="isRefreshing"
                    role="status"
                    aria-live="polite"
                    class="py-10 px-4 text-center space-y-3"
                >
                    <i class="pi pi-spin motion-reduce:animate-none pi-spinner text-2xl text-[#005BAC]" aria-hidden="true" />
                    <p class="text-sm font-medium text-slate-700">Memuat pembaruan daftar persetujuan lembur...</p>
                </div>

                <div
                    v-else-if="activeTab === 'pending'"
                    id="panel-pending"
                    role="tabpanel"
                    aria-labelledby="tab-pending"
                    class="space-y-4"
                >
                    <div
                        v-if="selectedIds.length > 0"
                        class="p-3.5 sm:p-4 rounded-xl bg-blue-50 border border-blue-200 flex flex-col sm:flex-row sm:items-center justify-between gap-3"
                    >
                        <div class="flex items-center gap-3">
                            <div class="size-9 rounded-lg bg-[#005BAC] text-white flex items-center justify-center shrink-0">
                                <i class="pi pi-check-circle text-base" aria-hidden="true" />
                            </div>
                            <div>
                                <p class="font-bold text-xs sm:text-sm text-slate-900">
                                    {{ selectedIds.length }} Surat Perintah Lembur Terpilih
                                </p>
                                <p class="text-xs text-slate-700">
                                    Total melibatkan <strong class="text-slate-900">{{ selectedParticipantsTotal }} orang pegawai peserta</strong> lembur.
                                </p>
                            </div>
                        </div>

                        <div class="flex flex-wrap items-center gap-2 shrink-0">
                            <button
                                type="button"
                                class="min-h-[44px] px-3.5 rounded-xl border border-slate-300 bg-white hover:bg-slate-100 text-xs font-semibold text-slate-700 transition-colors cursor-pointer"
                                @click="selectedIds = []"
                            >
                                Batal Pilihan
                            </button>
                            <Button
                                type="button"
                                :label="`Setujui ${selectedIds.length} SPL Terpilih`"
                                icon="pi pi-check"
                                size="small"
                                class="!bg-[#005BAC] hover:!bg-[#004280] !border-[#005BAC] !text-white min-h-[44px] text-xs font-semibold"
                                @click="openBatchApprovalModal"
                            />
                        </div>
                    </div>

                    <div v-if="pendingApprovals.length === 0" class="py-10 px-4 text-center space-y-3">
                        <div
                            class="size-14 rounded-2xl flex items-center justify-center mx-auto"
                            :class="canApproveOvertime ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-slate-100 text-slate-700 border border-slate-200'"
                        >
                            <i :class="canApproveOvertime ? 'pi pi-check-circle text-2xl' : 'pi pi-info-circle text-2xl'" aria-hidden="true" />
                        </div>
                        <div class="space-y-1 max-w-md mx-auto">
                            <p class="font-bold text-sm text-slate-900">
                                {{ canApproveOvertime ? 'Tidak Ada SPL yang Menunggu Persetujuan' : 'Tidak Ada Antrean Persetujuan untuk Akun Pelaksana' }}
                            </p>
                            <p class="text-xs text-slate-600">
                                {{ canApproveOvertime
                                    ? 'Semua permohonan lembur bawahan pada giliran ACC Anda telah selesai diproses.'
                                    : 'Wewenang persetujuan SPL berjenjang berada pada pejabat penilai struktural (Supervisor / SPV ke atas).'
                                }}
                            </p>
                        </div>
                    </div>

                    <div v-else-if="filteredPending.length === 0" class="py-10 px-4 text-center space-y-3">
                        <div class="size-12 rounded-2xl bg-slate-100 text-slate-600 flex items-center justify-center mx-auto">
                            <i class="pi pi-search text-xl" aria-hidden="true" />
                        </div>
                        <div class="space-y-1 max-w-md mx-auto">
                            <p class="font-bold text-sm text-slate-900">Tidak Ada SPL yang Cocok dengan Filter</p>
                            <p class="text-xs text-slate-600">
                                Ubah rentang tanggal atau kata kunci pencarian untuk menampilkan kembali antrean SPL.
                            </p>
                        </div>
                        <Button
                            type="button"
                            label="Reset Filter"
                            icon="pi pi-filter-slash"
                            severity="secondary"
                            outlined
                            size="small"
                            class="min-h-[44px] !text-slate-700"
                            @click="resetDateFilter"
                        />
                    </div>

                    <template v-else>
                        <div class="hidden lg:block">
                            <DataTable
                                v-model:first="pendingTableFirst"
                                v-model:rows="perPage"
                                :value="filteredPending"
                                dataKey="id"
                                paginator
                                :rowsPerPageOptions="[10, 25, 50]"
                                responsiveLayout="scroll"
                                striped-rows
                                class="text-sm approval-datatable"
                            >
                                <Column
                                    style="width: 2.75rem"
                                    class="text-center"
                                    :pt="{
                                        bodyCell: { class: '!py-2 !px-0.5' },
                                        headerCell: { class: '!py-2.5 !px-0.5' },
                                    }"
                                >
                                    <template #header>
                                        <label
                                            for="select-all-pending"
                                            class="inline-flex items-center justify-center size-11 cursor-pointer mx-auto"
                                            title="Pilih semua SPL yang dapat di-approve"
                                        >
                                            <span class="sr-only">Pilih semua SPL yang dapat di-approve</span>
                                            <input
                                                id="select-all-pending"
                                                name="select_all_pending"
                                                type="checkbox"
                                                :checked="isAllSelected"
                                                :indeterminate="isIndeterminate"
                                                :disabled="selectablePendingItems.length === 0"
                                                class="size-4 rounded border-slate-400 text-[#005BAC] focus:ring-[#005BAC] cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed"
                                                @change="toggleSelectAll"
                                            />
                                        </label>
                                    </template>
                                    <template #body="{ data }">
                                        <label
                                            :for="`select-spl-${data.id}`"
                                            class="inline-flex items-center justify-center size-11 cursor-pointer mx-auto"
                                            :title="!data.can_approve ? 'Bukan giliran persetujuan Anda' : `Pilih SPL ${data.code}`"
                                        >
                                            <span class="sr-only">
                                                {{ !data.can_approve ? `${data.code} bukan giliran persetujuan Anda` : `Pilih ${data.code} atas nama ${data.applicant_name}` }}
                                            </span>
                                            <input
                                                :id="`select-spl-${data.id}`"
                                                :name="`selected_spl_${data.id}`"
                                                type="checkbox"
                                                :value="data.id"
                                                v-model="selectedIds"
                                                :disabled="!data.can_approve"
                                                class="size-4 rounded border-slate-400 text-[#005BAC] focus:ring-[#005BAC] cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed"
                                            />
                                        </label>
                                    </template>
                                </Column>

                                <Column field="code" header="Kode SPL" sortable>
                                    <template #body="{ data }">
                                        <span class="font-mono font-bold text-[#005BAC] text-xs block whitespace-nowrap">
                                            {{ data.code }}
                                        </span>
                                        <span v-if="data.legacy_id" class="text-[11px] text-slate-600 font-mono block whitespace-nowrap">
                                            ID #{{ data.legacy_id }}
                                        </span>
                                    </template>
                                </Column>

                                <Column field="applicant_name" header="Pemohon" sortable>
                                    <template #body="{ data }">
                                        <div class="font-bold text-slate-900 text-xs leading-snug truncate max-w-[155px] 2xl:max-w-[200px]" :title="data.applicant_name">
                                            {{ data.applicant_name }}
                                        </div>
                                        <div class="text-[11px] text-slate-600 font-mono">
                                            {{ data.applicant_nip }}
                                        </div>
                                        <div class="text-[11px] text-slate-600 truncate max-w-[155px] 2xl:max-w-[200px]" :title="data.applicant_division">
                                            {{ data.applicant_division }}
                                        </div>
                                    </template>
                                </Column>

                                <Column field="raw_start_date" header="Jadwal" sortable>
                                    <template #body="{ data }">
                                        <div class="font-mono font-semibold text-slate-900 text-xs whitespace-nowrap">
                                            {{ data.start_date }}
                                        </div>
                                        <div class="text-[11px] text-slate-600 font-mono flex items-center gap-1 mt-0.5 whitespace-nowrap">
                                            <i class="pi pi-clock text-[11px] text-slate-600" aria-hidden="true" />
                                            <span>{{ data.start_time }} - {{ data.end_time }}</span>
                                        </div>
                                    </template>
                                </Column>

                                <Column field="type_label" header="Jenis &amp; Peserta" sortable>
                                    <template #body="{ data }">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-medium bg-slate-100 text-slate-800 border border-slate-200 whitespace-nowrap">
                                            {{ data.type_label }}
                                        </span>
                                        <div class="mt-1">
                                            <span class="inline-flex items-center gap-1 font-semibold text-slate-700 text-[11px] whitespace-nowrap">
                                                <i class="pi pi-users text-[11px] text-slate-600" aria-hidden="true" />
                                                <span>{{ data.participant_count }} Orang</span>
                                            </span>
                                        </div>
                                    </template>
                                </Column>

                                <Column field="approval_status" header="Status &amp; ACC" sortable>
                                    <template #body="{ data }">
                                        <Tag
                                            :value="data.status_label"
                                            :severity="getStatusSeverity(data.approval_status)"
                                            class="text-xs whitespace-nowrap"
                                        />
                                        <div class="mt-1">
                                            <span class="inline-flex items-center gap-1 text-[11px] font-bold px-2 py-0.5 rounded-md bg-amber-100 text-amber-900 border border-amber-300 whitespace-nowrap">
                                                <i class="pi pi-user-check text-[11px]" aria-hidden="true" />
                                                <span>{{ data.current_level }}</span>
                                            </span>
                                        </div>
                                    </template>
                                </Column>

                                <Column
                                    header="Aksi"
                                    style="text-align: right"
                                    :pt="{ columnHeaderContent: { class: 'justify-end' } }"
                                >
                                    <template #body="{ data }">
                                        <div class="flex items-center justify-end gap-1.5">
                                            <Button
                                                v-if="data.can_approve"
                                                type="button"
                                                label="Proses ACC"
                                                icon="pi pi-check-square"
                                                size="small"
                                                :aria-label="`Proses ACC untuk ${data.code} atas nama ${data.applicant_name}`"
                                                class="!bg-[#005BAC] hover:!bg-[#004280] !border-[#005BAC] !text-white min-h-[44px] !px-2 text-xs font-semibold whitespace-nowrap"
                                                @click="openApprovalModal(data)"
                                            />
                                            <Button
                                                type="button"
                                                label="Detail"
                                                icon="pi pi-eye"
                                                size="small"
                                                severity="secondary"
                                                outlined
                                                :aria-label="`Lihat detail ${data.code} atas nama ${data.applicant_name}`"
                                                class="min-h-[44px] !px-2 text-xs !text-slate-700 whitespace-nowrap"
                                                @click="openDetailModal(data)"
                                            />
                                        </div>
                                    </template>
                                </Column>
                            </DataTable>
                        </div>

                        <div class="lg:hidden space-y-3">
                            <div
                                v-if="selectablePendingItems.length > 0"
                                class="flex items-center justify-between gap-2 px-3 py-1.5 rounded-xl bg-slate-50 border border-slate-200"
                            >
                                <label
                                    for="mobile-select-all-pending"
                                    class="inline-flex items-center gap-2.5 min-h-[44px] text-xs font-semibold text-slate-800 cursor-pointer"
                                >
                                    <input
                                        id="mobile-select-all-pending"
                                        name="mobile_select_all_pending"
                                        type="checkbox"
                                        :checked="isAllSelected"
                                        :indeterminate="isIndeterminate"
                                        class="size-4 rounded border-slate-400 text-[#005BAC] focus:ring-[#005BAC] cursor-pointer"
                                        @change="toggleSelectAll"
                                    />
                                    <span>Pilih semua yang dapat di-ACC ({{ selectablePendingItems.length }})</span>
                                </label>
                                <span v-if="selectedIds.length > 0" class="text-xs font-bold text-[#005BAC]">
                                    {{ selectedIds.length }} dipilih
                                </span>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <article
                                    v-for="item in paginatedPendingCards"
                                    :key="item.id"
                                    class="p-4 rounded-xl border flex flex-col justify-between gap-3 transition-colors"
                                    :class="selectedIds.includes(item.id) ? 'border-[#005BAC] bg-blue-50/40' : 'border-slate-200 bg-slate-50/60'"
                                >
                                    <div class="space-y-2.5">
                                        <div class="flex items-start justify-between gap-2">
                                            <div class="flex items-start gap-2 min-w-0">
                                                <label
                                                    :for="`card-select-spl-${item.id}`"
                                                    class="inline-flex items-center justify-center size-11 -ml-2 -mt-2 cursor-pointer shrink-0"
                                                    :title="!item.can_approve ? 'Bukan giliran persetujuan Anda' : `Pilih SPL ${item.code}`"
                                                >
                                                    <span class="sr-only">
                                                        {{ !item.can_approve ? `${item.code} bukan giliran persetujuan Anda` : `Pilih ${item.code} atas nama ${item.applicant_name}` }}
                                                    </span>
                                                    <input
                                                        :id="`card-select-spl-${item.id}`"
                                                        :name="`card_selected_spl_${item.id}`"
                                                        type="checkbox"
                                                        :value="item.id"
                                                        v-model="selectedIds"
                                                        :disabled="!item.can_approve"
                                                        class="size-4 rounded border-slate-400 text-[#005BAC] focus:ring-[#005BAC] cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed"
                                                    />
                                                </label>
                                                <div class="min-w-0">
                                                    <div class="flex items-center gap-1.5 flex-wrap">
                                                        <h3 class="font-mono font-bold text-sm text-[#005BAC]">{{ item.code }}</h3>
                                                        <span v-if="item.legacy_id" class="font-mono text-xs text-slate-600">
                                                            ID #{{ item.legacy_id }}
                                                        </span>
                                                    </div>
                                                    <p class="text-sm font-bold text-slate-900 mt-0.5 leading-snug">
                                                        {{ item.applicant_name }}
                                                    </p>
                                                    <p class="text-xs text-slate-600 font-mono">
                                                        {{ item.applicant_nip }} &bull; {{ item.applicant_division }}
                                                    </p>
                                                </div>
                                            </div>
                                            <Tag
                                                :value="item.status_label"
                                                :severity="getStatusSeverity(item.approval_status)"
                                                class="text-xs shrink-0"
                                            />
                                        </div>

                                        <div class="space-y-1 text-xs text-slate-700 pt-1 border-t border-slate-200/70">
                                            <p>
                                                <span class="font-semibold text-slate-800">Jadwal:</span>
                                                <span class="font-mono ml-1">{{ item.start_date }}</span>
                                                <span class="mx-1.5 text-slate-600">&bull;</span>
                                                <span class="font-mono">{{ item.start_time }} - {{ item.end_time }}</span>
                                            </p>
                                            <div class="flex flex-wrap items-center gap-2 pt-0.5">
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-medium bg-white text-slate-800 border border-slate-200">
                                                    {{ item.type_label }}
                                                </span>
                                                <span class="inline-flex items-center gap-1 text-[11px] font-semibold text-slate-700">
                                                    <i class="pi pi-users text-[11px]" aria-hidden="true" />
                                                    <span>{{ item.participant_count }} Orang</span>
                                                </span>
                                                <span class="inline-flex items-center gap-1 text-[11px] font-bold px-2 py-0.5 rounded-md bg-amber-100 text-amber-900 border border-amber-300">
                                                    <i class="pi pi-user-check text-[11px]" aria-hidden="true" />
                                                    <span>{{ item.current_level }}</span>
                                                </span>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="pt-2 border-t border-slate-200/80 flex items-center justify-end gap-2 flex-wrap">
                                        <Button
                                            v-if="item.can_approve"
                                            type="button"
                                            label="Proses ACC"
                                            icon="pi pi-check-square"
                                            size="small"
                                            :aria-label="`Proses ACC untuk ${item.code} atas nama ${item.applicant_name}`"
                                            class="!bg-[#005BAC] hover:!bg-[#004280] !border-[#005BAC] !text-white min-h-[44px] text-xs font-semibold"
                                            @click="openApprovalModal(item)"
                                        />
                                        <Button
                                            type="button"
                                            label="Detail"
                                            icon="pi pi-eye"
                                            size="small"
                                            severity="secondary"
                                            outlined
                                            :aria-label="`Lihat detail ${item.code} atas nama ${item.applicant_name}`"
                                            class="min-h-[44px] text-xs !text-slate-700"
                                            @click="openDetailModal(item)"
                                        />
                                    </div>
                                </article>
                            </div>

                            <div
                                v-if="pendingResponsiveTotalPages > 1"
                                class="pt-2 flex items-center justify-between gap-2 border-t border-slate-200"
                            >
                                <Button
                                    type="button"
                                    label="Sebelumnya"
                                    icon="pi pi-chevron-left"
                                    size="small"
                                    severity="secondary"
                                    outlined
                                    :disabled="pendingResponsivePage <= 1"
                                    class="min-h-[44px] text-xs !text-slate-700"
                                    @click="pendingResponsivePage--"
                                />
                                <span class="text-xs font-mono text-slate-700">
                                    Hal {{ pendingResponsivePage }} / {{ pendingResponsiveTotalPages }}
                                </span>
                                <Button
                                    type="button"
                                    label="Berikutnya"
                                    icon="pi pi-chevron-right"
                                    iconPos="right"
                                    size="small"
                                    severity="secondary"
                                    outlined
                                    :disabled="pendingResponsivePage >= pendingResponsiveTotalPages"
                                    class="min-h-[44px] text-xs !text-slate-700"
                                    @click="pendingResponsivePage++"
                                />
                            </div>
                        </div>
                    </template>
                </div>

                <div
                    v-else
                    id="panel-history"
                    role="tabpanel"
                    aria-labelledby="tab-history"
                    class="space-y-4"
                >
                    <div v-if="historyApprovals.length === 0" class="py-10 px-4 text-center space-y-3">
                        <div class="size-12 rounded-2xl bg-slate-100 text-slate-600 flex items-center justify-center mx-auto">
                            <i class="pi pi-inbox text-xl" aria-hidden="true" />
                        </div>
                        <div class="space-y-1 max-w-md mx-auto">
                            <p class="font-bold text-sm text-slate-900">Belum Ada Riwayat Persetujuan</p>
                            <p class="text-xs text-slate-600">
                                Riwayat pengajuan lembur yang telah Anda setujui atau tolak akan ditampilkan di sini.
                            </p>
                        </div>
                    </div>

                    <div v-else-if="filteredHistory.length === 0" class="py-10 px-4 text-center space-y-3">
                        <div class="size-12 rounded-2xl bg-slate-100 text-slate-600 flex items-center justify-center mx-auto">
                            <i class="pi pi-search text-xl" aria-hidden="true" />
                        </div>
                        <div class="space-y-1 max-w-md mx-auto">
                            <p class="font-bold text-sm text-slate-900">Tidak Ada Riwayat yang Cocok dengan Filter</p>
                            <p class="text-xs text-slate-600">
                                Ubah rentang tanggal atau kata kunci pencarian untuk menampilkan kembali riwayat persetujuan.
                            </p>
                        </div>
                        <Button
                            type="button"
                            label="Reset Filter"
                            icon="pi pi-filter-slash"
                            severity="secondary"
                            outlined
                            size="small"
                            class="min-h-[44px] !text-slate-700"
                            @click="resetDateFilter"
                        />
                    </div>

                    <template v-else>
                        <div class="hidden lg:block">
                            <DataTable
                                v-model:first="historyTableFirst"
                                v-model:rows="perPage"
                                :value="filteredHistory"
                                dataKey="id"
                                paginator
                                :rowsPerPageOptions="[10, 25, 50]"
                                responsiveLayout="scroll"
                                striped-rows
                                class="text-sm approval-datatable"
                            >
                                <Column field="code" header="Kode SPL" sortable style="width: 6.25rem">
                                    <template #body="{ data }">
                                        <span class="font-mono font-bold text-[#005BAC] text-xs block whitespace-nowrap">
                                            {{ data.code }}
                                        </span>
                                        <span v-if="data.legacy_id" class="text-[11px] text-slate-600 font-mono block whitespace-nowrap">
                                            ID #{{ data.legacy_id }}
                                        </span>
                                    </template>
                                </Column>

                                <Column field="applicant_name" header="Pemohon" sortable style="min-width: 9.5rem">
                                    <template #body="{ data }">
                                        <div class="font-bold text-slate-900 text-xs leading-snug">
                                            {{ data.applicant_name }}
                                        </div>
                                        <div class="text-[11px] text-slate-600 font-mono">
                                            {{ data.applicant_nip }}
                                        </div>
                                        <div class="text-[11px] text-slate-600 truncate max-w-[160px]" :title="data.applicant_division">
                                            {{ data.applicant_division }}
                                        </div>
                                    </template>
                                </Column>

                                <Column field="raw_start_date" header="Waktu &amp; Jenis" sortable style="min-width: 8.5rem">
                                    <template #body="{ data }">
                                        <div class="font-mono font-semibold text-slate-900 text-xs whitespace-nowrap">
                                            {{ data.start_date }}
                                        </div>
                                        <div class="text-[11px] text-slate-600 font-mono whitespace-nowrap">
                                            {{ data.start_time }} - {{ data.end_time }}
                                        </div>
                                        <div class="mt-1 flex items-center gap-1.5 flex-wrap">
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-medium bg-slate-100 text-slate-800 border border-slate-200">
                                                {{ data.type_label }}
                                            </span>
                                            <span class="text-[11px] font-semibold text-slate-700">{{ data.participant_count }} Orang</span>
                                        </div>
                                    </template>
                                </Column>

                                <Column field="approval_status" header="Status Akhir" sortable style="width: 7.75rem">
                                    <template #body="{ data }">
                                        <Tag
                                            :value="data.status_label"
                                            :severity="getStatusSeverity(data.approval_status)"
                                            class="text-xs whitespace-nowrap"
                                        />
                                    </template>
                                </Column>

                                <Column header="Catatan" style="min-width: 9rem">
                                    <template #body="{ data }">
                                        <p class="text-xs text-slate-700 line-clamp-2 max-w-[190px]" :title="data.approval_notes || 'Belum ada catatan'">
                                            {{ data.approval_notes || '-' }}
                                        </p>
                                    </template>
                                </Column>

                                <Column
                                    header="Aksi"
                                    style="width: 6.5rem; text-align: right"
                                    :pt="{ columnHeaderContent: { class: 'justify-end' } }"
                                >
                                    <template #body="{ data }">
                                        <Button
                                            type="button"
                                            label="Detail"
                                            icon="pi pi-eye"
                                            size="small"
                                            severity="secondary"
                                            outlined
                                            :aria-label="`Lihat detail riwayat ${data.code} atas nama ${data.applicant_name}`"
                                            class="min-h-[44px] !px-2.5 text-xs !text-slate-700 whitespace-nowrap"
                                            @click="openDetailModal(data)"
                                        />
                                    </template>
                                </Column>
                            </DataTable>
                        </div>

                        <div class="lg:hidden space-y-3">
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <article
                                    v-for="item in paginatedHistoryCards"
                                    :key="item.id"
                                    class="p-4 rounded-xl border border-slate-200 bg-slate-50/60 flex flex-col justify-between gap-3"
                                >
                                    <div class="space-y-2">
                                        <div class="flex items-start justify-between gap-2">
                                            <div>
                                                <div class="flex items-center gap-1.5 flex-wrap">
                                                    <h3 class="font-mono font-bold text-sm text-[#005BAC]">{{ item.code }}</h3>
                                                    <span v-if="item.legacy_id" class="font-mono text-xs text-slate-600">
                                                        ID #{{ item.legacy_id }}
                                                    </span>
                                                </div>
                                                <p class="text-sm font-bold text-slate-900 mt-0.5 leading-snug">
                                                    {{ item.applicant_name }}
                                                </p>
                                                <p class="text-xs text-slate-600 font-mono">
                                                    {{ item.applicant_nip }} &bull; {{ item.applicant_division }}
                                                </p>
                                            </div>
                                            <Tag
                                                :value="item.status_label"
                                                :severity="getStatusSeverity(item.approval_status)"
                                                class="text-xs shrink-0"
                                            />
                                        </div>

                                        <div class="space-y-1 text-xs text-slate-700 pt-1 border-t border-slate-200/70">
                                            <p>
                                                <span class="font-semibold text-slate-800">Jadwal:</span>
                                                <span class="font-mono ml-1">{{ item.start_date }}</span>
                                                <span class="mx-1.5 text-slate-600">&bull;</span>
                                                <span class="font-mono">{{ item.start_time }} - {{ item.end_time }}</span>
                                            </p>
                                            <p class="text-slate-600">
                                                {{ item.type_label }} &bull; {{ item.participant_count }} Orang
                                            </p>
                                            <p v-if="item.approval_notes" class="text-slate-700 italic line-clamp-2">
                                                "{{ item.approval_notes }}"
                                            </p>
                                        </div>
                                    </div>

                                    <div class="pt-2 border-t border-slate-200/80 flex items-center justify-end">
                                        <Button
                                            type="button"
                                            label="Detail"
                                            icon="pi pi-eye"
                                            size="small"
                                            severity="secondary"
                                            outlined
                                            :aria-label="`Lihat detail riwayat ${item.code} atas nama ${item.applicant_name}`"
                                            class="min-h-[44px] text-xs !text-slate-700"
                                            @click="openDetailModal(item)"
                                        />
                                    </div>
                                </article>
                            </div>

                            <div
                                v-if="historyResponsiveTotalPages > 1"
                                class="pt-2 flex items-center justify-between gap-2 border-t border-slate-200"
                            >
                                <Button
                                    type="button"
                                    label="Sebelumnya"
                                    icon="pi pi-chevron-left"
                                    size="small"
                                    severity="secondary"
                                    outlined
                                    :disabled="historyResponsivePage <= 1"
                                    class="min-h-[44px] text-xs !text-slate-700"
                                    @click="historyResponsivePage--"
                                />
                                <span class="text-xs font-mono text-slate-700">
                                    Hal {{ historyResponsivePage }} / {{ historyResponsiveTotalPages }}
                                </span>
                                <Button
                                    type="button"
                                    label="Berikutnya"
                                    icon="pi pi-chevron-right"
                                    iconPos="right"
                                    size="small"
                                    severity="secondary"
                                    outlined
                                    :disabled="historyResponsivePage >= historyResponsiveTotalPages"
                                    class="min-h-[44px] text-xs !text-slate-700"
                                    @click="historyResponsivePage++"
                                />
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </section>

        <Dialog
            v-model:visible="approvalDialogVisible"
            modal
            header="Konfirmasi Persetujuan Surat Perintah Lembur"
            :style="{ width: '92vw', maxWidth: '560px' }"
        >
            <div v-if="selectedSpl" class="space-y-4 pt-1 text-xs sm:text-sm">
                <div class="p-3.5 rounded-xl bg-slate-50 border border-slate-200 space-y-2">
                    <div class="flex items-center justify-between border-b border-slate-200/80 pb-2">
                        <span class="font-mono font-bold text-[#005BAC] text-sm">
                            {{ selectedSpl.code }}
                        </span>
                        <span class="text-xs font-bold px-2.5 py-0.5 rounded-md bg-amber-100 text-amber-900 border border-amber-300">
                            Giliran: {{ selectedSpl.my_level || selectedSpl.current_level }}
                        </span>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                        <div>
                            <p class="text-slate-600 font-semibold">Pemohon:</p>
                            <p class="font-bold text-slate-900 mt-0.5">{{ selectedSpl.applicant_name }} ({{ selectedSpl.applicant_nip }})</p>
                            <p class="text-[11px] text-slate-600">{{ selectedSpl.applicant_division }}</p>
                        </div>
                        <div>
                            <p class="text-slate-600 font-semibold">Rencana Lembur:</p>
                            <p class="font-bold text-slate-900 mt-0.5">{{ selectedSpl.start_date }}</p>
                            <p class="text-slate-700 font-mono text-xs">{{ selectedSpl.start_time }} - {{ selectedSpl.end_time }} ({{ selectedSpl.participant_count }} orang)</p>
                        </div>
                    </div>
                </div>

                <fieldset class="border-0 p-0 m-0 space-y-2.5">
                    <legend class="font-bold text-slate-800 text-xs uppercase tracking-wider">
                        Keputusan Anda <span class="text-rose-700">*</span>
                    </legend>

                    <div
                        role="radiogroup"
                        aria-label="Pilihan keputusan persetujuan lembur"
                        class="grid grid-cols-1 sm:grid-cols-2 gap-3"
                    >
                        <button
                            ref="approveOptionRef"
                            type="button"
                            role="radio"
                            :aria-checked="String(approvalForm.decision === 'approve')"
                            class="p-3.5 min-h-[44px] rounded-xl border-2 cursor-pointer transition-colors flex flex-col items-center justify-center text-center gap-1.5"
                            :class="approvalForm.decision === 'approve'
                                ? 'border-emerald-700 bg-emerald-50 text-emerald-900 shadow-xs'
                                : 'border-slate-300 hover:border-slate-400 text-slate-700 bg-white'"
                            @click="approvalForm.decision = 'approve'"
                            @keydown="handleDecisionKeydown($event, 'approve')"
                        >
                            <div
                                class="size-8 rounded-full flex items-center justify-center"
                                :class="approvalForm.decision === 'approve' ? 'bg-emerald-700 text-white' : 'bg-slate-200 text-slate-700'"
                            >
                                <i class="pi pi-check text-xs font-bold" aria-hidden="true" />
                            </div>
                            <span class="font-bold text-xs sm:text-sm">Setujui Lembur</span>
                            <span class="text-[11px] text-slate-600 leading-tight">
                                Meneruskan ke tahap berikutnya atau ACC final.
                            </span>
                        </button>

                        <button
                            ref="rejectOptionRef"
                            type="button"
                            role="radio"
                            :aria-checked="String(approvalForm.decision === 'reject')"
                            class="p-3.5 min-h-[44px] rounded-xl border-2 cursor-pointer transition-colors flex flex-col items-center justify-center text-center gap-1.5"
                            :class="approvalForm.decision === 'reject'
                                ? 'border-rose-700 bg-rose-50 text-rose-900 shadow-xs'
                                : 'border-slate-300 hover:border-slate-400 text-slate-700 bg-white'"
                            @click="approvalForm.decision = 'reject'"
                            @keydown="handleDecisionKeydown($event, 'reject')"
                        >
                            <div
                                class="size-8 rounded-full flex items-center justify-center"
                                :class="approvalForm.decision === 'reject' ? 'bg-rose-700 text-white' : 'bg-slate-200 text-slate-700'"
                            >
                                <i class="pi pi-times text-xs font-bold" aria-hidden="true" />
                            </div>
                            <span class="font-bold text-xs sm:text-sm">Tolak Pengajuan</span>
                            <span class="text-[11px] text-slate-600 leading-tight">
                                Membatalkan rencana lembur peserta ini.
                            </span>
                        </button>
                    </div>
                </fieldset>

                <div class="space-y-1.5">
                    <div class="flex items-center justify-between gap-2">
                        <label for="approval-notes-input" class="font-bold text-slate-700 text-xs">
                            Catatan / Pesan Atasan
                            <span v-if="approvalForm.decision === 'reject'" class="text-rose-700 font-semibold">* (Wajib diisi jika menolak)</span>
                            <span v-else class="text-slate-600 font-normal"> (Opsional)</span>
                        </label>
                        <span class="text-xs font-mono text-slate-600">{{ approvalForm.notes.length }}/200</span>
                    </div>

                    <textarea
                        id="approval-notes-input"
                        v-model="approvalForm.notes"
                        name="approval_notes"
                        rows="3"
                        maxlength="200"
                        class="w-full border border-slate-300 rounded-xl px-3 py-2 text-xs sm:text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-[#005BAC]/30 focus:border-[#005BAC]"
                        :placeholder="approvalForm.decision === 'reject' ? 'Tuliskan alasan penolakan lembur ini...' : 'Tambahkan catatan arahan jika diperlukan...'"
                    />
                </div>
            </div>

            <template #footer>
                <div class="flex items-center justify-end gap-2.5 pt-3 border-t border-slate-200 w-full">
                    <Button
                        type="button"
                        label="Batal"
                        severity="secondary"
                        outlined
                        size="small"
                        :disabled="approvalForm.processing"
                        class="min-h-[44px] !text-slate-700"
                        @click="approvalDialogVisible = false"
                    />
                    <Button
                        type="button"
                        :label="approvalForm.decision === 'approve' ? 'Kirim Persetujuan' : 'Tolak Pengajuan SPL'"
                        :icon="approvalForm.decision === 'approve' ? 'pi pi-check' : 'pi pi-times'"
                        size="small"
                        :loading="approvalForm.processing"
                        class="min-h-[44px] font-semibold"
                        :class="approvalForm.decision === 'approve'
                            ? '!bg-[#005BAC] hover:!bg-[#004280] !border-[#005BAC] !text-white'
                            : '!bg-rose-700 hover:!bg-rose-800 !border-rose-700 !text-white'"
                        @click="submitApproval"
                    />
                </div>
            </template>
        </Dialog>

        <Dialog
            v-model:visible="detailDialogVisible"
            modal
            header="Detail Surat Perintah Lembur (SPL)"
            :style="{ width: '92vw', maxWidth: '640px' }"
        >
            <div v-if="detailLoading" role="status" aria-live="polite" class="py-12 text-center text-slate-600">
                <i class="pi pi-spin motion-reduce:animate-none pi-spinner text-3xl text-[#005BAC] mb-2 block" aria-hidden="true" />
                <p class="text-xs font-medium">Memuat rincian SPL...</p>
            </div>

            <div v-else-if="detailData" class="space-y-4 text-xs sm:text-sm pt-1">
                <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 bg-slate-50 p-4 rounded-xl border border-slate-200">
                    <div v-if="detailData.applicant_name" class="col-span-2 sm:col-span-3 pb-2 border-b border-slate-200/80">
                        <p class="text-[11px] text-slate-600 font-bold uppercase">Pegawai Pemohon</p>
                        <p class="font-bold text-slate-900 text-xs sm:text-sm mt-0.5">
                            {{ detailData.applicant_name }} <span class="font-mono text-slate-600 font-normal">({{ detailData.applicant_nip }})</span>
                        </p>
                        <p class="text-xs text-slate-600">{{ detailData.applicant_position }} &bull; {{ detailData.applicant_division }}</p>
                    </div>

                    <div>
                        <p class="text-[11px] text-slate-600 font-bold uppercase">Kode SPL</p>
                        <p class="font-mono font-bold text-[#005BAC] text-xs sm:text-sm mt-0.5">
                            {{ detailData.code }}
                            <span v-if="detailData.legacy_id" class="text-[11px] text-slate-600 font-normal">
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
                        <p class="text-[11px] text-slate-600 font-bold uppercase">Jam Rencana</p>
                        <p class="font-mono font-medium text-slate-900 mt-0.5">{{ detailData.start_time }} - {{ detailData.end_time }}</p>
                    </div>
                    <div>
                        <p class="text-[11px] text-slate-600 font-bold uppercase">Tgl Pengajuan</p>
                        <p class="font-mono font-medium text-slate-900 mt-0.5">{{ detailData.request_date }}</p>
                    </div>
                    <div>
                        <p class="text-[11px] text-slate-600 font-bold uppercase">Status Pengajuan</p>
                        <div class="mt-0.5">
                            <Tag :value="detailData.status_label" :severity="getStatusSeverity(detailData.approval_status)" class="text-xs" />
                        </div>
                    </div>
                </div>

                <div>
                    <div class="flex items-center justify-between mb-2">
                        <p class="text-xs font-bold uppercase tracking-wider text-slate-700">
                            Alur Persetujuan Atasan (ACC 1 - 3)
                        </p>
                        <span class="text-xs text-slate-600 font-medium">
                            {{ detailData.supervisors?.length ?? 0 }} Tingkat Approval
                        </span>
                    </div>

                    <div
                        v-if="detailData.supervisors && detailData.supervisors.length > 0"
                        class="grid gap-2.5"
                        :class="detailData.supervisors.length === 1 ? 'grid-cols-1' : (detailData.supervisors.length === 2 ? 'grid-cols-1 sm:grid-cols-2' : 'grid-cols-1 sm:grid-cols-3')"
                    >
                        <div
                            v-for="s in detailData.supervisors"
                            :key="s.level"
                            class="p-3 rounded-xl border bg-white shadow-2xs flex flex-col justify-between gap-2"
                            :class="s.status_label === 'Menunggu' ? 'border-amber-300 ring-2 ring-amber-100/70 bg-amber-50/20' : 'border-slate-200'"
                        >
                            <div class="flex items-center justify-between gap-1 pb-1.5 border-b border-slate-100">
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

                            <div v-if="s.approved_at" class="text-[11px] text-slate-600 pt-1 border-t border-slate-100 flex items-center gap-1">
                                <i class="pi pi-clock text-[11px]" aria-hidden="true" />
                                <span>{{ s.approved_at }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-if="detailData.approval_notes" class="bg-amber-50 border border-amber-200 rounded-xl p-3">
                    <p class="text-xs font-bold text-amber-800 uppercase">Catatan Atasan</p>
                    <p class="text-xs sm:text-sm text-amber-900 mt-0.5">{{ detailData.approval_notes }}</p>
                </div>

                <div>
                    <p class="text-xs font-bold uppercase tracking-wider text-slate-700 mb-2">
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
                                <tr v-for="(p, i) in detailData.participants" :key="i" class="hover:bg-slate-50/50">
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
                    size="small"
                    class="min-h-[44px] !text-slate-700"
                    @click="detailDialogVisible = false"
                />
            </template>
        </Dialog>

        <Dialog
            v-model:visible="batchApprovalDialogVisible"
            modal
            header="Konfirmasi Persetujuan Massal (Batch Approval)"
            :style="{ width: '92vw', maxWidth: '600px' }"
        >
            <div class="space-y-4 pt-1 text-xs sm:text-sm">
                <div class="p-4 rounded-xl bg-blue-50 border border-blue-200 flex items-start gap-3">
                    <i class="pi pi-info-circle text-[#005BAC] text-lg mt-0.5 shrink-0" aria-hidden="true" />
                    <div class="space-y-1">
                        <p class="font-bold text-slate-900 text-xs sm:text-sm">
                            Anda akan menyetujui {{ selectedIds.length }} Surat Perintah Lembur secara bersamaan.
                        </p>
                        <p class="text-xs text-slate-700 leading-relaxed">
                            Total melibatkan <strong class="text-slate-900">{{ selectedParticipantsTotal }} orang pegawai peserta</strong> lembur. Setiap berkas akan otomatis dimutasi sesuai alur hierarki struktural KFPB (ACC 1 &rarr; ACC 2 &rarr; ACC 3 &rarr; Disetujui Final).
                        </p>
                    </div>
                </div>

                <div>
                    <p class="text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">
                        Daftar Berkas Terpilih ({{ selectedSplList.length }} SPL)
                    </p>
                    <div class="max-h-48 overflow-y-auto custom-scrollbar border border-slate-200 rounded-xl divide-y divide-slate-100 bg-slate-50/40">
                        <div
                            v-for="spl in selectedSplList"
                            :key="spl.id"
                            class="p-2.5 flex items-center justify-between text-xs hover:bg-white transition-colors"
                        >
                            <div class="min-w-0 pr-2">
                                <span class="font-mono font-bold text-[#005BAC] block">{{ spl.code }}</span>
                                <span class="text-slate-900 font-medium truncate block">{{ spl.applicant_name }}</span>
                                <span class="text-[11px] text-slate-600 block">{{ spl.applicant_division }} &bull; {{ spl.start_date }} ({{ spl.start_time }} - {{ spl.end_time }})</span>
                            </div>
                            <div class="text-right shrink-0">
                                <span class="text-[11px] font-bold px-2 py-0.5 rounded-md bg-amber-100 text-amber-900 border border-amber-300 block mb-1">
                                    {{ spl.my_level || spl.current_level }}
                                </span>
                                <span class="text-[11px] text-slate-600 font-mono">{{ spl.participant_count }} Peserta</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="space-y-1.5">
                    <div class="flex items-center justify-between gap-2">
                        <label for="batch-notes-input" class="font-bold text-slate-700 text-xs">
                            Catatan Persetujuan Massal <span class="text-slate-600 font-normal">(Opsional)</span>
                        </label>
                        <span class="text-xs font-mono text-slate-600">{{ batchForm.notes.length }}/200</span>
                    </div>
                    <textarea
                        id="batch-notes-input"
                        v-model="batchForm.notes"
                        name="batch_notes"
                        rows="2"
                        maxlength="200"
                        class="w-full border border-slate-300 rounded-xl px-3 py-2 text-xs sm:text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-[#005BAC]/30 focus:border-[#005BAC]"
                        placeholder="Contoh: Disetujui untuk pemenuhan batch produksi obat..."
                    />
                </div>
            </div>

            <template #footer>
                <div class="flex items-center justify-end gap-2.5 pt-3 border-t border-slate-200 w-full">
                    <Button
                        type="button"
                        label="Batal"
                        severity="secondary"
                        outlined
                        size="small"
                        :disabled="batchForm.processing"
                        class="min-h-[44px] !text-slate-700"
                        @click="batchApprovalDialogVisible = false"
                    />
                    <Button
                        type="button"
                        :label="`Ya, Setujui ${selectedIds.length} SPL`"
                        icon="pi pi-check"
                        size="small"
                        :loading="batchForm.processing"
                        class="!bg-[#005BAC] hover:!bg-[#004280] !border-[#005BAC] !text-white min-h-[44px] font-semibold"
                        @click="submitBatchApproval"
                    />
                </div>
            </template>
        </Dialog>
    </div>
</template>

<style scoped>
:deep(.approval-datatable) {
    --p-datatable-header-cell-padding: 0.625rem 0.45rem;
    --p-datatable-body-cell-padding: 0.5rem 0.45rem;
}

:deep(.approval-datatable .p-datatable-header-cell) {
    padding: 0.625rem 0.45rem;
}

:deep(.approval-datatable .p-datatable-body-cell) {
    padding: 0.5rem 0.45rem;
}

:deep(.p-paginator .p-paginator-page.p-paginator-page-selected) {
    background: #e6f0f8;
    color: #005BAC;
    font-weight: 700;
}
</style>
