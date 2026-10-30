<script setup>
import { ref, computed } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AttendanceMetricsCard from '@/Components/AttendanceMetricsCard.vue';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Tag from 'primevue/tag';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import Avatar from 'primevue/avatar';
import Chart from 'primevue/chart';

defineOptions({ layout: AuthenticatedLayout });

const props = defineProps({
    appName: {
        type: String,
        default: 'E-SDM KFPB',
    },
});

// Filter states
const search = ref('');
const statusFilter = ref(null);
const unitFilter = ref(null);

const unitOptions = ref([
    { label: 'Semua Unit Kerja', value: null },
    { label: 'Divisi Teknologi Informasi', value: 'TI' },
    { label: 'Divisi SDM & Organisasi', value: 'SDM' },
    { label: 'Divisi Keuangan & Akuntansi', value: 'Keuangan' },
    { label: 'Divisi Operasional & Logistik', value: 'Ops' },
]);

// Attendance dummy data
const attendances = ref([
    {
        id: 'EMP-0104',
        name: 'Rian Pratama, S.Kom',
        nip: '199208152019031002',
        division: 'Divisi Teknologi Informasi',
        role: 'Senior Software Engineer',
        timeIn: '07:42 WIB',
        timeOut: '17:05 WIB',
        location: 'Kantor Pusat (Lt. 4)',
        method: 'Biometrik Face-ID',
        status: 'Hadir',
        statusSeverity: 'success',
        avatarBg: '#696cff',
    },
    {
        id: 'EMP-0219',
        name: 'Siti Sarah Nabila, M.M',
        nip: '199403122020122005',
        division: 'Divisi SDM & Organisasi',
        role: 'HR Specialist',
        timeIn: '07:55 WIB',
        timeOut: '-',
        location: 'Kantor Pusat (Lt. 2)',
        method: 'Mobile GPS Presensi',
        status: 'Hadir',
        statusSeverity: 'success',
        avatarBg: '#22c55e',
    },
    {
        id: 'EMP-0312',
        name: 'Budi Kurniawan, S.E',
        nip: '198910042018011003',
        division: 'Divisi Keuangan & Akuntansi',
        role: 'Supervisor Pajak',
        timeIn: '08:18 WIB',
        timeOut: '-',
        location: 'Kantor Pusat (Lt. 3)',
        method: 'Fingerprint Machine',
        status: 'Terlambat',
        statusSeverity: 'warn',
        avatarBg: '#f59e0b',
    },
    {
        id: 'EMP-0401',
        name: 'dr. Farah Anggraini',
        nip: '199105202021022004',
        division: 'Divisi Operasional & Logistik',
        role: 'Dokter Poliklinik KFPB',
        timeIn: '-',
        timeOut: '-',
        location: 'Izin Sakit (Surat Terlampir)',
        method: 'Formulir Digital',
        status: 'Izin',
        statusSeverity: 'info',
        avatarBg: '#0ea5e9',
    },
    {
        id: 'EMP-0518',
        name: 'Dedi Wahyudi, S.T',
        nip: '199511082022031001',
        division: 'Divisi Teknologi Informasi',
        role: 'Cloud Infrastructure Eng',
        timeIn: '-',
        timeOut: '-',
        location: 'Cuti Tahunan (Disetujui)',
        method: 'Layanan Mandiri Pegawai',
        status: 'Cuti',
        statusSeverity: 'info',
        avatarBg: '#8b5cf6',
    },
    {
        id: 'EMP-0622',
        name: 'Agus Setiawan',
        nip: '199607142023011004',
        division: 'Divisi Operasional & Logistik',
        role: 'Staf Administrasi Gudang',
        timeIn: '-',
        timeOut: '-',
        location: 'Belum Melakukan Presensi',
        method: '-',
        status: 'Belum Absen',
        statusSeverity: 'danger',
        avatarBg: '#ef4444',
    },
]);

// Filtered dataset
const filteredAttendances = computed(() => {
    return attendances.value.filter((item) => {
        const matchSearch =
            search.value === '' ||
            item.name.toLowerCase().includes(search.value.toLowerCase()) ||
            item.nip.includes(search.value) ||
            item.division.toLowerCase().includes(search.value.toLowerCase());
        const matchStatus = statusFilter.value === null || item.status === statusFilter.value;
        return matchSearch && matchStatus;
    });
});

// Mini Weekly Trend Chart
const weeklyTrendData = ref({
    labels: ['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'],
    datasets: [
        {
            label: 'Tepat Waktu (%)',
            data: [94, 96, 92, 95, 91, 98],
            borderColor: '#696cff',
            backgroundColor: 'rgba(105, 108, 255, 0.12)',
            fill: true,
            tension: 0.35,
            pointRadius: 4,
            pointBackgroundColor: '#696cff',
        },
    ],
});

const chartOptions = ref({
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: { display: false },
    },
    scales: {
        y: {
            min: 80,
            max: 100,
            grid: { color: 'rgba(0,0,0,0.04)' },
            ticks: {
                callback: (v) => v + '%',
                font: { size: 10 },
            },
        },
        x: {
            grid: { display: false },
            ticks: { font: { size: 10 } },
        },
    },
});
</script>

