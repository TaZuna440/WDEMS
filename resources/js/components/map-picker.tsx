import { MapPin } from 'lucide-react';
import { lazy, Suspense, useEffect, useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

const MapPickerLeaflet = lazy(() => import('@/components/map-picker-leaflet'));

export type MapPickerResult = {
    latitude: number;
    longitude: number;
    address: string;
};

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    initialLatitude?: number | null;
    initialLongitude?: number | null;
    initialAddress?: string;
    onConfirm: (result: MapPickerResult) => void;
};

export default function MapPicker({
    open,
    onOpenChange,
    initialLatitude = null,
    initialLongitude = null,
    initialAddress = '',
    onConfirm,
}: Props) {
    const [lat, setLat] = useState<number | null>(initialLatitude);
    const [lng, setLng] = useState<number | null>(initialLongitude);
    const [address, setAddress] = useState(initialAddress);

    // Reset local state every time the dialog opens
    useEffect(() => {
        if (open) {
            setLat(initialLatitude);
            setLng(initialLongitude);
            setAddress(initialAddress);
        }
    }, [open, initialLatitude, initialLongitude, initialAddress]);

    const canConfirm = lat !== null && lng !== null;

    const handleConfirm = () => {
        if (lat === null || lng === null) return;
        onConfirm({ latitude: lat, longitude: lng, address });
        onOpenChange(false);
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-w-3xl">
                <DialogHeader>
                    <DialogTitle>Pick venue location</DialogTitle>
                    <DialogDescription>
                        Click anywhere on the map to place the marker. The
                        address will be filled automatically — you can edit it.
                    </DialogDescription>
                </DialogHeader>

                <Suspense
                    fallback={
                        <div className="flex h-[400px] items-center justify-center rounded-lg border border-white/10 bg-white/5 text-sm text-muted-foreground">
                            Loading map...
                        </div>
                    }
                >
                    <MapPickerLeaflet
                        latitude={lat}
                        longitude={lng}
                        onPick={(nextLat, nextLng) => {
                            setLat(nextLat);
                            setLng(nextLng);
                        }}
                        onAddressResolved={setAddress}
                    />
                </Suspense>

                <div className="grid gap-2">
                    <Label htmlFor="map-address">Address</Label>
                    <Input
                        id="map-address"
                        value={address}
                        onChange={(e) => setAddress(e.target.value)}
                        placeholder="Address will fill in after you place the marker"
                    />
                </div>

                {lat !== null && lng !== null && (
                    <p className="text-xs text-muted-foreground">
                        <MapPin className="mr-1 inline h-3 w-3" />
                        {lat.toFixed(6)}, {lng.toFixed(6)}
                    </p>
                )}

                <DialogFooter className="gap-2">
                    <Button
                        variant="outline"
                        onClick={() => onOpenChange(false)}
                    >
                        Cancel
                    </Button>
                    <Button
                        onClick={handleConfirm}
                        disabled={! canConfirm}
                        className="bg-lime-brand text-navy-900 hover:bg-lime-brand/90"
                    >
                        Use this location
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
