import GeoPoint from './components/GeoPoint.vue';

Statamic.booting(() => {
    // Must match the fieldtype handle verbatim: the CP resolves a field's
    // component as `${config.type}-fieldtype` and never converts underscores.
    Statamic.component('geo_point-fieldtype', GeoPoint);
});
