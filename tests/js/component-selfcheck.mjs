import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';

const source = readFileSync(new URL('../../resources/js/components/GeoPoint.vue', import.meta.url), 'utf8');

assert.doesNotMatch(source, /Statamic\.\$axios/, 'must not use nonexistent Statamic.$axios');
assert.match(source, /getCurrentInstance\(\)/, 'must obtain Vue global properties from component instance');
assert.match(source, /\.map-point-map\s*\{[^}]*height:/s, 'map needs explicit CSS height');
assert.match(source, /marker-icon\.png/, 'must import and configure standard marker icon');
assert.match(source, /marker-icon-2x\.png/, 'must import and configure retina marker icon');
assert.match(source, /marker-shadow\.png/, 'must import and configure marker shadow');
assert.match(source, /requestToken/, 'search needs stale-response protection');
assert.match(
    source,
    /\.map-point-results\s*\{[^}]*z-index:\s*(\d+)/s,
    'results dropdown needs an explicit z-index above Leaflet panes',
);
assert.ok(
    Number(source.match(/\.map-point-results\s*\{[^}]*z-index:\s*(\d+)/s)[1]) > 1000,
    'results dropdown must stack above Leaflet panes (z-index 400-1000)',
);
assert.match(source, /<li[^>]*v-for=[^>]*>\s*<button/s, 'search results need focusable buttons');
assert.match(source, /v-if="showsMap"[^>]*class="flex items-end gap-2"/, 'manual coordinates belong to map modes only');

console.log('component self-check passed');
