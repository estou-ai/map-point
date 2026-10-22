# Map Point

A Statamic 6 control-panel fieldtype for capturing a geographic coordinate.
An editor drops a pin on a map or picks a real place by name; the field stores
latitude and longitude. The consuming site renders its own front-end map.

No Google JavaScript reaches the browser. Google's APIs are called only
server-side, through this addon's own authenticated CP routes.

## Installation

The addon is installed through a path repository:

```json
{
    "repositories": [
        { "type": "path", "url": "addons/estouai/map-point" }
    ]
}
```

```sh
composer require estouai/map-point
php artisan vendor:publish --tag=map-point-config
```

Built assets ship committed, so the consuming project does not build the addon.

## Google API key

Only needed for the two Google modes:

```dotenv
GOOGLE_MAPS_API_KEY=your-key
```

The key is read server-side via `config/map-point.php` and never reaches the
browser. Without it, the Google modes fall back to OpenStreetMap search and
say so inline — the field keeps working.

The key needs the **Places API** enabled.

## Modes

| Mode | What the editor sees | Needs a Google key |
|---|---|---|
| `google_poi` | A search box listing real places; picking one stores its coordinates, name and address | Yes (falls back to OpenStreetMap search) |
| `google_map` | A map to click, with Google-powered address search | Yes (falls back as above) |
| `leaflet_map` | A map to click, with OpenStreetMap address search | No |

In both map modes the search box only *centres* the map — the editor confirms
the location with a click. Two numeric inputs below the map always work, so the
field stays usable when the tiles or the network fail.

## Blueprint

```yaml
- handle: coordinates
  field:
    type: geo_point
    display: Location
    mode: leaflet_map
    required: true
```

Optional config: `default_latitude` (39.5), `default_longitude` (-8.0),
`default_zoom` (6), `tile_url`, `tile_attribution`. The defaults centre the map
on Portugal and use OpenStreetMap tiles.

## Antlers

```antlers
{{ if coordinates }}
    {{ coordinates:lat }}, {{ coordinates:lng }}
{{ /if }}
```

The augmented value also carries `place_id`, `formatted_address` and `name`
when a Google mode supplied them, and is `null` when no pin is set.

## Stored value

```json
{ "lat": 38.7223, "lng": -9.1393, "place_id": null, "formatted_address": null, "name": null }
```

Incomplete, non-numeric, or out-of-range input is stored as `null` rather than
a half-written value, so a `required` field refuses to save without a pin.

## Development

```sh
composer test
composer lint
npm run build
```
