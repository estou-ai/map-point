# estouai/map-point — design

Statamic 6 control-panel fieldtype for capturing a geographic coordinate.
Built for a site listing physical pharmacy locations, rendered as pins on a
front-end map the consuming project owns. The editor never pastes raw
coordinates by hand.

## Intent

An editor opening a pharmacy entry must be able to set its location in one of
three ways, chosen per-blueprint by whoever defines the field:

- pick a real-world place by name, from Google's place data;
- drop a pin on a map, searching with Google's geocoder;
- drop a pin on a map, searching with a free OpenStreetMap geocoder.

Success: an editor sets a correct location without leaving the entry screen and
without knowing what a coordinate is; a blueprint author picks a mode with one
YAML line; the field stays usable when the network or Google is unavailable.

### Hard constraint: no Google JS dependency

No `maps.googleapis.com/maps/api/js`, no Google npm package, no Google script
tag in the control panel. The browser never contacts Google. Google's HTTP APIs
are reached only server-side, from PHP, through this addon's own CP routes.

This is why there is no "Google map" in the visual sense: Google's tiles ship
with their JS SDK. All three modes that draw a map draw it with Leaflet.

## Decisions

| | |
|---|---|
| Package | `estouai/map-point`, PSR-4 `Estouai\MapPoint\`, licence proprietary |
| Fieldtype handle | `geo_point` (derived from class `GeoPoint`) |
| Modes | `google_poi`, `google_map`, `leaflet_map` |
| Google access | server-side proxy only |
| API key | `GOOGLE_MAPS_API_KEY` in `.env`, read via `config/map-point.php` |
| Google failure | degrade to Nominatim, inline notice |
| Map library | Leaflet pinned `1.9.4`, CSS bundled |
| Default tiles | OpenStreetMap, overridable per field |
| Stored value | `{lat, lng, place_id?, formatted_address?, name?}` or `null` |

Three mode labels, two code paths: `google_map` and `leaflet_map` render the
same map component and differ only in which geocoder the search box calls. The
third label exists because it is clearer to a blueprint author than a separate
`provider` config key would be.

### Out of scope

No Antlers tag, no front-end map component, no distance or radius search, no
geocoding on save, no clustering, no CP nav entry, no settings screen. The
consuming site renders its own front-end map.

## Structure

```
estouai/map-point/
  composer.json              statamic/cms ^6.0, Pest 4 + Orchestra Testbench
  package.json               @statamic/cms (file:), leaflet 1.9.4
  vite.config.js             statamic() + laravel(input: resources/js/cp.js)
  config/map-point.php
  routes/cp.php
  src/ServiceProvider.php
  src/Fieldtypes/GeoPoint.php
  src/Geocoders/Geocoder.php          interface
  src/Geocoders/GooglePlaces.php
  src/Geocoders/Nominatim.php
  src/Http/Controllers/GeocodeController.php
  resources/js/cp.js
  resources/js/components/GeoPoint.vue
  tests/
