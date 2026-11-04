<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { ref, reactive, computed, onMounted, onUnmounted } from 'vue';
import Toast from 'primevue/toast';
import { useToast } from 'primevue/usetoast';
import DatePicker from 'primevue/datepicker';
import InputText from 'primevue/inputtext';
import Textarea from 'primevue/textarea';
import RadioButton from 'primevue/radiobutton';
import Button from 'primevue/button';
import IftaLabel from 'primevue/iftalabel';
import { generateQrSvg } from '@/Utils/qrcode';

defineOptions({ layout: AuthenticatedLayout });

const props = defineProps({
    authEmployee: {
        type: Object,
        required: true,
    },
    supervisor: {
        type: Object,
        default: () => null,
    },
    isSupervisor: {
        type: Boolean,
        default: false,
    },
    isSecurity: {
        type: Boolean,
        default: false,
    },
    myExitPermits: {
        type: Array,
        default: () => [],
    },
    subordinatePermits: {
        type: Array,
        default: () => [],
    },
    subordinatePendingCount: {
        type: Number,
        default: 0,
    },
    todayDate: {
        type: String,
        required: true,
    },
});

const toast = useToast();

// State Tab Aktif ('my_permits' | 'approval')
const activeTab = ref(props.subordinatePendingCount > 0 && props.isSupervisor ? 'approval' : 'my_permits');

// State Modal Form Pengajuan Mandiri
const showCreateModal = ref(false);

// Tanggal izin (Date object untuk PrimeVue DatePicker)
function getInitialDate() {
    if (props.todayDate) {
        const parts = props.todayDate.split('-');
        if (parts.length === 3) {
            return new Date(Number(parts[0]), Number(parts[1]) - 1, Number(parts[2]), 0, 0, 0);
        }
    }
    return new Date();
}

const selectedDate = ref(getInitialDate());
const minDateObj = computed(() => {
    if (props.todayDate) {
        const parts = props.todayDate.split('-');
        if (parts.length === 3) {
            return new Date(Number(parts[0]), Number(parts[1]) - 1, Number(parts[2]), 0, 0, 0);
        }
    }
    const d = new Date();
    d.setHours(0, 0, 0, 0);
    return d;
});

function openCreateModal() {
    if (!selectedDate.value) {
        selectedDate.value = getInitialDate();
    }
    showCreateModal.value = true;
}

function closeCreateModal() {
    showCreateModal.value = false;
}

// Form Pengajuan Mandiri
const form = useForm({
    date: props.todayDate,
    type: 'keluar_tengah', // 'keluar_tengah' | 'datang_terlambat'
    exit_time: '',
    return_time: '',
    is_returning: true,
    atasan_nip: props.supervisor?.nip || '10000063',
    atasan_nama: props.supervisor?.name || 'apt. Drs. ASEP PERMANA',
    reason: '',
});

function submitForm() {
    // Sinkronisasi tanggal dari PrimeVue DatePicker ke format YYYY-MM-DD untuk validasi Laravel
    if (selectedDate.value instanceof Date && !isNaN(selectedDate.value.getTime())) {
        const y = selectedDate.value.getFullYear();
        const m = String(selectedDate.value.getMonth() + 1).padStart(2, '0');
        const d = String(selectedDate.value.getDate()).padStart(2, '0');
        form.date = `${y}-${m}-${d}`;
    } else if (typeof selectedDate.value === 'string' && selectedDate.value) {
        form.date = selectedDate.value;
    }

    form.post('/izin-keluar', {
        preserveScroll: true,
        onSuccess: () => {
            toast.add({
                severity: 'success',
                summary: 'Berhasil Diajukan',
                detail: `Pengajuan izin keluar kantor telah dikirim ke atasan Anda (${form.atasan_nama}).`,
                life: 4000,
            });
            form.reset('exit_time', 'return_time', 'reason');
            selectedDate.value = getInitialDate();
            closeCreateModal();
            activeTab.value = 'my_permits';
        },
        onError: (errs) => {
            const firstErr = Object.values(errs)[0];
            toast.add({
                severity: 'error',
                summary: 'Gagal Mengajukan',
                detail: firstErr || 'Periksa kembali formulir Anda.',
                life: 4000,
            });
        },
    });
}

// Modal Tiket Izin Keluar / Gate Pass (untuk ditunjukkan ke Satpam)
const showTicketModal = ref(false);
const activeTicket = ref(null);

// QR Code SVG asli ter-generate
const qrSvgHtml = computed(() => {
    if (!activeTicket.value) return '';
    const code = activeTicket.value.kode_izin || `IK-${activeTicket.value.id}`;
    return generateQrSvg(code, 180);
});

function openTicket(permit) {
    activeTicket.value = permit;
    showTicketModal.value = true;
}

function closeTicketModal() {
    showTicketModal.value = false;
    activeTicket.value = null;
}
const closeTicket = closeTicketModal;

// Modal Konfirmasi Approval Atasan
const approvalDialog = reactive({
    show: false,
    permit: null,
    status: 1, // 1: approve, 2: reject
    notes: '',
    processing: false,
});

function openApprovalModal(permit, status) {
    approvalDialog.permit = permit;
    approvalDialog.status = status;
    approvalDialog.notes = '';
    approvalDialog.processing = false;
    approvalDialog.show = true;
}

function closeApprovalModal() {
    approvalDialog.show = false;
    approvalDialog.permit = null;
    approvalDialog.notes = '';
    approvalDialog.processing = false;
}

// Tutup modal dengan tombol Escape
function handleEscapeKey(e) {
    if (e.key === 'Escape') {
        if (showCreateModal.value) closeCreateModal();
        if (showTicketModal.value) closeTicketModal();
        if (approvalDialog.show) closeApprovalModal();
    }
}

onMounted(() => {
    window.addEventListener('keydown', handleEscapeKey);
});

onUnmounted(() => {
    window.removeEventListener('keydown', handleEscapeKey);
});

function handleSupervisorApproval() {
    if (!approvalDialog.permit) return;

    approvalDialog.processing = true;

    router.post(`/izin-keluar/${approvalDialog.permit.id}/approval`, {
        status: approvalDialog.status,
        notes: approvalDialog.notes,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            toast.add({
                severity: approvalDialog.status === 1 ? 'success' : 'warn',
                summary: approvalDialog.status === 1 ? 'Izin Disetujui' : 'Izin Ditolak',
                detail: approvalDialog.status === 1
                    ? `Izin keluar untuk ${approvalDialog.permit.nama_pegawai} berhasil disetujui. Gate pass QR satpam aktif.`
                    : `Izin keluar untuk ${approvalDialog.permit.nama_pegawai} telah ditolak.`,
                life: 4000,
            });
            closeApprovalModal();
        },
        onError: () => {
            toast.add({
                severity: 'error',
                summary: 'Gagal Memproses',
                detail: 'Terjadi kesalahan sistem saat memperbarui status izin bawahan.',
                life: 3500,
            });
            approvalDialog.processing = false;
        },
        onFinish: () => {
            approvalDialog.processing = false;
        },
    });
}
</script>

