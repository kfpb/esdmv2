<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, reactive, onMounted, onBeforeUnmount, nextTick } from 'vue';
import Toast from 'primevue/toast';
import { useToast } from 'primevue/usetoast';
import Button from 'primevue/button';
import { Html5Qrcode, Html5QrcodeSupportedFormats } from 'html5-qrcode';

defineOptions({ layout: AuthenticatedLayout });

const props = defineProps({
    todayScans: {
        type: Array,
        default: () => [],
    },
    guardInfo: {
        type: Object,
        default: () => ({ username: 'SC_KFPB', name: 'Petugas Keamanan' }),
    },
    todayDate: {
        type: String,
        required: true,
    },
});

const toast = useToast();

// Form Scan Input
const inputCode = ref('');
const inputRef = ref(null);
const isProcessing = ref(false);

// State Hasil Scan Terakhir
const lastScanResult = ref(null);
const scanError = ref('');

// State Kamera Live QR Scanner
const isCameraActive = ref(false);
const isCameraStarting = ref(false);
const cameraError = ref('');
let html5QrCode = null;
let lastScannedCode = '';
let lastScannedTimestamp = 0;
let removeRouterListener = null;
const currentFacingMode = ref('environment'); // 'environment' (kamera belakang) | 'user' (depan/webcam)

/**
 * Hentikan langsung semua track MediaStream di browser hardware.
 * Ini mencegah CPU / RAM memory leak saat navigasi berpindah halaman di Vue/Inertia.
 */
function hardStopCameraTracks() {
    try {
        const streamContainer = document.getElementById('qr-camera-stream');
        if (streamContainer) {
            const videos = streamContainer.querySelectorAll('video');
            videos.forEach((vid) => {
                if (vid.srcObject && typeof vid.srcObject.getTracks === 'function') {
                    vid.srcObject.getTracks().forEach((track) => {
                        try {
                            track.stop();
                        } catch (e) {}
                    });
                    vid.srcObject = null;
                }
            });
        }
    } catch (e) {
        console.error('Error hard-stopping camera streams:', e);
    }
}

async function startCamera() {
    cameraError.value = '';
    isCameraStarting.value = true;
    isCameraActive.value = true;
    await nextTick();

    try {
        if (!html5QrCode) {
            html5QrCode = new Html5Qrcode('qr-camera-stream', {
                formatsToSupport: [Html5QrcodeSupportedFormats.QR_CODE],
                verbose: false,
            });
        }

        // Konfigurasi optimal:
        // - fps: 10 (standar stabil seperti sistem lama, memberi waktu decode optimal di CPU)
        // - Tanpa fixed qrbox: memindai seluruh frame sensor kamera (full-area scanning)
        // - Tanpa paksaan aspectRatio 1.0: webcam laptop dapat menggunakan resolusi asli (16:9 / 4:3) tanpa distorsi pixel
        const config = {
            fps: 10,
        };

        await html5QrCode.start(
            { facingMode: currentFacingMode.value },
            config,
            (decodedText) => {
                onQrCodeScanned(decodedText);
            },
            () => {
                // Abaikan loop frame yang belum mendeteksi QR
            }
        );
    } catch (err) {
        cameraError.value = 'Tidak dapat mengakses sensor kamera. Pastikan izin kamera telah disetujui di peramban Anda.';
        isCameraActive.value = false;
        console.error('Kamera scanner error:', err);
    } finally {
        isCameraStarting.value = false;
    }
}

async function stopCamera() {
    hardStopCameraTracks();
    if (html5QrCode) {
        try {
            if (html5QrCode.isScanning) {
                await html5QrCode.stop();
            }
            html5QrCode.clear();
        } catch (err) {
            // Ignore error during cleanup
        } finally {
            html5QrCode = null;
        }
    }
    isCameraActive.value = false;
    isCameraStarting.value = false;
}

async function toggleCamera() {
    if (isCameraActive.value) {
        await stopCamera();
    } else {
        await startCamera();
    }
}

async function switchCamera() {
    currentFacingMode.value = currentFacingMode.value === 'environment' ? 'user' : 'environment';
    if (isCameraActive.value) {
        await stopCamera();
        await startCamera();
    }
}

