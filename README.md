# Map Point

A Statamic 6 control-panel fieldtype for capturing a geographic coordinate.
An editor drops a pin on a map or picks a real place by name; the field stores
latitude and longitude. The consuming site renders its own front-end map.

No Google JavaScript reaches the browser. Google's APIs are called only
server-side, through this addon's own authenticated CP routes.

## Installation

```sh
composer require estouai/map-point
php artisan vendor:publish --tag=map-point-config
```

Publishing the config is optional — the defaults work. Built assets ship with
the package, so nothing needs building in the consuming project.

## Third-party services

The field talks to services outside your server. Nothing is optional about
this, so it is worth stating plainly:

- **Map tiles** load in the editor's browser from
  `tile.openstreetmap.org` by default. Point `tile_url` at another provider to
  change that.
- **Address search** in `leaflet_map` mode — and as the fallback for both
  Google modes — sends the editor's search text to
  `nominatim.openstreetmap.org` from your server. Searches run only when the
  editor presses Enter or the Search button, as [Nominatim's usage
  policy](https://operations.osmfoundation.org/policies/nominatim/) does not
  allow autocomplete. High-volume sites should run their own Nominatim
  instance or use a Google key.
- **Google place search**, when a key is configured, sends the search text to
  `places.googleapis.com` from your server.

## Google API key

Only needed for the two Google modes:

```dotenv
GOOGLE_MAPS_API_KEY=your-key
```

The key is read server-side via `config/map-point.php` and never reaches the
browser. Without it, the Google modes fall back to OpenStreetMap search and
say so inline — the field keeps working.

The key needs **Places API (New)** enabled in the Google Cloud project. The
addon calls `places.googleapis.com/v1`; the legacy Places API is not used and
can no longer be enabled on new projects.

## Modes

| Mode | What the editor sees | Needs a Google key |
|---|---|---|
| `google_poi` | A search box listing real places; picking one stores its coordinates, name and address | Yes (falls back to OpenStreetMap search) |
| `google_map` | A map to click, with Google-powered address search | Yes (falls back as above) |
| `leaflet_map` | A map to click, with OpenStreetMap address search | No |

Google modes search as the editor types; OpenStreetMap search runs on Enter or
the Search button.

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

## Support

Community support, best effort: open an issue at
https://github.com/estouai/map-point/issues, or email support@estou.ai.

See [CHANGELOG.md](CHANGELOG.md) for release notes and
[THIRD-PARTY-NOTICES.md](THIRD-PARTY-NOTICES.md) for bundled licences.

## Development

```sh
composer test
composer lint
npm run build
```
