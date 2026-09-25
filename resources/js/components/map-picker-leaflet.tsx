import L from 'leaflet';
import { useRef, useState } from 'react';
import { MapContainer, Marker, TileLayer, useMapEvents } from 'react-leaflet';
import 'leaflet/dist/leaflet.css';

import markerIcon from 'leaflet/dist/images/marker-icon.png';
import markerIcon2x from 'leaflet/dist/images/marker-icon-2x.png';
import markerShadow from 'leaflet/dist/images/marker-shadow.png';

// Fix Vite bundling icon paths for Leaflet default marker
delete (L.Icon.Default.prototype as unknown as { _getIconUrl?: unknown })._getIconUrl;
L.Icon.Default.mergeOptions({
    iconUrl: markerIcon,
    iconRetinaUrl: markerIcon2x,
    shadowUrl: markerShadow,
});

const MANILA: [number, number] = [14.5995, 120.9842];

type Props = {
    latitude: number | null;
    longitude: number | null;
    onPick: (lat: number, lng: number) => void;
    onAddressResolved: (address: string) => void;
};

function ClickHandler({
    onPick,
}: {
    onPick: (lat: number, lng: number) => void;
}) {
    useMapEvents({
        click(e) {
            onPick(e.latlng.lat, e.latlng.lng);
        },
    });
    return null;
}

export default function MapPickerLeaflet({
    latitude,
    longitude,
    onPick,
    onAddressResolved,
}: Props) {
    const hasInitial = latitude !== null && longitude !== null;
    const center: [number, number] = hasInitial
        ? [latitude as number, longitude as number]
        : MANILA;

    const [marker, setMarker] = useState<[number, number] | null>(
        hasInitial ? [latitude as number, longitude as number] : null,
    );
    const [loading, setLoading] = useState(false);
    const lastQueryRef = useRef<string | null>(null);

    const handlePick = async (lat: number, lng: number) => {
        setMarker([lat, lng]);
        onPick(lat, lng);

        const queryKey = `${lat.toFixed(6)},${lng.toFixed(6)}`;
        if (lastQueryRef.current === queryKey) return;
        lastQueryRef.current = queryKey;

        setLoading(true);
        try {
            const res = await fetch(
                `https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}&zoom=18&addressdetails=1`,
                { headers: { Accept: 'application/json' } },
            );
            if (! res.ok) throw new Error('Reverse geocode failed');
            const data = await res.json();
            const address =
                typeof data.display_name === 'string' ? data.display_name : '';
            if (address) onAddressResolved(address);
        } catch {
            // Silently fail — user can edit the address manually
        } finally {
            setLoading(false);
        }
    };

    return (
        <div className="relative h-[400px] w-full overflow-hidden rounded-lg border border-white/10">
            <MapContainer
                center={center}
                zoom={hasInitial ? 15 : 12}
                style={{ height: '100%', width: '100%' }}
                scrollWheelZoom
            >
                <TileLayer
                    attribution='&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
                    url="https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png"
                />
                <ClickHandler onPick={handlePick} />
                {marker && <Marker position={marker} />}
            </MapContainer>

            {loading && (
                <div className="pointer-events-none absolute left-3 top-3 rounded-md bg-navy-900/90 px-3 py-1.5 text-xs text-white">
                    Looking up address...
                </div>
            )}

            {! marker && (
                <div className="pointer-events-none absolute bottom-3 left-1/2 -translate-x-1/2 rounded-md bg-navy-900/90 px-3 py-1.5 text-xs text-white">
                    Click anywhere on the map to place the marker
                </div>
            )}
        </div>
    );
}