function playScanBeep() {
    try {
        const audioCtx = new (window.AudioContext || window.webkitAudioContext)();
        const osc = audioCtx.createOscillator();
        const gain = audioCtx.createGain();
        osc.type = 'sine';
        osc.frequency.value = 880; // A5 beep
        gain.gain.value = 0.2;
        osc.connect(gain);
        gain.connect(audioCtx.destination);
        osc.start();
        osc.stop(audioCtx.currentTime + 0.12);
    } catch (e) {
        // AudioContext not supported/allowed
    }
}

function onQrCodeScanned(decodedText) {
    if (isProcessing.value) {
        return; // Jangan memproses jika sedang menunggu validasi sebelumnya
    }

    let raw = String(decodedText || '').trim();
    if (!raw) return;

    let code = raw;
    // Tangani token= atau code= dari URL QR
    if (raw.includes('token=')) {
        try {
            code = raw.split('token=')[1].split('&')[0];
        } catch (e) {
            code = raw;
        }
    } else if (raw.includes('code=')) {
        try {
            code = raw.split('code=')[1].split('&')[0];
        } catch (e) {
            code = raw;
        }
    }

    // Ekstrak pola IK-xxxx-xxx jika ada
    const match = raw.match(/IK-\d+-(\d+)/i);
    if (match) {
        code = match[0];
    }

    const now = Date.now();
    // Debounce 3.5 detik untuk kode yang sama agar tidak double submit
    if (code === lastScannedCode && now - lastScannedTimestamp < 3500) {
        return;
    }
    lastScannedCode = code;
    lastScannedTimestamp = now;

    playScanBeep();
    inputCode.value = code;
    submitScan(code);
}

// Lifecycle listeners: hentikan kamera secara instan saat navigasi Inertia terjadi
onMounted(() => {
    removeRouterListener = router.on('before', () => {
        hardStopCameraTracks();
        if (html5QrCode && html5QrCode.isScanning) {
            try {
                html5QrCode.stop();
            } catch (e) {}
        }
    });
});

onBeforeUnmount(() => {
    if (typeof removeRouterListener === 'function') {
        removeRouterListener();
    }
    hardStopCameraTracks();
    if (html5QrCode) {
        try {
            if (html5QrCode.isScanning) {
                html5QrCode.stop().catch(() => {}).finally(() => {
                    try { html5QrCode.clear(); } catch(e) {}
                    html5QrCode = null;
                });
            } else {
                html5QrCode.clear();
                html5QrCode = null;
            }
        } catch (e) {}
    }
    isCameraActive.value = false;
    isCameraStarting.value = false;
});

// Pemrosesan Scan (Bisa dari tembakan USB Scanner barcode atau input manual atau QR decode)
function submitScan(customCode = null) {
    const code = customCode || inputCode.value.trim();
    if (!code) {
        scanError.value = 'Silakan masukkan atau scan kode izin terlebih dahulu.';
        return;
    }

    scanError.value = '';
    isProcessing.value = true;

    fetch('/izin-keluar/scan/process', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            'Accept': 'application/json',
        },
        body: JSON.stringify({ code: code }),
    })
    .then(async (response) => {
        const data = await response.json();
        if (!response.ok) {
            throw new Error(data.message || 'Gagal memproses QR izin.');
        }
        return data;
    })
    .then((res) => {
        if (res.success) {
            lastScanResult.value = res;
            inputCode.value = '';
            toast.add({
                severity: 'success',
                summary: 'Scan Berhasil',
                detail: res.message,
                life: 4500,
            });
            // Refresh riwayat scan tabel
            router.reload({ only: ['todayScans'] });
        } else {
            scanError.value = res.message || 'Gagal memvalidasi izin.';
            toast.add({
                severity: 'error',
                summary: 'Gagal Scan',
                detail: res.message,
                life: 4000,
            });
        }
    })
    .catch((err) => {
        scanError.value = err.message || 'Terjadi kesalahan sistem saat memproses scan.';
        toast.add({
            severity: 'error',
            summary: 'Gagal Scan',
            detail: err.message,
            life: 4000,
        });
    })
    .finally(() => {
        isProcessing.value = false;
        // Kembalikan fokus kursor ke input agar siap scan berikutnya
        nextTick(() => {
            const inputEl = document.getElementById('inputCode');
            if (inputEl) inputEl.focus();
        });
    });
}
</script>

