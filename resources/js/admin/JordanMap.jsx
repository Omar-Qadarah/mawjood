import { useEffect, useRef, useState } from 'react';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import boundaries from './jordan-boundaries.json';
import { Icon } from './ui';

const governorateNames = { "Ma'an": 'Maan', Tafilah: 'Tafileh' };
const regionName = (feature) => governorateNames[feature.properties.shapeName] ?? feature.properties.shapeName;

const regionStyle = { color: '#fff', weight: 1.5, fillColor: '#d5e6e2', fillOpacity: 1 };
const highlighted = { ...regionStyle, fillColor: '#23867c' };

export function JordanMap({ sites }) {
    const host = useRef(null);
    const instance = useRef(null);
    const [hovered, setHovered] = useState('');
    const [selected, setSelected] = useState('');
    const visibleRegion = selected || hovered;
    const regionSites = visibleRegion ? (sites ?? []).filter((site) => site.governorate === visibleRegion) : sites ?? [];
    const scope = visibleRegion || 'Jordan';

    function directoryLink(section, status) {
        const filters = new URLSearchParams();
        if (visibleRegion) filters.set('governorate', visibleRegion);
        if (status) filters.set('status', status);
        return '#' + section + (filters.size ? '?' + filters : '');
    }
    const active = regionSites.filter((site) => site.status === 'active').length;
    const completed = regionSites.reduce((total, site) => total + site.completed_reservations, 0);
    useEffect(() => {
        const map = L.map(host.current, { attributionControl: false, scrollWheelZoom: false, zoomSnap: 0.25, minZoom: 5 });
        const regions = L.geoJSON(boundaries, {
            style: regionStyle,
            onEachFeature(feature, layer) {
                const name = regionName(feature);
                function toggleRegion() {
                    setHovered('');
                    setSelected((value) => value === name ? '' : name);
                }
                layer.on({
                    mouseover: () => setHovered(name),
                    mouseout: () => setHovered(''),
                    click: toggleRegion,
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
                            toggleRegion();
                        }
                    });
                });
                layer.bindTooltip(name, { sticky: true, className: 'region-tooltip' });
            },
        }).addTo(map);
        const container = host.current;
        const bounds = regions.getBounds();
        let width = container.clientWidth;
        let height = container.clientHeight;
        map.fitBounds(bounds, { padding: [12, 12] });
        const resize = new ResizeObserver(() => {
            const nextWidth = container.clientWidth;
            const nextHeight = container.clientHeight;
            if (!nextWidth || !nextHeight || (width === nextWidth && height === nextHeight)) return;

            width = nextWidth;
            height = nextHeight;
            map.invalidateSize({ pan: false });
            map.fitBounds(bounds, { padding: [12, 12], animate: false });
        });
        resize.observe(container);
        instance.current = regions;

        return () => {
            resize.disconnect();
            instance.current = null;
            map.remove();
        };
    }, []);

    useEffect(() => {
        instance.current?.eachLayer((layer) =>
            layer.setStyle(regionName(layer.feature) === visibleRegion ? highlighted : regionStyle));
    }, [visibleRegion]);

    return <section className="panel map-card" aria-label="Pharmacy sites in Jordan">
        <header className="panel-header"><div><h2>Pharmacy sites in Jordan</h2><p>Explore by governorate</p></div><span className="map-symbol"><Icon name="location" /></span></header>
        <div className="jordan-map" ref={host} aria-label="Interactive map of Jordan" />
        <div className="map-info" aria-live="polite">
            <div className="map-info-head">
                <strong>{scope}</strong>
                {selected ? <button type="button" className="text-button" onClick={() => { setSelected(''); setHovered(''); }}>All Jordan</button>
                    : <span>All time</span>}
            </div>
            <dl className="map-insights">
                <div className="map-insight-link"><dt><a href={directoryLink('pharmacies')} aria-label={'Total pharmacies in ' + scope}>
                    Total pharmacies <Icon name="arrow" size={13} />
                </a></dt><dd>{sites ? regionSites.length : '—'}</dd></div>
                <div className="map-insight-link"><dt><a href={directoryLink('pharmacies', 'active')} aria-label={'Active pharmacies in ' + scope}>
                    Active pharmacies <Icon name="arrow" size={13} />
                </a></dt><dd>{sites ? active : '—'}</dd></div>
                <div className="map-insight-link"><dt><a href={directoryLink('reservations', 'completed')} aria-label={'Completed reservations in ' + scope}>
                    Completed reservations <Icon name="arrow" size={13} />
                </a></dt><dd>{sites ? completed : '—'}</dd></div>
            </dl>
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