```

Mirrors the sibling `estouai/cookie-consent` addon's tooling: same composer
scripts (`test`, `lint`), same `@statamic/cms` file-path npm dependency, same
`$vite` property on the service provider.

## PHP

### ServiceProvider

Extends `Statamic\Providers\AddonServiceProvider`.

```php
protected $vite = ['input' => ['resources/js/cp.js']];
protected $routes = ['cp' => __DIR__.'/../routes/cp.php'];
protected $fieldtypes = [GeoPoint::class];
```

`bootAddon()` merges and publishes `config/map-point.php`. No permissions, no
nav, no scheduled commands.

### Fieldtype

`GeoPoint extends Fieldtype`. Handle resolves to `geo_point` from the class
name. `protected $categories = ['special'];`

`configFieldItems()` returns grouped sections — the shape core v6 fieldtypes
use, detected by an integer first key:

- **Input Behavior** — `mode` (select: `google_poi`, `google_map`,
  `leaflet_map`; default `leaflet_map`), `default_latitude` (39.5),
  `default_longitude` (-8.0), `default_zoom` (6)
- **Appearance** — `tile_url`, `tile_attribution`

`preload()` hands the Vue component what it needs and nothing more: resolved
tile URL and attribution, the default centre and zoom, and `google_available`
— a boolean saying whether an API key is configured. The key itself never
reaches the browser.

When `google_available` is false and the mode is a Google one, the component
falls back to Nominatim and says so inline, rather than presenting a dead
control.

### Value handling

`process($data)` normalises on the way in:

- numeric strings from the manual inputs cast to float;
- `lat` outside -90..90 or `lng` outside -180..180 rejected;
- non-numeric input rejected;
- an incomplete pair (lat set, lng missing) collapses to `null`;
- `null` and `''` stay `null`.

Rejection means the value becomes `null`, never a half-written object.

`preProcess($data)` is the inverse, shaping stored data for the component.

`preProcessValidatable($value)` collapses an invalid or incomplete value to
`null` so Laravel's `required` rule trips on an unset pin. This is the v6 hook
for the problem: `required` on a raw object would pass for
`['lat' => null, 'lng' => null]`, because a non-empty array is not empty.

`augment($value)` returns an array exposing `lat` and `lng` so a template can
write `{{ coordinates:lat }}`. `null` stays `null`, so `{{ if coordinates }}`
behaves.

### Geocoders

One interface, one method:

```php
interface Geocoder {
    public function search(string $query): array;
}
```

`GooglePlaces` calls Places Autocomplete, and Place Details for a chosen
`place_id`. `Nominatim` calls `nominatim.openstreetmap.org/search`, sending a
descriptive `User-Agent` as their usage policy requires. Both return a
normalised list of `{name, formatted_address, lat, lng, place_id?}`.

### Routes

Two routes in `routes/cp.php`, registered through `Statamic::pushCpRoutes`,
which applies the CP prefix and the authenticated CP middleware:

- `GET /cp/map-point/search?q=` — autocomplete, Google or Nominatim
- `GET /cp/map-point/details?place_id=` — Google only

Both carry a per-user `throttle`. Every keystroke an editor types is a billable
Google request; the rate limit is a cost control, not just abuse protection.

`GeocodeController` tries Google when the mode asks for it, catches any
failure — missing key, invalid key, quota exhausted, network error — and
retries through Nominatim, marking `fallback: true` in the response. The
editor is never blocked by a Google outage.

## Control panel component

One `geo-point-fieldtype`, registered with
`Statamic.booting(() => Statamic.component('geo-point-fieldtype', GeoPoint))`.
Built on the v6 stub: `Fieldtype.use(emit, props)`, `update(value)` to write,
`isReadOnly` to honour the read-only state.

`props.config.mode` selects the branch.

**`google_poi`** — a text input with a result dropdown, no map. Typing
debounces 400ms and hits the search route; results show name and address.
Choosing one fetches details and stores coordinates together with `place_id`
and `formatted_address`.

**`google_map` / `leaflet_map`** — the shared map branch:

- Leaflet map, click to place or move the marker, marker also draggable;
- a search box above that only *centres* the map — it never sets the value;
  the editor confirms with a click;
- two numeric inputs below showing the current pair; editing a valid pair moves
  the marker;
- a Clear button returning the value to `null`.

The numeric inputs are the reason this field survives a bad network: with tiles
failing to load, the editor can still type a coordinate and save. That is the
contract — the field is never unusable.

Search debounces 400ms against Google and 1000ms against Nominatim, whose
policy caps request rate. A failed search shows an inline message and leaves
the map untouched.

Leaflet is imported, never pulled from a CDN, and its CSS is bundled. The map
instantiates in `onMounted` of the map branch and is destroyed in
`onUnmounted`, because fields mount and unmount repeatedly inside Replicator
and Bard sets.

## Tests

Pest with Orchestra Testbench, following the sibling addon.

- the service provider boots and the fieldtype is registered as `geo_point`;
- `process()` / `preProcess()` round-trip a `{lat, lng}` pair preserving
  precision, and cast numeric strings to float;
- `null`, `''`, and a partial pair each yield `null` rather than a half-written
  value;
- garbage input — non-numeric, lat outside -90..90, lng outside -180..180 —
  is not stored;
- `preProcessValidatable()` collapses an empty value so a `required` field
  fails validation with no pin set;
- `augment()` returns something an Antlers template can read;
- geocoder fallback: with Google faked to fail, the controller returns
  Nominatim results and flags `fallback: true`. HTTP is faked; no test touches
  the network.

`composer test` and `composer lint` both pass.

## Deliverable

A working addon directory, with `public/build` committed — the consuming
project installs this through a path repository and will not build the addon
itself. The README shows the blueprint YAML and the Antlers snippet:

```yaml
- handle: coordinates
  field:
    type: geo_point
    display: Location
    mode: leaflet_map
    required: true
```
