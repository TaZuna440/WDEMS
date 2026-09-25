import { AlertTriangle } from 'lucide-react';
import DatePicker from '@/components/date-picker';
import DistancePicker, {
    type DistanceUnit,
} from '@/components/distance-picker';
import InputError from '@/components/input-error';
import TimePicker from '@/components/time-picker';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type FormData = {
    event_date: string;
    start_time: string;
    end_time: string;
    distance_value: number | null;
    distance_unit: DistanceUnit;
    course_url: string;
};

type Props = {
    data: FormData;
    setData: (key: string, value: unknown) => void;
    errors: Record<string, string>;
};

const MAX_EVENT_YEARS_AHEAD = 2;

/**
 * If the user pasted a URL without a scheme (e.g. maps.app.goo.gl/xyz),
 * prepend https:// so the value passes both client and server validation.
 * Leaves the value alone if it already has a scheme, or if it is empty.
 */
function normalizeCourseUrl(value: string): string {
    const trimmed = value.trim();
    if (trimmed === '') return '';
    if (/^https?:\/\//i.test(trimmed)) return trimmed;
    return `https://${trimmed}`;
}

function pickerBounds(): { min: Date; max: Date } {
    const min = new Date();
    min.setHours(0, 0, 0, 0);
    const max = new Date(min);
    max.setFullYear(min.getFullYear() + MAX_EVENT_YEARS_AHEAD);
    return { min, max };
}

/**
 * Today's date as YYYY-MM-DD in local time. Uses the same construction
 * as the rest of the codebase so no UTC shift can move the day.
 */
function todayIso(): string {
    const d = new Date();
    d.setHours(0, 0, 0, 0);
    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return `${y}-${m}-${day}`;
}

export default function ScheduleStep({ data, setData, errors }: Props) {
    const bounds = pickerBounds();
    const isScheduledForToday = data.event_date === todayIso();

    return (
        <div className="flex flex-col gap-6">
            <div>
                <h2 className="text-lg font-semibold text-foreground">
                    Schedule
                </h2>
                <p className="mt-1 text-sm text-muted-foreground">
                    When the event happens, how far it is, and where to find
                    the course.
                </p>
            </div>

            <div className="grid gap-2">
                <Label htmlFor="event_date">
                    Event Date <span className="text-destructive">*</span>
                </Label>
                <DatePicker
                    id="event_date"
                    value={data.event_date}
                    onChange={(v) => setData('event_date', v)}
                    placeholder="Pick a date"
                    minDate={bounds.min}
                    maxDate={bounds.max}
                />
                <InputError message={errors.event_date} />
                <p className="text-xs text-muted-foreground">
                    Today or later, up to 2 years from today.
                </p>
                {isScheduledForToday && (
                    <p className="flex items-start gap-2 text-xs text-yellow-500/90">
                        <AlertTriangle className="mt-0.5 h-3.5 w-3.5 shrink-0" />
                        <span>
                            Heads up — events scheduled for today have no
                            advance registration window. Participants may not
                            see this in time.
                        </span>
                    </p>
                )}
            </div>

            <div className="grid gap-4 sm:grid-cols-2">
                <div className="grid gap-2">
                    <Label htmlFor="start_time">
                        Start Time <span className="text-destructive">*</span>
                    </Label>
                    <TimePicker
                        id="start_time"
                        value={data.start_time}
                        onChange={(v) => setData('start_time', v)}
                    />
                    <InputError message={errors.start_time} />
                    <p className="text-xs text-muted-foreground">
                        When participants should arrive.
                    </p>
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="end_time">End Time</Label>
                    <TimePicker
                        id="end_time"
                        value={data.end_time}
                        onChange={(v) => setData('end_time', v)}
                        clearable
                    />
                    <InputError message={errors.end_time} />
                    <p className="text-xs text-muted-foreground">
                        Optional. When set, must be at least 1 hour after
                        start time.
                    </p>
                </div>
            </div>

            <div className="grid gap-2">
                <Label htmlFor="distance_value">
                    Distance <span className="text-destructive">*</span>
                </Label>
                <DistancePicker
                    id="distance_value"
                    value={data.distance_value}
                    unit={data.distance_unit}
                    onChange={(value, unit) => {
                        setData('distance_value', value);
                        setData('distance_unit', unit);
                    }}
                />
                <InputError
                    message={
                        errors.distance_value ?? errors.distance_unit
                    }
                />
                <p className="text-xs text-muted-foreground">
                    Choose a standard distance and switch between KM and Miles
                    — the value converts automatically.
                </p>
            </div>

            <div className="grid gap-2">
                <Label htmlFor="course_url">Course Link</Label>
                <Input
                    id="course_url"
                    type="url"
                    value={data.course_url}
                    onChange={(e) => setData('course_url', e.target.value)}
                    onBlur={(e) =>
                        setData('course_url', normalizeCourseUrl(e.target.value))
                    }
                    placeholder="https://maps.app.goo.gl/..."
                />
                <InputError message={errors.course_url} />
                <p className="text-xs text-muted-foreground">
                    Paste any link to the route — Google Maps, Strava, GPX
                    file, or a map image. If you leave out the https:// part,
                    it is added automatically.
                </p>
            </div>
        </div>
    );
}
