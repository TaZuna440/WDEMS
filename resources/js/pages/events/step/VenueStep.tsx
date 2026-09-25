import { MapPin, Navigation } from 'lucide-react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import MapPicker from '@/components/map-picker';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type FormData = {
    venue: string;
    venue_address: string;
    venue_latitude: number | null;
    venue_longitude: number | null;
};

type Props = {
    data: FormData;
    setData: (key: string, value: unknown) => void;
    errors: Record<string, string>;
};

export default function VenueStep({ data, setData, errors }: Props) {
    const [mapOpen, setMapOpen] = useState(false);

    const hasLocation =
        data.venue_latitude !== null && data.venue_longitude !== null;

    return (
        <div className="flex flex-col gap-6">
            <div>
                <h2 className="text-lg font-semibold text-foreground">
                    Venue
                </h2>
                <p className="mt-1 text-sm text-muted-foreground">
                    Where the event takes place. Pick the exact spot on the
                    map so participants can navigate to it.
                </p>
            </div>

            <div className="grid gap-2">
                <Label htmlFor="venue">
                    Venue Name <span className="text-destructive">*</span>
                </Label>
                <Input
                    id="venue"
                    value={data.venue}
                    onChange={(e) => setData('venue', e.target.value)}
                    placeholder="e.g. SM Mall of Asia Grounds"
                />
                <InputError message={errors.venue} />
                <p className="text-xs text-muted-foreground">
                    Short name participants will recognize.
                </p>
            </div>

            <div className="flex flex-col gap-3 rounded-lg border border-white/10 bg-white/[0.02] p-4">
                <div className="flex items-start justify-between gap-3">
                    <div className="flex-1">
                        <Label className="text-sm">Location on map</Label>
                        <p className="mt-0.5 text-xs text-muted-foreground">
                            {hasLocation
                                ? 'Location set. Click to adjust.'
                                : 'Not set yet. Click the button to pick a spot.'}
                        </p>
                    </div>

                    <Button
                        type="button"
                        variant={hasLocation ? 'outline' : 'default'}
                        onClick={() => setMapOpen(true)}
                        className={
                            hasLocation
                                ? ''
                                : 'bg-lime-brand text-navy-900 hover:bg-lime-brand/90'
                        }
                    >
                        <MapPin className="mr-2 h-4 w-4" />
                        {hasLocation ? 'Change Location' : 'Pick on Map'}
                    </Button>
                </div>

                {hasLocation && (
                    <div className="flex items-start gap-2 rounded-md border border-lime-brand/20 bg-lime-brand/5 p-3">
                        <Navigation className="mt-0.5 h-3.5 w-3.5 shrink-0 text-lime-brand" />
                        <div className="flex flex-1 flex-col">
                            <span className="text-xs text-muted-foreground">
                                Coordinates
                            </span>
                            <span className="text-xs font-mono text-foreground">
                                {data.venue_latitude?.toFixed(6)},{' '}
                                {data.venue_longitude?.toFixed(6)}
                            </span>
                        </div>
                    </div>
                )}

                {errors.venue_latitude && (
                    <InputError message={errors.venue_latitude} />
                )}
                {errors.venue_longitude && (
                    <InputError message={errors.venue_longitude} />
                )}
            </div>

            <div className="grid gap-2">
                <Label htmlFor="venue_address">
                    Full Address <span className="text-destructive">*</span>
                </Label>
                <Input
                    id="venue_address"
                    value={data.venue_address}
                    onChange={(e) => setData('venue_address', e.target.value)}
                    placeholder="Fills in automatically after you pick on the map"
                />
                <InputError message={errors.venue_address} />
                <p className="text-xs text-muted-foreground">
                    Filled automatically from the map. Edit if it needs
                    adjusting.
                </p>
            </div>

            <MapPicker
                open={mapOpen}
                onOpenChange={setMapOpen}
                initialLatitude={data.venue_latitude}
                initialLongitude={data.venue_longitude}
                initialAddress={data.venue_address}
                onConfirm={(result) => {
                    setData('venue_latitude', result.latitude);
                    setData('venue_longitude', result.longitude);
                    setData('venue_address', result.address);
                }}
            />
        </div>
    );
}
