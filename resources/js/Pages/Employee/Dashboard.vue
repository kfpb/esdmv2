<script setup>
import { ref, computed, watch, onMounted, onUnmounted, nextTick } from 'vue';
import { useForm, usePage, Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Button from 'primevue/button';
import Select from 'primevue/select';
import Tag from 'primevue/tag';
import InputText from 'primevue/inputtext';
import { useToast } from 'primevue/usetoast';
import Toast from 'primevue/toast';

defineOptions({ layout: AuthenticatedLayout });

const props = defineProps({
    todayAttendance:   { type: Object, default: null },
    faceCheckIn:       { type: String, default: '-' },
    faceCheckOut:      { type: String, default: '-' },
    activeLeave:       { type: Object, default: null },
    birthdayData:      { type: Object, default: () => ({ isBirthday: false, employeeName: '', gender: 'Male' }) },
    topLateToday:      { type: Array,  default: () => [] },
    topLateBiometric:  { type: Array,  default: () => [] },
    recentAttendances: { type: [Object, Array], default: () => ({ data: [] }) },
    workShifts:        { type: Array,  default: () => [] },
    frequentShiftId:   { type: Number, default: null },
    monthSummary:      { type: Object, default: () => ({}) },
    geofenceZones:     { type: Array,  default: () => [] },
    todayLabel:        { type: String, default: '' },
    employee:          { type: Object, default: null },
});

const authPage     = usePage();
const toast        = useToast();
const employeeNip  = computed(() => props.employee?.nip || authPage.props.auth?.user?.employee_nip || '-');
const rawEmployeeName = computed(() => props.employee?.name || authPage.props.auth?.user?.name || 'Pegawai KFPB');

function toTitleCase(text) {
    if (!text || typeof text !== 'string') return '';
    return text
        .toLowerCase()
        .split(' ')
        .map(word => {
            if (word === '&' || word === 'dan' || word === 'di' || word === 'ke' || word === 'dari') {
                return word === '&' ? '&' : word;
            }
            return word.charAt(0).toUpperCase() + word.slice(1);
        })
        .join(' ')
        .trim();
}

const employeeName = computed(() => toTitleCase(rawEmployeeName.value));

const greetingName = computed(() => {
    const parts = employeeName.value.split(' ').filter(Boolean);
    if (parts.length > 2) {
        return parts.slice(0, 2).join(' ');
    }
    return employeeName.value;
});

const formattedPosition = computed(() => toTitleCase(props.employee?.position || 'Staf Pegawai'));
const formattedDivision = computed(() => toTitleCase(props.employee?.division || 'Plant Banjaran'));

// Daftar presensi terpaginasi (maks 3 per halaman)
const attendanceList = computed(() => {
    if (Array.isArray(props.recentAttendances)) return props.recentAttendances;
    return props.recentAttendances?.data || [];
});

// Flash notification dari backend (reaktif memantau setiap respons Inertia)
watch(
    () => authPage.props.flash?.success,
    (msg) => {
        if (msg) {
            toast.add({
                severity: 'success',
                summary: 'Presensi Berhasil',
                detail: msg,
                life: 6000,
            });
        }
    },
    { immediate: true }
);

watch(
    () => authPage.props.flash?.error,
    (msg) => {
        if (msg) {
            toast.add({
                severity: 'error',
                summary: 'Perhatian',
                detail: msg,
                life: 8000,
            });
        }
    },
    { immediate: true }
);

// ===== State Geolocation & Leaflet Peta =====
const gpsLat      = ref(null);
const gpsLon      = ref(null);
const gpsReady    = ref(false);
const insideZone  = ref(false);
const currentZoneName = ref(null);
const gpsError    = ref('');
const isLocating  = ref(false);
const isDevMode   = ref(false);
const isSimulated = ref(false);

const mapCheckInContainer  = ref(null);
const mapCheckOutContainer = ref(null);

let leafletMapIn       = null;
let leafletMapOut      = null;
let markerIn           = null;
let markerOut          = null;
let watchId            = null;
let activeGeoJsonGroup = null;

// Accordion Keterlambatan Absen Wajah
const showLateBiometric = ref(false);

// ===== State Jam Realtime Digital =====
const realtimeClock = ref('--:--:--');
let clockInterval   = null;

// ===== Formulir Presensi =====
const checkInForm = useForm({
    work_shift_id: props.frequentShiftId,
    work_mode:     'WFO',
    latitude:      '',
    longitude:     '',
});

const workModeOptions = [
    { label: 'Work From Office (WFO)', value: 'WFO' },
    { label: 'Flexi Time',             value: 'FLEXI' },
    { label: 'PKL / Magang',           value: 'PKL' },
];

const selectedShift = computed(() =>
    props.workShifts.find(s => s.id === checkInForm.work_shift_id) ?? null
);

const checkOutForm = useForm({
    latitude:  '',
    longitude: '',
    notes:     '',
});

// Status Alur Presensi Hari Ini
const attendanceStatus = computed(() => {
    if (props.activeLeave) return 'leave';
    if (!props.todayAttendance) return 'none';
    if (props.todayAttendance.is_present_in && !props.todayAttendance.check_out_time) return 'in';
    if (props.todayAttendance.is_present_in && props.todayAttendance.check_out_time)  return 'complete';
    return 'none';
});

// Badge Lokasi
const locationBadgeLabel = computed(() => {
    if (isLocating.value) return 'Mendeteksi lokasi…';
    if (!gpsReady.value) return 'Menunggu sinyal GPS…';
    if (gpsError.value)  return gpsError.value;
    if (insideZone.value) {
        return currentZoneName.value ? `DI DALAM: ${currentZoneName.value.toUpperCase()}` : 'DI DALAM AREA KFPB';
    }
    return 'DI LUAR AREA KERJA';
});

const locationBadgeSeverity = computed(() => {
    if (isLocating.value || !gpsReady.value) return 'secondary';
    if (gpsError.value) return 'warn';
    return insideZone.value ? 'success' : 'danger';
});

// ===== Perhitungan Jarak Haversine (meter) =====
function haversineDistance(lat1, lon1, lat2, lon2) {
    const R    = 6371000;
    const dLat = ((lat2 - lat1) * Math.PI) / 180;
    const dLon = ((lon2 - lon1) * Math.PI) / 180;
    const a =
        Math.sin(dLat / 2) ** 2 +
        Math.cos((lat1 * Math.PI) / 180) * Math.cos((lat2 * Math.PI) / 180) * Math.sin(dLon / 2) ** 2;
    return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
}

// ===== Algoritma Ray-Casting Point-in-Polygon =====
function pointInPolygon(lat, lon, polygon) {
    let inside = false;
    const count = polygon.length;
    for (let i = 0, j = count - 1; i < count; j = i++) {
        const xi = polygon[i][0];
        const yi = polygon[i][1];
        const xj = polygon[j][0];
        const yj = polygon[j][1];

        const intersect = ((yi > lon) !== (yj > lon)) &&
            (lat < ((xj - xi) * (lon - yi)) / (yj - yi) + xi);
        if (intersect) inside = !inside;
    }
    return inside;
}

// ===== Cek Titik di dalam Zona Gedung KFPB =====
const defaultKfpbCentroid = { lat: -7.039057, lon: 107.5960505, radius: 300 };

function checkInsideZones(lat, lon) {
    currentZoneName.value = null;

    const zones = (props.geofenceZones && props.geofenceZones.length > 0)
        ? props.geofenceZones
        : [];

    for (const zone of zones) {
        if (zone.koordinat && zone.koordinat.length >= 3) {
            if (pointInPolygon(lat, lon, zone.koordinat)) {
                currentZoneName.value = zone.label || zone.name;
                return true;
            }
        }

        const toleranceMeters = Math.max(zone.radius || 50, 60);

        if (zone.latitude && zone.longitude) {
            if (haversineDistance(lat, lon, zone.latitude, zone.longitude) <= toleranceMeters) {
                currentZoneName.value = zone.label || zone.name;
                return true;
            }
        }

        if (zone.koordinat) {
            for (const pt of zone.koordinat) {
                if (haversineDistance(lat, lon, pt[0], pt[1]) <= toleranceMeters) {
                    currentZoneName.value = zone.label || zone.name;
                    return true;
                }
            }
        }
    }

    if (zones.length === 0) {
        if (haversineDistance(lat, lon, defaultKfpbCentroid.lat, defaultKfpbCentroid.lon) <= defaultKfpbCentroid.radius) {
            currentZoneName.value = 'Plant Banjaran (Perimeter Pabrik)';
            return true;
        }
    }

    return false;
}

// Tambahkan Poligon Gedung ke Leaflet Layer
function addBuildingPolygons(lmap) {
    if (!lmap || typeof L === 'undefined') return null;

    const geoGroup = L.geoJSON(null, {
        onEachFeature: (feature, layer) => {
            if (feature.properties?.label) {
                layer.bindPopup(`<b>${feature.properties.label}</b><br><span style="font-size:11px;color:#64748b;">Area Presensi Resmi KFPB</span>`);
            }
        },
        style: {
            color: '#005BAC',
            fillColor: '#3b82f6',
            fillOpacity: 0.22,
            weight: 2,
        },
    }).addTo(lmap);

    props.geofenceZones.forEach(zone => {
        if (zone.koordinat && zone.koordinat.length >= 3) {
            const geoJsonCoords = zone.koordinat.map(pt => [pt[1], pt[0]]);
            geoGroup.addData({
                type: 'Feature',
                geometry: {
                    type: 'Polygon',
                    coordinates: [geoJsonCoords],
                },
                properties: {
                    name: zone.name,
                    label: zone.label || zone.name,
                },
            });
        }

        if (zone.latitude && zone.longitude) {
            L.circle([zone.latitude, zone.longitude], {
                radius: zone.radius || 50,
                color: '#3b82f6',
                fillColor: '#93c5fd',
                fillOpacity: 0.1,
                weight: 1,
                dashArray: '3, 4',
            }).addTo(lmap);
        }
    });

    return geoGroup;
}

// Inisialisasi Peta Form Presensi
function initLeafletMap(containerRef) {
    if (!containerRef || typeof L === 'undefined') return null;

    const mapCenter = gpsLat.value && gpsLon.value
        ? [gpsLat.value, gpsLon.value]
        : [-7.039057, 107.5960505];

    const lmap = L.map(containerRef, { zoomControl: true }).setView(mapCenter, 18);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '© OpenStreetMap contributors',
    }).addTo(lmap);

    const group = addBuildingPolygons(lmap);
    if (group) activeGeoJsonGroup = group;

    setTimeout(() => { lmap.invalidateSize(); }, 200);

    return lmap;
}

