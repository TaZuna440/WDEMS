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

export default function ScheduleStep({ data, setData, errors }: Props) {
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
                />
                <InputError message={errors.event_date} />
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
                    />
                    <InputError message={errors.end_time} />
                    <p className="text-xs text-muted-foreground">
                        Optional — when the event wraps up.
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
                    placeholder="https://maps.app.goo.gl/... or a Strava link"
                />
                <InputError message={errors.course_url} />
                <p className="text-xs text-muted-foreground">
                    Paste any link to the route — Google Maps, Strava, GPX
                    file, or a map image.
                </p>
            </div>
        </div>
    );
}
