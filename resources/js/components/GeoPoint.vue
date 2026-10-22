<script setup>
import { computed, onBeforeUnmount, onMounted, ref, shallowRef, watch } from 'vue';
import { Fieldtype } from '@statamic/cms';
import { Button, Input } from '@statamic/cms/ui';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

const emit = defineEmits(Fieldtype.emits);
const props = defineProps(Fieldtype.props);
const { expose, update, isReadOnly } = Fieldtype.use(emit, props);
defineExpose(expose);

const mode = computed(() => props.config.mode ?? 'leaflet_map');
const showsMap = computed(() => mode.value !== 'google_poi');
const provider = computed(() => (mode.value === 'leaflet_map' ? 'nominatim' : 'google'));
// Nominatim's usage policy caps the request rate; Google's cost does.
const searchDelay = computed(() => (provider.value === 'nominatim' ? 1000 : 400));

const query = ref('');
const results = ref([]);
const searching = ref(false);
const message = ref(null);
const fellBack = ref(false);

// 0 is a valid coordinate, so every "is there a value?" check tests for a
// finite number rather than truthiness.
const hasValue = computed(() => isPoint(props.value));

function isPoint(value) {
    return !!value && Number.isFinite(Number(value.lat)) && Number.isFinite(Number(value.lng));
}

const latInput = ref('');
const lngInput = ref('');

watch(
    () => props.value,
    (value) => {
        latInput.value = isPoint(value) ? String(value.lat) : '';
        lngInput.value = isPoint(value) ? String(value.lng) : '';
        syncMarker();
    },
    { immediate: true },
);

function setPoint(lat, lng, extra = {}) {
    update({
        lat: Number(lat),
        lng: Number(lng),
        place_id: extra.place_id ?? null,
        formatted_address: extra.formatted_address ?? null,
        name: extra.name ?? null,
    });
}

function clear() {
    update(null);
    removeMarker();
}

function commitManualInputs() {
    const lat = Number(latInput.value);
    const lng = Number(lngInput.value);

    if (latInput.value === '' || lngInput.value === '' || !Number.isFinite(lat) || !Number.isFinite(lng)) return;
    if (lat < -90 || lat > 90 || lng < -180 || lng > 180) {
        message.value = __('Coordinates out of range.');

        return;
    }

    message.value = null;
    setPoint(lat, lng);
    map.value?.setView([lat, lng]);
}

// --- search -----------------------------------------------------------

let searchTimer = null;

function onQueryInput() {
    clearTimeout(searchTimer);

    if (query.value.trim() === '') {
        results.value = [];

        return;
    }

    searchTimer = setTimeout(runSearch, searchDelay.value);
}

async function runSearch() {
    searching.value = true;
    message.value = null;

    try {
        const response = await Statamic.$axios.get(cpUrl('search'), {
            params: { q: query.value, provider: provider.value },
        });

        results.value = response.data.results ?? [];
        fellBack.value = !!response.data.fallback;

        if (results.value.length === 0) message.value = __('No results found.');
    } catch (e) {
        results.value = [];
        // Leave the map untouched — a failed search is not a reason to move it.
        message.value = __('Search failed. You can still set the coordinates by hand.');
    } finally {
        searching.value = false;
    }
}

async function choose(result) {
    // A map's search box only recentres; the editor confirms with a click.
    if (showsMap.value) {
        if (isPoint(result)) map.value?.setView([result.lat, result.lng], 16);
        results.value = [];

        return;
    }

    if (isPoint(result)) {
        setPoint(result.lat, result.lng, result);
        query.value = result.name;
        results.value = [];

        return;
    }

    try {
        const response = await Statamic.$axios.get(cpUrl('details'), {
            params: { place_id: result.place_id },
        });

        const row = (response.data.results ?? [])[0];

        if (!isPoint(row)) throw new Error('no coordinates');

        setPoint(row.lat, row.lng, row);
        query.value = row.name;
        results.value = [];
    } catch (e) {
        message.value = __('Could not resolve that place.');
    }
}