<template>
    <Head title="Scanner QR Pos Satpam - Izin Keluar Kantor KFPB" />
    <Toast position="top-right" />

    <div class="space-y-6 max-w-5xl mx-auto px-3 sm:px-6 py-2">

        <!-- ================= TOP HEADER BREADCRUMB & TITLE ================= -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm">
            <div class="space-y-1">
                <div class="flex items-center gap-2 text-xs font-medium text-slate-500">
                    <span class="text-slate-400">Pos Keamanan (Satpam)</span>
                    <span>/</span>
                    <span class="text-slate-800 font-semibold">Scanner QR Izin Keluar</span>
                </div>
                <h1 class="text-xl sm:text-2xl font-bold text-slate-900 tracking-tight flex items-center gap-2">
                    <i class="pi pi-qrcode text-emerald-600" />
                    <span>Scanner Gate Pass Pos Satpam</span>
                </h1>
                <p class="text-xs sm:text-sm text-slate-500">
                    Pencatatan real-time waktu keluar dan kembali pegawai di gerbang pos keamanan PT Kimia Farma Plant Banjaran.
                </p>
            </div>

            <!-- Petugas Info -->
            <div class="flex items-center gap-3 p-2.5 rounded-xl bg-slate-50 border border-slate-200/80 shrink-0 self-start sm:self-auto">
                <div class="size-9 rounded-xl bg-emerald-600 text-white flex items-center justify-center font-bold text-xs">
                    <i class="pi pi-shield" />
                </div>
                <div class="text-xs">
                    <span class="font-bold text-slate-800 block">{{ guardInfo.name }}</span>
                    <span class="font-mono text-slate-500 text-[11px] block">{{ guardInfo.username }} &bull; Pos Gerbang</span>
                </div>
            </div>
        </div>

        <!-- ================= MAIN SCANNER PANEL ================= -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
            <!-- Panel 1: Input Scanner & Kamera -->
            <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-5 sm:p-6 space-y-4">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3 gap-2">
                    <div class="flex items-center gap-2.5">
                        <div class="size-8 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center shrink-0">
                            <i class="pi pi-camera text-base" />
                        </div>
                        <div>
                            <h2 class="text-sm sm:text-base font-bold text-slate-900">
                                Pindai Tiket Izin Keluar
                            </h2>
                            <p class="text-xs text-slate-500">
                                Scan via kamera live, barcode scanner USB, atau input manual.
                            </p>
                        </div>
                    </div>

                    <!-- Tombol Nyalakan / Matikan Kamera -->
                    <Button
                        :label="isCameraActive ? 'Tutup Kamera' : 'Buka Kamera'"
                        :icon="isCameraActive ? 'pi pi-camera-slash' : 'pi pi-camera'"
                        :severity="isCameraActive ? 'danger' : 'success'"
                        size="small"
                        :outlined="!isCameraActive"
                        class="shrink-0 font-bold"
                        @click="toggleCamera"
                    />
                </div>

                <!-- Banner Pilihan Kamera Scanner Live (Ketika Kamera Belum Dibuka) -->
                <div v-if="!isCameraActive" class="p-4 rounded-xl border border-dashed border-emerald-300 bg-emerald-50/60 flex flex-col sm:flex-row items-center justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <div class="size-10 rounded-xl bg-emerald-600 text-white flex items-center justify-center shrink-0 shadow-xs">
                            <i class="pi pi-camera text-lg" />
                        </div>
                        <div>
                            <div class="font-bold text-xs sm:text-sm text-slate-800">Pemindai Kamera Live (Webcam / HP)</div>
                            <p class="text-[11px] text-slate-500">Nyalakan sensor kamera untuk memindai tiket QR izin pegawai secara instan.</p>
                        </div>
                    </div>
                    <Button
                        label="Buka Kamera Scanner"
                        icon="pi pi-video"
                        severity="success"
                        size="small"
                        class="shrink-0 w-full sm:w-auto font-bold cursor-pointer"
                        @click="startCamera"
                    />
                </div>

                <!-- Tampilan Kamera Scanner Live (Ketika Dibuka) -->
                <div v-show="isCameraActive" class="space-y-2.5">
                    <div class="relative rounded-2xl overflow-hidden bg-slate-950 border-2 border-emerald-500 shadow-md">
                        <!-- Isolasi DOM murni untuk Html5Qrcode (steril tanpa child node Vue agar tidak crash insertBefore) -->
                        <div id="qr-camera-stream" class="w-full min-h-[280px] bg-black"></div>

                        <!-- Overlay Loading Kamera (Sibling di luar qr-camera-stream) -->
                        <div v-if="isCameraStarting" class="absolute inset-0 flex items-center justify-center bg-slate-950/80 z-20 text-white text-xs">
                            <span class="flex items-center gap-2 text-slate-300">
                                <i class="pi pi-spin pi-spinner text-emerald-400" />
                                <span>Menghubungkan ke sensor kamera...</span>
                            </span>
                        </div>

                        <!-- Scan Crosshair / Viewfinder Target Box Overlay -->
                        <div v-if="!isCameraStarting" class="pointer-events-none absolute inset-0 flex flex-col items-center justify-center z-10">
                            <div class="size-48 sm:size-56 border-2 border-emerald-400/90 rounded-2xl relative shadow-[0_0_0_9999px_rgba(0,0,0,0.3)]">
                                <span class="absolute -top-1 -left-1 size-4 border-t-4 border-l-4 border-emerald-400 rounded-tl" />
                                <span class="absolute -top-1 -right-1 size-4 border-t-4 border-r-4 border-emerald-400 rounded-tr" />
                                <span class="absolute -bottom-1 -left-1 size-4 border-b-4 border-l-4 border-emerald-400 rounded-bl" />
                                <span class="absolute -bottom-1 -right-1 size-4 border-b-4 border-r-4 border-emerald-400 rounded-br" />
                                <div class="absolute inset-x-2 top-1/2 h-0.5 bg-emerald-400 shadow-[0_0_8px_#34d399] animate-pulse" />
                            </div>
                            <span class="text-[11px] font-semibold text-white bg-slate-900/80 px-3 py-1 rounded-full mt-3 backdrop-blur-xs border border-white/20">
                                Arahkan kamera ke QR Code Tiket Izin
                            </span>
                        </div>
                    </div>

                    <!-- Tombol Pengaturan Kamera (Ganti Kamera & Hentikan) -->
                    <div class="flex items-center justify-between text-xs pt-0.5">
                        <button
                            type="button"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold transition-colors cursor-pointer text-xs"
                            @click="switchCamera"
                        >
                            <i class="pi pi-sync text-[11px]" />
                            <span>Ganti Kamera Depan/Belakang</span>
                        </button>

                        <button
                            type="button"
                            class="text-rose-600 hover:text-rose-800 font-semibold cursor-pointer text-xs"
                            @click="stopCamera"
                        >
                            Hentikan Kamera
                        </button>
                    </div>
                </div>

                <!-- Alert Error Kamera jika ada kendala izin kamera -->
                <div v-if="cameraError" class="p-3.5 rounded-xl bg-amber-50 border border-amber-200 text-amber-900 text-xs flex items-start gap-2">
                    <i class="pi pi-exclamation-triangle text-amber-600 text-base shrink-0 mt-0.5" />
                    <span>{{ cameraError }}</span>
                </div>

                <!-- Form Input Manual / Tembakan Barcode USB (Fungsi Lama 100% Tetap Berjalan) -->
                <form @submit.prevent="submitScan()" class="space-y-4">
                    <div>
                        <label for="inputCode" class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                            Kode Tiket / QR Code Izin
                        </label>
                        <div class="relative">
                            <input
                                id="inputCode"
                                v-model="inputCode"
                                type="text"
                                autofocus
                                placeholder="Contoh: IK-20261104-001 atau ID izin"
                                class="w-full pl-10 pr-4 py-3 rounded-xl border border-slate-300 text-sm font-mono font-bold text-slate-900 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-emerald-500/30 focus:border-emerald-600 transition-all uppercase"
                                :disabled="isProcessing"
                            />
                            <i class="pi pi-qrcode absolute left-3.5 top-3.5 text-slate-400 text-base" />
                        </div>
                        <span class="text-[11px] text-slate-400 block mt-1">
                            Tip: Kode tiket dari kamera atau scanner USB akan terisi otomatis di sini.
                        </span>
                    </div>

                    <!-- Alert Error jika ada -->
                    <div v-if="scanError" class="p-3.5 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-xs flex items-start gap-2">
                        <i class="pi pi-exclamation-circle text-rose-600 text-base shrink-0 mt-0.5" />
                        <span>{{ scanError }}</span>
                    </div>

                    <button
                        type="submit"
                        :disabled="isProcessing || !inputCode.trim()"
                        class="w-full inline-flex items-center justify-center gap-2 py-3 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs sm:text-sm shadow-sm transition-all disabled:opacity-50 disabled:cursor-not-allowed cursor-pointer"
                    >
                        <i v-if="isProcessing" class="pi pi-spin pi-spinner text-sm" />
                        <i v-else class="pi pi-check text-sm" />
                        <span>{{ isProcessing ? 'Memvalidasi...' : 'Catat Izin (Validasi Pos Satpam)' }}</span>
                    </button>
                </form>

                <!-- Panduan Satpam -->
                <div class="p-3.5 rounded-xl bg-slate-50 border border-slate-200/70 text-xs text-slate-600 space-y-1.5">
                    <div class="font-bold text-slate-800 flex items-center gap-1.5">
                        <i class="pi pi-info-circle text-blue-600" />
                        <span>Panduan Operasional Pos Satpam:</span>
                    </div>
                    <ul class="list-disc list-inside space-y-1 text-[11px] text-slate-500 pl-1">
                        <li><b>Scan Pertama:</b> Pegawai keluar gerbang &rarr; mencatat jam keluar aktual.</li>
                        <li><b>Scan Kedua:</b> Pegawai kembali masuk gerbang &rarr; mencatat jam kembali aktual (selesai).</li>
                        <li><b>Datang Terlambat:</b> Pegawai tiba di pos satpam &rarr; satu kali scan langsung selesai mencatat jam masuk.</li>
                    </ul>
                </div>
            </div>

            <!-- Panel 2: Tampilan Hasil Scan Terkini -->
            <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-5 sm:p-6 flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between border-b border-slate-100 pb-3 mb-4">
                        <h2 class="text-sm sm:text-base font-bold text-slate-900 flex items-center gap-2">
                            <i class="pi pi-id-card text-[#005BAC]" />
                            <span>Hasil Validasi Tiket Terkini</span>
                        </h2>
                        <span v-if="lastScanResult" class="px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200 text-[10px] font-bold">
                            Berhasil Dicatat
                        </span>
                    </div>

                    <!-- Ketika Belum Ada Scan -->
                    <div v-if="!lastScanResult" class="text-center py-12 text-slate-400 text-xs space-y-2">
                        <div class="size-16 rounded-2xl bg-slate-100 mx-auto flex items-center justify-center text-slate-400">
                            <i class="pi pi-barcode text-3xl" />
                        </div>
                        <p class="font-medium">Menunggu pemindaian tiket QR pegawai...</p>
                        <p class="text-[11px] text-slate-400 max-w-xs mx-auto">
                            Hasil pemeriksaan identitas dan waktu keluar/kembali akan tampil di sini secara instan.
                        </p>
                    </div>

                    <!-- Tampilan Kartu Hasil Scan Sukses -->
                    <div v-else class="space-y-4">
                        <div class="p-3.5 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-900 text-xs font-semibold flex items-center gap-2">
                            <i class="pi pi-check-circle text-emerald-600 text-base" />
                            <span>{{ lastScanResult.message }}</span>
                        </div>

                        <div class="p-4 rounded-xl border border-dashed border-slate-200 bg-slate-50 space-y-2 text-xs">
                            <div class="flex justify-between">
                                <span class="text-slate-400">Kode Tiket:</span>
                                <span class="font-mono font-bold text-slate-900">{{ lastScanResult.data?.kode_izin }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-slate-400">Nama Pegawai:</span>
                                <span class="font-bold text-slate-900 text-right">{{ lastScanResult.data?.nama_pegawai }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-slate-400">NIP / Unit:</span>
                                <span class="font-mono text-slate-700">{{ lastScanResult.data?.nip }} &bull; {{ lastScanResult.data?.bagian }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-slate-400">Waktu Aktual Dicatat:</span>
                                <span class="font-mono font-bold text-emerald-700 text-sm">{{ lastScanResult.data?.actual_time }} WIB</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-slate-400">Status Terbaru:</span>
                                <span class="font-bold text-[#005BAC]">{{ lastScanResult.data?.status_label }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="text-[11px] text-slate-400 text-center pt-4 border-t border-slate-100">
                    Pos Pengamanan & Satpam &bull; PT Kimia Farma Plant Banjaran
                </div>
            </div>
        </div>

        <!-- ================= TABEL RIWAYAT SCAN HARI INI ================= -->
        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-5 sm:p-6 space-y-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2.5">
                    <i class="pi pi-list text-slate-700" />
                    <h3 class="text-sm sm:text-base font-bold text-slate-900 tracking-tight">
                        Riwayat Scan Izin Keluar Hari Ini
                    </h3>
                </div>
                <span class="text-xs text-slate-400 font-mono">
                    Tanggal: {{ todayDate }}
                </span>
            </div>

            <!-- Empty State -->
            <div v-if="todayScans.length === 0" class="text-center py-10 text-slate-400 text-xs">
                <i class="pi pi-inbox text-3xl mb-2 block text-slate-300" />
                <span>Belum ada pegawai yang discan keluar/kembali hari ini.</span>
            </div>

            <!-- Table Riwayat Scan Satpam -->
            <div v-else class="overflow-x-auto">
                <table class="w-full text-left text-xs border-collapse">
                    <thead>
                        <tr class="bg-slate-50/80 border-b border-slate-200 text-slate-600 font-bold uppercase text-[10px] tracking-wider">
                            <th class="py-2.5 px-3">Kode Tiket</th>
                            <th class="py-2.5 px-3">Pegawai</th>
                            <th class="py-2.5 px-3">Rencana Waktu</th>
                            <th class="py-2.5 px-3">Scan Keluar</th>
                            <th class="py-2.5 px-3">Scan Kembali</th>
                            <th class="py-2.5 px-3 text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-slate-700">
                        <tr v-for="scan in todayScans" :key="scan.id" class="hover:bg-slate-50/60 transition-colors">
                            <td class="py-3 px-3 font-mono font-bold text-slate-800 whitespace-nowrap">
                                {{ scan.kode_izin }}
                            </td>
                            <td class="py-3 px-3">
                                <div class="font-bold text-slate-900">{{ scan.nama_pegawai }}</div>
                                <div class="text-[11px] text-slate-500 font-mono">{{ scan.nip }} &bull; {{ scan.bagian }}</div>
                            </td>
                            <td class="py-3 px-3 font-mono text-slate-600 whitespace-nowrap">
                                {{ scan.exit_time }} s/d {{ scan.return_time || 'Pulang' }}
                            </td>
                            <td class="py-3 px-3 font-mono text-slate-800 font-semibold whitespace-nowrap">
                                <span v-if="scan.actual_exit_time" class="text-emerald-700">{{ scan.actual_exit_time }}</span>
                                <span v-else class="text-slate-400">-</span>
                            </td>
                            <td class="py-3 px-3 font-mono text-slate-800 font-semibold whitespace-nowrap">
                                <span v-if="scan.actual_return_time" class="text-emerald-700">{{ scan.actual_return_time }}</span>
                                <span v-else class="text-slate-400">-</span>
                            </td>
                            <td class="py-3 px-3 text-center whitespace-nowrap">
                                <span
                                    v-if="scan.approval_status === 3"
                                    class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-blue-50 text-blue-700 border border-blue-200"
                                >
                                    <i class="pi pi-external-link text-[9px]" />
                                    <span>Sedang Di Luar</span>
                                </span>
                                <span
                                    v-else-if="scan.approval_status === 4"
                                    class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-slate-100 text-slate-700 border border-slate-200"
                                >
                                    <i class="pi pi-check-circle text-[9px]" />
                                    <span>Selesai</span>
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</template>

<style>
#qr-camera-stream video {
    width: 100% !important;
    max-height: 280px !important;
    object-fit: cover !important;
    border-radius: 0.875rem;
}
#qr-camera-stream {
    border: none !important;
}
</style>

