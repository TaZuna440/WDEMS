import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type FormData = {
    venue: string;
    venue_address: string;
    venue_map_url: string;
    venue_latitude: number | null;
    venue_longitude: number | null;
};

type Props = {
    data: FormData;
    setData: (key: string, value: unknown) => void;
    errors: Record<string, string>;
};

/**
 * If the user pasted a map link without a scheme (e.g. maps.app.goo.gl/xyz),
 * prepend https:// so the value passes both client and server validation.
 * Leaves the value alone if it already has a scheme, or if it is empty.
 */
function normalizeMapUrl(value: string): string {
    const trimmed = value.trim();
    if (trimmed === '') return '';
    if (/^https?:\/\//i.test(trimmed)) return trimmed;
    return `https://${trimmed}`;
}

export default function VenueStep({ data, setData, errors }: Props) {
    return (
        <div className="flex flex-col gap-6">
            <div>
                <h2 className="text-lg font-semibold text-foreground">
                    Venue
                </h2>
                <p className="mt-1 text-sm text-muted-foreground">
                    Where the event takes place.
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

            <div className="grid gap-2">
                <Label htmlFor="venue_address">
                    Full Address <span className="text-destructive">*</span>
                </Label>
                <Input
                    id="venue_address"
                    value={data.venue_address}
                    onChange={(e) => setData('venue_address', e.target.value)}
                    placeholder="e.g. Seaside Blvd, Pasay, Metro Manila"
                />
                <InputError message={errors.venue_address} />
                <p className="text-xs text-muted-foreground">
                    Full street address participants can type into a
                    ride-hailing app if needed.
                </p>
            </div>

            <div className="grid gap-2">
                <Label htmlFor="venue_map_url">Map Link</Label>
                <Input
                    id="venue_map_url"
                    type="url"
                    value={data.venue_map_url}
                    onChange={(e) =>
                        setData('venue_map_url', e.target.value)
                    }
                    onBlur={(e) =>
                        setData(
                            'venue_map_url',
                            normalizeMapUrl(e.target.value),
                        )
                    }
                    placeholder="Paste a Google Maps link to the meeting point"
                />
                <InputError message={errors.venue_map_url} />
                <p className="text-xs text-muted-foreground">
                    Optional. Open Google Maps, tap Share, and paste the link
                    here. Participants will get a one-tap "navigate here"
                    button. If you leave out the https:// part, it is added
                    automatically.
                </p>
            </div>
        </div>
    );
}
