import { useEffect, useRef, useState } from 'react';
import { Field, Notice } from './ui';

const size = 384;
const centered = { zoom: 1, horizontal: 50, vertical: 50 };
const clampPosition = value => Math.round(Math.max(0, Math.min(100, value)));

export function ProfilePhotoCrop({ source, initialCrop, onApply, onBack }) {
    const [crop, setCrop] = useState(initialCrop ?? centered);
    const [image, setImage] = useState(null);
    const [error, setError] = useState('');
    const [applying, setApplying] = useState(false);
    const canvas = useRef(null);
    const drag = useRef(null);

    useEffect(() => {
        const photo = new Image();
        photo.onload = () => setImage(photo);
        photo.onerror = () => setError('This photo could not be opened.');
        photo.src = source;
        return () => { photo.onload = null; photo.onerror = null; };
    }, [source]);

    useEffect(() => {
        if (!image) return;

        const { naturalWidth: width, naturalHeight: height } = image;
        const side = Math.min(width, height) / crop.zoom;
        const left = (width - side) * crop.horizontal / 100;
        const top = (height - side) * crop.vertical / 100;
        const context = canvas.current.getContext('2d');
        context.clearRect(0, 0, size, size);
        context.drawImage(image, left, top, side, side, 0, 0, size, size);
    }, [crop, image]);

    function change(field, value) {
        setCrop(previous => ({ ...previous, [field]: Number(value) }));
    }

    function startDrag(event) {
        if (!image || applying || !event.isPrimary || event.button !== 0) return;

        const { naturalWidth: width, naturalHeight: height } = image;
        const side = Math.min(width, height) / crop.zoom;
        const screenWidth = event.currentTarget.getBoundingClientRect().width;
        if (!screenWidth) return;

        drag.current = {
            pointerId: event.pointerId,
            x: event.clientX,
            y: event.clientY,
            horizontal: crop.horizontal,
            vertical: crop.vertical,
            scaleX: width > side ? 100 * side / (screenWidth * (width - side)) : 0,
            scaleY: height > side ? 100 * side / (screenWidth * (height - side)) : 0,
        };
        event.currentTarget.setPointerCapture(event.pointerId);
    }

    function moveDrag(event) {
        const start = drag.current;
        if (start?.pointerId !== event.pointerId) return;

        const horizontal = clampPosition(start.horizontal - (event.clientX - start.x) * start.scaleX);
        const vertical = clampPosition(start.vertical - (event.clientY - start.y) * start.scaleY);
        setCrop(previous => ({ ...previous, horizontal, vertical }));
    }

    function endDrag(event) {
        if (drag.current?.pointerId === event.pointerId) drag.current = null;
    }

    function applyCrop() {
        setApplying(true);
        canvas.current.toBlob(blob => {
            if (!canvas.current) return;

            if (blob) {
                onApply(new File([blob], 'profile-crop.png', { type: 'image/png' }), crop);
            } else {
                setError('The crop could not be created. Try again.');
                setApplying(false);
            }
        }, 'image/png');
    }

    return <div className="profile-crop">
        <Notice error>{error}</Notice>
        <canvas ref={canvas} width={size} height={size} role="img" aria-label="Circular crop preview" aria-describedby="profile-crop-help"
            onPointerDown={startDrag} onPointerMove={moveDrag} onPointerUp={endDrag}
            onPointerCancel={endDrag} onLostPointerCapture={endDrag} />
        <p id="profile-crop-help">Drag the photo to reposition it, or use the position sliders.</p>
        <fieldset className="profile-crop-controls" disabled={!image || applying}>
            <Field label="Zoom"><input type="range" min="1" max="3" step="0.05" value={crop.zoom}
                onChange={event => change('zoom', event.target.value)} /></Field>
            <Field label="Horizontal position"><input type="range" min="0" max="100" value={crop.horizontal}
                onChange={event => change('horizontal', event.target.value)} /></Field>
            <Field label="Vertical position"><input type="range" min="0" max="100" value={crop.vertical}
                onChange={event => change('vertical', event.target.value)} /></Field>
        </fieldset>
        <footer className="form-actions">
            <button className="button secondary" type="button" onClick={onBack}>Back</button>
            <button className="button primary" type="button" disabled={!image || applying} onClick={applyCrop}>Apply crop</button>
        </footer>
    </div>;
}
