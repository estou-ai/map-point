import GeoPoint from './components/GeoPoint.vue';

Statamic.booting(() => {
    Statamic.component('geo-point-fieldtype', GeoPoint);
});
