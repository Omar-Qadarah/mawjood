import { useEffect, useRef, useState } from 'react';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import boundaries from './jordan-boundaries.json';
import { Icon, Status } from './ui';

const governorateNames = { "Ma'an": 'Maan', Tafilah: 'Tafileh' };
const regionName = (feature) => governorateNames[feature.properties.shapeName] ?? feature.properties.shapeName;

function containsPoint(ring, x, y) {
    let contains = false;
    for (let i = 0, j = ring.length - 1; i < ring.length; j = i++) {
        const [a, b] = ring[i];
        const [c, d] = ring[j];
        if ((b > y) !== (d > y) && x < ((c - a) * (y - b)) / (d - b) + a) contains = !contains;
    }
    return contains;
}

function locateSite(site) {
    if (site.governorate) return site.governorate;
    const point = [Number(site.longitude), Number(site.latitude)];
    const region = boundaries.features.find(({ geometry }) => {
        const polygons = geometry.type === 'Polygon' ? [geometry.coordinates] : geometry.coordinates;
        return polygons.some(([outline, ...holes]) =>
            containsPoint(outline, ...point) && !holes.some((hole) => containsPoint(hole, ...point)));
    });
    return region ? regionName(region) : null;
}

const regionStyle = { color: '#fff', weight: 1.5, fillColor: '#e3e6f0', fillOpacity: 1 };
const highlighted = { ...regionStyle, fillColor: '#8061d2' };

export function JordanMap({ sites, onSite }) {
    const host = useRef(null);
    const instance = useRef(null);
    const [hovered, setHovered] = useState('');
    const [selected, setSelected] = useState('');
    const visibleRegion = hovered || selected;
    const regionSites = visibleRegion ? sites.filter((site) => locateSite(site) === visibleRegion) : [];

    useEffect(() => {
        const map = L.map(host.current, { attributionControl: false, scrollWheelZoom: false, zoomSnap: 0.25, minZoom: 5 });
        const regions = L.geoJSON(boundaries, {
            style: regionStyle,
            onEachFeature(feature, layer) {
                const name = regionName(feature);
                layer.on({
                    mouseover: () => setHovered(name),
                    mouseout: () => setHovered(''),
                    click: () => setSelected((value) => value === name ? '' : name),
                });
                layer.on('add', () => {
                    const element = layer.getElement();
                    element.setAttribute('tabindex', '0');
                    element.setAttribute('role', 'button');
                    element.setAttribute('aria-label', name + ' governorate');
                    element.addEventListener('focus', () => setHovered(name));
                    element.addEventListener('blur', () => setHovered(''));
                    element.addEventListener('keydown', (event) => {
                        if (event.key === 'Enter' || event.key === ' ') {
                            event.preventDefault();
                            setSelected((value) => value === name ? '' : name);
                        }
                    });
                });
                layer.bindTooltip(name, { sticky: true, className: 'region-tooltip' });
            },
        }).addTo(map);
        map.fitBounds(regions.getBounds(), { padding: [12, 12] });
        instance.current = { regions, markers: L.layerGroup().addTo(map) };

        return () => { instance.current = null; map.remove(); };
    }, []);

    useEffect(() => {
        instance.current?.regions.eachLayer((layer) =>
            layer.setStyle(regionName(layer.feature) === visibleRegion ? highlighted : regionStyle));
    }, [visibleRegion]);

    useEffect(() => {
        const markers = instance.current?.markers;
        if (!markers) return;
        markers.clearLayers();
        if (!visibleRegion) return;
        sites.filter((site) => locateSite(site) === visibleRegion).forEach((site) => {
            L.circleMarker([Number(site.latitude), Number(site.longitude)], {
                radius: 5, color: '#fff', weight: 2, fillColor: '#2b2450', fillOpacity: 1, interactive: false,
            }).addTo(markers);
        });
    }, [visibleRegion, sites]);

    return <section className="panel map-card" aria-label="Pharmacy sites in Jordan">
        <header className="panel-header"><div><h2>Pharmacy sites in Jordan</h2><p>Explore by governorate</p></div><span className="map-symbol"><Icon name="location" /></span></header>
        <div className="jordan-map" ref={host} aria-label="Interactive map of Jordan" />
        <div className="map-info" aria-live="polite">
            {visibleRegion ? <>
                <div className="map-info-head"><strong>{visibleRegion}</strong><span>{regionSites.length} {regionSites.length === 1 ? 'site' : 'sites'}</span></div>
                {regionSites.length ? <ul>{regionSites.map((site) => <li key={site.id}>
                    <button type="button" onClick={() => onSite(site.id)}>{site.name_en}<small>{site.city}</small></button>
                    <Status status={site.status} />
                </li>)}</ul> : <p>No pharmacies in this governorate.</p>}
            </> : <p>Hover over or select a governorate to see its pharmacies.</p>}
        </div>
        <a className="map-credit" href="https://www.geoboundaries.org/api/current/gbOpen/JOR/ADM1/" target="_blank" rel="noreferrer">Boundaries: geoBoundaries / Wikimedia</a>
    </section>;
}

export function LocationPicker({ coordinates, governorate, center, onChange }) {
    const host = useRef(null);
    const mapRef = useRef(null);
    const markerRef = useRef(null);

    function place({ lat, lng }) {
        if (lat >= 29 && lat <= 33.5 && lng >= 34.9 && lng <= 39.4) {
            onChange({ latitude: Number(lat.toFixed(6)), longitude: Number(lng.toFixed(6)) });
        }
    }

    useEffect(() => {
        const map = L.map(host.current, { scrollWheelZoom: false }).setView([31.25, 36.4], 7);
        L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
        }).addTo(map);
        L.geoJSON(boundaries, { interactive: false, style: { color: '#348578', weight: 1, fillOpacity: 0 } }).addTo(map);
        map.on('click', ({ latlng }) => place(latlng));
        mapRef.current = map;
        const frame = requestAnimationFrame(() => map.invalidateSize());
        return () => { cancelAnimationFrame(frame); map.remove(); };
    }, []);

    useEffect(() => {
        if (center) mapRef.current?.setView(center, 12);
    }, [governorate, center]);

    useEffect(() => {
        const map = mapRef.current;
        if (!coordinates || !map) return;
        const point = [coordinates.latitude, coordinates.longitude];
        if (!markerRef.current) {
            markerRef.current = L.circleMarker(point, { radius: 9, color: '#fff', weight: 3, fillColor: '#116b5c', fillOpacity: 1 }).addTo(map);
            map.setView(point, 13);
        }
        markerRef.current.setLatLng(point);
    }, [coordinates]);

    return <fieldset className="wide fieldset"><legend>Map location</legend>
        <div className="location-picker" ref={host} aria-label="Select a pharmacy location on the map" />
        <div className="location-actions">
            <button className="button secondary" type="button" onClick={() => place(mapRef.current.getCenter())}>Use map center</button>
            <span role="status">{coordinates ? 'Pharmacy pin placed.' : 'Click the map or use its center to place a pin.'}</span>
        </div>
    </fieldset>;
}