function updateMapPosition(lmap, markerRef, lat, lon) {
    if (!lmap) return null;
    lmap.setView([lat, lon], 18);
    if (markerRef) {
        markerRef.setLatLng([lat, lon]);
        return markerRef;
    }
    return L.marker([lat, lon]).addTo(lmap).bindPopup('Posisi Anda').openPopup();
}

// Pusatkan Lokasi Pengguna pada Peta Presensi Terpadu
function centerUserLocation() {
    if (gpsLat.value && gpsLon.value) {
        if (leafletMapIn) leafletMapIn.setView([gpsLat.value, gpsLon.value], 18);
        if (leafletMapOut) leafletMapOut.setView([gpsLat.value, gpsLon.value], 18);
        if (markerIn) markerIn.openPopup();
        if (markerOut) markerOut.openPopup();
    } else {
        startGeolocation(false);
    }
}

function waitForLeaflet(callback) {
    if (typeof L !== 'undefined') {
        callback();
    } else {
        const checkId = setInterval(() => {
            if (typeof L !== 'undefined') {
                clearInterval(checkId);
                callback();
            }
        }, 300);
    }
}

// Inisialisasi atau pembaruan peta Form Masuk
function initOrUpdateCheckInMap(lat = null, lon = null) {
    if (!mapCheckInContainer.value) return;
    waitForLeaflet(() => {
        if (!mapCheckInContainer.value) return;
        const targetLat = lat ?? gpsLat.value ?? -7.039057;
        const targetLon = lon ?? gpsLon.value ?? 107.5960505;

        if (!leafletMapIn) {
            leafletMapIn = initLeafletMap(mapCheckInContainer.value);
            if (leafletMapIn && (lat !== null || gpsLat.value !== null)) {
                markerIn = L.marker([targetLat, targetLon]).addTo(leafletMapIn).bindPopup('Posisi Anda Saat Ini').openPopup();
            }
        } else {
            leafletMapIn.invalidateSize();
            if (lat !== null || gpsLat.value !== null) {
                markerIn = updateMapPosition(leafletMapIn, markerIn, targetLat, targetLon);
            }
        }
    });
}

// Inisialisasi atau pembaruan peta Form Pulang
function initOrUpdateCheckOutMap(lat = null, lon = null) {
    if (!mapCheckOutContainer.value) return;
    waitForLeaflet(() => {
        if (!mapCheckOutContainer.value) return;
        const targetLat = lat ?? gpsLat.value ?? -7.039057;
        const targetLon = lon ?? gpsLon.value ?? 107.5960505;

        if (!leafletMapOut) {
            leafletMapOut = initLeafletMap(mapCheckOutContainer.value);
            if (leafletMapOut && (lat !== null || gpsLat.value !== null)) {
                markerOut = L.marker([targetLat, targetLon]).addTo(leafletMapOut).bindPopup('Posisi Anda Saat Ini').openPopup();
            }
        } else {
            leafletMapOut.invalidateSize();
            if (lat !== null || gpsLat.value !== null) {
                markerOut = updateMapPosition(leafletMapOut, markerOut, targetLat, targetLon);
            }
        }
    });
}

watch(mapCheckInContainer, (el) => {
    if (el) nextTick(() => initOrUpdateCheckInMap());
});

watch(mapCheckOutContainer, (el) => {
    if (el) nextTick(() => initOrUpdateCheckOutMap());
});

// GPS Callbacks
function onGpsSuccess(position) {
    const lat = position.coords.latitude;
    const lon = position.coords.longitude;

    gpsLat.value     = lat;
    gpsLon.value     = lon;
    gpsReady.value   = true;
    gpsError.value   = '';
    isLocating.value = false;
    insideZone.value = checkInsideZones(lat, lon);

    checkInForm.latitude   = String(lat);
    checkInForm.longitude  = String(lon);
    checkOutForm.latitude  = String(lat);
    checkOutForm.longitude = String(lon);

    nextTick(() => {
        initOrUpdateCheckInMap(lat, lon);
        initOrUpdateCheckOutMap(lat, lon);
    });
}

function onGpsError(err, alreadyLowAccuracy = false) {
    isLocating.value = false;

    if (err.code === err.TIMEOUT && !alreadyLowAccuracy) {
        gpsError.value = 'GPS satelit lambat, beralih ke deteksi jaringan...';
        startGeolocation(true);
        return;
    }

    gpsReady.value = false;
    switch (err.code) {
        case err.PERMISSION_DENIED:
            gpsError.value = 'Izin akses lokasi ditolak oleh browser.';
            break;
        case err.POSITION_UNAVAILABLE:
            gpsError.value = 'Sinyal lokasi / GPS perangkat tidak tersedia.';
            break;
        case err.TIMEOUT:
            gpsError.value = 'Waktu pengambilan GPS habis (Timeout).';
            break;
        default:
            gpsError.value = 'Terjadi kesalahan lokasi (' + (err.message || 'Error') + ')';
    }
}

function startGeolocation(useLowAccuracy = false) {
    if (!navigator.geolocation) {
        gpsError.value = 'Browser tidak mendukung Geolocation.';
        return;
    }

    isSimulated.value = false;
    isLocating.value  = true;
    if (!useLowAccuracy) {
        gpsError.value = '';
    }

    const options = {
        enableHighAccuracy: !useLowAccuracy,
        timeout: useLowAccuracy ? 15000 : 10000,
        maximumAge: 0,
    };

    if (watchId !== null) {
        navigator.geolocation.clearWatch(watchId);
        watchId = null;
    }

    navigator.geolocation.getCurrentPosition(
        (pos) => {
            isLocating.value = false;
            onGpsSuccess(pos);

            watchId = navigator.geolocation.watchPosition(
                onGpsSuccess,
                (err) => {
                    if (!gpsReady.value) onGpsError(err, useLowAccuracy);
                },
                {
                    enableHighAccuracy: !useLowAccuracy,
                    timeout: 25000,
                    maximumAge: 3000,
                }
            );
        },
        (err) => {
            onGpsError(err, useLowAccuracy);
        },
        options
    );
}

// Simulasi KFPB Plant Banjaran untuk dev di PC
function useLocalSimulation() {
    isLocating.value  = false;
    gpsError.value    = '';
    isSimulated.value = true;
    const dummyPos = {
        coords: {
            latitude: -7.0390570,
            longitude: 107.5960505,
            accuracy: 10,
        },
    };
    onGpsSuccess(dummyPos);
    toast.add({
        severity: 'info',
        summary: 'Mode Simulasi KFPB',
        detail: 'Koordinat Plant Banjaran diterapkan untuk pengujian.',
        life: 5000,
    });
}

