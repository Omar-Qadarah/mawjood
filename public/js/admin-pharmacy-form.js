const form = document.querySelector('#pharmacy-form');
const dialog = document.querySelector('#pharmacy-dialog');
const fields = form.elements;
const places = JSON.parse(document.querySelector('#admin-app').dataset.places);
const errorBox = document.querySelector('#pharmacy-form-error');
const slots = document.querySelector('#opening-slots');
const pinStatus = document.querySelector('#pin-status');
const centerButton = document.querySelector('#use-map-center');
let map;
let pin;
let legacyHours;
let originalPhone;

export function populateGovernors(select, placeholder) {
    select.replaceChildren(new Option(placeholder, ''));
    Object.keys(places).forEach((name) => select.add(new Option(name, name)));
}

export function populateCities(select, governorate, placeholder, existingCity = '') {
    select.replaceChildren(new Option(placeholder, ''));
    const cities = places[governorate]?.cities ?? [];
    cities.forEach((city) => select.add(new Option(city, city)));
    if (existingCity && !cities.includes(existingCity)) {
        select.add(new Option(existingCity + ' (existing)', existingCity));
    }
    select.disabled = !governorate && !existingCity;
    select.value = existingCity;
}

function setCoordinates(latitude, longitude) {
    if (latitude < 29 || latitude > 33.5 || longitude < 34.9 || longitude > 39.4) {
        pinStatus.textContent = 'Choose a location within the Jordan map area.';
        return;
    }

    fields.latitude.value = latitude.toFixed(6);
    fields.longitude.value = longitude.toFixed(6);
    if (!pin) {
        pin = window.L.circleMarker([latitude, longitude], {
            radius: 9, color: '#fff', weight: 3, fillColor: '#0d756e', fillOpacity: 1,
        }).addTo(map);
    }
    pin.setLatLng([latitude, longitude]);
    pinStatus.textContent = 'Pharmacy pin placed.';
}

function showMap(pharmacy) {
    if (!window.L) {
        pinStatus.textContent = pharmacy
            ? 'Map unavailable. The saved location will be kept.'
            : 'Map unavailable. Reopen this form once the map library is available.';
        centerButton.disabled = true;
        return;
    }

    if (!map) {
        map = window.L.map('pharmacy-map');
        window.L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
        }).addTo(map);
        map.on('click', ({ latlng }) => setCoordinates(latlng.lat, latlng.lng));
    }

    centerButton.disabled = false;
    if (pin) {
        pin.remove();
        pin = null;
    }

    map.invalidateSize();
    if (pharmacy) {
        map.setView([pharmacy.latitude, pharmacy.longitude], 15);
        setCoordinates(pharmacy.latitude, pharmacy.longitude);
    } else {
        map.setView([31.25, 36.4], 7);
    }
}

function addSlot(slot = {}) {
    const row = document.createElement('div');
    row.className = 'schedule-slot';
    for (const [name, label, type] of [
        ['date', 'Date', 'date'], ['opens_at', 'Opens', 'time'], ['closes_at', 'Closes', 'time'],
    ]) {
        const wrapper = document.createElement('label');
        const input = document.createElement('input');
        input.type = type;
        input.dataset.slotField = name;
        input.required = true;
        input.value = slot[name] ?? '';
        wrapper.append(label, input);
        row.append(wrapper);
    }

    const remove = document.createElement('button');
    remove.type = 'button';
    remove.className = 'button button-small button-secondary';
    remove.textContent = 'Remove';
    remove.addEventListener('click', () => row.remove());
    row.append(remove);
    slots.append(row);
}

populateGovernors(fields.governorate, 'Choose a governorate');
fields.governorate.addEventListener('change', () => {
    populateCities(fields.city, fields.governorate.value, 'Choose a city');
    if (map && places[fields.governorate.value]) {
        map.setView(places[fields.governorate.value].center, 12);
    }
});
centerButton.addEventListener('click', () => {
    const center = map.getCenter();
    setCoordinates(center.lat, center.lng);
});
document.querySelector('#add-slot').addEventListener('click', () => addSlot());

export function showPharmacyError(message = '') {
    errorBox.textContent = message;
    errorBox.hidden = !message;
    if (message) errorBox.scrollIntoView({ block: 'nearest' });
}

export function openPharmacyEditor(pharmacy = null) {
    form.reset();
    fields.id.value = '';
    fields.latitude.value = '';
    fields.longitude.value = '';
    showPharmacyError();
    slots.replaceChildren();
    const hours = pharmacy?.opening_hours;
    const hasDatedSlots = Array.isArray(hours) && hours.every((slot) => Boolean(slot?.date));
    legacyHours = hours != null && !hasDatedSlots;
    document.querySelector('#legacy-hours').hidden = !legacyHours;
    document.querySelector('#form-title').textContent = pharmacy ? 'Edit pharmacy' : 'Add pharmacy';
    document.querySelector('#save-pharmacy').textContent = pharmacy ? 'Save changes' : 'Save pharmacy';
    pinStatus.textContent = 'No location selected.';
    originalPhone = pharmacy?.phone ?? '';

    if (pharmacy) {
        for (const name of ['id', 'name_ar', 'name_en', 'license_no', 'phone', 'email', 'address', 'status', 'latitude', 'longitude']) {
            fields[name].value = pharmacy[name] ?? '';
        }
        if (hasDatedSlots) {
            hours.forEach(addSlot);
        }
    }

    fields.governorate.value = pharmacy?.governorate ?? '';
    fields.governorate.required = !pharmacy || Boolean(pharmacy.governorate);
    populateCities(fields.city, fields.governorate.value, 'Choose a city', pharmacy?.city);
    dialog.showModal();
    showMap(pharmacy);
}

export function pharmacyPayload() {
    const body = Object.fromEntries(new FormData(form));
    delete body.id;
    if (!body.latitude || !body.longitude) {
        throw new Error('Place the pharmacy pin on the map before saving.');
    }
    if (!body.governorate) {
        delete body.governorate;
    }
    if (fields.id.value && body.phone === originalPhone) {
        delete body.phone;
    }

    const schedule = [...slots.children].map((row) => Object.fromEntries(
        [...row.querySelectorAll('[data-slot-field]')].map((input) => [input.dataset.slotField, input.value])
    ));
    if (schedule.some((slot) => slot.closes_at <= slot.opens_at)) {
        throw new Error('Each closing time must be later than its opening time.');
    }
    if (!legacyHours || schedule.length) {
        body.opening_hours = schedule;
    }

    return body;
}
