# Changelog

## 1.0.1 — 2026-10-26

### Changed

- OpenStreetMap (Nominatim) search no longer runs while typing; it runs when
  the editor presses Enter or the Search button, per Nominatim's usage policy.
- Google modes now use the Places API (New) (`places.googleapis.com/v1`). The
  legacy Places API can no longer be enabled on new Google Cloud projects.
- The search endpoint returns a handled 503 when Nominatim fails, instead of
  a 500.

### Added

- `THIRD-PARTY-NOTICES.md` with the Leaflet BSD-2-Clause notice.
- Latitude and longitude labels are now associated with their inputs.

### Fixed

- Installation instructions and licence terms for marketplace customers.

## 1.0.0

- First release: `geo_point` fieldtype with `google_poi`, `google_map` and
  `leaflet_map` modes.