// Submit Absen Masuk (Memastikan koordinat langsung terambil)
function submitCheckIn() {
    if (checkInForm.processing || isLocating.value) return;

    if (isSimulated.value && isDevMode.value && gpsLat.value && gpsLon.value) {
        executeCheckInSubmit(gpsLat.value, gpsLon.value);
        return;
    }

    if (!navigator.geolocation) {
        toast.add({ severity: 'error', summary: 'GPS Tidak Didukung', detail: 'Browser tidak mendukung sensor Geolocation.', life: 5000 });
        return;
    }

    isLocating.value = true;
    checkInForm.processing = true;

    navigator.geolocation.getCurrentPosition(
        (pos) => {
            isLocating.value = false;
            onGpsSuccess(pos);
            executeCheckInSubmit(pos.coords.latitude, pos.coords.longitude);
        },
        (err) => {
            isLocating.value = false;
            checkInForm.processing = false;
            onGpsError(err, false);
            toast.add({
                severity: 'error',
                summary: 'Gagal Mengambil Lokasi Langsung',
                detail: 'Sensor GPS perangkat belum memberikan koordinat. Pastikan GPS dan izin lokasi aktif.',
                life: 6000,
            });
        },
        {
            enableHighAccuracy: true,
            timeout: 10000,
            maximumAge: 0,
        }
    );
}

function executeCheckInSubmit(lat, lon) {
    const isInside = checkInsideZones(lat, lon);

    if (checkInForm.work_mode === 'WFO' && !isInside) {
        checkInForm.processing = false;
        toast.add({
            severity: 'error',
            summary:  'Di Luar Area KFPB!',
            detail:   `Lokasi langsung Anda (${lat.toFixed(6)}, ${lon.toFixed(6)}) berada di luar area kantor KFPB. Absen Masuk ditolak.`,
            life:     7000,
        });
        return;
    }

    checkInForm.latitude  = String(lat);
    checkInForm.longitude = String(lon);

    checkInForm.post('/absensi/masuk', {
        preserveScroll: true,
        onError: (errors) => {
            const firstErr = Object.values(errors)[0] || 'Terjadi kesalahan saat memproses absen masuk.';
            toast.add({ severity: 'error', summary: 'Gagal Absen Masuk', detail: firstErr, life: 6000 });
        },
        onFinish: () => {
            checkInForm.processing = false;
        },
    });
}

// Submit Absen Pulang (Memastikan koordinat langsung terambil)
function submitCheckOut() {
    if (checkOutForm.processing || isLocating.value) return;

    if (isSimulated.value && isDevMode.value && gpsLat.value && gpsLon.value) {
        executeCheckOutSubmit(gpsLat.value, gpsLon.value);
        return;
    }

    if (!navigator.geolocation) {
        toast.add({ severity: 'error', summary: 'GPS Tidak Didukung', detail: 'Browser tidak mendukung sensor Geolocation.', life: 5000 });
        return;
    }

    isLocating.value = true;
    checkOutForm.processing = true;

    navigator.geolocation.getCurrentPosition(
        (pos) => {
            isLocating.value = false;
            onGpsSuccess(pos);
            executeCheckOutSubmit(pos.coords.latitude, pos.coords.longitude);
        },
        (err) => {
            isLocating.value = false;
            checkOutForm.processing = false;
            onGpsError(err, false);
            toast.add({
                severity: 'error',
                summary: 'Gagal Mengambil Lokasi Langsung',
                detail: 'Sensor GPS perangkat belum memberikan koordinat. Pastikan GPS dan izin lokasi aktif.',
                life: 6000,
            });
        },
        {
            enableHighAccuracy: true,
            timeout: 10000,
            maximumAge: 0,
        }
    );
}

function executeCheckOutSubmit(lat, lon) {
    const isInside = checkInsideZones(lat, lon);
    const workMode = props.todayAttendance?.work_mode ?? 'WFO';

    if (workMode === 'WFO' && !isInside) {
        checkOutForm.processing = false;
        toast.add({
            severity: 'error',
            summary:  'Di Luar Area KFPB!',
            detail:   `Lokasi langsung Anda (${lat.toFixed(6)}, ${lon.toFixed(6)}) berada di luar area kantor KFPB. Absen Pulang ditolak.`,
            life:     7000,
        });
        return;
    }

    checkOutForm.latitude  = String(lat);
    checkOutForm.longitude = String(lon);

    checkOutForm.post('/absensi/pulang', {
        preserveScroll: true,
        onSuccess: () => {
            if (watchId !== null) {
                navigator.geolocation.clearWatch(watchId);
                watchId = null;
            }
        },
        onError: (errors) => {
            const firstErr = Object.values(errors)[0] || 'Terjadi kesalahan saat memproses absen pulang.';
            toast.add({ severity: 'error', summary: 'Gagal Absen Pulang', detail: firstErr, life: 6000 });
        },
        onFinish: () => {
            checkOutForm.processing = false;
        },
    });
}

function handleResize() {
    if (leafletMapIn) leafletMapIn.invalidateSize();
    if (leafletMapOut) leafletMapOut.invalidateSize();
}

onMounted(() => {
    isDevMode.value = window.location.hostname === 'localhost' ||
                      window.location.hostname === '127.0.0.1' ||
                      window.location.hostname.includes('.test') ||
                      window.location.hostname.includes('.local');

    const tick = () => {
        const now = new Date();
        realtimeClock.value = [
            now.getHours().toString().padStart(2, '0'),
            now.getMinutes().toString().padStart(2, '0'),
            now.getSeconds().toString().padStart(2, '0'),
        ].join(':');
    };
    tick();
    clockInterval = setInterval(tick, 1000);

    window.addEventListener('resize', handleResize);

    if (attendanceStatus.value !== 'complete') {
        startGeolocation();
    }
});

onUnmounted(() => {
    if (clockInterval) clearInterval(clockInterval);
    if (watchId !== null) navigator.geolocation.clearWatch(watchId);
    window.removeEventListener('resize', handleResize);
    if (leafletMapIn)  { leafletMapIn.remove();  leafletMapIn  = null; }
    if (leafletMapOut) { leafletMapOut.remove(); leafletMapOut = null; }
});

const fmt = (t) => (t ? t.substring(0, 5) : '-');
const fmtRp = (n) => new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(n);
</script>

