<template>
  <div>
    <template v-if="compact">
      <button v-if="!expanded" class="btn btn-outline-secondary btn-sm w-100" @click="expanded = true; $nextTick(initMap)">
        <i class="bi bi-geo-alt me-1"></i> Set Location
      </button>
      <div v-show="expanded">
        <div ref="mapContainer" class="map-picker rounded border" style="height: 120px; width: 100%;"></div>
        <div v-if="latitude && longitude" class="text-secondary small mt-1">
          Lat: {{ latitude.toFixed(6) }}, Lng: {{ longitude.toFixed(6) }}
        </div>
        <button class="btn btn-sm btn-link p-0 mt-1" @click="expanded = false">Collapse</button>
      </div>
    </template>
    <template v-else>
      <div ref="mapContainer" class="map-picker rounded border" style="height: 250px; width: 100%;"></div>
      <div v-if="latitude && longitude" class="text-secondary small mt-1">
        Lat: {{ latitude.toFixed(6) }}, Lng: {{ longitude.toFixed(6) }}
      </div>
    </template>
  </div>
</template>

<script setup>
import { ref, onMounted, watch, onBeforeUnmount, nextTick } from 'vue';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

const props = defineProps({
  modelValue: { type: Object, default: () => ({ lat: null, lng: null }) },
  height: { type: String, default: '250px' },
  compact: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue']);

const mapContainer = ref(null);
const expanded = ref(!props.compact);
let map = null;
let marker = null;

const latitude = ref(props.modelValue.lat);
const longitude = ref(props.modelValue.lng);

const defaultCenter = { lat: 23.8103, lng: 90.4125 };

const pinSvg = '<svg xmlns="http://www.w3.org/2000/svg" width="32" height="42" viewBox="0 0 32 42"><path d="M16 2C8.27 2 2 8.27 2 16c0 5.82 4.5 10.82 10.5 14.45L16 33l3.5-2.55C25.5 26.82 30 21.82 30 16 30 8.27 23.73 2 16 2z" fill="#dc3545" stroke="#fff" stroke-width="1.5"/><circle cx="16" cy="15" r="5" fill="#fff"/></svg>';

const icon = L.icon({
  iconUrl: 'data:image/svg+xml;base64,' + btoa(pinSvg),
  iconSize: [32, 42],
  iconAnchor: [16, 42],
  popupAnchor: [0, -42],
});

const initMap = () => {
  if (map) return;
  const el = mapContainer.value;
  if (!el) return;

  const center = (latitude.value && longitude.value)
    ? { lat: Number(latitude.value), lng: Number(longitude.value) }
    : defaultCenter;

  map = L.map(el, {
    center: [center.lat, center.lng],
    zoom: props.compact ? 10 : 13,
    zoomControl: !props.compact,
    attributionControl: !props.compact,
    dragging: !props.compact,
  });

  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19,
  }).addTo(map);

  if (latitude.value && longitude.value) {
    marker = L.marker([Number(latitude.value), Number(longitude.value)], { icon, draggable: !props.compact }).addTo(map);
    if (!props.compact) marker.on('dragend', onMarkerDrag);
  }

  if (!props.compact) {
    map.on('click', (e) => {
      const { lat, lng } = e.latlng;
      setPosition(lat, lng);
    });
  }

  setTimeout(() => map.invalidateSize(), 200);
};

onMounted(() => {
  if (!props.compact) initMap();
});

const onMarkerDrag = () => {
  if (!marker) return;
  const pos = marker.getLatLng();
  setPosition(pos.lat, pos.lng);
};

const setPosition = (lat, lng) => {
  latitude.value = lat;
  longitude.value = lng;
  if (!marker) {
    marker = L.marker([lat, lng], { icon, draggable: !props.compact }).addTo(map);
    if (!props.compact) marker.on('dragend', onMarkerDrag);
  } else {
    marker.setLatLng([lat, lng]);
  }
  emit('update:modelValue', { lat: Number(lat.toFixed(7)), lng: Number(lng.toFixed(7)) });
};

watch(() => props.modelValue, (val) => {
  if (val?.lat && val?.lng && map) {
    const lat = Number(val.lat);
    const lng = Number(val.lng);
    if (!isNaN(lat) && !isNaN(lng)) {
      latitude.value = lat;
      longitude.value = lng;
      if (marker) marker.setLatLng([lat, lng]);
      map.setView([lat, lng]);
    }
  }
});

onBeforeUnmount(() => {
  if (map) { map.remove(); map = null; }
});
</script>
