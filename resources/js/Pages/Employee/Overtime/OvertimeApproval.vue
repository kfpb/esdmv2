<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import axios from 'axios';
import Toast from 'primevue/toast';
import { useToast } from 'primevue/usetoast';
import Button from 'primevue/button';
import Tag from 'primevue/tag';
import Dialog from 'primevue/dialog';

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
});

const toast = useToast();

const activeTab = ref('pending');

const searchQuery = ref('');
const filterStartDate = ref('');
const filterEndDate = ref('');

const isFilterActive = computed(() => {
    return !!(searchQuery.value.trim() || filterStartDate.value || filterEndDate.value);
});

function resetDateFilter() {
    searchQuery.value = '';
    filterStartDate.value = '';
    filterEndDate.value = '';
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
    const m = String(today.getMonth() + 1).padStart(2, '0');
    const d = String(today.getDate()).padStart(2, '0');
    const todayStr = `${y}-${m}-${d}`;

    if (period === 'today') {
        filterStartDate.value = todayStr;
        filterEndDate.value = todayStr;
    } else if (period === '7days') {
        const past7 = new Date();
        past7.setDate(past7.getDate() - 7);
        const y7 = past7.getFullYear();
        const m7 = String(past7.getMonth() + 1).padStart(2, '0');
        const d7 = String(past7.getDate()).padStart(2, '0');
        filterStartDate.value = `${y7}-${m7}-${d7}`;
        filterEndDate.value = todayStr;
    } else if (period === 'thisMonth') {
        const lastDay = new Date(y, today.getMonth() + 1, 0).getDate();
        filterStartDate.value = `${y}-${m}-01`;
        filterEndDate.value = `${y}-${m}-${String(lastDay).padStart(2, '0')}`;
    } else if (period === 'all') {
        filterStartDate.value = '';
        filterEndDate.value = '';
    }
}