<template>
    <div class="space-y-6 max-w-7xl mx-auto px-3 sm:px-6 py-2">
        <Toast
            position="top-right"
            :breakpoints="{ '640px': { width: 'calc(100vw - 20px)', left: '10px', right: '10px', top: '10px' } }"
        />

        <!-- ================= TOP HERO BANNER ================= -->
        <div
            v-if="attendanceStatus !== 'complete'"
            class="relative bg-white rounded-2xl border border-slate-200/80 p-5 sm:p-6 shadow-[0_2px_10px_rgba(67,89,113,0.06)] overflow-hidden flex flex-col md:flex-row md:items-center justify-between gap-5"
        >
            <div class="space-y-1.5 max-w-xl">
                <h1 class="text-xl sm:text-2xl font-bold text-slate-900 tracking-tight">
                    Halo, {{ greetingName }}
                </h1>
                <p class="text-xs sm:text-sm text-slate-500">
                    Pastikan lokasi presensi Anda sudah sesuai sebelum melakukan absen.
                </p>
                <div class="flex flex-wrap items-center gap-1.5 sm:gap-2 pt-1">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-md bg-slate-100 text-slate-700 font-mono text-xs font-semibold border border-slate-200/70">
                        {{ employeeNip }}
                    </span>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-md bg-slate-100 text-slate-700 text-xs font-medium border border-slate-200/70">
                        {{ formattedPosition }}
                    </span>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-md bg-blue-50 text-[#005BAC] text-xs font-medium border border-blue-200/60">
                        {{ formattedDivision }}
                    </span>
                </div>
            </div>

        </div>

        <!-- ================= CTA BANNER: LAPOR TIDAK MASUK KERJA (CUTI / SAKIT) ================= -->
        <div
            v-if="attendanceStatus === 'none'"
            class="bg-[#005BAC] rounded-2xl p-4 sm:p-5 text-white shadow-sm flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4"
        >
            <div class="flex items-center gap-3.5">
                <div class="size-11 sm:size-12 rounded-xl bg-white/10 flex items-center justify-center shrink-0 border border-white/20">
                    <i class="pi pi-file-edit text-white text-lg" />
                </div>
                <div>
                    <h2 class="text-sm sm:text-base font-bold tracking-tight text-white">Lapor Tidak Masuk Kerja</h2>
                    <p class="text-xs text-blue-100">Pengajuan Cuti Tahunan • Izin Sakit • Dinas Luar • Dispensasi Kerja</p>
                </div>
            </div>
            <Link
                href="/forms"
                class="inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl bg-white text-[#005BAC] font-bold text-xs sm:text-sm hover:bg-slate-100 transition-colors shadow-sm w-full sm:w-auto shrink-0"
            >
                <span>Ajukan Permohonan</span>
                <i class="pi pi-arrow-right text-xs" />
            </Link>
        </div>

        <!-- ================= KARTU ULANG TAHUN KORPORAT (KHUSUS HARI ULTAH) ================= -->
        <div
            v-if="birthdayData.isBirthday"
            :class="[
                'relative rounded-2xl p-5 sm:p-6 text-white shadow-sm overflow-hidden select-none',
                birthdayData.gender === 'Female'
                    ? 'bg-rose-700'
                    : 'bg-[#005BAC] border border-blue-400/30'
            ]"
        >
            <div class="flex flex-col sm:flex-row items-start sm:items-center gap-4 relative z-10">
                <div class="size-12 rounded-xl bg-white/10 flex items-center justify-center shrink-0 border border-white/20">
                    <i class="pi pi-gift text-2xl text-white" />
                </div>
                <div class="space-y-1">
                    <span class="text-xs font-bold uppercase tracking-wider text-amber-300">Pemberitahuan Khusus</span>
                    <h2 class="text-base sm:text-lg font-bold">Selamat Ulang Tahun, {{ birthdayData.employeeName }}!</h2>
                    <p class="text-xs sm:text-sm text-white/90 leading-relaxed">
                        Manajemen dan segenap keluarga besar PT Kimia Farma Plant Banjaran mengucapkan selamat ulang tahun. Semoga senantiasa diberikan kesehatan, kelancaran rezeki, serta kesuksesan dalam berkarya bersama perusahaan.
                    </p>
                </div>
            </div>
        </div>

        <!-- ================= KONDISI A: KEHADIRAN HARI INI TELAH LENGKAP ================= -->
        <!-- Sesuai instruksi: hanya munculkan card status kehadiran hari ini saja -->
        <div v-if="attendanceStatus === 'complete'" class="max-w-3xl mx-auto space-y-6">
            <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
                <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
                    <div class="flex items-center gap-2">
                        <i class="pi pi-check-circle text-[#005BAC]" />
                        <h2 class="font-bold text-slate-800 text-sm tracking-tight">Status Kehadiran Hari Ini</h2>
                    </div>
                    <Tag value="Kehadiran Selesai" severity="info" class="!text-[10px] !font-bold" />
                </div>

                <div class="p-6 space-y-5">
                    <!-- Pesan Narasi Lengkap Sesuai Permintaan Pengguna -->
                    <div class="p-4 sm:p-5 rounded-xl bg-blue-50 border border-blue-200 space-y-2">
                        <div class="font-bold text-[#005BAC] text-sm sm:text-base flex items-center gap-2">
                            <i class="pi pi-verified text-[#005BAC] text-lg" />
                            <span>Kehadiran Hari Ini Telah Selesai</span>
                        </div>
                        <p class="text-xs sm:text-sm text-blue-900 leading-relaxed">
                            Halo <b>{{ greetingName }}</b>, Anda telah menyelesaikan rangkaian presensi masuk (<b>{{ fmt(todayAttendance?.check_in_time) }}</b>) dan presensi pulang (<b>{{ fmt(todayAttendance?.check_out_time) }}</b>). Terima kasih atas dedikasi kerja Anda hari ini.
                        </p>
                    </div>

                    <!-- Data Komparasi eSDM & Biometrik Wajah -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
                        <!-- eSDM Aplikasi -->
                        <div class="p-4 rounded-xl bg-slate-50 border border-slate-200/70 space-y-2">
                            <div class="text-[11px] font-bold text-slate-500 uppercase tracking-wider flex items-center justify-between">
                                <span>Presensi Aplikasi eSDM</span>
                                <span class="text-[10px] text-blue-600 font-semibold font-mono">Web/GPS</span>
                            </div>
                            <div class="grid grid-cols-2 gap-2 text-xs">
                                <div>
                                    <span class="text-slate-400 block">Jam Masuk</span>
                                    <span class="font-bold text-slate-800 font-mono text-sm sm:text-base text-[#005BAC]">{{ fmt(todayAttendance?.check_in_time) }}</span>
                                </div>
                                <div>
                                    <span class="text-slate-400 block">Jam Pulang</span>
                                    <span class="font-bold text-slate-800 font-mono text-sm sm:text-base text-[#005BAC]">{{ fmt(todayAttendance?.check_out_time) }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Absen Wajah (Face Biometric) -->
                        <div class="p-4 rounded-xl bg-slate-50 border border-slate-200/70 space-y-2">
                            <div class="text-[11px] font-bold text-slate-500 uppercase tracking-wider flex items-center justify-between">
                                <span>Absensi Wajah (FaceID)</span>
                                <span class="text-[10px] text-blue-600 font-semibold font-mono">Mesin Plant</span>
                            </div>
                            <div class="grid grid-cols-2 gap-2 text-xs">
                                <div>
                                    <span class="text-slate-400 block">Jam Masuk</span>
                                    <span class="font-bold text-slate-800 font-mono text-sm sm:text-base text-[#005BAC]">{{ faceCheckIn }}</span>
                                </div>
                                <div>
                                    <span class="text-slate-400 block">Jam Pulang</span>
                                    <span class="font-bold text-slate-800 font-mono text-sm sm:text-base text-[#005BAC]">{{ faceCheckOut }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <p class="text-[11px] text-slate-400 italic text-center">
                        *Sinkronisasi data mesin absensi wajah terhubung otomatis (±1 jam setelah proses pemindaian di lokasi).
                    </p>
                </div>
            </div>
        </div>

        <!-- ================= KONDISI B: BELUM LENGKAP (BELUM MASUK / SEDANG IN / IZIN) ================= -->
        <div v-else class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">

            <!-- ============================================================== -->
            <!-- KOLOM KIRI (7 Kolom): Status, Form Presensi, & Riwayat Pegawai -->
            <!-- ============================================================== -->
            <div class="lg:col-span-7 space-y-6">

                <!-- 1. FORMULIR ABSEN MASUK (KONDISIONAL: BELUM ABSEN — PRIORITAS UTAMA) -->
                <div
                    v-if="attendanceStatus === 'none'"
                    class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden"
                >
                    <div class="px-5 py-4 bg-[#005BAC] text-white flex items-center justify-between">
                        <div class="flex items-center gap-2.5">
                            <i class="pi pi-sign-in text-lg" />
                            <div>
                                <h2 class="font-bold text-sm sm:text-base tracking-tight text-white">Form Absen Masuk</h2>
                                <p class="text-[11px] text-white/80">{{ todayLabel }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="p-5 sm:p-6 space-y-4">
                        <!-- Waktu Realtime Masuk (Sans-Serif Modern & Slate Netral) -->
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 p-4 sm:p-5 rounded-xl bg-slate-50 border border-slate-200/80">
                            <div class="flex items-center gap-3">
                                <div class="size-11 rounded-xl bg-white border border-slate-200 flex items-center justify-center shrink-0 shadow-xs text-[#005BAC]">
                                    <i class="pi pi-clock text-lg" />
                                </div>
                                <div>
                                    <span class="text-[11px] font-bold text-slate-500 uppercase tracking-wider block">Waktu Saat Ini</span>
                                    <p class="text-xs text-slate-500 mt-0.5">Waktu presensi masuk tercatat otomatis saat Anda menekan tombol absen</p>
                                </div>
                            </div>
                            <div class="flex items-baseline gap-1.5 font-sans sm:text-right">
                                <span class="text-3xl sm:text-4xl font-extrabold text-slate-800 tracking-tight font-sans select-none">
                                    {{ realtimeClock }}
                                </span>
                                <span class="text-xs sm:text-sm font-bold text-slate-500 font-sans">WIB</span>
                            </div>
                        </div>

                        <!-- Input NPP -->
                        <div>
                            <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-1">NPP / NIP</label>
                            <InputText :value="employeeNip" readonly class="w-full !text-sm !bg-slate-50 font-mono font-semibold" />
                        </div>

                        <!-- Pilih Shift -->
                        <div>
                            <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-1">Pilih Shift Kerja</label>
                            <Select
                                v-model="checkInForm.work_shift_id"
                                :options="workShifts"
                                option-label="name"
                                option-value="id"
                                placeholder="— Pilih Shift —"
                                class="w-full !text-sm"
                            >
                                <template #option="{ option }">
                                    <div class="flex items-center justify-between w-full">
                                        <span class="font-medium text-xs sm:text-sm">{{ option.name }}</span>
                                        <span class="text-[11px] text-slate-400 font-mono ml-3">{{ option.check_in }} – {{ option.check_out }}</span>
                                    </div>
                                </template>
                                <template #value="{ value }">
                                    <span v-if="selectedShift" class="text-xs sm:text-sm">
                                        {{ selectedShift.name }}
                                        <span class="text-slate-400 font-mono ml-1 text-[11px]">
                                             ({{ selectedShift.check_in }} – {{ selectedShift.check_out }})
                                        </span>
                                    </span>
                                    <span v-else class="text-slate-400 text-xs sm:text-sm">— Pilih Shift —</span>
                                </template>
                            </Select>
                            <p class="text-[11px] text-[#005BAC] mt-1 font-medium flex items-center gap-1">
                                <i class="pi pi-info-circle text-[11px]" />
                                <span><b>Tips:</b> Untuk lembur di hari Sabtu/Minggu/Libur, silakan pilih shift lembur (libur).</span>
                            </p>
                        </div>

                        <!-- Mode Kerja -->
                        <div>
                            <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-1">Work From</label>
                            <Select
                                v-model="checkInForm.work_mode"
                                :options="workModeOptions"
                                option-label="label"
                                option-value="value"
                                class="w-full !text-sm"
                            >
                            </Select>
                        </div>

                        <!-- Peta Geofence Form Masuk -->
                        <div class="space-y-1.5">
                            <div class="flex items-center justify-between mb-1">
                                <label class="text-xs font-bold text-slate-700 uppercase tracking-wider flex items-center gap-1.5">
                                    <i class="pi pi-map-marker text-[#005BAC]" />
                                    <span>Peta Lokasi &amp; Geofence KFPB</span>
                                </label>
                                <div class="flex items-center gap-2 flex-wrap justify-end">
                                    <span v-if="insideZone && currentZoneName" class="text-[11px] text-[#005BAC] font-bold flex items-center gap-1">
                                        <i class="pi pi-map-marker text-[10px]" />
                                        <span>Zona: {{ currentZoneName }}</span>
                                    </span>
                                    <span v-else-if="gpsReady && !insideZone && !isLocating" class="text-[11px] text-rose-600 font-bold flex items-center gap-1">
                                        <i class="pi pi-exclamation-circle text-[10px]" />
                                        <span>Di Luar Area KFPB</span>
                                    </span>
                                    <Button
                                        icon="pi pi-crosshairs"
                                        label="Pusatkan"
                                        size="small"
                                        text
                                        class="!text-[11px] !py-0.5 !px-2 !text-[#005BAC] cursor-pointer"
                                        @click="centerUserLocation"
                                    />
                                </div>
                            </div>
                            <div
                                ref="mapCheckInContainer"
                                class="w-full rounded-xl border border-slate-200 overflow-hidden shadow-xs"
                                style="height: 260px; background: #e2e8f0;"
                            />

                            <!-- GPS Status & Map Legend -->
                            <div class="flex items-center justify-between text-[11px] text-slate-500 pt-0.5 flex-wrap gap-2">
                                <div class="flex items-center gap-1.5">
                                    <span class="size-2 rounded-full bg-[#005BAC]" />
                                    <span>Area Biru = 6 Gedung KFPB</span>
                                </div>
                                <div v-if="gpsReady && gpsLat" class="font-mono text-slate-600">
                                    📍 {{ gpsLat?.toFixed(6) }}, {{ gpsLon?.toFixed(6) }}
                                    <span v-if="isLocating" class="text-slate-400 text-[10px] ml-1">
                                        <i class="pi pi-spin pi-spinner" />
                                    </span>
                                </div>
                            </div>
                            <div v-if="gpsError || isLocating" class="mt-2 p-3 rounded-xl bg-amber-50 border border-amber-200 text-xs text-amber-900 space-y-2">
                                <div class="flex items-center gap-2">
                                    <i :class="isLocating ? 'pi pi-spin pi-spinner text-amber-600' : 'pi pi-exclamation-triangle text-amber-600'" />
                                    <span class="font-medium">{{ isLocating ? 'Sedang mendeteksi sinyal GPS perangkat...' : gpsError }}</span>
                                </div>
                                <div v-if="!isLocating" class="flex items-center gap-2 pt-1 border-t border-amber-200 flex-wrap">
                                    <Button
                                        label="Coba Ulangi GPS"
                                        icon="pi pi-refresh"
                                        size="small"
                                        severity="secondary"
                                        outlined
                                        class="!text-[11px] !py-1 !px-2.5 !rounded-md"
                                        @click="startGeolocation(false)"
                                    />
                                    <Button
                                        v-if="isDevMode || gpsError.includes('Timeout') || gpsError.includes('tidak tersedia')"
                                        label="Gunakan Titik KFPB (Mode Dev)"
                                        icon="pi pi-map-marker"
                                        size="small"
                                        severity="info"
                                        outlined
                                        class="!text-[11px] !py-1 !px-2.5 !rounded-md"
                                        @click="useLocalSimulation"
                                    />
                                </div>
                            </div>
                        </div>

                        <!-- Tombol Submit Absen Masuk -->
                        <Button
                            :label="isLocating ? 'Mengecek Lokasi GPS Langsung...' : 'Klik Untuk Absen Masuk'"
                            icon="pi pi-sign-in"
                            :loading="checkInForm.processing || isLocating"
                            :disabled="checkInForm.processing || isLocating || (!gpsReady && !isDevMode)"
                            class="w-full !bg-[#005BAC] !border-[#005BAC] hover:!bg-[#004b8e] !font-bold !py-3.5 shadow-md text-sm sm:text-base !rounded-xl"
                            @click="submitCheckIn"
                        />
                    </div>
                </div>

                <!-- 2. KOTAK STATUS KEHADIRAN HARI INI (ESDM & BIOMETRIK WAJAH) -->
                <!-- Saat sudah absen masuk ('in') atau izin ('leave'), card status ini tampil teratas sebelum form pulang -->
                <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
                    <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
                        <div class="flex items-center gap-2">
                            <i class="pi pi-info-circle text-[#005BAC]" />
                            <h2 class="font-bold text-slate-800 text-sm tracking-tight">Status Kehadiran Hari Ini</h2>
                        </div>
                        <Tag
                            v-if="attendanceStatus === 'leave'"
                            value="Lapor Izin / Cuti"
                            severity="warn"
                            class="!text-[10px] !font-bold"
                        />
                        <Tag
                            v-else-if="attendanceStatus === 'in'"
                            value="Sudah Absen Masuk"
                            severity="info"
                            class="!text-[10px] !font-bold"
                        />
                        <Tag
                            v-else
                            value="Belum Absen Masuk"
                            severity="danger"
                            class="!text-[10px] !font-bold"
                        />
                    </div>

                    <div class="p-5 space-y-4">
                        <!-- Kasus A: Sedang Izin -->
                        <div v-if="attendanceStatus === 'leave'" class="p-4 rounded-xl bg-blue-50 border border-blue-200 text-sm space-y-2">
                            <div class="font-bold text-blue-900 flex items-center gap-2">
                                <i class="pi pi-check-circle text-blue-600" />
                                <span>Permohonan {{ activeLeave.name }} Tercatat</span>
                            </div>
                            <p class="text-xs text-blue-800 leading-relaxed">
                                Keterangan: <b>{{ activeLeave.reason }}</b>. Anda tidak diwajibkan melakukan absen masuk dan pulang untuk hari ini.
                            </p>
                        </div>

                        <!-- Data Komparasi eSDM & Biometrik Wajah -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
                            <!-- eSDM Aplikasi -->
                            <div class="p-3.5 rounded-xl bg-slate-50 border border-slate-200/70 space-y-2">
                                <div class="text-[11px] font-bold text-slate-500 uppercase tracking-wider flex items-center justify-between">
                                    <span>Presensi Aplikasi eSDM</span>
                                    <span class="text-[10px] text-blue-600 font-semibold font-mono">Web/GPS</span>
                                </div>
                                <div class="grid grid-cols-2 gap-2 text-xs">
                                    <div>
                                        <span class="text-slate-400 block">Jam Masuk</span>
                                        <span class="font-bold text-slate-800 font-mono text-sm">{{ fmt(todayAttendance?.check_in_time) }}</span>
                                    </div>
                                    <div>
                                        <span class="text-slate-400 block">Jam Pulang</span>
                                        <span class="font-bold text-slate-800 font-mono text-sm">{{ fmt(todayAttendance?.check_out_time) }}</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Absen Wajah (Face Biometric) -->
                            <div class="p-3.5 rounded-xl bg-slate-50 border border-slate-200/70 space-y-2">
                                <div class="text-[11px] font-bold text-slate-500 uppercase tracking-wider flex items-center justify-between">
                                    <span>Absensi Wajah (FaceID)</span>
                                    <span class="text-[10px] text-blue-600 font-semibold font-mono">Mesin Plant</span>
                                </div>
                                <div class="grid grid-cols-2 gap-2 text-xs">
                                    <div>
                                        <span class="text-slate-400 block">Jam Masuk</span>
                                        <span class="font-bold text-slate-800 font-mono text-sm">{{ faceCheckIn }}</span>
                                    </div>
                                    <div>
                                        <span class="text-slate-400 block">Jam Pulang</span>
                                        <span class="font-bold text-slate-800 font-mono text-sm">{{ faceCheckOut }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <p class="text-[11px] text-slate-400 italic text-center">
                            *Sinkronisasi data mesin biometrik wajah terhubung otomatis berkala (±1 jam setelah proses pemindaian di lokasi).
                        </p>
                    </div>
                </div>

                <!-- 3. ALUR KHUSUS SUDAH ABSEN MASUK (REKAP KETERLAMBATAN & FORM PULANG) -->
                <template v-if="attendanceStatus === 'in'">
                    <!-- 3A. TABEL PALING TERLAMBAT DATANG HARI INI (ESDM) -->
                    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
                        <div class="p-4 sm:p-5 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                            <div>
                                <h2 class="font-bold text-slate-800 text-sm tracking-tight flex items-center gap-2">
                                    <i class="pi pi-clock text-amber-500" />
                                    <span>Paling Terlambat Datang Hari Ini (eSDM)</span>
                                </h2>
                                <p class="text-xs text-slate-400 mt-0.5">Top 5 keterlambatan presensi masuk pegawai hari ini</p>
                            </div>
                            <Link
                                href="/charts"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-100 text-slate-700 text-xs font-semibold hover:bg-slate-200 transition-colors shrink-0"
                            >
                                <i class="pi pi-file-pdf text-rose-600" />
                                <span>Download Laporan Terlambat (PDF)</span>
                            </Link>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-xs text-slate-600 border-collapse">
                                <thead>
                                    <tr class="bg-slate-50 text-slate-500 font-bold border-b border-slate-200/60 uppercase tracking-wider text-[11px]">
                                        <th class="py-2.5 px-4">Nama Pegawai & Unit</th>
                                        <th class="py-2.5 px-4">Jam Masuk</th>
                                        <th class="py-2.5 px-4">Shift</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    <tr v-for="(late, idx) in topLateToday" :key="idx" class="hover:bg-slate-50/50">
                                        <td class="py-2.5 px-4">
                                            <div class="font-bold text-slate-800">{{ late.name }}</div>
                                            <div class="text-[11px] text-slate-400">{{ late.division }}</div>
                                        </td>
                                        <td class="py-2.5 px-4 font-mono font-bold text-rose-600">{{ late.time }}</td>
                                        <td class="py-2.5 px-4 text-slate-700 font-medium">{{ late.shift }}</td>
                                    </tr>
                                    <tr v-if="topLateToday.length === 0">
                                        <td colspan="3" class="py-5 px-4 text-center text-slate-400 italic">
                                            Tidak ada data keterlambatan hari ini.
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- 3B. CARD KETERLAMBATAN ABSEN WAJAH (BIOMETRIK) — COLLAPSIBLE -->
                    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
                        <button
                            type="button"
                            class="w-full p-4 sm:p-5 flex items-center justify-between text-left hover:bg-slate-50/50 transition-colors"
                            @click="showLateBiometric = !showLateBiometric"
                        >
                            <div class="flex items-center gap-2.5">
                                <i class="pi pi-user-edit text-[#005BAC]" />
                                <div>
                                    <h2 class="font-bold text-slate-800 text-sm tracking-tight">Tampilkan Paling Terlambat Hari Ini (Absen Wajah)</h2>
                                    <p class="text-xs text-slate-400 mt-0.5">Sensor biometrik wajah mesin Plant</p>
                                </div>
                            </div>
                            <i :class="['pi text-xs text-slate-400 transition-transform', showLateBiometric ? 'pi-chevron-up' : 'pi-chevron-down']" />
                        </button>

                        <div v-show="showLateBiometric" class="p-4 sm:p-5 pt-0 border-t border-slate-100 space-y-3">
                            <p class="text-[11px] text-slate-400 italic">
                                *Catatan: Data mesin biometrik wajah disinkronkan secara berkala dan diverifikasi sistem eSDM.
                            </p>
                            <div class="overflow-x-auto">
                                <table class="w-full text-left text-xs text-slate-600 border-collapse">
                                    <thead>
                                        <tr class="bg-slate-50 text-slate-500 font-bold border-b border-slate-200/60 uppercase tracking-wider text-[11px]">
                                            <th class="py-2.5 px-3">Nama Pegawai & Unit</th>
                                            <th class="py-2.5 px-3">Jam Masuk</th>
                                            <th class="py-2.5 px-3">Metode</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100">
                                        <tr v-for="(lateBio, idx) in topLateBiometric" :key="idx" class="hover:bg-slate-50/50">
                                            <td class="py-2.5 px-3">
                                                <div class="font-bold text-slate-800">{{ lateBio.name }}</div>
                                                <div class="text-[11px] text-slate-400">{{ lateBio.division }}</div>
                                            </td>
                                            <td class="py-2.5 px-3 font-mono font-bold text-rose-600">{{ lateBio.time }}</td>
                                            <td class="py-2.5 px-3 text-slate-600 font-medium">{{ lateBio.shift }}</td>
                                        </tr>
                                        <tr v-if="topLateBiometric.length === 0">
                                            <td colspan="3" class="py-5 px-3 text-center text-slate-400 italic">
                                                Tidak ada data keterlambatan absen wajah hari ini.
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- 3C. FORMULIR ABSEN PULANG -->
                    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
                        <div class="px-5 py-4 bg-[#005BAC] text-white flex items-center justify-between">
                            <div class="flex items-center gap-2.5">
                                <i class="pi pi-sign-out text-lg" />
                                <div>
                                    <h2 class="font-bold text-sm sm:text-base tracking-tight text-white">Form Absen Pulang</h2>
                                    <p class="text-[11px] text-white/80">Masuk: {{ fmt(todayAttendance?.check_in_time) }} • {{ todayLabel }}</p>
                                </div>
                            </div>
                        </div>

                        <div class="p-5 sm:p-6 space-y-4">
                            <!-- Waktu Realtime Kepulangan (Sans-Serif Modern & Slate Netral) -->
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 p-4 sm:p-5 rounded-xl bg-slate-50 border border-slate-200/80">
                                <div class="flex items-center gap-3">
                                    <div class="size-11 rounded-xl bg-white border border-slate-200 flex items-center justify-center shrink-0 shadow-xs text-[#005BAC]">
                                        <i class="pi pi-clock text-lg" />
                                    </div>
                                    <div>
                                        <span class="text-[11px] font-bold text-slate-500 uppercase tracking-wider block">Waktu Saat Ini</span>
                                        <p class="text-xs text-slate-500 mt-0.5">Waktu kepulangan tercatat otomatis saat Anda menekan tombol absen</p>
                                    </div>
                                </div>
                                <div class="flex items-baseline gap-1.5 font-sans sm:text-right">
                                    <span class="text-3xl sm:text-4xl font-extrabold text-slate-800 tracking-tight font-sans select-none">
                                        {{ realtimeClock }}
                                    </span>
                                    <span class="text-xs sm:text-sm font-bold text-slate-500 font-sans">WIB</span>
                                </div>
                            </div>

                            <!-- Info Grid Ringkas -->
                            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 text-xs">
                                <div class="bg-slate-50 p-2.5 rounded-lg border border-slate-100">
                                    <span class="text-slate-400 block text-[10px] uppercase font-bold">NPP</span>
                                    <span class="font-bold text-slate-800 font-mono">{{ employeeNip }}</span>
                                </div>
                                <div class="bg-slate-50 p-2.5 rounded-lg border border-slate-100">
                                    <span class="text-slate-400 block text-[10px] uppercase font-bold">Shift</span>
                                    <span class="font-bold text-slate-800 truncate block">{{ todayAttendance?.shift_name }}</span>
                                </div>
                                <div class="bg-slate-50 p-2.5 rounded-lg border border-slate-100">
                                    <span class="text-slate-400 block text-[10px] uppercase font-bold">Mode</span>
                                    <span class="font-bold text-slate-800">{{ todayAttendance?.work_mode }}</span>
                                </div>
                                <div class="bg-slate-50 p-2.5 rounded-lg border border-slate-100">
                                    <span class="text-slate-400 block text-[10px] uppercase font-bold">Jam Masuk</span>
                                    <span class="font-bold text-[#005BAC] font-mono">{{ fmt(todayAttendance?.check_in_time) }}</span>
                                </div>
                            </div>

                            <!-- Peta Geofence Form Pulang -->
                            <div class="space-y-1.5">
                                <div class="flex items-center justify-between mb-1">
                                    <label class="text-xs font-bold text-slate-700 uppercase tracking-wider flex items-center gap-1.5">
                                        <i class="pi pi-map-marker text-[#005BAC]" />
                                        <span>Peta Lokasi &amp; Geofence Pabrik</span>
                                    </label>
                                    <div class="flex items-center gap-2 flex-wrap justify-end">
                                        <span v-if="insideZone && currentZoneName" class="text-[11px] text-[#005BAC] font-bold flex items-center gap-1">
                                            <i class="pi pi-check text-[10px]" />
                                            <span>Zona: {{ currentZoneName }}</span>
                                        </span>
                                        <span v-else-if="gpsReady && !insideZone && !isLocating" class="text-[11px] text-rose-600 font-bold flex items-center gap-1">
                                            <i class="pi pi-exclamation-circle text-[10px]" />
                                            <span>Di Luar Area Sah</span>
                                        </span>
                                        <Button
                                            icon="pi pi-crosshairs"
                                            label="Pusatkan"
                                            size="small"
                                            text
                                            class="!text-[11px] !py-0.5 !px-2 !text-[#005BAC] cursor-pointer"
                                            @click="centerUserLocation"
                                        />
                                    </div>
                                </div>
                                <div
                                    ref="mapCheckOutContainer"
                                    class="w-full rounded-xl border border-slate-200 overflow-hidden shadow-xs"
                                    style="height: 260px; background: #e2e8f0;"
                                />

                                <!-- GPS Status & Map Legend -->
                                <div class="flex items-center justify-between text-[11px] text-slate-500 pt-0.5 flex-wrap gap-2">
                                    <div class="flex items-center gap-1.5">
                                        <span class="size-2 rounded-full bg-[#005BAC]" />
                                        <span>Area Biru = 6 Gedung KFPB</span>
                                    </div>
                                    <div v-if="gpsReady && gpsLat" class="font-mono text-slate-600">
                                        <i class="pi pi-map-marker"></i> {{ gpsLat?.toFixed(6) }}, {{ gpsLon?.toFixed(6) }}
                                        <span v-if="isLocating" class="text-slate-400 text-[10px] ml-1">
                                            <i class="pi pi-spin pi-spinner" />
                                        </span>
                                    </div>
                                </div>
                                <div v-if="gpsError || isLocating" class="mt-2 p-3 rounded-xl bg-amber-50 border border-amber-200 text-xs text-amber-900 space-y-2">
                                    <div class="flex items-center gap-2">
                                        <i :class="isLocating ? 'pi pi-spin pi-spinner text-amber-600' : 'pi pi-exclamation-triangle text-amber-600'" />
                                        <span class="font-medium">{{ isLocating ? 'Sedang mendeteksi sinyal GPS perangkat...' : gpsError }}</span>
                                    </div>
                                    <div v-if="!isLocating" class="flex items-center gap-2 pt-1 border-t border-amber-200 flex-wrap">
                                        <Button
                                            label="Coba Ulangi GPS"
                                            icon="pi pi-refresh"
                                            size="small"
                                            severity="secondary"
                                            outlined
                                            class="!text-[11px] !py-1 !px-2.5 !rounded-md"
                                            @click="startGeolocation(false)"
                                        />
                                        <Button
                                            v-if="isDevMode || gpsError.includes('Timeout') || gpsError.includes('tidak tersedia')"
                                            label="Gunakan Titik KFPB (Mode Dev)"
                                            icon="pi pi-map-marker"
                                            size="small"
                                            severity="info"
                                            outlined
                                            class="!text-[11px] !py-1 !px-2.5 !rounded-md"
                                            @click="useLocalSimulation"
                                        />
                                    </div>
                                </div>
                            </div>

                            <!-- Catatan Pulang -->
                            <div>
                                <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-1">Informasi / Catatan (Opsional)</label>
                                <InputText
                                    v-model="checkOutForm.notes"
                                    placeholder="Isi catatan jika ada..."
                                    class="w-full !text-sm"
                                />
                            </div>

                            <!-- Tombol Submit Absen Pulang -->
                            <Button
                                :label="isLocating ? 'Mengecek Lokasi GPS Langsung...' : 'Klik Untuk Absen Pulang'"
                                icon="pi pi-sign-out"
                                :loading="checkOutForm.processing || isLocating"
                                :disabled="checkOutForm.processing || isLocating || (!gpsReady && !isDevMode)"
                                class="w-full !bg-[#005BAC] !border-[#005BAC] hover:!bg-[#004b8e] !font-bold !py-3.5 shadow-md text-sm sm:text-base !rounded-xl"
                                @click="submitCheckOut"
                            />
                        </div>
                    </div>
                </template>

                <!-- 4. TABEL RIWAYAT PRESENSI SAYA (DIBATASI 3 DATA DENGAN PAGINATION) -->
                <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
                    <div class="p-4 sm:p-5 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                        <div>
                            <h2 class="font-bold text-slate-800 text-sm tracking-tight flex items-center gap-2">
                                <i class="pi pi-history text-[#005BAC]" />
                                <span>Riwayat Presensi Mandiri Anda</span>
                            </h2>
                            <p class="text-xs text-slate-400 mt-0.5">Catatan presensi kerja Anda di PT Kimia Farma Plant Banjaran</p>
                        </div>
                    </div>

                    <!-- Tampilan Desktop (Tabel) -->
                    <div class="hidden sm:block overflow-x-auto">
                        <table class="w-full text-left text-xs text-slate-600 border-collapse">
                            <thead>
                                <tr class="bg-slate-50 text-slate-500 font-bold border-b border-slate-200/60 uppercase tracking-wider text-[11px]">
                                    <th class="py-3 px-4">Tanggal & Shift</th>
                                    <th class="py-3 px-4">Jam Masuk</th>
                                    <th class="py-3 px-4">Jam Pulang</th>
                                    <th class="py-3 px-4">Mode</th>
                                    <th class="py-3 px-4">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr v-for="att in attendanceList" :key="att.id" class="hover:bg-slate-50/50">
                                    <td class="py-3 px-4">
                                        <div class="font-bold text-slate-800">{{ att.date_formatted }}</div>
                                        <div class="text-[11px] text-slate-400">{{ att.shift_name }}</div>
                                    </td>
                                    <td class="py-3 px-4 font-mono font-bold" :class="att.is_late ? 'text-[#F37021]' : 'text-[#005BAC]'">
                                        {{ att.check_in }}
                                    </td>
                                    <td class="py-3 px-4 font-mono font-bold" :class="att.is_early_leave ? 'text-rose-600' : 'text-slate-700'">
                                        {{ att.check_out }}
                                    </td>
                                    <td class="py-3 px-4">
                                        <span class="inline-block px-2 py-0.5 rounded text-[10px] font-bold bg-slate-100 text-slate-700">
                                            {{ att.work_mode }}
                                        </span>
                                    </td>
                                    <td class="py-3 px-4">
                                        <Tag
                                            :value="att.status_label"
                                            :severity="att.status_severity"
                                            class="!text-[10px] !font-bold"
                                        />
                                    </td>
                                </tr>
                                <tr v-if="attendanceList.length === 0">
                                    <td colspan="5" class="py-6 px-4 text-center text-slate-400 italic">
                                        Belum ada riwayat presensi tercatat.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Tampilan Mobile (Daftar Kartu Kompak) -->
                    <div class="block sm:hidden divide-y divide-slate-100">
                        <div v-for="att in attendanceList" :key="'m-' + att.id" class="p-4 space-y-2">
                            <div class="flex items-center justify-between">
                                <span class="font-bold text-xs text-slate-800">{{ att.date_formatted }}</span>
                                <Tag :value="att.status_label" :severity="att.status_severity" class="!text-[9px] !font-bold" />
                            </div>
                            <div class="flex items-center justify-between text-xs text-slate-500 font-mono">
                                <span>Shift: {{ att.shift_name }}</span>
                                <span>Mode: {{ att.work_mode }}</span>
                            </div>
                            <div class="grid grid-cols-2 gap-2 text-xs pt-1">
                                <div class="bg-slate-50 p-2 rounded border border-slate-100">
                                    <span class="text-slate-400 block text-[10px]">Masuk:</span>
                                    <span class="font-bold font-mono" :class="att.is_late ? 'text-[#F37021]' : 'text-[#005BAC]'">{{ att.check_in }}</span>
                                </div>
                                <div class="bg-slate-50 p-2 rounded border border-slate-100">
                                    <span class="text-slate-400 block text-[10px]">Pulang:</span>
                                    <span class="font-bold font-mono" :class="att.is_early_leave ? 'text-rose-600' : 'text-slate-700'">{{ att.check_out }}</span>
                                </div>
                            </div>
                        </div>
                        <div v-if="attendanceList.length === 0" class="p-6 text-center text-slate-400 text-xs italic">
                            Belum ada riwayat presensi tercatat.
                        </div>
                    </div>

                    <!-- Pagination Controls Minimalis (Load Cepat) -->
                    <div
                        v-if="recentAttendances?.total > 3"
                        class="px-4 py-3 bg-slate-50 border-t border-slate-100 flex items-center justify-between text-xs text-slate-500"
                    >
                        <div>
                            Halaman <span class="font-bold text-slate-700">{{ recentAttendances.current_page }}</span> dari <span class="font-bold text-slate-700">{{ recentAttendances.last_page }}</span>
                            <span class="hidden sm:inline"> (Total {{ recentAttendances.total }} data)</span>
                        </div>
                        <div class="flex items-center gap-1.5">
                            <Link
                                v-if="recentAttendances.prev_page_url"
                                :href="recentAttendances.prev_page_url"
                                preserve-scroll
                                class="px-2.5 py-1 rounded bg-white border border-slate-200 text-slate-700 hover:bg-slate-50 font-medium transition-colors"
                                title="Halaman sebelumnya"
                            >
                                <i class="pi pi-chevron-left text-[10px]" />
                            </Link>
                            <button
                                v-else
                                disabled
                                class="px-2.5 py-1 rounded bg-slate-100 border border-slate-200 text-slate-400 cursor-not-allowed"
                            >
                                <i class="pi pi-chevron-left text-[10px]" />
                            </button>

                            <Link
                                v-if="recentAttendances.next_page_url"
                                :href="recentAttendances.next_page_url"
                                preserve-scroll
                                class="px-2.5 py-1 rounded bg-white border border-slate-200 text-slate-700 hover:bg-slate-50 font-medium transition-colors"
                                title="Halaman berikutnya"
                            >
                                <i class="pi pi-chevron-right text-[10px]" />
                            </Link>
                            <button
                                v-else
                                disabled
                                class="px-2.5 py-1 rounded bg-slate-100 border border-slate-200 text-slate-400 cursor-not-allowed"
                            >
                                <i class="pi pi-chevron-right text-[10px]" />
                            </button>
                        </div>
                    </div>
                </div>

            </div>

            <!-- ============================================================== -->
            <!-- KOLOM KANAN (5 Kolom): Ringkasan Kehadiran & Ketentuan Area      -->
            <!-- ============================================================== -->
            <div class="lg:col-span-5 space-y-6">

                <!-- 1. RINGKASAN KEHADIRAN BULAN BERJALAN -->
                <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-5 space-y-4">
                    <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                        <div class="flex items-center gap-2">
                            <i class="pi pi-calendar-check text-[#005BAC] text-base" />
                            <h2 class="font-bold text-slate-800 text-sm tracking-tight">Presensi Bulan Ini</h2>
                        </div>
                        <span class="text-[11px] font-bold text-slate-500 capitalize">
                            {{ new Intl.DateTimeFormat('id-ID', { month: 'long', year: 'numeric' }).format(new Date()) }}
                        </span>
                    </div>

                    <div class="grid grid-cols-2 gap-3 text-center">
                        <div class="p-3.5 rounded-xl bg-blue-50/80 border border-blue-200/60">
                            <span class="text-[11px] font-bold text-[#005BAC] uppercase tracking-wider block">Total Hadir</span>
                            <span class="text-2xl font-black text-[#005BAC] font-mono mt-1 block">{{ monthSummary?.total_hadir ?? 0 }}</span>
                            <span class="text-[10px] text-blue-600 mt-0.5 block">Hari Kerja</span>
                        </div>
                        <div class="p-3.5 rounded-xl bg-orange-50/80 border border-orange-200/60">
                            <span class="text-[11px] font-bold text-[#F37021] uppercase tracking-wider block">Terlambat</span>
                            <span class="text-2xl font-black text-[#F37021] font-mono mt-1 block">{{ monthSummary?.total_terlambat ?? 0 }}</span>
                            <span class="text-[10px] text-orange-600 mt-0.5 block">Kali Terlambat</span>
                        </div>
                    </div>

                    <div v-if="(monthSummary?.total_potongan ?? 0) > 0" class="p-2.5 rounded-xl bg-rose-50 border border-rose-200/60 flex items-center justify-between text-xs">
                        <span class="text-rose-700 font-medium">Potongan Keterlambatan:</span>
                        <span class="font-bold font-mono text-rose-800">{{ fmtRp(monthSummary.total_potongan) }}</span>
                    </div>
                </div>

                <!-- 2. KETENTUAN & ZONA AREA SAH KFPB -->
                <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-5 space-y-4">
                    <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                        <div class="flex items-center gap-2">
                            <i class="pi pi-shield text-[#005BAC] text-base" />
                            <h2 class="font-bold text-slate-800 text-sm tracking-tight">Area Presensi KFPB</h2>
                        </div>
                        <Tag
                            :value="insideZone ? 'Lokasi Sah' : 'Di Luar Area'"
                            :severity="insideZone ? 'success' : 'danger'"
                            class="!text-[10px] !font-bold"
                        />
                    </div>

                    <!-- Status Indikator Realtime -->
                    <div
                        :class="[
                            'p-3.5 rounded-xl border flex items-center gap-3 transition-colors',
                            insideZone
                                ? 'bg-blue-50 border-blue-200 text-blue-900'
                                : 'bg-rose-50 border-rose-200 text-rose-900'
                        ]"
                    >
                        <i :class="['text-xl shrink-0', insideZone ? 'pi pi-check-circle text-[#005BAC]' : 'pi pi-exclamation-triangle text-rose-600']" />
                        <div class="min-w-0 text-xs">
                            <p class="font-bold leading-tight">
                                {{ insideZone ? (currentZoneName || 'Di Dalam Area KFPB') : 'Posisi Anda Di Luar Area Pabrik' }}
                            </p>
                            <p class="text-[11px] opacity-80 mt-0.5 leading-relaxed">
                                {{ insideZone ? 'Presensi sah dan dapat disubmit sesuai koordinat GPS terverifikasi.' : 'Pastikan Anda berada di salah satu zona gedung resmi Plant Banjaran.' }}
                            </p>
                        </div>
                    </div>

                    <!-- Daftar 6 Gedung KFPB (Ramping tanpa koordinat mentah) -->
                    <div class="space-y-2">
                        <div class="flex items-center justify-between text-xs text-slate-500 font-semibold px-0.5">
                            <span>6 Gedung Resmi Plant Banjaran</span>
                            <span class="text-[10px] text-slate-400">Radius ~300m</span>
                        </div>

                        <div class="space-y-1.5">
                            <div
                                v-for="(zone, i) in geofenceZones"
                                :key="i"
                                :class="[
                                    'flex items-center justify-between px-3 py-2 rounded-xl border text-xs transition-all',
                                    currentZoneName === (zone.label || zone.name)
                                        ? 'bg-blue-50/90 border-[#005BAC]/40 text-[#005BAC] font-bold shadow-xs'
                                        : 'bg-slate-50/70 border-slate-200/60 text-slate-700'
                                ]"
                            >
                                <div class="flex items-center gap-2.5 truncate">
                                    <span
                                        :class="[
                                            'size-5 rounded-md flex items-center justify-center text-[10px] font-bold shrink-0',
                                            currentZoneName === (zone.label || zone.name)
                                                ? 'bg-[#005BAC] text-white'
                                                : 'bg-[#005BAC]/10 text-[#005BAC]'
                                        ]"
                                    >
                                        {{ i + 1 }}
                                    </span>
                                    <span class="truncate">{{ zone.label || zone.name }}</span>
                                </div>
                                <span
                                    v-if="currentZoneName === (zone.label || zone.name)"
                                    class="text-[10px] text-[#005BAC] font-bold flex items-center gap-1 shrink-0 ml-1.5"
                                >
                                    <i class="pi pi-check text-[9px]" /> Terdeteksi
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="p-3 rounded-xl bg-blue-50 border border-blue-200 text-xs text-blue-900 leading-relaxed flex items-start gap-2">
                        <i class="pi pi-info-circle text-[#005BAC] text-sm shrink-0 mt-0.5" />
                        <p class="text-[11px] text-blue-800">
                            Bagi pegawai dengan mode <b>Flexi Time</b> atau <b>PKL / Magang</b>, pembatasan geofence gedung diabaikan otomatis oleh sistem.
                        </p>
                    </div>
                </div>

            </div>

        </div>
    </div>
</template>
