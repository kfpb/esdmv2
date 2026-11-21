<script setup>
import { ref, computed, watch, onMounted, onUnmounted, nextTick } from 'vue';
import { useForm, usePage, Link, Head, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Button from 'primevue/button';
import Select from 'primevue/select';
import Tag from 'primevue/tag';
import InputText from 'primevue/inputtext';
import Dialog from 'primevue/dialog';
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

const authPage = usePage();
const toast = useToast();
const employeeNip = computed(() => props.employee?.nip || authPage.props.auth?.user?.employee_nip || '-');
const rawEmployeeName = computed(() => props.employee?.name || authPage.props.auth?.user?.name || 'Pegawai KFPB');

function toTitleCase(text) {
    if (!text || typeof text !== 'string') return '';
    return text
        .toLowerCase()
        .split(' ')
        .map((word) => {
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
const currentMonthLabel = computed(() =>
    new Intl.DateTimeFormat('id-ID', { month: 'long', year: 'numeric' }).format(new Date())
);

// Daftar presensi terpaginasi (maks 3 per halaman)
const attendanceList = computed(() => {
    if (Array.isArray(props.recentAttendances)) return props.recentAttendances;
    return props.recentAttendances?.data || [];
});

// Flash notification dari backend
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
const gpsLat = ref(null);
const gpsLon = ref(null);
const gpsReady = ref(false);
const insideZone = ref(false);
const currentZoneName = ref(null);
const gpsError = ref('');
const isLocating = ref(false);
const isDevMode = ref(false);
const isSimulated = ref(false);

const mapCheckInContainer = ref(null);
const mapCheckOutContainer = ref(null);
const mapLoadError = ref(false);

let leafletMapIn = null;
let leafletMapOut = null;
let markerIn = null;
let markerOut = null;
let watchId = null;
const activeLeafletTimers = new Set();

// Progressive disclosure & loading states
const showGeofenceZones = ref(false);
const lateTab = ref('esdm');
const isPaginatingHistory = ref(false);
const showEarlyCheckoutDialog = ref(false);
let pendingHistoryNav = false;
let removeStartListener = null;
let removeFinishListener = null;

// ===== State Jam Realtime Digital =====
const realtimeClock = ref('--:--:--');
let clockInterval = null;

// ===== Formulir Presensi =====
const checkInForm = useForm({
    work_shift_id: props.frequentShiftId,
    work_mode: 'WFO',
    latitude: '',
    longitude: '',
});

const workModeOptions = [
    { label: 'Work From Office (WFO)', value: 'WFO' },
    { label: 'Flexi Time', value: 'FLEXI' },
    { label: 'PKL / Magang', value: 'PKL' },
];

const selectedShift = computed(() =>
    props.workShifts.find((s) => s.id === checkInForm.work_shift_id) ?? null
);

const checkOutForm = useForm({
    latitude: '',
    longitude: '',
    notes: '',
});

// Status Alur Presensi Hari Ini
const attendanceStatus = computed(() => {
    if (props.activeLeave) return 'leave';
    if (!props.todayAttendance) return 'none';
    if (props.todayAttendance.is_present_in && !props.todayAttendance.check_out_time) return 'in';
    if (props.todayAttendance.is_present_in && props.todayAttendance.check_out_time) return 'complete';
    return 'none';
});

const activeWorkMode = computed(() =>
    attendanceStatus.value === 'in'
        ? (props.todayAttendance?.work_mode ?? 'WFO')
        : checkInForm.work_mode
);

const requiresGeofence = computed(() => activeWorkMode.value === 'WFO');

const effectiveLocationState = computed(() => {
    if (isLocating.value) {
        return {
            severity: 'neutral',
            boxClass: 'bg-slate-50 border-slate-200 text-slate-800',
            iconClass: 'pi pi-spin motion-reduce:animate-none pi-spinner text-[#005BAC]',
            title: 'Mendeteksi lokasi GPS...',
            detail: 'Pastikan izin lokasi browser aktif.',
        };
    }
    if (!gpsReady.value) {
        return {
            severity: 'warn',
            boxClass: 'bg-amber-50 border-amber-200 text-amber-900',
            iconClass: 'pi pi-exclamation-triangle text-amber-700',
            title: 'Sinyal GPS belum tersedia',
            detail: gpsError.value || 'Aktifkan GPS lalu klik Ulangi GPS.',
        };
    }
    if (!requiresGeofence.value) {
        return {
            severity: 'info',
            boxClass: 'bg-blue-50 border-blue-200 text-[#004280]',
            iconClass: 'pi pi-check-circle text-[#005BAC]',
            title: `Lokasi siap (Mode ${activeWorkMode.value})`,
            detail: 'Mode ini tidak dibatasi area gedung pabrik.',
        };
    }
    if (insideZone.value) {
        return {
            severity: 'success',
            boxClass: 'bg-emerald-50 border-emerald-200 text-emerald-900',
            iconClass: 'pi pi-check-circle text-emerald-700',
            title: `Lokasi sah: ${currentZoneName.value || 'Area Pabrik KFPB'}`,
            detail: 'Berada di dalam area presensi resmi Plant Banjaran.',
        };
    }
    return {
        severity: 'danger',
        boxClass: 'bg-rose-50 border-rose-200 text-rose-900',
        iconClass: 'pi pi-exclamation-triangle text-rose-700',
        title: 'Di luar area pabrik KFPB',
        detail: 'Mode WFO wajib berada di area resmi Plant Banjaran.',
    };
});

const isCheckInDisabled = computed(() =>
    checkInForm.processing ||
    isLocating.value ||
    !gpsReady.value ||
    !checkInForm.work_shift_id ||
    (checkInForm.work_mode === 'WFO' && !insideZone.value)
);

const isCheckOutDisabled = computed(() =>
    checkOutForm.processing ||
    isLocating.value ||
    !gpsReady.value ||
    ((props.todayAttendance?.work_mode ?? 'WFO') === 'WFO' && !insideZone.value)
);

const checkInButtonLabel = computed(() => {
    if (checkInForm.processing) return 'Menyimpan Presensi...';
    if (isLocating.value) return 'Mendeteksi Lokasi...';
    return 'Absen Masuk';
});

const checkOutButtonLabel = computed(() => {
    if (checkOutForm.processing) return 'Menyimpan Presensi...';
    if (isLocating.value) return 'Mendeteksi Lokasi...';
    return 'Absen Pulang';
});

const activeShiftSchedule = computed(() => {
    const att = props.todayAttendance;
    if (!att) return null;

    if (att.shift_check_out) {
        return {
            id: att.work_shift_id ?? null,
            name: att.shift_name,
            check_in: att.shift_check_in ?? null,
            check_out: att.shift_check_out,
            is_night: Boolean(att.shift_is_night),
        };
    }

    if (att.work_shift_id) {
        const byId = props.workShifts.find((s) => s.id === att.work_shift_id);
        if (byId) return byId;
    }

    return props.workShifts.find((s) => s.name === att.shift_name) ?? null;
});

function parseHmToMinutes(timeStr) {
    if (!timeStr || typeof timeStr !== 'string') return null;
    const match = timeStr.trim().match(/^(\d{1,2}):(\d{2})/);
    if (!match) return null;
    const h = Number(match[1]);
    const m = Number(match[2]);
    if (h < 0 || h > 23 || m < 0 || m > 59) return null;
    return h * 60 + m;
}

function isEarlyLeaveAt(now = new Date()) {
    if (attendanceStatus.value !== 'in') return false;

    const shift = activeShiftSchedule.value;
    if (!shift || !shift.check_out) return false;

    const outMinutes = parseHmToMinutes(shift.check_out);
    const inMinutes = parseHmToMinutes(shift.check_in);
    if (outMinutes === null) return false;
    if (inMinutes !== null && outMinutes === inMinutes) return false;

    const nowAtMinute = new Date(
        now.getFullYear(),
        now.getMonth(),
        now.getDate(),
        now.getHours(),
        now.getMinutes(),
        0,
        0
    );

    let baseYear = now.getFullYear();
    let baseMonth = now.getMonth();
    let baseDay = now.getDate();

    const dateMatch = typeof props.todayAttendance?.attendance_date === 'string'
        ? props.todayAttendance.attendance_date.match(/^(\d{4})-(\d{2})-(\d{2})$/)
        : null;

    if (dateMatch) {
        baseYear = Number(dateMatch[1]);
        baseMonth = Number(dateMatch[2]) - 1;
        baseDay = Number(dateMatch[3]);
    }

    const crossesMidnight = Boolean(shift.is_night) || (inMinutes !== null && outMinutes < inMinutes);
    let dayOffset = 0;

    if (crossesMidnight) {
        const actualInMinutes = parseHmToMinutes(props.todayAttendance?.check_in_time);
        dayOffset = (actualInMinutes !== null && actualInMinutes < outMinutes) ? 0 : 1;
    }

    const scheduledCheckoutAtMinute = new Date(
        baseYear,
        baseMonth,
        baseDay + dayOffset,
        Math.floor(outMinutes / 60),
        outMinutes % 60,
        0,
        0
    );

    return (scheduledCheckoutAtMinute.getTime() - nowAtMinute.getTime()) >= 60 * 1000;
}

function handleCheckOutClick() {
    if (isCheckOutDisabled.value) return;

    const now = new Date();
    realtimeClock.value = [
        now.getHours().toString().padStart(2, '0'),
        now.getMinutes().toString().padStart(2, '0'),
        now.getSeconds().toString().padStart(2, '0'),
    ].join(':');

    if (isEarlyLeaveAt(now)) {
        showEarlyCheckoutDialog.value = true;
        return;
    }
    submitCheckOut();
}

function confirmEarlyCheckOut() {
    showEarlyCheckoutDialog.value = false;
    submitCheckOut();
}

function handlePaginationClick() {
    pendingHistoryNav = true;
}

// ===== Perhitungan Jarak Haversine (meter) =====
function haversineDistance(lat1, lon1, lat2, lon2) {
    const R = 6371000;
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
                layer.bindPopup(`<b>${feature.properties.label}</b><br><span style="font-size:11px;color:#475569;">Area Presensi Resmi KFPB</span>`);
            }
        },
        style: {
            color: '#005BAC',
            fillColor: '#3b82f6',
            fillOpacity: 0.22,
            weight: 2,
        },
    }).addTo(lmap);

    props.geofenceZones.forEach((zone) => {
        if (zone.koordinat && zone.koordinat.length >= 3) {
            const geoJsonCoords = zone.koordinat.map((pt) => [pt[1], pt[0]]);
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
            // Non-interactive circle so clicks inside overlapping building polygons still open popups
            L.circle([zone.latitude, zone.longitude], {
                radius: zone.radius || 50,
                color: '#3b82f6',
                fillColor: '#93c5fd',
                fillOpacity: 0.1,
                weight: 1,
                dashArray: '3, 4',
                interactive: false,
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

    addBuildingPolygons(lmap);

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
        mapLoadError.value = false;
        callback();
        return;
    }

    let attempts = 0;
    const checkId = setInterval(() => {
        attempts += 1;
        if (typeof L !== 'undefined') {
            clearInterval(checkId);
            activeLeafletTimers.delete(checkId);
            mapLoadError.value = false;
            callback();
        } else if (attempts >= 40) {
            clearInterval(checkId);
            activeLeafletTimers.delete(checkId);
            mapLoadError.value = true;
        }
    }, 300);

    activeLeafletTimers.add(checkId);
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
    if (el) {
        nextTick(() => initOrUpdateCheckInMap());
    } else if (leafletMapIn) {
        leafletMapIn.remove();
        leafletMapIn = null;
        markerIn = null;
    }
});

watch(mapCheckOutContainer, (el) => {
    if (el) {
        nextTick(() => initOrUpdateCheckOutMap());
    } else if (leafletMapOut) {
        leafletMapOut.remove();
        leafletMapOut = null;
        markerOut = null;
    }
});

// GPS Callbacks
function onGpsSuccess(position) {
    const lat = position.coords.latitude;
    const lon = position.coords.longitude;

    gpsLat.value = lat;
    gpsLon.value = lon;
    gpsReady.value = true;
    gpsError.value = '';
    isLocating.value = false;
    insideZone.value = checkInsideZones(lat, lon);

    checkInForm.latitude = String(lat);
    checkInForm.longitude = String(lon);
    checkOutForm.latitude = String(lat);
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
    isLocating.value = true;
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
    isLocating.value = false;
    gpsError.value = '';
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
            summary: 'Di Luar Area KFPB',
            detail: `Lokasi Anda (${lat.toFixed(5)}, ${lon.toFixed(5)}) berada di luar area pabrik KFPB. Absen Masuk ditolak.`,
            life: 7000,
        });
        return;
    }

    checkInForm.latitude = String(lat);
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
            summary: 'Di Luar Area KFPB',
            detail: `Lokasi Anda (${lat.toFixed(5)}, ${lon.toFixed(5)}) berada di luar area pabrik KFPB. Absen Pulang ditolak.`,
            life: 7000,
        });
        return;
    }

    checkOutForm.latitude = String(lat);
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

    removeStartListener = router.on('start', () => {
        if (pendingHistoryNav) {
            isPaginatingHistory.value = true;
        }
    });
    removeFinishListener = router.on('finish', () => {
        pendingHistoryNav = false;
        isPaginatingHistory.value = false;
    });

    window.addEventListener('resize', handleResize);

    if (attendanceStatus.value !== 'complete') {
        startGeolocation();
        nextTick(() => {
            initOrUpdateCheckInMap();
            initOrUpdateCheckOutMap();
        });
    }
});