<template>
    <div class="space-y-6">

        <!-- ================= WELCOME HERO BANNER (Sneat Style) ================= -->
        <div
            class="relative bg-white rounded-xl border border-slate-200/70 p-6 shadow-[0_2px_6px_0_rgba(67,89,113,0.06)] overflow-hidden flex flex-col md:flex-row items-start md:items-center justify-between gap-4"
        >
            <div class="space-y-1.5 max-w-xl">
                <div class="inline-flex items-center gap-2 px-2.5 py-1 rounded-md bg-[#696cff]/10 text-[#696cff] text-xs font-semibold">
                    <i class="pi pi-calendar text-[11px]" />
                    <span>Jumat, 30 Oktober 2026 • Shift Pagi</span>
                </div>
                <h2 class="text-xl sm:text-2xl font-bold text-slate-800 tracking-tight">
                    Dashboard Monitoring Presensi &amp; SDM
                </h2>
                <p class="text-xs sm:text-sm text-slate-500 leading-relaxed">
                    Pantau kehadiran real-time 160 pegawai aktif di lingkungan Kantor Pusat &amp; Unit Operasional KFPB.
                </p>
            </div>

            <!-- Quick Action Buttons -->
            <div class="flex items-center gap-2.5 shrink-0 w-full sm:w-auto">
                <Button
                    label="Tarik Laporan"
                    icon="pi pi-download"
                    severity="secondary"
                    outlined
                    size="small"
                    class="!rounded-lg !text-xs !font-semibold !border-slate-300"
                />
                <Button
                    label="Input Izin Manual"
                    icon="pi pi-plus"
                    size="small"
                    class="!rounded-lg !text-xs !font-semibold !bg-[#696cff] !border-[#696cff] hover:!bg-[#5f61e6] shadow-[0_2px_6px_rgba(105,108,255,0.35)]"
                />
            </div>
        </div>

        <!-- ================= 4 ATTENDANCE METRIC CARDS ================= -->
        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 sm:gap-5">
            <!-- 1. Hadir Hari Ini -->
            <AttendanceMetricsCard
                title="Hadir Hari Ini"
                :count="142"
                :total="160"
                variant="success"
                icon="pi pi-check-circle"
                badgeText="+4.2%"
                description="88.75% dari total pegawai aktif"
            />

            <!-- 2. Terlambat -->
            <AttendanceMetricsCard
                title="Terlambat"
                :count="8"
                variant="warning"
                icon="pi pi-clock"
                badgeText="Disiplin 95%"
                description="Masuk lewat dari pukul 08:00 WIB"
            />

            <!-- 3. Izin / Cuti -->
            <AttendanceMetricsCard
                title="Izin / Cuti"
                :count="6"
                variant="info"
                icon="pi pi-calendar-times"
                badgeText="3 Pending"
                description="4 Cuti tahunan, 2 izin sakit"
            />

            <!-- 4. Belum Absen -->
            <AttendanceMetricsCard
                title="Belum Absen"
                :count="4"
                variant="danger"
                icon="pi pi-user-minus"
                badgeText="Batas 09:30"
                description="Peringatan dikirim otomatis via WA"
            />
        </div>

        <!-- ================= INSIGHT & MONITORING SECTION ================= -->
        <div class="grid grid-cols-1 lg:grid-cols-4 gap-5">

            <!-- Weekly Compliance Sparkline Card -->
            <div
                class="lg:col-span-1 bg-white rounded-xl border border-slate-200/70 p-5 shadow-[0_2px_6px_0_rgba(67,89,113,0.06)] flex flex-col justify-between"
            >
                <div>
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-xs font-bold uppercase tracking-wider text-slate-400">Tren Disiplin</span>
                        <span class="text-xs font-bold text-emerald-600 bg-emerald-50 px-2 py-0.5 rounded">94.8%</span>
                    </div>
                    <h3 class="text-base font-bold text-slate-800">Kepatuhan Jam Kerja</h3>
                    <p class="text-xs text-slate-500 mt-1">Rata-rata kehadiran tepat waktu 6 hari terakhir.</p>
                </div>

                <div class="h-40 my-3">
                    <Chart type="line" :data="weeklyTrendData" :options="chartOptions" class="h-full" />
                </div>

                <div class="pt-3 border-t border-slate-100 flex items-center justify-between text-xs text-slate-500">
                    <span>Shift Normal: 08:00 - 17:00</span>
                    <span class="text-[#696cff] font-semibold cursor-pointer hover:underline">Detail KPI</span>
                </div>
            </div>

            <!-- ================= CLEAN BORDERLESS DATA TABLE ================= -->
            <div
                class="lg:col-span-3 bg-white rounded-xl border border-slate-200/70 shadow-[0_2px_6px_0_rgba(67,89,113,0.06)] overflow-hidden flex flex-col"
            >
                <!-- Card Table Header -->
                <div class="p-5 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <div>
                        <h3 class="font-bold text-slate-800 text-base tracking-tight">
                            Log Presensi Real-Time
                        </h3>
                        <p class="text-xs text-slate-400 mt-0.5">
                            Menampilkan riwayat check-in/out pegawai hari ini
                        </p>
                    </div>

                    <!-- Search & Filter Controls -->
                    <div class="flex items-center gap-2 flex-wrap">
                        <div class="relative">
                            <i class="pi pi-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs" />
                            <InputText
                                v-model="search"
                                placeholder="Cari nama / NIP..."
                                class="!pl-8 !py-1.5 !text-xs !w-48 sm:!w-56 !rounded-lg !border-slate-200"
                            />
                        </div>

                        <Select
                            v-model="unitFilter"
                            :options="unitOptions"
                            optionLabel="label"
                            optionValue="value"
                            placeholder="Unit Kerja"
                            class="!text-xs !rounded-lg !border-slate-200"
                        />
                    </div>
                </div>

                <!-- Borderless & Seamless PrimeVue DataTable -->
                <div class="flex-1 overflow-x-auto">
                    <DataTable
                        :value="filteredAttendances"
                        dataKey="id"
                        paginator
                        :rows="5"
                        :rowsPerPageOptions="[5, 10, 20]"
                        class="clean-datatable"
                        tableStyle="min-width: 48rem"
                    >
                        <!-- Pegawai (Avatar + Nama & NIP) -->
                        <Column header="Pegawai">
                            <template #body="{ data }">
                                <div class="flex items-center gap-3 py-1">
                                    <Avatar
                                        :label="data.name.charAt(0)"
                                        shape="circle"
                                        class="shrink-0 size-8 !text-xs font-bold !text-white"
                                        :style="{ backgroundColor: data.avatarBg }"
                                    />
                                    <div class="min-w-0">
                                        <p class="text-xs font-bold text-slate-800 truncate leading-snug">
                                            {{ data.name }}
                                        </p>
                                        <p class="text-[11px] text-slate-400 truncate leading-snug font-mono">
                                            {{ data.nip }} • {{ data.role }}
                                        </p>
                                    </div>
                                </div>
                            </template>
                        </Column>

                        <!-- Unit Kerja -->
                        <Column header="Unit Kerja" field="division">
                            <template #body="{ data }">
                                <span class="text-xs text-slate-600 font-medium">
                                    {{ data.division }}
                                </span>
                            </template>
                        </Column>

                        <!-- Jam Masuk -->
                        <Column header="Masuk">
                            <template #body="{ data }">
                                <div class="text-xs font-semibold text-slate-700">
                                    {{ data.timeIn }}
                                </div>
                                <div class="text-[10px] text-slate-400">
                                    {{ data.method }}
                                </div>
                            </template>
                        </Column>

                        <!-- Status Badge -->
                        <Column header="Status">
                            <template #body="{ data }">
                                <Tag
                                    :value="data.status"
                                    :severity="data.statusSeverity"
                                    class="!text-[10px] !font-bold !px-2.5 !py-0.5 !rounded-md"
                                />
                            </template>
                        </Column>

                        <!-- Lokasi / Verifikasi -->
                        <Column header="Keterangan / Lokasi">
                            <template #body="{ data }">
                                <span class="text-xs text-slate-500 truncate block max-w-xs">
                                    {{ data.location }}
                                </span>
                            </template>
                        </Column>

                        <!-- Aksi -->
                        <Column header="" headerStyle="width: 3rem">
                            <template #body>
                                <Button
                                    icon="pi pi-ellipsis-v"
                                    text
                                    rounded
                                    size="small"
                                    severity="secondary"
                                    class="!size-7 !text-slate-400 hover:!text-slate-600"
                                />
                            </template>
                        </Column>

                        <template #empty>
                            <div class="text-center py-8 text-xs text-slate-400">
                                Tidak ada data presensi yang sesuai kriteria filter.
                            </div>
                        </template>
                    </DataTable>
                </div>
            </div>
        </div>

    </div>
</template>

<style scoped>
/* Styling borderless clean table khusus Sneat Dashboard aesthetic */
:deep(.clean-datatable.p-datatable) {
    border: none;
}
:deep(.clean-datatable .p-datatable-thead > tr > th) {
    background-color: #fafafa;
    color: #64748b;
    font-size: 0.75rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    padding: 0.75rem 1rem;
    border-bottom: 1px solid #f1f5f9;
    border-top: none;
}
:deep(.clean-datatable .p-datatable-tbody > tr > td) {
    padding: 0.85rem 1rem;
    border-bottom: 1px solid #f8fafc;
}
:deep(.clean-datatable .p-datatable-tbody > tr:hover) {
    background-color: #f8fafc;
}
:deep(.clean-datatable .p-paginator) {
    padding: 0.75rem 1rem;
    border-top: 1px solid #f1f5f9;
    background: transparent;
    font-size: 0.75rem;
}
</style>