function cpUrl(action) {
    return `${Statamic.$config.get('cpUrl')}/map-point/${action}`;
}

// --- map --------------------------------------------------------------

const mapEl = ref(null);
const map = shallowRef(null);
const marker = shallowRef(null);

onMounted(() => {
    if (!showsMap.value) return;

    const centre = hasValue.value
        ? [props.value.lat, props.value.lng]
        : [props.meta.default_latitude, props.meta.default_longitude];

    map.value = L.map(mapEl.value).setView(centre, hasValue.value ? 15 : props.meta.default_zoom);

    L.tileLayer(props.meta.tile_url, { attribution: props.meta.tile_attribution }).addTo(map.value);

    if (!isReadOnly.value) {
        map.value.on('click', (event) => setPoint(event.latlng.lat, event.latlng.lng));
    }

    syncMarker();
});

// Fields mount and unmount repeatedly inside Replicator and Bard sets; a
// leaked map instance keeps running.
onBeforeUnmount(() => {
    clearTimeout(searchTimer);
    map.value?.remove();
    map.value = null;
});

function syncMarker() {
    if (!map.value) return;

    if (!hasValue.value) {
        removeMarker();

        return;
    }

    const position = [props.value.lat, props.value.lng];

    if (marker.value) {
        marker.value.setLatLng(position);

        return;
    }

    marker.value = L.marker(position, { draggable: !isReadOnly.value }).addTo(map.value);
    marker.value.on('dragend', () => {
        const { lat, lng } = marker.value.getLatLng();
        setPoint(lat, lng, props.value ?? {});
    });
}

function removeMarker() {
    if (!marker.value) return;

    marker.value.remove();
    marker.value = null;
}
</script>

<template>
    <div class="space-y-2">
        <div class="relative">
            <Input
                v-model="query"
                :disabled="isReadOnly"
                :placeholder="showsMap ? __('Search for an address to centre the map') : __('Search for a place')"
                @input="onQueryInput"
            />

            <ul
                v-if="results.length"
                class="absolute z-10 mt-1 w-full max-h-64 overflow-auto rounded-md border bg-white shadow-lg dark:bg-gray-800"
            >
                <li
                    v-for="(result, index) in results"
                    :key="result.place_id ?? index"
                    class="cursor-pointer px-3 py-2 text-sm hover:bg-gray-100 dark:hover:bg-gray-700"
                    @click="choose(result)"
                >
                    <div class="font-medium">{{ result.name }}</div>
                    <div v-if="result.formatted_address" class="text-xs text-gray-500">
                        {{ result.formatted_address }}
                    </div>
                </li>
            </ul>
        </div>

        <p v-if="searching" class="text-xs text-gray-500">{{ __('Searching…') }}</p>
        <p v-if="message" class="text-xs text-red-500">{{ message }}</p>
        <p v-if="fellBack" class="text-xs text-gray-500">
            {{ __('Google is unavailable; results come from OpenStreetMap.') }}
        </p>
        <p v-if="showsMap && !isReadOnly" class="text-xs text-gray-500">
            {{ __('Click the map to place the pin.') }}
        </p>

        <div v-show="showsMap" ref="mapEl" class="h-80 w-full rounded-md border"></div>

        <!-- The manual inputs are what keep this field usable when tiles fail
             to load, so they are never disabled on a tile error. -->
        <div class="flex items-end gap-2">
            <div class="flex-1">
                <label class="text-xs text-gray-500">{{ __('Latitude') }}</label>
                <Input v-model="latInput" :disabled="isReadOnly" type="number" step="any" @change="commitManualInputs" />
            </div>
            <div class="flex-1">
                <label class="text-xs text-gray-500">{{ __('Longitude') }}</label>
                <Input v-model="lngInput" :disabled="isReadOnly" type="number" step="any" @change="commitManualInputs" />
            </div>
            <Button v-if="!isReadOnly" variant="subtle" :text="__('Clear')" @click="clear" />
        </div>
    </div>
</template>