onUnmounted(() => {
    if (clockInterval) clearInterval(clockInterval);
    if (watchId !== null) navigator.geolocation.clearWatch(watchId);
    activeLeafletTimers.forEach((timerId) => clearInterval(timerId));
    activeLeafletTimers.clear();
    if (typeof removeStartListener === 'function') removeStartListener();
    if (typeof removeFinishListener === 'function') removeFinishListener();
    window.removeEventListener('resize', handleResize);
    if (leafletMapIn) { leafletMapIn.remove(); leafletMapIn = null; }
    if (leafletMapOut) { leafletMapOut.remove(); leafletMapOut = null; }
});

const fmt = (t) => (t ? t.substring(0, 5) : '-');
const fmtRp = (n) => new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(n);
</script>

<template>
    <Head title="Presensi Harian" />

    <div class="lg:max-w-7xl lg:mx-auto lg:px-6 lg:pt-1 lg:pb-6 lg:space-y-5">
        <Toast
            position="top-right"
            :breakpoints="{ '640px': { width: 'calc(100vw - 20px)', left: '10px', right: '10px', top: '10px' } }"
        />

        <section
            aria-label="Ringkasan Pegawai"
            class="relative text-white px-4 sm:px-6 pt-2 pb-[76px] sm:pb-[88px] lg:text-slate-900 lg:px-0 lg:pt-0 lg:pb-0"
        >
            <div
                aria-hidden="true"
                class="mobile-navy-arch vt-mobile-navy lg:hidden absolute inset-x-0 -top-[61px] bottom-0 bg-[#0b1c34] pointer-events-none"
            />

            <div class="vt-mobile-hero-content relative z-10 flex flex-col sm:flex-row sm:items-end justify-between gap-3">
                <div class="space-y-1 min-w-0">
                    <h1 class="text-xl sm:text-2xl font-bold text-white lg:text-slate-900 tracking-tight">
                        Halo, {{ greetingName }}
                    </h1>
                    <p class="text-xs sm:text-sm text-blue-100 lg:text-slate-600 flex flex-wrap items-center gap-x-2 gap-y-1">
                        <span>{{ todayLabel }}</span>
                        <span aria-hidden="true">&bull;</span>
                        <span class="font-mono font-semibold text-white lg:text-slate-700">NIP {{ employeeNip }}</span>
                        <span aria-hidden="true">&bull;</span>
                        <span>{{ formattedPosition }}</span>
                        <span aria-hidden="true">&bull;</span>
                        <span class="text-blue-200 lg:text-[#005BAC] font-medium">{{ formattedDivision }}</span>
                    </p>
                </div>

                <Link
                    href="/absensi/laporan"
                    class="inline-flex items-center justify-center gap-2 min-h-[44px] px-4 py-2 rounded-xl border border-[#6b8bb8] bg-[#162b4a] hover:bg-[#1e375e] text-white focus-visible:!outline-white lg:border-slate-300 lg:bg-white lg:hover:bg-slate-50 lg:text-slate-700 lg:focus-visible:!outline-[#005BAC] font-semibold text-xs sm:text-sm transition-colors motion-reduce:transition-none shrink-0 self-start sm:self-auto"
                >
                    <i class="pi pi-calendar text-xs" aria-hidden="true" />
                    <span>Laporan Absensi</span>
                </Link>
            </div>
        </section>

        <div class="vt-mobile-body relative z-10 -mt-12 sm:-mt-14 px-4 sm:px-6 pb-6 space-y-5 lg:mt-0 lg:px-0 lg:pb-0">

        <!-- ================= KARTU ULANG TAHUN KORPORAT ================= -->
        <section
            v-if="birthdayData.isBirthday"
            aria-label="Ucapan Ulang Tahun"
            :class="[
                'rounded-2xl p-4 sm:p-5 text-white shadow-xs',
                birthdayData.gender === 'Female' ? 'bg-rose-700' : 'bg-[#004280]'
            ]"
        >
            <div class="flex flex-col sm:flex-row items-start sm:items-center gap-3.5">
                <div class="size-11 rounded-xl bg-white/10 flex items-center justify-center shrink-0 border border-white/20">
                    <i class="pi pi-gift text-xl text-white" aria-hidden="true" />
                </div>
                <div class="space-y-0.5">
                    <span class="text-xs font-bold text-amber-100 block">Ucapan Khusus</span>
                    <h2 class="text-sm sm:text-base font-bold">Selamat Ulang Tahun, {{ birthdayData.employeeName }}!</h2>
                    <p class="text-xs text-white/95 leading-relaxed">
                        Manajemen dan keluarga besar PT Kimia Farma Plant Banjaran mendoakan kesehatan, kelancaran rezeki, dan kesuksesan bagi Anda.
                    </p>
                </div>
            </div>
        </section>

        <!-- ================= KARTU LAPOR TIDAK MASUK / IZIN (TERLIHAT JELAS SAAT MODE ABSEN MASUK) ================= -->
        <section
            v-if="attendanceStatus === 'none'"
            aria-label="Lapor Tidak Masuk Kerja"
            class="bg-blue-50 border border-[#005BAC]/30 rounded-2xl px-4 sm:px-5 py-3.5 shadow-md lg:shadow-2xs flex flex-col sm:flex-row sm:items-center justify-between gap-3"
        >
            <div class="flex items-center gap-3.5 min-w-0">
                <div class="size-10 rounded-xl bg-[#005BAC] text-white flex items-center justify-center shrink-0">
                    <i class="pi pi-file-edit text-base" aria-hidden="true" />
                </div>
                <div class="min-w-0">
                    <h2 class="text-sm sm:text-base font-bold text-slate-900 tracking-tight">
                        Lapor Tidak Masuk Kerja
                    </h2>
                    <p class="text-xs text-slate-700">
                        Pengajuan sakit, cuti tahunan, dinas luar, atau dispensasi kerja.
                    </p>
                </div>
            </div>
            <Link
                href="/izin/buat"
                class="inline-flex items-center justify-center gap-2 min-h-[44px] px-4 py-2 rounded-xl bg-white hover:bg-[#005BAC] text-[#005BAC] hover:text-white border border-[#005BAC]/40 font-semibold text-xs sm:text-sm transition-colors shrink-0 w-full sm:w-auto"
            >
                <span>Ajukan Izin / Cuti</span>
                <i class="pi pi-arrow-right text-xs" aria-hidden="true" />
            </Link>
        </section>

        <!-- ================= KONDISI A: KEHADIRAN HARI INI TELAH LENGKAP ================= -->
        <div v-if="attendanceStatus === 'complete'" class="max-w-3xl mx-auto">
            <div class="bg-white rounded-2xl border border-slate-200/90 shadow-md lg:shadow-sm overflow-hidden">
                <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between gap-3">
                    <div class="flex items-center gap-2.5">
                        <div class="size-9 rounded-xl bg-blue-50 text-[#005BAC] border border-blue-100 flex items-center justify-center shrink-0">
                            <i class="pi pi-check-circle text-base" aria-hidden="true" />
                        </div>
                        <h2 class="font-bold text-slate-900 text-base tracking-tight">Status Kehadiran Hari Ini</h2>
                    </div>
                    <Tag value="Selesai" severity="success" class="!text-xs !font-bold" />
                </div>

                <div class="p-5 sm:p-6 space-y-5">
                    <div class="p-4 rounded-xl bg-emerald-50 border border-emerald-200 space-y-1">
                        <div class="font-bold text-emerald-900 text-sm flex items-center gap-2">
                            <i class="pi pi-verified text-emerald-700 text-base" aria-hidden="true" />
                            <span>Presensi Hari Ini Lengkap</span>
                        </div>
                        <p class="text-xs sm:text-sm text-emerald-900 leading-relaxed">
                            Masuk pukul <b>{{ fmt(todayAttendance?.check_in_time) }} WIB</b> dan pulang pukul <b>{{ fmt(todayAttendance?.check_out_time) }} WIB</b>.
                        </p>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
                        <div class="p-4 rounded-xl bg-slate-50 border border-slate-200 space-y-2">
                            <div class="text-xs font-semibold text-slate-700 flex items-center justify-between">
                                <span>Aplikasi eSDM</span>
                                <span class="text-[11px] text-[#005BAC] font-semibold font-mono">Web/GPS</span>
                            </div>
                            <div class="grid grid-cols-2 gap-2 text-xs">
                                <div>
                                    <span class="text-slate-600 block">Jam Masuk</span>
                                    <span class="font-bold font-mono text-sm sm:text-base text-[#005BAC]">{{ fmt(todayAttendance?.check_in_time) }}</span>
                                </div>
                                <div>
                                    <span class="text-slate-600 block">Jam Pulang</span>
                                    <span class="font-bold font-mono text-sm sm:text-base text-[#005BAC]">{{ fmt(todayAttendance?.check_out_time) }}</span>
                                </div>
                            </div>
                        </div>

                        <div class="p-4 rounded-xl bg-slate-50 border border-slate-200 space-y-2">
                            <div class="text-xs font-semibold text-slate-700 flex items-center justify-between">
                                <span>Mesin Wajah (FaceID)</span>
                                <span class="text-[11px] text-[#005BAC] font-semibold font-mono">Mesin Plant</span>
                            </div>
                            <div class="grid grid-cols-2 gap-2 text-xs">
                                <div>
                                    <span class="text-slate-600 block">Jam Masuk</span>
                                    <span class="font-bold font-mono text-sm sm:text-base text-[#005BAC]">{{ faceCheckIn }}</span>
                                </div>
                                <div>
                                    <span class="text-slate-600 block">Jam Pulang</span>
                                    <span class="font-bold font-mono text-sm sm:text-base text-[#005BAC]">{{ faceCheckOut }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <p class="text-xs text-slate-600 text-center">
                        Sinkronisasi FaceID berkala (±1 jam).
                    </p>

                    <div class="pt-1 flex flex-wrap items-center justify-center gap-3">
                        <Link
                            href="/absensi/laporan"
                            class="inline-flex items-center justify-center gap-2 min-h-[44px] px-4 py-2 rounded-xl bg-[#005BAC] hover:bg-[#004280] text-white font-semibold text-xs sm:text-sm transition-colors"
                        >
                            <i class="pi pi-calendar text-xs" aria-hidden="true" />
                            <span>Lihat Laporan Absensi</span>
                        </Link>
                        <Link
                            href="/lembur/ajukan"
                            class="inline-flex items-center justify-center gap-2 min-h-[44px] px-4 py-2 rounded-xl border border-slate-300 bg-white hover:bg-slate-50 text-slate-700 font-semibold text-xs sm:text-sm transition-colors"
                        >
                            <i class="pi pi-clock text-xs" aria-hidden="true" />
                            <span>Pengajuan Lembur (SPL)</span>
                        </Link>
                    </div>
                </div>
            </div>
        </div>

        <!-- ================= KONDISI B: BELUM MASUK / SEDANG MASUK / IZIN ================= -->
        <div v-else class="grid grid-cols-1 xl:grid-cols-12 gap-5 items-start">

            <!-- ================= KOLOM KIRI (7 Kolom): Tugas Utama & Riwayat ================= -->
            <div class="xl:col-span-7 min-w-0 space-y-5">

                <!-- 1A. FORM PRESENSI MASUK -->
                <section
                    v-if="attendanceStatus === 'none'"
                    aria-label="Formulir Presensi Masuk"
                    class="bg-white rounded-2xl border border-slate-200/90 shadow-sm overflow-hidden"
                >
                    <div class="px-5 py-4 border-b border-slate-100 flex flex-wrap items-center justify-between gap-3">
                        <div class="flex items-center gap-3 min-w-0">
                            <div class="size-9 rounded-xl bg-blue-50 text-[#005BAC] border border-blue-100 flex items-center justify-center shrink-0">
                                <i class="pi pi-sign-in text-base" aria-hidden="true" />
                            </div>
                            <div class="min-w-0">
                                <h2 class="font-bold text-base text-slate-900 tracking-tight">Presensi Masuk</h2>
                                <p class="text-xs text-slate-600 mt-0.5 truncate">
                                    {{ selectedShift ? `${selectedShift.name} (${selectedShift.check_in} - ${selectedShift.check_out})` : 'Pilih shift kerja Anda' }}
                                </p>
                            </div>
                        </div>
                        <div class="flex items-baseline gap-1.5 shrink-0">
                            <span class="font-mono text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight tabular-nums select-none">
                                {{ realtimeClock }}
                            </span>
                            <span class="text-xs font-bold text-slate-600">WIB</span>
                        </div>
                    </div>

                    <div class="p-5 space-y-4">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                            <div>
                                <label id="attendance-shift-label" for="attendance-shift" class="block text-xs font-semibold text-slate-700 mb-1.5">
                                    Shift Kerja
                                </label>
                                <Select
                                    label-id="attendance-shift"
                                    aria-labelledby="attendance-shift-label"
                                    v-model="checkInForm.work_shift_id"
                                    :options="workShifts"
                                    option-label="name"
                                    option-value="id"
                                    placeholder="Pilih Shift"
                                    class="w-full !text-sm"
                                >
                                    <template #option="{ option }">
                                        <div class="flex items-center justify-between w-full">
                                            <span class="font-medium text-xs sm:text-sm">{{ option.name }}</span>
                                            <span class="text-xs text-slate-600 font-mono ml-3">{{ option.check_in }} - {{ option.check_out }}</span>
                                        </div>
                                    </template>
                                    <template #value="{ value }">
                                        <span v-if="selectedShift" class="text-xs sm:text-sm">
                                            {{ selectedShift.name }}
                                            <span class="text-slate-600 font-mono ml-1 text-xs">
                                                ({{ selectedShift.check_in }} - {{ selectedShift.check_out }})
                                            </span>
                                        </span>
                                        <span v-else class="text-slate-600 text-xs sm:text-sm">Pilih Shift</span>
                                    </template>
                                </Select>
                                <p class="text-xs text-slate-600 mt-1">
                                    Hari libur/lembur? Pilih shift lembur.
                                </p>
                            </div>

                            <div>
                                <label id="attendance-work-mode-label" for="attendance-work-mode" class="block text-xs font-semibold text-slate-700 mb-1.5">
                                    Mode Kerja
                                </label>
                                <Select
                                    label-id="attendance-work-mode"
                                    aria-labelledby="attendance-work-mode-label"
                                    v-model="checkInForm.work_mode"
                                    :options="workModeOptions"
                                    option-label="label"
                                    option-value="value"
                                    class="w-full !text-sm"
                                />
                                <p v-if="checkInForm.work_mode !== 'WFO'" class="text-xs text-[#005BAC] mt-1 font-medium">
                                    Bebas area pabrik (GPS tetap dicatat).
                                </p>
                            </div>
                        </div>

                        <!-- Status Bar Lokasi Terpadu -->
                        <div
                            role="status"
                            aria-live="polite"
                            :class="['p-3.5 rounded-xl border flex flex-col sm:flex-row sm:items-center justify-between gap-2.5 transition-colors', effectiveLocationState.boxClass]"
                        >
                            <div class="flex items-start gap-2.5 min-w-0">
                                <i :class="['text-base shrink-0 mt-0.5', effectiveLocationState.iconClass]" aria-hidden="true" />
                                <div class="min-w-0 text-xs">
                                    <p class="font-bold leading-snug">{{ effectiveLocationState.title }}</p>
                                    <p class="text-xs mt-0.5 leading-snug">{{ effectiveLocationState.detail }}</p>
                                </div>
                            </div>

                            <div class="flex items-center gap-2 shrink-0 flex-wrap">
                                <Button
                                    v-if="!isLocating && (!gpsReady || (requiresGeofence && !insideZone))"
                                    type="button"
                                    label="Ulangi GPS"
                                    icon="pi pi-refresh"
                                    size="small"
                                    severity="secondary"
                                    outlined
                                    class="!text-xs !min-h-[44px] !px-3 !rounded-lg !text-slate-700 !border-slate-300 !bg-white hover:!bg-slate-50"
                                    @click="startGeolocation(false)"
                                />
                                <Button
                                    v-if="isDevMode && !isLocating && (!gpsReady || (requiresGeofence && !insideZone))"
                                    type="button"
                                    label="Simulasi KFPB"
                                    icon="pi pi-map-marker"
                                    size="small"
                                    outlined
                                    class="!text-xs !min-h-[44px] !px-3 !rounded-lg !text-[#005BAC] !border-[#005BAC]/50 !bg-white hover:!bg-blue-50"
                                    @click="useLocalSimulation"
                                />
                                <Button
                                    v-if="gpsReady && (!requiresGeofence || insideZone)"
                                    type="button"
                                    icon="pi pi-crosshairs"
                                    label="Pusatkan"
                                    size="small"
                                    severity="secondary"
                                    outlined
                                    class="!text-xs !min-h-[44px] !px-3 !rounded-lg !text-slate-700 !border-slate-300 !bg-white hover:!bg-slate-50"
                                    @click="centerUserLocation"
                                />
                            </div>
                        </div>

                        <!-- Peta Lokasi -->
                        <div class="space-y-1.5">
                            <div
                                class="relative z-0 isolate w-full rounded-xl border border-slate-200 overflow-hidden shadow-2xs"
                                style="height: 240px; background: #e2e8f0;"
                            >
                                <div ref="mapCheckInContainer" class="w-full h-full" />
                                <div
                                    v-if="mapLoadError"
                                    class="absolute inset-0 flex flex-col items-center justify-center p-4 text-center bg-slate-100 text-slate-700 text-xs gap-1.5"
                                >
                                    <i class="pi pi-map text-lg text-[#005BAC]" aria-hidden="true" />
                                    <p class="font-semibold">Peta lokasi tidak dapat dimuat saat ini.</p>
                                    <p class="text-slate-600">Verifikasi koordinat GPS tetap berjalan di perangkat Anda.</p>
                                </div>
                            </div>

                            <div class="flex items-center justify-between text-xs text-slate-600 flex-wrap gap-2">
                                <div class="flex items-center gap-1.5">
                                    <span class="size-2.5 rounded-full bg-[#005BAC]" aria-hidden="true" />
                                    <span>Area Biru = Wilayah Pabrik KFPB</span>
                                </div>
                                <div v-if="gpsReady && gpsLat" class="font-mono text-slate-700 flex items-center gap-1">
                                    <i class="pi pi-map-marker text-[#005BAC]" aria-hidden="true" />
                                    <span>{{ gpsLat?.toFixed(5) }}, {{ gpsLon?.toFixed(5) }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- CTA Utama Absen Masuk -->
                        <div>
                            <Button
                                type="button"
                                :label="checkInButtonLabel"
                                icon="pi pi-sign-in"
                                :loading="checkInForm.processing || isLocating"
                                :disabled="isCheckInDisabled"
                                class="w-full !min-h-[48px] !bg-[#005BAC] !border-[#005BAC] hover:!bg-[#004280] !font-bold !py-3 shadow-sm text-sm sm:text-base !rounded-xl"
                                @click="submitCheckIn"
                            />
                            <p v-if="!checkInForm.work_shift_id" class="text-xs text-slate-600 mt-1.5">
                                Pilih shift kerja terlebih dahulu.
                            </p>
                        </div>
                    </div>
                </section>

                <!-- 1B. ALUR SUDAH ABSEN MASUK: KETERLAMBATAN HARI INI & FORM PRESENSI PULANG -->
                <template v-else-if="attendanceStatus === 'in'">
                    <section
                        aria-label="Keterlambatan Hari Ini"
                        class="bg-white rounded-2xl border border-slate-200/90 shadow-md lg:shadow-xs overflow-hidden"
                    >
                        <div class="px-5 py-3.5 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                            <div class="flex items-center gap-3 min-w-0">
                                <div class="size-9 rounded-xl bg-orange-50 text-[#c2410c] border border-orange-200/70 flex items-center justify-center shrink-0">
                                    <i class="pi pi-clock text-base" aria-hidden="true" />
                                </div>
                                <div class="min-w-0">
                                    <h2 class="font-bold text-slate-900 text-sm tracking-tight">Keterlambatan Hari Ini</h2>
                                    <p class="text-xs text-slate-600 mt-0.5">5 presensi terlambat terbaru hari ini</p>
                                </div>
                            </div>

                            <div class="grid grid-cols-2 p-1 bg-slate-100 rounded-xl gap-1 shrink-0 w-full sm:w-auto">
                                <button
                                    type="button"
                                    :aria-pressed="String(lateTab === 'esdm')"
                                    class="min-h-[44px] px-3 py-1.5 rounded-lg text-xs font-bold transition-colors cursor-pointer"
                                    :class="lateTab === 'esdm'
                                        ? 'bg-white text-slate-900 shadow-2xs'
                                        : 'text-slate-700 hover:text-slate-900'"
                                    @click="lateTab = 'esdm'"
                                >
                                    Aplikasi eSDM ({{ topLateToday.length }})
                                </button>
                                <button
                                    type="button"
                                    :aria-pressed="String(lateTab === 'face')"
                                    class="min-h-[44px] px-3 py-1.5 rounded-lg text-xs font-bold transition-colors cursor-pointer"
                                    :class="lateTab === 'face'
                                        ? 'bg-white text-slate-900 shadow-2xs'
                                        : 'text-slate-700 hover:text-slate-900'"
                                    @click="lateTab = 'face'"
                                >
                                    Mesin Wajah ({{ topLateBiometric.length }})
                                </button>
                            </div>
                        </div>

                        <div v-if="lateTab === 'esdm'" class="overflow-x-auto">
                            <table class="w-full text-left text-xs text-slate-700 border-collapse">
                                <caption class="sr-only">Daftar keterlambatan presensi aplikasi eSDM hari ini</caption>
                                <thead>
                                    <tr class="bg-slate-50 text-slate-700 font-semibold border-b border-slate-200 text-xs">
                                        <th scope="col" class="py-2.5 px-4">Nama &amp; Unit</th>
                                        <th scope="col" class="py-2.5 px-4">Masuk</th>
                                        <th scope="col" class="py-2.5 px-4">Shift</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    <tr v-for="(late, idx) in topLateToday" :key="idx" class="hover:bg-slate-50/50">
                                        <td class="py-2.5 px-4">
                                            <div class="font-bold text-slate-800">{{ late.name }}</div>
                                            <div class="text-xs text-slate-600">{{ late.division }}</div>
                                        </td>
                                        <td class="py-2.5 px-4 font-mono font-bold text-rose-700 whitespace-nowrap">{{ late.time }}</td>
                                        <td class="py-2.5 px-4 text-slate-700 font-medium">{{ late.shift }}</td>
                                    </tr>
                                    <tr v-if="topLateToday.length === 0">
                                        <td colspan="3" class="py-5 px-4 text-center text-slate-600">
                                            Tidak ada data keterlambatan eSDM hari ini.
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div v-else class="overflow-x-auto">
                            <table class="w-full text-left text-xs text-slate-700 border-collapse">
                                <caption class="sr-only">Daftar keterlambatan mesin wajah hari ini</caption>
                                <thead>
                                    <tr class="bg-slate-50 text-slate-700 font-semibold border-b border-slate-200 text-xs">
                                        <th scope="col" class="py-2.5 px-4">Nama &amp; Unit</th>
                                        <th scope="col" class="py-2.5 px-4">Masuk</th>
                                        <th scope="col" class="py-2.5 px-4">Metode</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    <tr v-for="(lateBio, idx) in topLateBiometric" :key="idx" class="hover:bg-slate-50/50">
                                        <td class="py-2.5 px-4">
                                            <div class="font-bold text-slate-800">{{ lateBio.name }}</div>
                                            <div class="text-xs text-slate-600">{{ lateBio.division }}</div>
                                        </td>
                                        <td class="py-2.5 px-4 font-mono font-bold text-rose-700 whitespace-nowrap">{{ lateBio.time }}</td>
                                        <td class="py-2.5 px-4 text-slate-700 font-medium">{{ lateBio.shift }}</td>
                                    </tr>
                                    <tr v-if="topLateBiometric.length === 0">
                                        <td colspan="3" class="py-5 px-4 text-center text-slate-600">
                                            Tidak ada data keterlambatan mesin wajah hari ini.
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </section>

                    <section
                        aria-label="Formulir Presensi Pulang"
                        class="bg-white rounded-2xl border border-slate-200/90 shadow-sm overflow-hidden"
                    >
                        <div class="px-5 py-4 border-b border-slate-100 flex flex-wrap items-center justify-between gap-3">
                            <div class="flex items-center gap-3 min-w-0">
                                <div class="size-9 rounded-xl bg-blue-50 text-[#005BAC] border border-blue-100 flex items-center justify-center shrink-0">
                                    <i class="pi pi-sign-out text-base" aria-hidden="true" />
                                </div>
                                <div class="min-w-0">
                                    <h2 class="font-bold text-base text-slate-900 tracking-tight">Presensi Pulang</h2>
                                    <p class="text-xs text-slate-600 mt-0.5 truncate">
                                        Masuk {{ fmt(todayAttendance?.check_in_time) }} WIB &bull; {{ todayAttendance?.shift_name }} &bull; {{ todayAttendance?.work_mode }}
                                    </p>
                                </div>
                            </div>
                            <div class="flex items-baseline gap-1.5 shrink-0">
                                <span class="font-mono text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight tabular-nums select-none">
                                    {{ realtimeClock }}
                                </span>
                                <span class="text-xs font-bold text-slate-600">WIB</span>
                            </div>
                        </div>

                    <div class="p-5 space-y-4">
                        <!-- Status Bar Lokasi Terpadu -->
                        <div
                            role="status"
                            aria-live="polite"
                            :class="['p-3.5 rounded-xl border flex flex-col sm:flex-row sm:items-center justify-between gap-2.5 transition-colors', effectiveLocationState.boxClass]"
                        >
                            <div class="flex items-start gap-2.5 min-w-0">
                                <i :class="['text-base shrink-0 mt-0.5', effectiveLocationState.iconClass]" aria-hidden="true" />
                                <div class="min-w-0 text-xs">
                                    <p class="font-bold leading-snug">{{ effectiveLocationState.title }}</p>
                                    <p class="text-xs mt-0.5 leading-snug">{{ effectiveLocationState.detail }}</p>
                                </div>
                            </div>

                            <div class="flex items-center gap-2 shrink-0 flex-wrap">
                                <Button
                                    v-if="!isLocating && (!gpsReady || (requiresGeofence && !insideZone))"
                                    type="button"
                                    label="Ulangi GPS"
                                    icon="pi pi-refresh"
                                    size="small"
                                    severity="secondary"
                                    outlined
                                    class="!text-xs !min-h-[44px] !px-3 !rounded-lg !text-slate-700 !border-slate-300 !bg-white hover:!bg-slate-50"
                                    @click="startGeolocation(false)"
                                />
                                <Button
                                    v-if="isDevMode && !isLocating && (!gpsReady || (requiresGeofence && !insideZone))"
                                    type="button"
                                    label="Simulasi KFPB"
                                    icon="pi pi-map-marker"
                                    size="small"
                                    outlined
                                    class="!text-xs !min-h-[44px] !px-3 !rounded-lg !text-[#005BAC] !border-[#005BAC]/50 !bg-white hover:!bg-blue-50"
                                    @click="useLocalSimulation"
                                />
                                <Button
                                    v-if="gpsReady && (!requiresGeofence || insideZone)"
                                    type="button"
                                    icon="pi pi-crosshairs"
                                    label="Pusatkan"
                                    size="small"
                                    severity="secondary"
                                    outlined
                                    class="!text-xs !min-h-[44px] !px-3 !rounded-lg !text-slate-700 !border-slate-300 !bg-white hover:!bg-slate-50"
                                    @click="centerUserLocation"
                                />
                            </div>
                        </div>

                        <!-- Peta Lokasi Pulang -->
                        <div class="space-y-1.5">
                            <div
                                class="relative z-0 isolate w-full rounded-xl border border-slate-200 overflow-hidden shadow-2xs"
                                style="height: 230px; background: #e2e8f0;"
                            >
                                <div ref="mapCheckOutContainer" class="w-full h-full" />
                                <div
                                    v-if="mapLoadError"
                                    class="absolute inset-0 flex flex-col items-center justify-center p-4 text-center bg-slate-100 text-slate-700 text-xs gap-1.5"
                                >
                                    <i class="pi pi-map text-lg text-[#005BAC]" aria-hidden="true" />
                                    <p class="font-semibold">Peta lokasi tidak dapat dimuat saat ini.</p>
                                    <p class="text-slate-600">Verifikasi koordinat GPS tetap berjalan di perangkat Anda.</p>
                                </div>
                            </div>

                            <div class="flex items-center justify-between text-xs text-slate-600 flex-wrap gap-2">
                                <div class="flex items-center gap-1.5">
                                    <span class="size-2.5 rounded-full bg-[#005BAC]" aria-hidden="true" />
                                    <span>Area Biru = Wilayah Pabrik KFPB</span>
                                </div>
                                <div v-if="gpsReady && gpsLat" class="font-mono text-slate-700 flex items-center gap-1">
                                    <i class="pi pi-map-marker text-[#005BAC]" aria-hidden="true" />
                                    <span>{{ gpsLat?.toFixed(5) }}, {{ gpsLon?.toFixed(5) }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Catatan Pulang Opsional -->
                        <div>
                            <label for="checkout-notes" class="block text-xs font-semibold text-slate-700 mb-1.5">
                                Catatan Pulang (Opsional)
                            </label>
                            <InputText
                                id="checkout-notes"
                                name="notes"
                                v-model="checkOutForm.notes"
                                placeholder="Isi catatan jika ada..."
                                class="w-full !text-sm"
                            />
                        </div>

                        <!-- CTA Utama Absen Pulang -->
                        <Button
                            type="button"
                            :label="checkOutButtonLabel"
                            icon="pi pi-sign-out"
                            :loading="checkOutForm.processing || isLocating"
                            :disabled="isCheckOutDisabled"
                            class="w-full !min-h-[48px] !bg-[#005BAC] !border-[#005BAC] hover:!bg-[#004280] !font-bold !py-3 shadow-sm text-sm sm:text-base !rounded-xl"
                            @click="handleCheckOutClick"
                        />
                    </div>
                    </section>
                </template>

                <!-- 1C. STATUS IZIN / CUTI AKTIF -->
                <section
                    v-else-if="attendanceStatus === 'leave'"
                    aria-label="Status Izin atau Cuti Aktif"
                    class="bg-white rounded-2xl border border-slate-200/90 shadow-md lg:shadow-sm p-5 space-y-3"
                >
                    <div class="flex items-center justify-between gap-2">
                        <div class="flex items-center gap-2.5 min-w-0">
                            <div class="size-9 rounded-xl bg-blue-50 text-[#005BAC] border border-blue-100 flex items-center justify-center shrink-0">
                                <i class="pi pi-calendar-minus text-base" aria-hidden="true" />
                            </div>
                            <h2 class="font-bold text-base text-slate-900 tracking-tight truncate">
                                Permohonan {{ activeLeave.name }} Tercatat
                            </h2>
                        </div>
                        <Tag value="Izin / Cuti" severity="warn" class="!text-xs !font-bold shrink-0" />
                    </div>
                    <div class="bg-blue-50 border border-blue-200 rounded-xl p-4 text-xs sm:text-sm text-blue-900 leading-relaxed">
                        Keterangan: <b>{{ activeLeave.reason }}</b>. Anda tidak perlu absen masuk dan pulang hari ini.
                    </div>
                    <div>
                        <Link
                            href="/izin/buat?tab=history"
                            class="inline-flex items-center justify-center gap-2 min-h-[44px] px-4 py-2 rounded-xl border border-slate-300 bg-white hover:bg-slate-50 text-slate-700 font-semibold text-xs transition-colors"
                        >
                            <span>Lihat Riwayat Izin</span>
                            <i class="pi pi-arrow-right text-xs" aria-hidden="true" />
                        </Link>
                    </div>
                </section>

                <!-- 2. RIWAYAT PRESENSI TERAKHIR -->
                <section aria-label="Riwayat Presensi Terakhir" class="bg-white rounded-2xl border border-slate-200 shadow-xs overflow-hidden">
                    <div class="px-5 py-3.5 border-b border-slate-200/80 flex items-center justify-between gap-3">
                        <div class="flex items-center gap-2">
                            <i class="pi pi-history text-[#005BAC]" aria-hidden="true" />
                            <h2 class="font-bold text-slate-900 text-sm tracking-tight">Riwayat Presensi Terakhir</h2>
                        </div>
                        <Link
                            href="/absensi/laporan"
                            class="inline-flex items-center gap-1.5 min-h-[44px] px-2.5 rounded-lg text-xs font-semibold text-[#005BAC] hover:bg-blue-50 transition-colors shrink-0"
                        >
                            <span>Lihat Semua</span>
                            <i class="pi pi-arrow-right text-[11px]" aria-hidden="true" />
                        </Link>
                    </div>

                    <div
                        v-if="isPaginatingHistory"
                        role="status"
                        aria-live="polite"
                        class="py-8 px-4 text-center space-y-2"
                    >
                        <i class="pi pi-spin motion-reduce:animate-none pi-spinner text-xl text-[#005BAC]" aria-hidden="true" />
                        <p class="text-xs font-medium text-slate-700">Memuat halaman riwayat presensi...</p>
                    </div>

                    <template v-else>
                        <!-- Tampilan Desktop (Tabel) -->
                        <div class="hidden sm:block overflow-x-auto">
                            <table class="w-full text-left text-xs text-slate-700 border-collapse">
                                <caption class="sr-only">Riwayat presensi terakhir pegawai</caption>
                                <thead>
                                    <tr class="bg-slate-50 text-slate-700 font-semibold border-b border-slate-200 text-xs">
                                        <th scope="col" class="py-3 px-4">Tanggal &amp; Shift</th>
                                        <th scope="col" class="py-3 px-4">Jam Masuk</th>
                                        <th scope="col" class="py-3 px-4">Jam Pulang</th>
                                        <th scope="col" class="py-3 px-4">Mode</th>
                                        <th scope="col" class="py-3 px-4">Status</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    <tr v-for="att in attendanceList" :key="att.id" class="hover:bg-slate-50/50">
                                        <td class="py-3 px-4">
                                            <div class="font-bold text-slate-800">{{ att.date_formatted }}</div>
                                            <div class="text-xs text-slate-600">{{ att.shift_name }}</div>
                                        </td>
                                        <td class="py-3 px-4 font-mono font-bold whitespace-nowrap" :class="att.is_late ? 'text-[#c2410c]' : 'text-[#005BAC]'">
                                            {{ att.check_in }}
                                        </td>
                                        <td class="py-3 px-4 font-mono font-bold whitespace-nowrap" :class="att.is_early_leave ? 'text-rose-700' : 'text-slate-700'">
                                            {{ att.check_out }}
                                        </td>
                                        <td class="py-3 px-4">
                                            <span class="inline-block px-2 py-0.5 rounded text-[11px] font-bold bg-slate-100 text-slate-700">
                                                {{ att.work_mode }}
                                            </span>
                                        </td>
                                        <td class="py-3 px-4">
                                            <Tag
                                                :value="att.status_label"
                                                :severity="att.status_severity"
                                                class="!text-[11px] !font-bold"
                                            />
                                        </td>
                                    </tr>
                                    <tr v-if="attendanceList.length === 0">
                                        <td colspan="5" class="py-6 px-4 text-center text-slate-600">
                                            Belum ada riwayat presensi tercatat.
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Tampilan Mobile (Daftar Kartu Kompak) -->
                        <div class="block sm:hidden divide-y divide-slate-100">
                            <div v-for="att in attendanceList" :key="'m-' + att.id" class="p-4 space-y-2">
                                <div class="flex items-center justify-between gap-2">
                                    <span class="font-bold text-xs text-slate-800">{{ att.date_formatted }}</span>
                                    <Tag :value="att.status_label" :severity="att.status_severity" class="!text-[11px] !font-bold shrink-0" />
                                </div>
                                <div class="flex items-center justify-between gap-2 text-xs text-slate-600 font-mono flex-wrap">
                                    <span>Shift: {{ att.shift_name }}</span>
                                    <span>Mode: {{ att.work_mode }}</span>
                                </div>
                                <div class="grid grid-cols-2 gap-2 text-xs pt-1">
                                    <div class="bg-slate-50 p-2.5 rounded-lg border border-slate-200/80">
                                        <span class="text-slate-600 block text-[11px]">Masuk:</span>
                                        <span class="font-bold font-mono" :class="att.is_late ? 'text-[#c2410c]' : 'text-[#005BAC]'">{{ att.check_in }}</span>
                                    </div>
                                    <div class="bg-slate-50 p-2.5 rounded-lg border border-slate-200/80">
                                        <span class="text-slate-600 block text-[11px]">Pulang:</span>
                                        <span class="font-bold font-mono" :class="att.is_early_leave ? 'text-rose-700' : 'text-slate-700'">{{ att.check_out }}</span>
                                    </div>
                                </div>
                            </div>
                            <div v-if="attendanceList.length === 0" class="p-6 text-center text-slate-600 text-xs">
                                Belum ada riwayat presensi tercatat.
                            </div>
                        </div>
                    </template>

                    <!-- Pagination Controls -->
                    <div
                        v-if="recentAttendances?.total > 3"
                        class="px-4 py-3 bg-slate-50 border-t border-slate-100 flex items-center justify-between text-xs text-slate-600 gap-2"
                    >
                        <div>
                            Halaman <span class="font-bold text-slate-800">{{ recentAttendances.current_page }}</span> dari <span class="font-bold text-slate-800">{{ recentAttendances.last_page }}</span>
                            <span class="hidden sm:inline"> (Total {{ recentAttendances.total }} data)</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <Link
                                v-if="recentAttendances.prev_page_url"
                                :href="recentAttendances.prev_page_url"
                                preserve-scroll
                                aria-label="Halaman sebelumnya"
                                class="min-w-11 min-h-11 inline-flex items-center justify-center rounded-lg bg-white border border-slate-300 text-slate-700 hover:bg-slate-100 font-medium transition-colors"
                                @click="handlePaginationClick"
                            >
                                <i class="pi pi-chevron-left text-xs" aria-hidden="true" />
                            </Link>
                            <button
                                v-else
                                type="button"
                                disabled
                                aria-label="Halaman sebelumnya"
                                class="min-w-11 min-h-11 inline-flex items-center justify-center rounded-lg bg-slate-100 border border-slate-200 text-slate-600 cursor-not-allowed"
                            >
                                <i class="pi pi-chevron-left text-xs" aria-hidden="true" />
                            </button>

                            <Link
                                v-if="recentAttendances.next_page_url"
                                :href="recentAttendances.next_page_url"
                                preserve-scroll
                                aria-label="Halaman berikutnya"
                                class="min-w-11 min-h-11 inline-flex items-center justify-center rounded-lg bg-white border border-slate-300 text-slate-700 hover:bg-slate-100 font-medium transition-colors"
                                @click="handlePaginationClick"
                            >
                                <i class="pi pi-chevron-right text-xs" aria-hidden="true" />
                            </Link>
                            <button
                                v-else
                                type="button"
                                disabled
                                aria-label="Halaman berikutnya"
                                class="min-w-11 min-h-11 inline-flex items-center justify-center rounded-lg bg-slate-100 border border-slate-200 text-slate-600 cursor-not-allowed"
                            >
                                <i class="pi pi-chevron-right text-xs" aria-hidden="true" />
                            </button>
                        </div>
                    </div>
                </section>
            </div>

            <!-- ================= KOLOM KANAN (5 Kolom): Status, Ringkasan & Referensi ================= -->
            <div class="xl:col-span-5 min-w-0 space-y-5">

                <!-- 1. STATUS HARI INI (eSDM vs FaceID) -->
                <section aria-label="Status Kehadiran Hari Ini" class="bg-white rounded-2xl border border-slate-200 shadow-xs overflow-hidden">
                    <div class="px-5 py-3.5 border-b border-slate-100 flex items-center justify-between gap-2 bg-slate-50">
                        <div class="flex items-center gap-2">
                            <i class="pi pi-info-circle text-[#005BAC]" aria-hidden="true" />
                            <h2 class="font-bold text-slate-900 text-sm tracking-tight">Status Hari Ini</h2>
                        </div>
                        <Tag
                            v-if="attendanceStatus === 'leave'"
                            value="Izin / Cuti"
                            severity="warn"
                            class="!text-[11px] !font-bold"
                        />
                        <Tag
                            v-else-if="attendanceStatus === 'in'"
                            value="Sudah Masuk"
                            severity="info"
                            class="!text-[11px] !font-bold"
                        />
                        <Tag
                            v-else
                            value="Belum Masuk"
                            severity="danger"
                            class="!text-[11px] !font-bold"
                        />
                    </div>

                    <div class="p-4 sm:p-5 space-y-3">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
                            <div class="p-3.5 rounded-xl bg-slate-50 border border-slate-200 space-y-2">
                                <div class="text-xs font-semibold text-slate-700 flex items-center justify-between">
                                    <span>Aplikasi eSDM</span>
                                    <span class="text-[11px] text-[#005BAC] font-semibold font-mono">Web/GPS</span>
                                </div>
                                <div class="grid grid-cols-2 gap-2 text-xs">
                                    <div>
                                        <span class="text-slate-600 block">Masuk</span>
                                        <span class="font-bold text-slate-900 font-mono text-sm">{{ fmt(todayAttendance?.check_in_time) }}</span>
                                    </div>
                                    <div>
                                        <span class="text-slate-600 block">Pulang</span>
                                        <span class="font-bold text-slate-900 font-mono text-sm">{{ fmt(todayAttendance?.check_out_time) }}</span>
                                    </div>
                                </div>
                            </div>

                            <div class="p-3.5 rounded-xl bg-slate-50 border border-slate-200 space-y-2">
                                <div class="text-xs font-semibold text-slate-700 flex items-center justify-between">
                                    <span>Mesin Wajah (FaceID)</span>
                                    <span class="text-[11px] text-[#005BAC] font-semibold font-mono">Plant</span>
                                </div>
                                <div class="grid grid-cols-2 gap-2 text-xs">
                                    <div>
                                        <span class="text-slate-600 block">Masuk</span>
                                        <span class="font-bold text-slate-900 font-mono text-sm">{{ faceCheckIn }}</span>
                                    </div>
                                    <div>
                                        <span class="text-slate-600 block">Pulang</span>
                                        <span class="font-bold text-slate-900 font-mono text-sm">{{ faceCheckOut }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <p class="text-xs text-slate-600 text-center">
                            Sinkronisasi mesin wajah berkala (±1 jam).
                        </p>
                    </div>
                </section>

                <!-- 2. RINGKASAN PRESENSI BULAN INI -->
                <section aria-label="Presensi Bulan Ini" class="bg-white rounded-2xl border border-slate-200 shadow-xs p-5 space-y-4">
                    <div class="flex items-center justify-between border-b border-slate-100 pb-3 gap-2">
                        <div class="flex items-center gap-2">
                            <i class="pi pi-calendar-check text-[#005BAC] text-base" aria-hidden="true" />
                            <h2 class="font-bold text-slate-900 text-sm tracking-tight">Presensi Bulan Ini</h2>
                        </div>
                        <span class="text-xs font-semibold text-slate-600 capitalize">
                            {{ currentMonthLabel }}
                        </span>
                    </div>

                    <div class="grid grid-cols-2 gap-3 text-center">
                        <div class="p-3.5 rounded-xl bg-blue-50 border border-blue-200">
                            <span class="text-xs font-bold text-[#005BAC] block">Total Hadir</span>
                            <span class="text-2xl font-black text-[#005BAC] font-mono mt-1 block">{{ monthSummary?.total_hadir ?? 0 }}</span>
                            <span class="text-xs text-[#004280] mt-0.5 block">Hari Kerja</span>
                        </div>
                        <div class="p-3.5 rounded-xl bg-orange-50 border border-orange-200">
                            <span class="text-xs font-bold text-[#c2410c] block">Terlambat</span>
                            <span class="text-2xl font-black text-[#c2410c] font-mono mt-1 block">{{ monthSummary?.total_terlambat ?? 0 }}</span>
                            <span class="text-xs text-[#9a3412] mt-0.5 block">Kali Terlambat</span>
                        </div>
                    </div>

                    <div v-if="(monthSummary?.total_potongan ?? 0) > 0" class="p-2.5 rounded-xl bg-rose-50 border border-rose-200 flex items-center justify-between text-xs">
                        <span class="text-rose-900 font-medium">Potongan Keterlambatan:</span>
                        <span class="font-bold font-mono text-rose-900">{{ fmtRp(monthSummary.total_potongan) }}</span>
                    </div>

                    <div class="pt-1 border-t border-slate-100 flex justify-end">
                        <Link
                            href="/absensi/laporan"
                            class="inline-flex items-center gap-1.5 min-h-[44px] px-2.5 rounded-lg text-xs font-semibold text-[#005BAC] hover:bg-blue-50 transition-colors"
                        >
                            <span>Lihat Laporan Lengkap</span>
                            <i class="pi pi-arrow-right text-[11px]" aria-hidden="true" />
                        </Link>
                    </div>
                </section>

                <!-- 3. AREA GEDUNG RESMI KFPB (PROGRESSIVE DISCLOSURE) -->
                <section aria-label="Area Gedung Resmi KFPB" class="bg-white rounded-2xl border border-slate-200 shadow-xs overflow-hidden">
                    <button
                        type="button"
                        :aria-expanded="String(showGeofenceZones)"
                        aria-controls="kfpb-zones-panel"
                        class="w-full min-h-[44px] px-5 py-3.5 flex items-center justify-between gap-3 text-left hover:bg-slate-50 transition-colors cursor-pointer"
                        @click="showGeofenceZones = !showGeofenceZones"
                    >
                        <div class="flex items-center gap-2 min-w-0">
                            <i class="pi pi-shield text-[#005BAC] text-base shrink-0" aria-hidden="true" />
                            <span class="font-bold text-slate-900 text-sm tracking-tight truncate">
                                Area Gedung Resmi KFPB ({{ geofenceZones.length }})
                            </span>
                        </div>
                        <div class="flex items-center gap-2 shrink-0">
                            <span
                                v-if="insideZone && currentZoneName"
                                class="hidden sm:inline-flex items-center px-2 py-0.5 rounded-full bg-emerald-50 border border-emerald-200 text-emerald-800 text-[11px] font-bold"
                            >
                                {{ currentZoneName }}
                            </span>
                            <i
                                :class="['pi text-xs text-slate-600 transition-transform', showGeofenceZones ? 'pi-chevron-up' : 'pi-chevron-down']"
                                aria-hidden="true"
                            />
                        </div>
                    </button>

                    <div id="kfpb-zones-panel" v-show="showGeofenceZones" class="px-5 pb-4 pt-3 border-t border-slate-100 space-y-3">
                        <div v-if="geofenceZones.length > 0" class="grid grid-cols-1 sm:grid-cols-2 gap-1.5">
                            <div
                                v-for="(zone, i) in geofenceZones"
                                :key="zone.id || i"
                                :class="[
                                    'flex items-center justify-between px-3 py-2 rounded-xl border text-xs transition-colors',
                                    currentZoneName === (zone.label || zone.name)
                                        ? 'bg-blue-50 border-[#005BAC]/50 text-[#004280] font-bold'
                                        : 'bg-slate-50 border-slate-200 text-slate-700'
                                ]"
                            >
                                <div class="flex items-center gap-2 min-w-0">
                                    <span
                                        :class="[
                                            'size-5 rounded-md flex items-center justify-center text-[11px] font-bold shrink-0',
                                            currentZoneName === (zone.label || zone.name)
                                                ? 'bg-[#005BAC] text-white'
                                                : 'bg-[#005BAC]/10 text-[#005BAC]'
                                        ]"
                                    >
                                        {{ i + 1 }}
                                    </span>
                                    <span class="truncate">{{ zone.label || zone.name }}</span>
                                </div>
                                <template v-if="currentZoneName === (zone.label || zone.name)">
                                    <i class="pi pi-check text-xs text-[#005BAC] shrink-0 ml-1" aria-hidden="true" />
                                    <span class="sr-only">Zona aktif</span>
                                </template>
                            </div>
                        </div>
                        <p v-else class="text-xs text-slate-600">
                            Daftar zona gedung belum tersedia; sistem memakai perimeter pabrik Plant Banjaran (~300m).
                        </p>
                        <p class="text-xs text-slate-600">
                            Mode Flexi Time &amp; PKL bebas batas area gedung.
                        </p>
                    </div>
                </section>
            </div>
        </div>
        </div>

        <!-- ================= DIALOG KONFIRMASI PULANG LEBIH AWAL ================= -->
        <Dialog
            v-model:visible="showEarlyCheckoutDialog"
            modal
            header="Konfirmasi Pulang Lebih Awal"
            :style="{ width: '440px', maxWidth: '94vw' }"
            :closable="true"
        >
            <p class="text-sm text-slate-700 leading-relaxed">
                Jadwal pulang <b>{{ activeShiftSchedule?.name || todayAttendance?.shift_name }}</b> pukul
                <b class="font-mono text-slate-900">{{ activeShiftSchedule?.check_out }} WIB</b>
                (sekarang <b class="font-mono text-slate-900">{{ realtimeClock.substring(0, 5) }} WIB</b>).
                Lanjutkan absen pulang?
            </p>

            <template #footer>
                <div class="flex flex-col-reverse sm:flex-row items-stretch sm:items-center justify-end gap-2 w-full">
                    <Button
                        type="button"
                        label="Batal"
                        severity="secondary"
                        outlined
                        class="min-h-[44px] !text-slate-700"
                        @click="showEarlyCheckoutDialog = false"
                    />
                    <Button
                        type="button"
                        label="Ya, Absen Pulang"
                        icon="pi pi-sign-out"
                        class="min-h-[44px] !bg-[#005BAC] hover:!bg-[#004280] !border-[#005BAC] !text-white font-semibold"
                        @click="confirmEarlyCheckOut"
                    />
                </div>
            </template>
        </Dialog>
    </div>
</template>

<style scoped>
@media (max-width: 1023px) {
    .mobile-navy-arch {
        border-bottom-left-radius: 50% 34px;
        border-bottom-right-radius: 50% 34px;
    }
}

@media (min-width: 640px) and (max-width: 1023px) {
    .mobile-navy-arch {
        border-bottom-left-radius: 50% 42px;
        border-bottom-right-radius: 50% 42px;
    }
}
</style>