const filteredPending = computed(() => {
    const q = searchQuery.value.toLowerCase().trim();
    const start = filterStartDate.value;
    const end = filterEndDate.value;

    return props.pendingApprovals.filter(item => {
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

    return props.historyApprovals.filter(item => {
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

function getStatusSeverity(status) {
    switch (status) {
        case 1: return 'success';
        case 2: return 'danger';
        case 3:
        case 4:
        case 5: return 'warn';
        default: return 'secondary';
    }
}

const approvalDialogVisible = ref(false);
const selectedSpl = ref(null);

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

    approvalForm.post(`/lembur/${selectedSpl.value.id}/approval`, {
        preserveScroll: true,
        onSuccess: () => {
            approvalDialogVisible.value = false;
            toast.add({
                severity: 'success',
                summary: 'Berhasil Diproses',
                detail: `Keputusan untuk ${selectedSpl.value.code} berhasil disimpan.`,
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

// Hanya SPL yang berada pada giliran atasan yang login yang dapat dipilih
const selectablePendingItems = computed(() => {
    return filteredPending.value.filter(item => item.can_approve);
});

const isAllSelected = computed(() => {
    return selectablePendingItems.value.length > 0 &&
        selectablePendingItems.value.every(item => selectedIds.value.includes(item.id));
});

const isIndeterminate = computed(() => {
    const selectedCountInSelectable = selectablePendingItems.value.filter(item => selectedIds.value.includes(item.id)).length;
    return selectedCountInSelectable > 0 && selectedCountInSelectable < selectablePendingItems.value.length;
});

function toggleSelectAll(event) {
    if (event.target.checked) {
        const currentIds = new Set(selectedIds.value);
        selectablePendingItems.value.forEach(item => currentIds.add(item.id));
        selectedIds.value = Array.from(currentIds);
    } else {
        const selectableIdSet = new Set(selectablePendingItems.value.map(i => i.id));
        selectedIds.value = selectedIds.value.filter(id => !selectableIdSet.has(id));
    }
}

const selectedSplList = computed(() => {
    return props.pendingApprovals.filter(i => selectedIds.value.includes(i.id));
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

    <div class="space-y-6 max-w-7xl mx-auto px-3 sm:px-6 py-2">
        <Toast position="top-right" />
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white p-5 rounded-2xl border border-slate-200/80 shadow-xs">
            <div class="space-y-1">
                <div class="flex items-center gap-2 text-xs font-medium text-slate-500">
                    <Link href="/absensi" class="hover:text-[#005BAC] flex items-center gap-1 transition-colors">
                        <i class="pi pi-calendar-check text-[11px]" />
                        <span>Presensi</span>
                    </Link>
                    <span>/</span>
                    <span class="text-slate-800 font-semibold">Persetujuan Lembur (Atasan)</span>
                </div>
                <h1 class="text-xl sm:text-2xl font-bold text-slate-900 tracking-tight flex items-center gap-2.5">
                    <span>Pusat Persetujuan Surat Perintah Lembur</span>
                    <span
                        v-if="isAdmin"
                        class="text-[10px] uppercase font-bold tracking-wider px-2 py-0.5 rounded-full bg-blue-100 text-[#005BAC] border border-blue-200"
                    >
                        Mode Administrator
                    </span>
                </h1>
                <p class="text-xs sm:text-sm text-slate-500 max-w-3xl">
                    Tinjau, setujui, atau tolak permohonan Surat Perintah Lembur (SPL) bawahan secara berjenjang (ACC 1 – 3) sesuai otoritas struktural PT Kimia Farma Plant Banjaran.
                </p>
            </div>
            <div class="flex items-center gap-3 shrink-0">
                <div class="px-4 py-3 rounded-xl border flex items-center gap-3 bg-slate-50/70 border-slate-200">
                    <div
                        class="size-10 rounded-lg flex items-center justify-center shrink-0"
                        :class="pendingApprovals.length > 0 ? 'bg-amber-100 text-amber-700' : 'bg-emerald-100 text-emerald-700'"
                    >
                        <i :class="pendingApprovals.length > 0 ? 'pi pi-clock text-lg' : 'pi pi-check-circle text-lg'" />
                    </div>
                    <div>
                        <p class="text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Perlu Persetujuan</p>
                        <p class="text-lg font-bold text-slate-900 leading-tight">
                            {{ pendingApprovals.length }} <span class="text-xs font-normal text-slate-500">SPL</span>
                        </p>
                    </div>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs p-4 sm:p-5 space-y-4">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-slate-100 pb-3">
                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        class="px-4 py-2 rounded-xl text-xs sm:text-sm font-semibold transition-all flex items-center gap-2 cursor-pointer"
                        :class="activeTab === 'pending'
                            ? 'bg-[#005BAC] text-white shadow-xs'
                            : 'bg-slate-100/80 text-slate-600 hover:bg-slate-200/70 hover:text-slate-900'"
                        @click="activeTab = 'pending'"
                    >
                        <i class="pi pi-hourglass text-xs" />
                        <span>Perlu Persetujuan</span>
                        <span
                            v-if="pendingApprovals.length > 0"
                            class="text-[10px] font-bold px-1.5 py-0.5 rounded-full"
                            :class="activeTab === 'pending' ? 'bg-white text-[#005BAC]' : 'bg-amber-100 text-amber-800'"
                        >
                            {{ pendingApprovals.length }}
                        </span>
                    </button>

                    <button
                        type="button"
                        class="px-4 py-2 rounded-xl text-xs sm:text-sm font-semibold transition-all flex items-center gap-2 cursor-pointer"
                        :class="activeTab === 'history'
                            ? 'bg-[#005BAC] text-white shadow-xs'
                            : 'bg-slate-100/80 text-slate-600 hover:bg-slate-200/70 hover:text-slate-900'"
                        @click="activeTab = 'history'"
                    >
                        <i class="pi pi-history text-xs" />
                        <span>Riwayat Persetujuan</span>
                        <span
                            class="text-[10px] font-bold px-1.5 py-0.5 rounded-full"
                            :class="activeTab === 'history' ? 'bg-white text-[#005BAC]' : 'bg-slate-200 text-slate-700'"
                        >
                            {{ historyApprovals.length }}
                        </span>
                    </button>
                </div>
                <div v-if="isFilterActive" class="flex items-center gap-2">
                    <button
                        type="button"
                        class="text-xs text-rose-600 hover:text-rose-700 font-semibold flex items-center gap-1.5 px-3 py-1.5 rounded-lg hover:bg-rose-50 transition-colors cursor-pointer border border-rose-200"
                        @click="resetDateFilter"
                    >
                        <i class="pi pi-refresh text-[11px]" />
                        <span>Reset Semua Filter</span>
                    </button>
                </div>
            </div>
            <div class="bg-slate-50/80 p-3.5 sm:p-4 rounded-xl border border-slate-200/90 space-y-3">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2.5">
                    <div class="flex items-center gap-1.5 flex-wrap">
                        <span class="text-[11px] font-bold text-slate-500 uppercase tracking-wider mr-1 flex items-center gap-1">
                            <i class="pi pi-calendar text-[10px]" />
                            <span>Periode:</span>
                        </span>
                        <button
                            type="button"
                            class="px-2.5 py-1 rounded-lg text-xs font-semibold transition-all cursor-pointer"
                            :class="quickFilterActive === 'all'
                                ? 'bg-[#005BAC] text-white shadow-2xs'
                                : 'bg-white text-slate-600 border border-slate-200 hover:bg-slate-100'"
                            @click="setQuickFilter('all')"
                        >
                            Semua Tanggal
                        </button>
                        <button
                            type="button"
                            class="px-2.5 py-1 rounded-lg text-xs font-semibold transition-all cursor-pointer"
                            :class="quickFilterActive === 'today'
                                ? 'bg-[#005BAC] text-white shadow-2xs'
                                : 'bg-white text-slate-600 border border-slate-200 hover:bg-slate-100'"
                            @click="setQuickFilter('today')"
                        >
                            Hari Ini
                        </button>
                        <button
                            type="button"
                            class="px-2.5 py-1 rounded-lg text-xs font-semibold transition-all cursor-pointer"
                            :class="quickFilterActive === '7days'
                                ? 'bg-[#005BAC] text-white shadow-2xs'
                                : 'bg-white text-slate-600 border border-slate-200 hover:bg-slate-100'"
                            @click="setQuickFilter('7days')"
                        >
                            7 Hari Terakhir
                        </button>
                        <button
                            type="button"
                            class="px-2.5 py-1 rounded-lg text-xs font-semibold transition-all cursor-pointer"
                            :class="quickFilterActive === 'thisMonth'
                                ? 'bg-[#005BAC] text-white shadow-2xs'
                                : 'bg-white text-slate-600 border border-slate-200 hover:bg-slate-100'"
                            @click="setQuickFilter('thisMonth')"
                        >
                            Bulan Ini
                        </button>
                    </div>
                    <div class="text-[11px] text-slate-500 font-medium">
                        Menampilkan <strong class="text-slate-800">{{ activeTab === 'pending' ? filteredPending.length : filteredHistory.length }}</strong> pengajuan
                    </div>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-2.5 pt-2 border-t border-slate-200/70">
                    <div>
                        <label class="block text-[11px] font-bold text-slate-600 mb-1 flex items-center gap-1">
                            <i class="pi pi-calendar-plus text-[10px] text-slate-400" />
                            <span>Tanggal Lembur Dari</span>
                        </label>
                        <input
                            v-model="filterStartDate"
                            type="date"
                            class="w-full px-3 py-1.5 rounded-lg border border-slate-300 text-xs sm:text-sm text-slate-800 bg-white focus:outline-none focus:ring-2 focus:ring-[#005BAC]/30 focus:border-[#005BAC] transition-all"
                        />
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-slate-600 mb-1 flex items-center gap-1">
                            <i class="pi pi-calendar-minus text-[10px] text-slate-400" />
                            <span>Tanggal Lembur Sampai</span>
                        </label>
                        <input
                            v-model="filterEndDate"
                            type="date"
                            class="w-full px-3 py-1.5 rounded-lg border border-slate-300 text-xs sm:text-sm text-slate-800 bg-white focus:outline-none focus:ring-2 focus:ring-[#005BAC]/30 focus:border-[#005BAC] transition-all"
                        />
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-[11px] font-bold text-slate-600 mb-1 flex items-center gap-1">
                            <i class="pi pi-search text-[10px] text-slate-400" />
                            <span>Pencarian Kata Kunci</span>
                        </label>
                        <div class="relative">
                            <i class="pi pi-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs" />
                            <input
                                v-model="searchQuery"
                                type="text"
                                placeholder="Cari kode SPL, nama pemohon, NIP, divisi..."
                                class="w-full pl-8 pr-3 py-1.5 rounded-lg border border-slate-300 text-xs sm:text-sm text-slate-800 bg-white focus:outline-none focus:ring-2 focus:ring-[#005BAC]/30 focus:border-[#005BAC] transition-all"
                            />
                        </div>
                    </div>
                </div>
            </div>
            <div v-if="activeTab === 'pending'" class="space-y-4">
                <div
                    v-if="selectedIds.length > 0"
                    class="p-3.5 sm:p-4 rounded-xl bg-gradient-to-r from-blue-50 to-indigo-50 border border-blue-200 flex flex-col sm:flex-row sm:items-center justify-between gap-3 shadow-xs"
                >
                    <div class="flex items-center gap-3">
                        <div class="size-9 rounded-lg bg-[#005BAC] text-white flex items-center justify-center shrink-0">
                            <i class="pi pi-check-circle text-base" />
                        </div>
                        <div>
                            <p class="font-bold text-xs sm:text-sm text-slate-900">
                                {{ selectedIds.length }} Surat Perintah Lembur Terpilih
                            </p>
                            <p class="text-[11px] text-slate-500">
                                Total melibatkan <strong class="text-slate-800">{{ selectedParticipantsTotal }} orang pegawai peserta</strong> lembur.
                            </p>
                        </div>
                    </div>

                    <div class="flex items-center gap-2 shrink-0">
                        <button
                            type="button"
                            class="text-xs text-slate-500 hover:text-slate-700 font-medium px-2.5 py-1.5 cursor-pointer"
                            @click="selectedIds = []"
                        >
                            Batal Pilihan
                        </button>
                        <Button
                            :label="`Setujui ${selectedIds.length} SPL Terpilih`"
                            icon="pi pi-check"
                            size="small"
                            class="!text-xs !py-1.5 !px-3 bg-[#005BAC] hover:bg-[#004280]"
                            @click="openBatchApprovalModal"
                        />
                    </div>
                </div>

                <div v-if="filteredPending.length === 0" class="py-12 text-center text-slate-400">
                    <div class="size-16 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center mx-auto mb-3">
                        <i class="pi pi-check-circle text-2xl" />
                    </div>
                    <p class="font-bold text-sm text-slate-700">Tidak Ada SPL yang Menunggu Persetujuan</p>
                    <p class="text-xs text-slate-400 mt-1 max-w-md mx-auto">
                        Semua permohonan lembur bawahan pada giliran ACC Anda telah selesai diproses.
                    </p>
                </div>

                <div v-else class="overflow-x-auto rounded-xl border border-slate-200/90">
                    <table class="w-full text-left text-xs border-collapse">
                        <thead class="bg-slate-50/90 text-slate-700 font-bold border-b border-slate-200 text-[11px] uppercase tracking-wider">
                            <tr>
                                <th class="px-3 py-3 w-10 text-center">
                                    <input
                                        type="checkbox"
                                        :checked="isAllSelected"
                                        :indeterminate="isIndeterminate"
                                        class="rounded border-slate-300 text-[#005BAC] focus:ring-[#005BAC] size-4 cursor-pointer"
                                        title="Pilih Semua yang dapat di-approve"
                                        @change="toggleSelectAll"
                                    />
                                </th>
                                <th class="px-4 py-3">Kode SPL</th>
                                <th class="px-4 py-3">Pemohon</th>
                                <th class="px-4 py-3">Waktu Pelaksanaan</th>
                                <th class="px-4 py-3">Jenis Lembur</th>
                                <th class="px-4 py-3 text-center">Peserta</th>
                                <th class="px-4 py-3">Tahap ACC</th>
                                <th class="px-4 py-3">Status</th>
                                <th class="px-4 py-3 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr
                                v-for="item in filteredPending"
                                :key="item.id"
                                class="hover:bg-blue-50/30 transition-colors"
                                :class="selectedIds.includes(item.id) ? 'bg-blue-50/40' : ''"
                            >
                                <td class="px-3 py-3.5 text-center">
                                    <input
                                        type="checkbox"
                                        :value="item.id"
                                        v-model="selectedIds"
                                        :disabled="!item.can_approve"
                                        class="rounded border-slate-300 text-[#005BAC] focus:ring-[#005BAC] size-4 cursor-pointer disabled:opacity-30 disabled:cursor-not-allowed"
                                        :title="!item.can_approve ? 'Bukan giliran persetujuan Anda' : 'Pilih SPL ini'"
                                    />
                                </td>
                                <td class="px-4 py-3.5 whitespace-nowrap">
                                    <div class="font-mono font-bold text-[#005BAC] text-xs">
                                        {{ item.code }}
                                    </div>
                                    <span v-if="item.legacy_id" class="text-[10px] text-slate-400 font-mono block">
                                        ID #{{ item.legacy_id }}
                                    </span>
                                </td>
                                <td class="px-4 py-3.5">
                                    <div class="font-bold text-slate-900 leading-tight">
                                        {{ item.applicant_name }}
                                    </div>
                                    <div class="text-[11px] text-slate-500 font-mono">
                                        {{ item.applicant_nip }}
                                    </div>
                                    <div class="text-[10px] text-slate-400 truncate max-w-[180px]">
                                        {{ item.applicant_division }}
                                    </div>
                                </td>
                                <td class="px-4 py-3.5 whitespace-nowrap">
                                    <div class="font-semibold text-slate-800">
                                        {{ item.start_date }}
                                    </div>
                                    <div class="text-[11px] text-slate-500 font-mono flex items-center gap-1 mt-0.5">
                                        <i class="pi pi-clock text-[10px] text-slate-400" />
                                        <span>{{ item.start_time }} – {{ item.end_time }}</span>
                                    </div>
                                </td>
                                <td class="px-4 py-3.5 whitespace-nowrap">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-medium bg-slate-100 text-slate-700 border border-slate-200">
                                        {{ item.type_label }}
                                    </span>
                                </td>
                                <td class="px-4 py-3.5 text-center whitespace-nowrap">
                                    <span class="inline-flex items-center gap-1 font-semibold text-slate-800 bg-slate-100 px-2 py-0.5 rounded-full text-[11px]">
                                        <i class="pi pi-users text-[10px] text-slate-500" />
                                        {{ item.participant_count }} Orang
                                    </span>
                                </td>
                                <td class="px-4 py-3.5 whitespace-nowrap">
                                    <span
                                        class="inline-flex items-center gap-1 text-[11px] font-bold px-2 py-0.5 rounded-md bg-amber-100 text-amber-900 border border-amber-200"
                                    >
                                        <i class="pi pi-user-check text-[10px]" />
                                        {{ item.current_level }}
                                    </span>
                                </td>
                                <td class="px-4 py-3.5 whitespace-nowrap">
                                    <Tag
                                        :value="item.status_label"
                                        :severity="getStatusSeverity(item.approval_status)"
                                        class="text-[10px]"
                                    />
                                </td>
                                <td class="px-4 py-3.5 text-right whitespace-nowrap">
                                    <div class="flex items-center justify-end gap-1.5">
                                        <Button
                                            v-if="item.can_approve"
                                            label="Proses ACC"
                                            icon="pi pi-check-square"
                                            size="small"
                                            class="!text-xs !py-1.5 !px-2.5 bg-[#005BAC] hover:bg-[#004280]"
                                            @click="openApprovalModal(item)"
                                        />
                                        <Button
                                            icon="pi pi-eye"
                                            size="small"
                                            severity="secondary"
                                            outlined
                                            class="!text-xs !p-1.5"
                                            title="Lihat Detail SPL"
                                            @click="openDetailModal(item)"
                                        />
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div v-if="activeTab === 'history'" class="space-y-4">
                <div v-if="filteredHistory.length === 0" class="py-12 text-center text-slate-400">
                    <i class="pi pi-inbox text-3xl text-slate-300 mb-2 block" />
                    <p class="font-bold text-sm text-slate-700">Belum Ada Riwayat Persetujuan</p>
                    <p class="text-xs text-slate-400 mt-1">
                        Riwayat pengajuan lembur yang telah Anda setujui atau tolak akan ditampilkan di sini.
                    </p>
                </div>

                <div v-else class="overflow-x-auto rounded-xl border border-slate-200/90">
                    <table class="w-full text-left text-xs border-collapse">
                        <thead class="bg-slate-50/90 text-slate-700 font-bold border-b border-slate-200 text-[11px] uppercase tracking-wider">
                            <tr>
                                <th class="px-4 py-3">Kode SPL</th>
                                <th class="px-4 py-3">Pemohon</th>
                                <th class="px-4 py-3">Waktu Pelaksanaan</th>
                                <th class="px-4 py-3">Jenis Lembur</th>
                                <th class="px-4 py-3 text-center">Peserta</th>
                                <th class="px-4 py-3">Status Akhir</th>
                                <th class="px-4 py-3">Catatan</th>
                                <th class="px-4 py-3 text-right">Detail</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr
                                v-for="item in filteredHistory"
                                :key="item.id"
                                class="hover:bg-slate-50/60 transition-colors"
                            >
                                <td class="px-4 py-3.5 whitespace-nowrap">
                                    <div class="font-mono font-bold text-[#005BAC] text-xs">
                                        {{ item.code }}
                                    </div>
                                    <span v-if="item.legacy_id" class="text-[10px] text-slate-400 font-mono block">
                                        ID #{{ item.legacy_id }}
                                    </span>
                                </td>

                                <td class="px-4 py-3.5">
                                    <div class="font-bold text-slate-900 leading-tight">
                                        {{ item.applicant_name }}
                                    </div>
                                    <div class="text-[11px] text-slate-500 font-mono">
                                        {{ item.applicant_nip }}
                                    </div>
                                </td>

                                <td class="px-4 py-3.5 whitespace-nowrap">
                                    <div class="font-semibold text-slate-800">
                                        {{ item.start_date }}
                                    </div>
                                    <div class="text-[11px] text-slate-500 font-mono">
                                        {{ item.start_time }} – {{ item.end_time }}
                                    </div>
                                </td>

                                <td class="px-4 py-3.5 whitespace-nowrap">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-medium bg-slate-100 text-slate-700 border border-slate-200">
                                        {{ item.type_label }}
                                    </span>
                                </td>

                                <td class="px-4 py-3.5 text-center whitespace-nowrap">
                                    <span class="inline-flex items-center gap-1 font-semibold text-slate-700 bg-slate-100 px-2 py-0.5 rounded-full text-[11px]">
                                        {{ item.participant_count }} Orang
                                    </span>
                                </td>

                                <td class="px-4 py-3.5 whitespace-nowrap">
                                    <Tag
                                        :value="item.status_label"
                                        :severity="getStatusSeverity(item.approval_status)"
                                        class="text-[10px]"
                                    />
                                </td>

                                <td class="px-4 py-3.5 max-w-[200px]">
                                    <p class="text-[11px] text-slate-600 truncate" :title="item.approval_notes">
                                        {{ item.approval_notes || '—' }}
                                    </p>
                                </td>

                                <td class="px-4 py-3.5 text-right whitespace-nowrap">
                                    <Button
                                        icon="pi pi-eye"
                                        label="Detail"
                                        size="small"
                                        severity="secondary"
                                        outlined
                                        class="!text-xs !py-1 !px-2.5"
                                        @click="openDetailModal(item)"
                                    />
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
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
                        <span class="text-[11px] font-bold px-2 py-0.5 rounded-md bg-amber-100 text-amber-900 border border-amber-200">
                            Giliran: {{ selectedSpl.my_level || selectedSpl.current_level }}
                        </span>
                    </div>

                    <div class="grid grid-cols-2 gap-2 text-xs">
                        <div>
                            <p class="text-slate-400 font-medium">Pemohon:</p>
                            <p class="font-bold text-slate-800">{{ selectedSpl.applicant_name }} ({{ selectedSpl.applicant_nip }})</p>
                            <p class="text-[10px] text-slate-500">{{ selectedSpl.applicant_division }}</p>
                        </div>
                        <div>
                            <p class="text-slate-400 font-medium">Rencana Lembur:</p>
                            <p class="font-bold text-slate-800">{{ selectedSpl.start_date }}</p>
                            <p class="text-slate-600 font-mono text-[11px]">{{ selectedSpl.start_time }} – {{ selectedSpl.end_time }} ({{ selectedSpl.participant_count }} orang)</p>
                        </div>
                    </div>
                </div>
                <div class="space-y-2">
                    <label class="font-bold text-slate-800 text-xs uppercase tracking-wider block">
                        Keputusan Anda <span class="text-rose-500">*</span>
                    </label>

                    <div class="grid grid-cols-2 gap-3">
                        <div
                            class="p-3.5 rounded-xl border-2 cursor-pointer transition-all flex flex-col items-center justify-center text-center gap-1.5"
                            :class="approvalForm.decision === 'approve'
                                ? 'border-emerald-600 bg-emerald-50/70 text-emerald-900 shadow-xs'
                                : 'border-slate-200 hover:border-slate-300 text-slate-600 bg-white'"
                            @click="approvalForm.decision = 'approve'"
                        >
                            <div
                                class="size-8 rounded-full flex items-center justify-center"
                                :class="approvalForm.decision === 'approve' ? 'bg-emerald-600 text-white' : 'bg-slate-100 text-slate-400'"
                            >
                                <i class="pi pi-check text-xs font-bold" />
                            </div>
                            <span class="font-bold text-xs sm:text-sm">Setujui Lembur</span>
                            <span class="text-[10px] text-slate-500 leading-tight">
                                Meneruskan ke tahap berikutnya atau ACC final.
                            </span>
                        </div>
                        <div
                            class="p-3.5 rounded-xl border-2 cursor-pointer transition-all flex flex-col items-center justify-center text-center gap-1.5"
                            :class="approvalForm.decision === 'reject'
                                ? 'border-rose-600 bg-rose-50/70 text-rose-900 shadow-xs'
                                : 'border-slate-200 hover:border-slate-300 text-slate-600 bg-white'"
                            @click="approvalForm.decision = 'reject'"
                        >
                            <div
                                class="size-8 rounded-full flex items-center justify-center"
                                :class="approvalForm.decision === 'reject' ? 'bg-rose-600 text-white' : 'bg-slate-100 text-slate-400'"
                            >
                                <i class="pi pi-times text-xs font-bold" />
                            </div>
                            <span class="font-bold text-xs sm:text-sm">Tolak Pengajuan</span>
                            <span class="text-[10px] text-slate-500 leading-tight">
                                Membatalkan rencana lembur peserta ini.
                            </span>
                        </div>
                    </div>
                </div>
                <div class="space-y-1.5">
                    <div class="flex items-center justify-between">
                        <label class="font-bold text-slate-700 text-xs">
                            Catatan / Pesan Atasan
                            <span v-if="approvalForm.decision === 'reject'" class="text-rose-500">* (Wajib diisi jika menolak)</span>
                            <span v-else class="text-slate-400 font-normal"> (Opsional)</span>
                        </label>
                        <span class="text-[10px] text-slate-400">{{ approvalForm.notes.length }}/200</span>
                    </div>

                    <textarea
                        v-model="approvalForm.notes"
                        rows="3"
                        maxlength="200"
                        class="w-full border border-slate-300 rounded-xl px-3 py-2 text-xs sm:text-sm focus:outline-none focus:ring-2 focus:ring-[#005BAC]/30 focus:border-[#005BAC]"
                        :placeholder="approvalForm.decision === 'reject' ? 'Tuliskan alasan penolakan lembur ini...' : 'Tambahkan catatan arahan jika diperlukan...'"
                    />
                </div>
            </div>

            <template #footer>
                <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-100">
                    <Button
                        label="Batal"
                        severity="secondary"
                        outlined
                        size="small"
                        :disabled="approvalForm.processing"
                        @click="approvalDialogVisible = false"
                    />
                    <Button
                        :label="approvalForm.decision === 'approve' ? 'Kirim Persetujuan' : 'Tolak Pengajuan SPL'"
                        :icon="approvalForm.decision === 'approve' ? 'pi pi-check' : 'pi pi-times'"
                        :severity="approvalForm.decision === 'approve' ? 'success' : 'danger'"
                        size="small"
                        :loading="approvalForm.processing"
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
            <div v-if="detailLoading" class="py-12 text-center text-slate-400">
                <i class="pi pi-spin pi-spinner text-3xl text-[#005BAC] mb-2 block" />
                <p class="text-xs">Memuat rincian SPL...</p>
            </div>

            <div v-else-if="detailData" class="space-y-4 text-xs sm:text-sm pt-1">
                <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 bg-slate-50 p-4 rounded-xl border border-slate-200">
                    <div v-if="detailData.applicant_name" class="col-span-2 sm:col-span-3 pb-2 border-b border-slate-200/80">
                        <p class="text-[10px] text-slate-400 font-semibold uppercase">Pegawai Pemohon</p>
                        <p class="font-bold text-slate-900 text-xs sm:text-sm mt-0.5">
                            {{ detailData.applicant_name }} <span class="font-mono text-slate-500 font-normal">({{ detailData.applicant_nip }})</span>
                        </p>
                        <p class="text-[11px] text-slate-500">{{ detailData.applicant_position }} &bull; {{ detailData.applicant_division }}</p>
                    </div>

                    <div>
                        <p class="text-[10px] text-slate-400 font-semibold uppercase">Kode SPL</p>
                        <p class="font-mono font-bold text-[#005BAC] text-xs sm:text-sm">
                            {{ detailData.code }}
                            <span v-if="detailData.legacy_id" class="text-[10px] text-slate-400 font-normal">
                                (#{{ detailData.legacy_id }})
                            </span>
                        </p>
                    </div>
                    <div>
                        <p class="text-[10px] text-slate-400 font-semibold uppercase">Jenis Lembur</p>
                        <p class="font-medium text-slate-800">{{ detailData.type_label }}</p>
                    </div>
                    <div>
                        <p class="text-[10px] text-slate-400 font-semibold uppercase">Tanggal Lembur</p>
                        <p class="font-medium text-slate-800">{{ detailData.start_date }}</p>
                    </div>
                    <div>
                        <p class="text-[10px] text-slate-400 font-semibold uppercase">Jam Rencana</p>
                        <p class="font-mono font-medium text-slate-800">{{ detailData.start_time }} – {{ detailData.end_time }}</p>
                    </div>
                    <div>
                        <p class="text-[10px] text-slate-400 font-semibold uppercase">Tgl Pengajuan</p>
                        <p class="font-medium text-slate-800">{{ detailData.request_date }}</p>
                    </div>
                    <div>
                        <p class="text-[10px] text-slate-400 font-semibold uppercase">Status Pengajuan</p>
                        <div class="mt-0.5">
                            <Tag :value="detailData.status_label" :severity="getStatusSeverity(detailData.approval_status)" class="text-[10px]" />
                        </div>
                    </div>
                </div>
                <div>
                    <div class="flex items-center justify-between mb-2">
                        <p class="text-xs font-bold uppercase tracking-wider text-slate-700">
                            Alur Persetujuan Atasan (ACC 1 – 3)
                        </p>
                        <span class="text-[11px] text-slate-500">
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
                                <span class="text-[10px] font-bold uppercase tracking-wide flex items-center gap-1"
                                    :class="s.status === 1 ? 'text-emerald-700' : (s.status_label === 'Menunggu' ? 'text-amber-700' : 'text-slate-500')"
                                >
                                    <i class="pi pi-user-check text-[9px]" />
                                    {{ s.level_label }}
                                </span>
                                <Tag
                                    :value="s.status_label"
                                    :severity="s.status === 1 ? 'success' : (s.status === 2 ? 'danger' : (s.status_label === 'Menunggu Antrean' ? 'secondary' : 'warn'))"
                                    class="text-[9px] px-1 py-0.5"
                                />
                            </div>

                            <div class="space-y-0.5">
                                <p class="font-bold text-slate-900 text-xs leading-snug line-clamp-1" :title="s.name">{{ s.name }}</p>
                                <p class="text-[10px] text-slate-400 font-mono">{{ s.nip }}</p>
                                <p class="text-[10px] text-slate-500 truncate" :title="s.position">{{ s.position }}</p>
                            </div>

                            <div v-if="s.approved_at" class="text-[9px] text-slate-400 pt-1 border-t border-slate-100 flex items-center gap-1">
                                <i class="pi pi-clock text-[9px]" />
                                <span>{{ s.approved_at }}</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div v-if="detailData.approval_notes" class="bg-amber-50 border border-amber-200 rounded-xl p-3">
                    <p class="text-[10px] font-semibold text-amber-700 uppercase">Catatan Atasan</p>
                    <p class="text-xs text-amber-900 mt-0.5">{{ detailData.approval_notes }}</p>
                </div>
                <div>
                    <p class="text-xs font-bold uppercase tracking-wider text-slate-700 mb-2">
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
                <Button label="Tutup" severity="secondary" outlined size="small" @click="detailDialogVisible = false" />
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
                    <i class="pi pi-info-circle text-[#005BAC] text-lg mt-0.5 shrink-0" />
                    <div class="space-y-1">
                        <p class="font-bold text-slate-900 text-xs sm:text-sm">
                            Anda akan menyetujui {{ selectedIds.length }} Surat Perintah Lembur secara bersamaan.
                        </p>
                        <p class="text-[11px] text-slate-600 leading-relaxed">
                            Total melibatkan <strong class="text-slate-800">{{ selectedParticipantsTotal }} orang pegawai peserta</strong> lembur. Setiap berkas akan otomatis dimutasi sesuai alur hierarki struktural KFPB (ACC 1 &rarr; ACC 2 &rarr; ACC 3 &rarr; Disetujui Final).
                        </p>
                    </div>
                </div>
                <div>
                    <p class="text-[11px] font-bold uppercase tracking-wider text-slate-600 mb-1.5">
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
                                <span class="text-slate-800 font-medium truncate block">{{ spl.applicant_name }}</span>
                                <span class="text-[10px] text-slate-400 block">{{ spl.applicant_division }} &bull; {{ spl.start_date }} ({{ spl.start_time }} – {{ spl.end_time }})</span>
                            </div>
                            <div class="text-right shrink-0">
                                <span class="text-[10px] font-bold px-1.5 py-0.5 rounded-md bg-amber-100 text-amber-900 border border-amber-200 block mb-1">
                                    {{ spl.my_level || spl.current_level }}
                                </span>
                                <span class="text-[10px] text-slate-500 font-mono">{{ spl.participant_count }} Peserta</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="space-y-1.5">
                    <div class="flex items-center justify-between">
                        <label class="font-bold text-slate-700 text-xs">
                            Catatan Persetujuan Massal <span class="text-slate-400 font-normal">(Opsional)</span>
                        </label>
                        <span class="text-[10px] text-slate-400">{{ batchForm.notes.length }}/200</span>
                    </div>
                    <textarea
                        v-model="batchForm.notes"
                        rows="2"
                        maxlength="200"
                        class="w-full border border-slate-300 rounded-xl px-3 py-2 text-xs sm:text-sm focus:outline-none focus:ring-2 focus:ring-[#005BAC]/30 focus:border-[#005BAC]"
                        placeholder="Contoh: Disetujui untuk pemenuhan batch produksi obat..."
                    />
                </div>
            </div>

            <template #footer>
                <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-100">
                    <Button
                        label="Batal"
                        severity="secondary"
                        outlined
                        size="small"
                        :disabled="batchForm.processing"
                        @click="batchApprovalDialogVisible = false"
                    />
                    <Button
                        :label="`Ya, Setujui ${selectedIds.length} SPL`"
                        icon="pi pi-check"
                        severity="success"
                        size="small"
                        :loading="batchForm.processing"
                        @click="submitBatchApproval"
                    />
                </div>
            </template>
        </Dialog>
    </div>
</template>