<template>
    <Head title="Izin Keluar Kantor & Approval Atasan - eSDM KFPB" />
    <Toast position="top-right" />

    <div class="space-y-5 max-w-5xl mx-auto px-3 sm:px-6 py-2">

        <!-- ================= TOP HEADER BREADCRUMB & TITLE ================= -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm">
            <div class="space-y-1">
                <div class="flex items-center gap-2 text-xs font-medium text-slate-500">
                    <Link href="/absensi" class="hover:text-[#005BAC] flex items-center gap-1">
                        <i class="pi pi-calendar-check text-[11px]" />
                        <span>Presensi</span>
                    </Link>
                    <span>/</span>
                    <span class="text-slate-800 font-semibold">Izin Keluar</span>
                </div>
                <h1 class="text-xl sm:text-2xl font-bold text-slate-900 tracking-tight flex items-center gap-2">
                    <span>Izin Keluar</span>
                    <span v-if="isSupervisor" class="text-xs font-semibold px-2.5 py-0.5 rounded-full bg-blue-50 text-[#005BAC] border border-blue-200">
                        Atasan Langsung
                    </span>
                </h1>
                <p class="text-xs sm:text-sm text-slate-500">
                    Pengajuan mandiri izin keluar sementara, izin datang terlambat, serta persetujuan approval izin bawahan KFPB.
                </p>
            </div>

            <!-- Tombol Aksi Utama -->
            <div class="flex flex-wrap items-center gap-2 shrink-0">
                <!-- Tombol Primer: Ajukan Izin Keluar (PrimeVue Button) -->
                <Button
                    label="Ajukan Izin Keluar"
                    icon="pi pi-plus"
                    size="small"
                    @click="openCreateModal"
                />

                <!-- Tombol Navigasi Scanner Satpam (Jika Akun Satpam) -->
                <Link
                    v-if="isSecurity"
                    href="/izin-keluar/scan"
                    class="inline-flex items-center gap-2 px-3.5 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-semibold text-xs transition-colors shadow-xs"
                >
                    <i class="pi pi-qrcode text-xs" />
                    <span>Scanner Satpam</span>
                </Link>

                <Link
                    href="/absensi"
                    class="inline-flex items-center gap-1.5 px-3.5 py-2.5 rounded-xl border border-slate-200 text-slate-700 bg-slate-50 hover:bg-slate-100 font-semibold text-xs transition-colors"
                >
                    <i class="pi pi-arrow-left text-xs" />
                    <span class="hidden sm:inline">Presensi</span>
                </Link>
            </div>
        </div>

        <!-- ================= BANNER PRIORITAS PERSETUJUAN ATASAN ================= -->
        <div
            v-if="isSupervisor && subordinatePendingCount > 0"
            class="p-4 rounded-2xl bg-amber-50 border border-amber-200/90 shadow-xs flex flex-col sm:flex-row sm:items-center justify-between gap-3"
        >
            <div class="flex items-center gap-3">
                <div class="size-10 rounded-xl bg-amber-500 text-white flex items-center justify-center shrink-0 shadow-xs">
                    <i class="pi pi-bell text-lg" />
                </div>
                <div class="space-y-0.5">
                    <p class="font-bold text-amber-950 text-xs sm:text-sm">
                        {{ subordinatePendingCount }} Pengajuan Izin Bawahan Menunggu Tindakan Anda
                    </p>
                    <p class="text-[11px] text-amber-800 leading-tight">
                        Sebagai atasan langsung, berikan keputusan persetujuan untuk menerbitkan Gate Pass tiket pos satpam.
                    </p>
                </div>
            </div>

            <button
                type="button"
                class="px-4 py-2 rounded-xl bg-amber-600 hover:bg-amber-700 active:bg-amber-800 text-white font-bold text-xs transition-colors shrink-0 self-start sm:self-auto cursor-pointer inline-flex items-center gap-1.5"
                @click="activeTab = 'approval'"
            >
                <i class="pi pi-check-square text-xs" />
                <span>Tinjau Persetujuan Sekarang</span>
            </button>
        </div>

        <!-- ================= SEGMENTED TAB NAVIGATION (ZERO-SCROLL MOBILE FIT) ================= -->
        <div v-if="isSupervisor" class="grid grid-cols-2 p-1.5 bg-slate-200/70 rounded-2xl max-w-md w-full select-none">
            <!-- Tab 1: Riwayat Saya -->
            <button
                type="button"
                class="flex items-center justify-center gap-2 py-2.5 px-3 rounded-xl font-bold text-xs sm:text-sm transition-all cursor-pointer"
                :class="activeTab === 'my_permits'
                    ? 'bg-white text-slate-900 shadow-sm'
                    : 'text-slate-600 hover:text-slate-900 hover:bg-white/50'"
                @click="activeTab = 'my_permits'"
            >
                <i class="pi pi-history text-xs" />
                <span>Riwayat Saya</span>
                <span
                    v-if="myExitPermits.length > 0"
                    class="px-1.5 py-0.2 rounded-full text-[10px]"
                    :class="activeTab === 'my_permits' ? 'bg-slate-100 text-slate-700 font-mono' : 'bg-slate-300 text-slate-800 font-mono'"
                >
                    {{ myExitPermits.length }}
                </span>
            </button>

            <!-- Tab 2: Persetujuan Bawahan -->
            <button
                type="button"
                class="flex items-center justify-center gap-2 py-2.5 px-3 rounded-xl font-bold text-xs sm:text-sm transition-all cursor-pointer"
                :class="activeTab === 'approval'
                    ? 'bg-white text-slate-900 shadow-sm'
                    : 'text-slate-600 hover:text-slate-900 hover:bg-white/50'"
                @click="activeTab = 'approval'"
            >
                <i class="pi pi-check-square text-xs" />
                <span>Persetujuan</span>
                <span
                    v-if="subordinatePendingCount > 0"
                    class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-500 text-white animate-pulse"
                >
                    {{ subordinatePendingCount }}
                </span>
            </button>
        </div>

        <!-- ================= VIEW 1: TAB RIWAYAT SAYA (DATA-FIRST) ================= -->
        <div v-show="activeTab === 'my_permits'" class="space-y-4">
            <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-4 sm:p-6 space-y-4">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-slate-100 pb-3">
                    <div class="flex items-center gap-2.5">
                        <div class="size-8 rounded-xl bg-blue-50 text-[#005BAC] flex items-center justify-center shrink-0">
                            <i class="pi pi-history text-base" />
                        </div>
                        <div>
                            <h3 class="text-sm sm:text-base font-bold text-slate-900 tracking-tight">
                                Riwayat Izin Keluar Kantor Saya
                            </h3>
                            <p class="text-xs text-slate-500">
                                Daftar pengajuan perizinan keluar kantor Anda yang tercatat di sistem eSDM KFPB.
                            </p>
                        </div>
                    </div>

                    <Button
                        label="Ajukan Izin Baru"
                        icon="pi pi-plus"
                        size="small"
                        class="sm:hidden self-start"
                        @click="openCreateModal"
                    />
                </div>

                <!-- Empty State -->
                <div v-if="myExitPermits.length === 0" class="text-center py-12 text-slate-400 text-xs space-y-3">
                    <i class="pi pi-inbox text-3xl block text-slate-300" />
                    <p>Belum ada riwayat izin keluar kantor yang diajukan.</p>
                    <Button
                        label="Mulai Buat Pengajuan Izin"
                        icon="pi pi-plus"
                        size="small"
                        @click="openCreateModal"
                    />
                </div>

                <div v-else class="space-y-4">
                    <!-- A. Desktop View (Tabel Elegan) -->
                    <div class="hidden md:block overflow-x-auto">
                        <table class="w-full text-left text-xs text-slate-700">
                            <thead>
                                <tr class="border-b border-slate-200 text-slate-400 font-bold uppercase text-[10px] tracking-wider">
                                    <th class="py-2.5 px-3">Kode Izin</th>
                                    <th class="py-2.5 px-3">Tanggal</th>
                                    <th class="py-2.5 px-3">Jam Rencana</th>
                                    <th class="py-2.5 px-3">Keperluan / Alasan</th>
                                    <th class="py-2.5 px-3 text-center">Status</th>
                                    <th class="py-2.5 px-3 text-right">Tiket Pos Satpam</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr v-for="permit in myExitPermits" :key="permit.id" class="hover:bg-slate-50/70 transition-colors">
                                    <td class="py-3 px-3 font-mono font-bold text-slate-800 whitespace-nowrap">
                                        {{ permit.kode_izin }}
                                    </td>
                                    <td class="py-3 px-3 font-mono text-slate-600 whitespace-nowrap">
                                        {{ permit.date }}
                                    </td>
                                    <td class="py-3 px-3 font-mono text-slate-700 whitespace-nowrap">
                                        <span v-if="permit.exit_time !== '00:00'">Keluar: {{ permit.exit_time }}</span>
                                        <span v-if="permit.return_time" class="text-slate-500 block text-[11px]">
                                            Kembali: {{ permit.return_time }}
                                        </span>
                                        <span v-else-if="permit.exit_time !== '00:00'" class="text-slate-400 block text-[11px]">
                                            (Langsung Pulang)
                                        </span>
                                    </td>
                                    <td class="py-3 px-3 max-w-xs truncate text-slate-600" :title="permit.reason">
                                        {{ permit.reason }}
                                    </td>
                                    <td class="py-3 px-3 text-center whitespace-nowrap">
                                        <span
                                            v-if="permit.approval_status === 0"
                                            class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-amber-50 text-amber-700 border border-amber-200"
                                        >
                                            <i class="pi pi-clock text-[9px]" />
                                            <span>Menunggu</span>
                                        </span>
                                        <span
                                            v-else-if="permit.approval_status === 1"
                                            class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200"
                                        >
                                            <i class="pi pi-check text-[9px]" />
                                            <span>Disetujui</span>
                                        </span>
                                        <span
                                            v-else-if="permit.approval_status === 2"
                                            class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-rose-50 text-rose-700 border border-rose-200"
                                        >
                                            <i class="pi pi-times text-[9px]" />
                                            <span>Ditolak</span>
                                        </span>
                                        <span
                                            v-else-if="permit.approval_status === 3"
                                            class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-blue-50 text-blue-700 border border-blue-200"
                                        >
                                            <i class="pi pi-external-link text-[9px]" />
                                            <span>Sudah Keluar</span>
                                        </span>
                                        <span
                                            v-else
                                            class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-slate-100 text-slate-700 border border-slate-200"
                                        >
                                            <i class="pi pi-check-circle text-[9px]" />
                                            <span>Selesai</span>
                                        </span>
                                    </td>
                                    <td class="py-3 px-3 text-right whitespace-nowrap">
                                        <button
                                            v-if="[1, 3, 4].includes(permit.approval_status)"
                                            type="button"
                                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs shadow-xs transition-colors cursor-pointer"
                                            @click="openTicket(permit)"
                                        >
                                            <i class="pi pi-qrcode text-xs" />
                                            <span>Buka Tiket QR</span>
                                        </button>
                                        <span v-else class="text-[11px] text-slate-400 italic">
                                            QR aktif setelah disetujui
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- B. Mobile View (Card List Ramah Jempol) -->
                    <div class="space-y-3 block md:hidden">
                        <div
                            v-for="permit in myExitPermits"
                            :key="permit.id"
                            class="p-4 rounded-2xl bg-slate-50/70 border border-slate-200/90 shadow-2xs space-y-2.5"
                        >
                            <div class="flex items-center justify-between gap-2 border-b border-slate-200/80 pb-2">
                                <span class="font-mono font-bold text-xs text-slate-900">{{ permit.kode_izin }}</span>
                                <span
                                    v-if="permit.approval_status === 0"
                                    class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-amber-50 text-amber-700 border border-amber-200"
                                >
                                    <i class="pi pi-clock text-[9px]" />
                                    <span>Menunggu Approval</span>
                                </span>
                                <span
                                    v-else-if="permit.approval_status === 1"
                                    class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200"
                                >
                                    <i class="pi pi-check text-[9px]" />
                                    <span>Disetujui Atasan</span>
                                </span>
                                <span
                                    v-else-if="permit.approval_status === 2"
                                    class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-rose-50 text-rose-700 border border-rose-200"
                                >
                                    <i class="pi pi-times text-[9px]" />
                                    <span>Ditolak</span>
                                </span>
                                <span
                                    v-else-if="permit.approval_status === 3"
                                    class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-blue-50 text-blue-700 border border-blue-200"
                                >
                                    <i class="pi pi-external-link text-[9px]" />
                                    <span>Di Luar Pabrik</span>
                                </span>
                                <span
                                    v-else
                                    class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-slate-200 text-slate-800"
                                >
                                    <i class="pi pi-check-circle text-[9px]" />
                                    <span>Selesai</span>
                                </span>
                            </div>

                            <div class="space-y-1 text-xs">
                                <div class="flex items-center gap-2 text-slate-700">
                                    <i class="pi pi-calendar text-slate-400 text-[11px]" />
                                    <span class="font-mono font-bold">{{ permit.date }}</span>
                                    <span>&bull;</span>
                                    <span class="font-mono text-[#005BAC] font-semibold">
                                        {{ permit.exit_time !== '00:00' ? permit.exit_time : '' }}
                                        {{ permit.return_time ? 's/d ' + permit.return_time : (permit.exit_time !== '00:00' ? '(Pulang)' : '') }}
                                    </span>
                                </div>
                                <p class="text-slate-600 italic text-[11px] leading-relaxed pt-0.5">
                                    "{{ permit.reason }}"
                                </p>
                            </div>

                            <!-- Tombol Tiket Lebar di Mobile (1 Sentuhan di Depan Pos Satpam) -->
                            <div v-if="[1, 3, 4].includes(permit.approval_status)" class="pt-1">
                                <button
                                    type="button"
                                    class="w-full py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 active:bg-emerald-800 text-white font-bold text-xs shadow-xs transition-colors flex items-center justify-center gap-2 cursor-pointer"
                                    @click="openTicket(permit)"
                                >
                                    <i class="pi pi-qrcode text-sm" />
                                    <span>Buka Tiket QR Gate Pass (Satpam)</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ================= VIEW 2: TAB PERSETUJUAN ATASAN (APPROVAL BAWAHAN) ================= -->
        <div v-if="isSupervisor" v-show="activeTab === 'approval'" class="space-y-4">
            <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-4 sm:p-6 space-y-4">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-slate-100 pb-3">
                    <div class="flex items-center gap-2.5">
                        <div class="size-8 rounded-xl bg-blue-50 text-[#005BAC] flex items-center justify-center shrink-0">
                            <i class="pi pi-check-square text-base" />
                        </div>
                        <div>
                            <h3 class="text-sm sm:text-base font-bold text-slate-900 tracking-tight">
                                Pusat Persetujuan Izin Bawahan
                            </h3>
                            <p class="text-xs text-slate-500">
                                Sebagai atasan langsung, tinjau dan berikan persetujuan atas permohonan staf Anda.
                            </p>
                        </div>
                    </div>
                    <div v-if="subordinatePendingCount > 0" class="px-3 py-1 rounded-full bg-amber-50 border border-amber-200 text-amber-800 text-xs font-bold self-start sm:self-auto">
                        {{ subordinatePendingCount }} Izin Menunggu Persetujuan
                    </div>
                </div>

                <!-- Empty State Subordinate -->
                <div v-if="subordinatePermits.length === 0" class="text-center py-12 text-slate-400 text-xs">
                    <i class="pi pi-inbox text-3xl mb-2 block text-slate-300" />
                    <span>Belum ada pengajuan izin keluar dari bawahan kerja Anda.</span>
                </div>

                <div v-else class="space-y-4">
                    <!-- A. Desktop View Subordinates -->
                    <div class="hidden md:block overflow-x-auto">
                        <table class="w-full text-left text-xs border-collapse">
                            <thead>
                                <tr class="bg-slate-50/80 border-b border-slate-200 text-slate-600 font-bold uppercase text-[10px] tracking-wider">
                                    <th class="py-2.5 px-3">Kode Izin</th>
                                    <th class="py-2.5 px-3">Pegawai / Bawahan</th>
                                    <th class="py-2.5 px-3">Tanggal & Waktu</th>
                                    <th class="py-2.5 px-3 max-w-xs">Keperluan</th>
                                    <th class="py-2.5 px-3 text-center">Status</th>
                                    <th class="py-2.5 px-3 text-center w-36">Aksi Persetujuan</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 text-slate-700">
                                <tr v-for="item in subordinatePermits" :key="item.id" class="hover:bg-slate-50/60 transition-colors">
                                    <td class="py-3 px-3 font-mono font-bold text-slate-800 whitespace-nowrap">
                                        {{ item.kode_izin }}
                                    </td>
                                    <td class="py-3 px-3">
                                        <div class="font-bold text-slate-900">{{ item.nama_pegawai }}</div>
                                        <div class="text-[11px] text-slate-500 font-mono">
                                            {{ item.nip }} &bull; {{ item.bagian }}
                                        </div>
                                    </td>
                                    <td class="py-3 px-3 whitespace-nowrap font-mono text-slate-700">
                                        <div>{{ item.date }}</div>
                                        <div class="text-[11px] text-slate-500 font-normal">
                                            {{ item.exit_time }} s/d {{ item.return_time || 'Pulang' }}
                                        </div>
                                    </td>
                                    <td class="py-3 px-3 max-w-xs text-slate-600 text-xs">
                                        <p class="line-clamp-2" :title="item.reason">{{ item.reason }}</p>
                                    </td>
                                    <td class="py-3 px-3 text-center whitespace-nowrap">
                                        <span
                                            v-if="item.approval_status === 0"
                                            class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-amber-50 text-amber-700 border border-amber-200"
                                        >
                                            <i class="pi pi-clock text-[9px]" />
                                            <span>Menunggu</span>
                                        </span>
                                        <span
                                            v-else-if="item.approval_status === 1"
                                            class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200"
                                        >
                                            <i class="pi pi-check text-[9px]" />
                                            <span>Disetujui</span>
                                        </span>
                                        <span
                                            v-else-if="item.approval_status === 2"
                                            class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-rose-50 text-rose-700 border border-rose-200"
                                        >
                                            <i class="pi pi-times text-[9px]" />
                                            <span>Ditolak</span>
                                        </span>
                                        <span
                                            v-else-if="item.approval_status === 3"
                                            class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-blue-50 text-blue-700 border border-blue-200"
                                        >
                                            <i class="pi pi-external-link text-[9px]" />
                                            <span>Di Luar</span>
                                        </span>
                                        <span
                                            v-else
                                            class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-slate-100 text-slate-700 border border-slate-200"
                                        >
                                            <i class="pi pi-check-circle text-[9px]" />
                                            <span>Selesai</span>
                                        </span>
                                    </td>
                                    <td class="py-3 px-3 text-center whitespace-nowrap">
                                        <div v-if="item.approval_status === 0" class="flex items-center justify-center gap-1.5">
                                            <button
                                                type="button"
                                                class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white font-semibold text-xs transition-colors shadow-xs cursor-pointer"
                                                title="Setujui Izin Bawahan"
                                                @click="openApprovalModal(item, 1)"
                                            >
                                                <i class="pi pi-check text-[10px]" />
                                                <span>Setujui</span>
                                            </button>
                                            <button
                                                type="button"
                                                class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg border border-rose-200 bg-rose-50 hover:bg-rose-100 text-rose-700 font-semibold text-xs transition-colors cursor-pointer"
                                                title="Tolak Izin Bawahan"
                                                @click="openApprovalModal(item, 2)"
                                            >
                                                <i class="pi pi-times text-[10px]" />
                                                <span>Tolak</span>
                                            </button>
                                        </div>
                                        <span v-else class="text-[11px] text-slate-400">
                                            Sudah diproses
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- B. Mobile View Subordinates (Card Ramah Jempol) -->
                    <div class="space-y-3 block md:hidden">
                        <div
                            v-for="item in subordinatePermits"
                            :key="item.id"
                            class="p-4 rounded-2xl bg-slate-50/70 border border-slate-200/90 shadow-2xs space-y-3"
                        >
                            <div class="flex items-start justify-between gap-2 border-b border-slate-200/80 pb-2">
                                <div>
                                    <div class="font-bold text-xs text-slate-900">{{ item.nama_pegawai }}</div>
                                    <div class="text-[11px] text-slate-500 font-mono">{{ item.nip }} &bull; {{ item.bagian }}</div>
                                </div>
                                <span
                                    v-if="item.approval_status === 0"
                                    class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-amber-50 text-amber-700 border border-amber-200 shrink-0"
                                >
                                    <i class="pi pi-clock text-[9px]" />
                                    <span>Menunggu</span>
                                </span>
                                <span
                                    v-else-if="item.approval_status === 1"
                                    class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200 shrink-0"
                                >
                                    <i class="pi pi-check text-[9px]" />
                                    <span>Disetujui</span>
                                </span>
                                <span
                                    v-else
                                    class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-slate-200 text-slate-700 shrink-0"
                                >
                                    <span>Diproses</span>
                                </span>
                            </div>

                            <div class="space-y-1 text-xs">
                                <div class="flex items-center gap-2 text-slate-700">
                                    <i class="pi pi-calendar text-slate-400 text-[11px]" />
                                    <span class="font-mono font-bold">{{ item.date }}</span>
                                    <span>&bull;</span>
                                    <span class="font-mono text-[#005BAC] font-semibold">
                                        {{ item.exit_time }} s/d {{ item.return_time || 'Pulang' }}
                                    </span>
                                </div>
                                <p class="text-slate-600 italic text-[11px] leading-relaxed pt-0.5">
                                    "{{ item.reason }}"
                                </p>
                            </div>

                            <!-- Tombol Aksi Persetujuan Atasan di Mobile -->
                            <div v-if="item.approval_status === 0" class="grid grid-cols-2 gap-2 pt-1 border-t border-slate-200/70">
                                <button
                                    type="button"
                                    class="py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 active:bg-emerald-800 text-white font-bold text-xs shadow-xs transition-colors flex items-center justify-center gap-1.5 cursor-pointer"
                                    @click="openApprovalModal(item, 1)"
                                >
                                    <i class="pi pi-check text-xs" />
                                    <span>Setujui Izin</span>
                                </button>
                                <button
                                    type="button"
                                    class="py-2.5 rounded-xl border border-rose-300 bg-rose-50 hover:bg-rose-100 active:bg-rose-200 text-rose-700 font-bold text-xs transition-colors flex items-center justify-center gap-1.5 cursor-pointer"
                                    @click="openApprovalModal(item, 2)"
                                >
                                    <i class="pi pi-times text-xs" />
                                    <span>Tolak Izin</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ================= MODAL FORMULIR PENGAJUAN IZIN KELUAR ================= -->
        <div
            v-if="showCreateModal"
            class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-4 bg-slate-900/60 backdrop-blur-xs overflow-y-auto cursor-pointer"
            @click.self="closeCreateModal"
        >
            <div class="bg-white rounded-2xl sm:rounded-3xl max-w-2xl w-full max-h-[92vh] flex flex-col overflow-hidden shadow-2xl border border-slate-200 relative my-auto cursor-default">
                <!-- Header Modal Form -->
                <div class="px-5 sm:px-6 py-4 bg-gradient-to-r from-[#005BAC] to-[#004280] text-white flex items-center justify-between shrink-0">
                    <div class="flex items-center gap-3">
                        <div class="size-10 rounded-xl bg-white/10 border border-white/20 flex items-center justify-center shrink-0">
                            <i class="pi pi-file-edit text-white text-lg" />
                        </div>
                        <div>
                            <h2 class="font-bold text-sm sm:text-base tracking-tight text-white">
                                Formulir Permohonan Izin Keluar
                            </h2>
                            <p class="text-[11px] text-blue-100">
                                Lengkapi rincian izin keluar kantor Kimia Farma Plant Banjaran
                            </p>
                        </div>
                    </div>

                    <!-- Tombol Silang (X) -->
                    <button
                        type="button"
                        class="size-9 rounded-full bg-white/20 hover:bg-white/30 active:bg-white/40 text-white flex items-center justify-center cursor-pointer transition-all shrink-0"
                        title="Tutup Formulir"
                        @click="closeCreateModal"
                    >
                        <i class="pi pi-times text-sm font-bold" />
                    </button>
                </div>

                <!-- Body Modal Form (Scrollable) -->
                <form id="exitPermitForm" @submit.prevent="submitForm" class="p-4 sm:p-6 space-y-5 text-xs sm:text-sm overflow-y-auto flex-1 overscroll-contain">
                    <!-- Error Summary -->
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

                    <!-- 1. Identitas Pemohon -->
                    <div class="space-y-1.5">
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-700">
                            Data Pegawai Pemohon
                        </label>
                        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-3 p-3.5 rounded-xl bg-slate-50 border border-slate-200/80">
                            <div>
                                <span class="text-[10px] uppercase font-bold text-slate-400 block">NPP / NIP</span>
                                <span class="text-xs sm:text-sm font-bold font-mono text-slate-800">{{ authEmployee.nip }}</span>
                            </div>
                            <div>
                                <span class="text-[10px] uppercase font-bold text-slate-400 block">Nama Lengkap</span>
                                <span class="text-xs sm:text-sm font-bold text-slate-800">{{ authEmployee.name }}</span>
                            </div>
                            <div>
                                <span class="text-[10px] uppercase font-bold text-slate-400 block">Bagian / Unit</span>
                                <span class="text-xs text-slate-700 truncate block">{{ authEmployee.division }}</span>
                            </div>
                            <div>
                                <span class="text-[10px] uppercase font-bold text-slate-400 block">Jabatan</span>
                                <span class="text-xs text-slate-700 truncate block">{{ authEmployee.position }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- 2. Atasan Langsung yang Menyetujui (Otomatis Terdeteksi Seperti ESDM Lama) -->
                    <div class="space-y-1.5">
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-700">
                            Atasan Langsung yang Menyetujui
                        </label>
                        <div class="p-3.5 rounded-xl bg-slate-50 border border-slate-200/80 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                            <div class="flex items-center gap-3">
                                <div class="size-10 rounded-xl bg-blue-100/80 text-[#005BAC] flex items-center justify-center shrink-0">
                                    <i class="pi pi-user-check text-lg" />
                                </div>
                                <div class="space-y-0.5">
                                    <div class="text-xs sm:text-sm font-bold text-slate-800 flex items-center gap-1.5">
                                        <span>{{ form.atasan_nama }}</span>
                                        <span class="font-mono text-[11px] font-normal text-slate-500">({{ form.atasan_nip }})</span>
                                    </div>
                                    <p class="text-[11px] text-slate-500">
                                        Izin keluar Anda akan diteruskan secara otomatis ke atasan langsung di atas untuk approval.
                                    </p>
                                </div>
                            </div>
                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200 text-[10px] font-bold shrink-0 self-start sm:self-auto">
                                <i class="pi pi-check text-[9px]" />
                                <span>Atasan Terverifikasi</span>
                            </span>
                        </div>
                    </div>

                    <!-- 3. Pilihan Tipe Izin Keluar -->
                    <div class="space-y-2">
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-700">
                            Pilih Tipe Izin Keluar <span class="text-rose-500">*</span>
                        </label>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <!-- Tipe Keluar Sementara -->
                            <label
                                for="type_keluar_tengah"
                                class="flex items-start gap-3 p-3.5 rounded-xl border cursor-pointer transition-all select-none"
                                :class="form.type === 'keluar_tengah'
                                    ? 'bg-blue-50/70 border-[#005BAC] ring-1 ring-[#005BAC] shadow-xs'
                                    : 'bg-white border-slate-200 hover:border-slate-300 hover:bg-slate-50/50'"
                            >
                                <RadioButton
                                    v-model="form.type"
                                    inputId="type_keluar_tengah"
                                    value="keluar_tengah"
                                    class="mt-0.5"
                                />
                                <div class="space-y-0.5">
                                    <p class="text-xs sm:text-sm font-bold text-slate-800">
                                        Izin Keluar Sementara (Jam Kerja)
                                    </p>
                                    <p class="text-[11px] text-slate-500 leading-relaxed">
                                        Sudah di kantor/pabrik, lalu keluar sementara untuk dinas/pribadi. Perlu scan keluar & scan kembali di pos satpam.
                                    </p>
                                </div>
                            </label>

                            <!-- Tipe Datang Terlambat -->
                            <label
                                for="type_datang_terlambat"
                                class="flex items-start gap-3 p-3.5 rounded-xl border cursor-pointer transition-all select-none"
                                :class="form.type === 'datang_terlambat'
                                    ? 'bg-blue-50/70 border-[#005BAC] ring-1 ring-[#005BAC] shadow-xs'
                                    : 'bg-white border-slate-200 hover:border-slate-300 hover:bg-slate-50/50'"
                            >
                                <RadioButton
                                    v-model="form.type"
                                    inputId="type_datang_terlambat"
                                    value="datang_terlambat"
                                    class="mt-0.5"
                                />
                                <div class="space-y-0.5">
                                    <p class="text-xs sm:text-sm font-bold text-slate-800">
                                        Izin Datang Terlambat
                                    </p>
                                    <p class="text-[11px] text-slate-500 leading-relaxed">
                                        Keperluan mendesak pagi hari sehingga masuk lebih lambat dari shift normal. Scan dilakukan saat tiba di pos satpam.
                                    </p>
                                </div>
                            </label>
                        </div>
                    </div>

                    <!-- 4. Tanggal & Jam Menggunakan Komponen Template PrimeVue (/forms) -->
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3.5 pt-1">
                        <!-- Tanggal Izin (PrimeVue DatePicker dengan IftaLabel) -->
                        <div>
                            <IftaLabel class="w-full">
                                <DatePicker
                                    id="date"
                                    inputId="date"
                                    v-model="selectedDate"
                                    class="w-full"
                                    dateFormat="dd/mm/yy"
                                    :minDate="minDateObj"
                                    showIcon
                                    :invalid="!!form.errors.date"
                                />
                                <label for="date">Tanggal Izin *</label>
                            </IftaLabel>
                            <small v-if="form.errors.date" class="text-red-500 block mt-1">{{ form.errors.date }}</small>
                        </div>

                        <!-- Keluar Tengah (Jam Keluar) -->
                        <div v-if="form.type === 'keluar_tengah'">
                            <IftaLabel class="w-full">
                                <InputText
                                    id="exit_time"
                                    v-model="form.exit_time"
                                    type="time"
                                    class="w-full"
                                    :invalid="!!form.errors.exit_time"
                                />
                                <label for="exit_time">Jam Rencana Keluar *</label>
                            </IftaLabel>
                            <small v-if="form.errors.exit_time" class="text-red-500 block mt-1">{{ form.errors.exit_time }}</small>
                        </div>

                        <!-- Keluar Tengah (Jam Kembali) -->
                        <div v-if="form.type === 'keluar_tengah'">
                            <IftaLabel class="w-full">
                                <InputText
                                    id="return_time"
                                    v-model="form.return_time"
                                    type="time"
                                    class="w-full"
                                    :disabled="!form.is_returning"
                                    :invalid="!!form.errors.return_time"
                                />
                                <label for="return_time">Jam Rencana Kembali</label>
                            </IftaLabel>
                            <small v-if="form.errors.return_time" class="text-red-500 block mt-1">{{ form.errors.return_time }}</small>
                        </div>

                        <!-- Datang Terlambat (Jam Rencana Tiba) -->
                        <div v-if="form.type === 'datang_terlambat'" class="sm:col-span-2">
                            <IftaLabel class="w-full">
                                <InputText
                                    id="return_time_late"
                                    v-model="form.return_time"
                                    type="time"
                                    class="w-full"
                                    :invalid="!!form.errors.return_time"
                                />
                                <label for="return_time_late">Jam Rencana Tiba / Masuk Kerja *</label>
                            </IftaLabel>
                            <small v-if="form.errors.return_time" class="text-red-500 block mt-1">{{ form.errors.return_time }}</small>
                            <span class="text-[11px] text-slate-500 block mt-1">Perkiraan waktu tiba di pos satpam KFPB.</span>
                        </div>
                    </div>

                    <!-- Kembali ke Kantor? (Khusus Keluar Tengah) -->
                    <div v-if="form.type === 'keluar_tengah'" class="p-3.5 rounded-xl bg-slate-50 border border-slate-200/80 flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                        <div>
                            <span class="text-xs font-bold text-slate-800 block">Kembali lagi ke pabrik hari ini?</span>
                            <span class="text-[11px] text-slate-500 block">Pilih "Tidak" jika Anda langsung pulang dan tidak kembali lagi.</span>
                        </div>
                        <div class="flex items-center gap-4">
                            <div class="flex items-center gap-2">
                                <RadioButton v-model="form.is_returning" inputId="returning_yes" :value="true" />
                                <label for="returning_yes" class="text-xs font-semibold text-slate-700 cursor-pointer">Ya, Kembali</label>
                            </div>
                            <div class="flex items-center gap-2">
                                <RadioButton v-model="form.is_returning" inputId="returning_no" :value="false" />
                                <label for="returning_no" class="text-xs font-semibold text-slate-700 cursor-pointer">Tidak (Langsung Pulang)</label>
                            </div>
                        </div>
                    </div>

                    <!-- 5. Alasan / Keperluan (PrimeVue Textarea dengan IftaLabel) -->
                    <div class="space-y-1">
                        <IftaLabel class="w-full">
                            <Textarea
                                id="reason"
                                v-model="form.reason"
                                rows="3"
                                class="w-full"
                                :invalid="!!form.errors.reason"
                                placeholder=" "
                            />
                            <label for="reason">Alasan / Keperluan Izin Keluar *</label>
                        </IftaLabel>
                        <small v-if="form.errors.reason" class="text-red-500 block mt-1">{{ form.errors.reason }}</small>
                    </div>
                </form>

                <!-- Footer Modal Form (Menggunakan PrimeVue Button) -->
                <div class="p-3.5 sm:p-4 bg-slate-50 border-t border-slate-200 flex items-center justify-end gap-2 shrink-0">
                    <Button
                        label="Batal"
                        icon="pi pi-times"
                        severity="secondary"
                        outlined
                        size="small"
                        @click="closeCreateModal"
                    />
                    <Button
                        label="Kirim Pengajuan ke Atasan"
                        icon="pi pi-send"
                        type="submit"
                        form="exitPermitForm"
                        :disabled="form.processing"
                        :loading="form.processing"
                        size="small"
                    />
                </div>
            </div>
        </div>

        <!-- ================= MODAL DIALOG APPROVAL ATASAN ================= -->
        <div
            v-if="approvalDialog.show && approvalDialog.permit"
            class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-4 bg-slate-900/60 backdrop-blur-xs overflow-y-auto cursor-pointer"
            @click.self="closeApprovalModal"
        >
            <div class="bg-white rounded-2xl max-w-md w-full max-h-[90vh] flex flex-col overflow-hidden shadow-2xl border border-slate-200 my-auto cursor-default">
                <!-- Header Modal Approval -->
                <div class="p-4 sm:p-5 border-b border-slate-100 flex items-start justify-between gap-3 shrink-0">
                    <div class="flex items-start gap-3">
                        <div
                            class="size-10 sm:size-11 rounded-2xl flex items-center justify-center shrink-0"
                            :class="approvalDialog.status === 1 ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700'"
                        >
                            <i :class="approvalDialog.status === 1 ? 'pi pi-check-circle text-lg sm:text-xl' : 'pi pi-times-circle text-lg sm:text-xl'" />
                        </div>
                        <div class="space-y-0.5">
                            <h3 class="text-sm sm:text-base font-bold text-slate-900">
                                {{ approvalDialog.status === 1 ? 'Setujui Izin Keluar?' : 'Tolak Izin Keluar?' }}
                            </h3>
                            <p class="text-[11px] sm:text-xs text-slate-500 leading-tight">
                                Konfirmasi keputusan persetujuan atas pengajuan izin keluar bawahan:
                            </p>
                        </div>
                    </div>

                    <!-- Tombol Silang (X) Cepat Tutup -->
                    <button
                        type="button"
                        class="size-8 rounded-full bg-slate-100 hover:bg-slate-200 active:bg-slate-300 text-slate-500 hover:text-slate-800 flex items-center justify-center cursor-pointer transition-colors shrink-0"
                        title="Tutup Modal"
                        @click="closeApprovalModal"
                    >
                        <i class="pi pi-times text-xs" />
                    </button>
                </div>

                <!-- Body Modal Approval (Scrollable jika layar HP pendek) -->
                <div class="p-4 sm:p-5 space-y-3.5 text-xs overflow-y-auto flex-1 overscroll-contain">
                    <!-- Info Rinci Pemohon -->
                    <div class="bg-slate-50 p-3.5 rounded-xl border border-slate-200/80 space-y-1.5 text-xs">
                        <div class="flex justify-between items-start gap-2">
                            <span class="text-slate-400 shrink-0">Pegawai:</span>
                            <span class="font-bold text-slate-800 text-right">{{ approvalDialog.permit.nama_pegawai }} ({{ approvalDialog.permit.nip }})</span>
                        </div>
                        <div class="flex justify-between items-start gap-2">
                            <span class="text-slate-400 shrink-0">Waktu Izin:</span>
                            <span class="font-mono font-semibold text-slate-800 text-right">
                                {{ approvalDialog.permit.date }} &bull; {{ approvalDialog.permit.exit_time }} s/d {{ approvalDialog.permit.return_time || 'Pulang' }}
                            </span>
                        </div>
                        <div class="pt-1.5 border-t border-slate-200 text-slate-600 italic">
                            "{{ approvalDialog.permit.reason }}"
                        </div>
                    </div>

                    <!-- Input Catatan Atasan Opsional (PrimeVue IftaLabel & InputText) -->
                    <div>
                        <IftaLabel class="w-full">
                            <InputText
                                id="approval_notes"
                                v-model="approvalDialog.notes"
                                class="w-full text-xs"
                                placeholder=" "
                            />
                            <label for="approval_notes">Pesan / Catatan Atasan (Opsional)</label>
                        </IftaLabel>
                    </div>
                </div>

                <!-- Footer Aksi (PrimeVue Button) -->
                <div class="p-3.5 sm:p-4 bg-slate-50 border-t border-slate-100 flex items-center justify-end gap-2 shrink-0">
                    <Button
                        label="Batal"
                        icon="pi pi-times"
                        severity="secondary"
                        outlined
                        size="small"
                        :disabled="approvalDialog.processing"
                        @click="closeApprovalModal"
                    />
                    <Button
                        :label="approvalDialog.status === 1 ? 'Ya, Setujui Izin' : 'Ya, Tolak Izin'"
                        :icon="approvalDialog.status === 1 ? 'pi pi-check' : 'pi pi-times'"
                        :severity="approvalDialog.status === 1 ? 'success' : 'danger'"
                        size="small"
                        :disabled="approvalDialog.processing"
                        :loading="approvalDialog.processing"
                        @click="handleSupervisorApproval"
                    />
                </div>
            </div>
        </div>

        <!-- ================= MODAL TIKET GATE PASS DIGITAL SATPAM ================= -->
        <div
            v-if="showTicketModal && activeTicket"
            class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-4 bg-slate-900/60 backdrop-blur-xs overflow-y-auto cursor-pointer"
            @click.self="closeTicketModal"
        >
            <div class="bg-white rounded-2xl sm:rounded-3xl max-w-sm w-full max-h-[92vh] flex flex-col overflow-hidden shadow-2xl border border-slate-200 relative my-auto cursor-default">
                <!-- Header Tiket BUMN -->
                <div class="bg-gradient-to-r from-[#005BAC] to-[#004785] p-4 sm:p-5 text-white text-center relative shrink-0">
                    <button
                        type="button"
                        class="absolute top-3.5 right-3.5 size-9 sm:size-10 rounded-full bg-white/20 hover:bg-white/30 active:bg-white/40 text-white flex items-center justify-center cursor-pointer transition-all z-20"
                        title="Tutup Tiket"
                        aria-label="Tutup Tiket"
                        @click="closeTicketModal"
                    >
                        <i class="pi pi-times text-sm font-bold" />
                    </button>
                    <div class="text-[10px] tracking-widest uppercase font-mono opacity-80 mb-0.5">Kimia Farma Plant Banjaran</div>
                    <h3 class="text-sm sm:text-base font-bold tracking-tight">TIKET GATE PASS DIGITAL</h3>
                    <div class="text-[11px] font-mono mt-1 px-2.5 py-0.5 rounded-full bg-white/20 inline-block font-bold">
                        {{ activeTicket.kode_izin }}
                    </div>
                </div>

                <!-- Badan Tiket (Scrollable di HP kecil agar tombol tutup selalu bisa dicapai) -->
                <div class="p-4 sm:p-5 space-y-3.5 text-xs overflow-y-auto flex-1 overscroll-contain">
                    <!-- Status Stempel -->
                    <div class="text-center">
                        <span
                            v-if="[1, 3, 4].includes(activeTicket.approval_status)"
                            class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800 border border-emerald-300"
                        >
                            <i class="pi pi-check-circle" />
                            <span>IZIN DISETUJUI ATASAN</span>
                        </span>
                        <span
                            v-else-if="activeTicket.approval_status === 0"
                            class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-amber-100 text-amber-800 border border-amber-300"
                        >
                            <i class="pi pi-clock" />
                            <span>MENUNGGU PERSETUJUAN</span>
                        </span>
                        <span
                            v-else
                            class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-rose-100 text-rose-800 border border-rose-300"
                        >
                            <i class="pi pi-times-circle" />
                            <span>PENGAJUAN DITOLAK</span>
                        </span>
                    </div>

                    <!-- Informasi Rinci Pegawai -->
                    <div class="border border-dashed border-slate-200 rounded-2xl p-3.5 sm:p-4 bg-slate-50/70 space-y-2">
                        <div class="flex justify-between items-start gap-2">
                            <span class="text-slate-400 shrink-0">Nama Pegawai:</span>
                            <span class="font-bold text-slate-900 text-right">{{ authEmployee.name }}</span>
                        </div>
                        <div class="flex justify-between items-start gap-2">
                            <span class="text-slate-400 shrink-0">NIP / Unit:</span>
                            <span class="font-mono text-slate-800 font-semibold text-right">{{ authEmployee.nip }} ({{ authEmployee.division }})</span>
                        </div>
                        <div class="flex justify-between items-start gap-2">
                            <span class="text-slate-400 shrink-0">Tanggal Izin:</span>
                            <span class="font-mono font-bold text-slate-900">{{ activeTicket.date }}</span>
                        </div>
                        <div class="flex justify-between items-start gap-2">
                            <span class="text-slate-400 shrink-0">Waktu Rencana:</span>
                            <span class="font-mono font-bold text-[#005BAC]">
                                {{ activeTicket.exit_time }} s/d {{ activeTicket.return_time || 'Selesai Pulang' }}
                            </span>
                        </div>
                        <div class="pt-2 border-t border-slate-200">
                            <span class="text-[10px] text-slate-400 block mb-0.5">Keperluan:</span>
                            <p class="text-slate-700 italic font-sans leading-relaxed">{{ activeTicket.reason }}</p>
                        </div>
                    </div>

                    <!-- QR Code Asli Ter-generate (Bisa discan HP / Satpam) -->
                    <div class="text-center pt-1">
                        <div class="size-40 sm:size-44 mx-auto border-2 border-dashed border-slate-300 rounded-2xl p-2.5 flex flex-col items-center justify-center bg-white shadow-inner overflow-hidden">
                            <div v-if="qrSvgHtml" v-html="qrSvgHtml" class="flex items-center justify-center size-full" />
                            <div v-else class="text-slate-400 text-xs">Memuat QR Code...</div>
                        </div>
                        <span class="text-xs font-mono font-bold text-slate-800 mt-2 block tracking-wider">
                            {{ activeTicket.kode_izin }}
                        </span>
                        <span class="text-[10px] text-slate-400 mt-1 block leading-tight">
                            Tunjukkan QR code ini kepada petugas keamanan di Pos Satpam saat keluar maupun kembali.
                        </span>
                    </div>
                </div>

                <!-- Footer Modal (Selalu terlihat di layar) -->
                <div class="p-3.5 sm:p-4 bg-slate-50 border-t border-slate-200 flex justify-end shrink-0">
                    <button
                        type="button"
                        class="w-full py-2.5 sm:py-3 rounded-xl bg-slate-800 hover:bg-slate-900 active:bg-slate-950 text-white font-semibold text-xs sm:text-sm transition-colors cursor-pointer shadow-sm flex items-center justify-center gap-2"
                        @click="closeTicketModal"
                    >
                        <i class="pi pi-times text-xs" />
                        <span>Tutup Tiket</span>
                    </button>
                </div>
            </div>
        </div>

    </div>
</template>