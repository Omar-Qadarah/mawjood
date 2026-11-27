import { useEffect, useRef, useState } from 'react';
import { Field, Notice } from './ui';

const size = 384;
const centered = { zoom: 1, horizontal: 50, vertical: 50 };
const clampPosition = value => Math.round(Math.max(0, Math.min(100, value)));
const clampZoom = value => Math.round(Math.max(1, Math.min(3, value)) * 20) / 20;
const touchDistance = ([first, second]) => Math.hypot(first.x - second.x, first.y - second.y);

export function ProfilePhotoCrop({ source, initialCrop, onApply, onBack }) {
    const [crop, setCrop] = useState(initialCrop ?? centered);
    const [image, setImage] = useState(null);
    const [error, setError] = useState('');
    const [applying, setApplying] = useState(false);
    const canvas = useRef(null);
    const drag = useRef(null);
    const touches = useRef(new Map());
    const pinch = useRef(null);

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

    useEffect(() => {
        if (!image || applying) return;

        const target = canvas.current;
        function wheelZoom(event) {
            if (!event.deltaY) return;

            event.preventDefault();
            const step = event.deltaY < 0 ? 0.1 : -0.1;
            setCrop(previous => ({ ...previous, zoom: clampZoom(previous.zoom + step) }));
        }
        target.addEventListener('wheel', wheelZoom, { passive: false });
        return () => target.removeEventListener('wheel', wheelZoom);
    }, [image, applying]);

    function change(field, value) {
        setCrop(previous => ({ ...previous, [field]: Number(value) }));
    }

    function startDrag(event) {
        if (!image || applying || event.button !== 0 || (event.pointerType !== 'touch' && !event.isPrimary)) return;

        if (event.pointerType === 'touch') {
            if (touches.current.size >= 2) return;

            touches.current.set(event.pointerId, { x: event.clientX, y: event.clientY });
            if (touches.current.size === 2) {
                drag.current = null;
                pinch.current = { distance: touchDistance([...touches.current.values()]), zoom: crop.zoom };
                event.currentTarget.setPointerCapture(event.pointerId);
                return;
            }
        }

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
        if (touches.current.has(event.pointerId)) {
            touches.current.set(event.pointerId, { x: event.clientX, y: event.clientY });
            if (pinch.current?.distance > 0) {
                const { distance, zoom } = pinch.current;
                const nextZoom = clampZoom(zoom * touchDistance([...touches.current.values()]) / distance);
                setCrop(previous => ({ ...previous, zoom: nextZoom }));
                return;
            }
        }

        const start = drag.current;
        if (start?.pointerId !== event.pointerId) return;

        const horizontal = clampPosition(start.horizontal - (event.clientX - start.x) * start.scaleX);
        const vertical = clampPosition(start.vertical - (event.clientY - start.y) * start.scaleY);
        setCrop(previous => ({ ...previous, horizontal, vertical }));
    }

    function endDrag(event) {
        if (touches.current.delete(event.pointerId)) pinch.current = null;
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
        <p id="profile-crop-help">Drag to reposition. Pinch, scroll, or use the slider to zoom.</p>
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
            <button className="button secondary" type="button" disabled={!image || applying} onClick={() => setCrop(centered)}>Reset crop</button>
            <button className="button primary" type="button" disabled={!image || applying} onClick={applyCrop}>Apply crop</button>
        </footer>
    </div>;
}
