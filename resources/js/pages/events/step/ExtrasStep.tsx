import AccessibilityGrid, {
    type AccessibilityField,
} from '@/components/accessibility-grid';
import PartnerEditor, { type Partner } from '@/components/partner-editor';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';

type FormData = {
    rsvp_required: boolean;
    partners: Partner[];
} & Partial<Record<AccessibilityField, boolean>>;

type Props = {
    data: FormData;
    setData: (key: string, value: unknown) => void;
    errors: Record<string, string>;
};

const ACCESSIBILITY_FIELDS: AccessibilityField[] = [
    'walkers_welcome',
    'all_paces_welcome',
    'all_ages_welcome',
    'stroller_friendly',
    'wheelchair_accessible',
    'sweeper_present',
    'service_animals_allowed',
    'leashed_pets_allowed',
    'quiet_space_available',
];

export default function ExtrasStep({ data, setData, errors }: Props) {
    const accessibilityValue: Partial<Record<AccessibilityField, boolean>> = {};
    for (const field of ACCESSIBILITY_FIELDS) {
        accessibilityValue[field] = data[field] === true;
    }

    return (
        <div className="flex flex-col gap-8">
            <div>
                <h2 className="text-lg font-semibold text-foreground">
                    Extras
                </h2>
                <p className="mt-1 text-sm text-muted-foreground">
                    Optional details. All of this can be edited later.
                </p>
            </div>

            {/* RSVP */}
            <section className="flex flex-col gap-3">
                <h3 className="text-sm font-semibold uppercase tracking-wider text-muted-foreground">
                    Registration
                </h3>

                <div className="flex items-start gap-3 rounded-lg border border-white/5 bg-white/[0.02] p-3">
                    <Checkbox
                        id="rsvp_required"
                        checked={data.rsvp_required === true}
                        onCheckedChange={(checked) =>
                            setData('rsvp_required', checked === true)
                        }
                        className="mt-0.5"
                    />
                    <div className="flex flex-1 flex-col">
                        <Label
                            htmlFor="rsvp_required"
                            className="cursor-pointer text-sm font-medium"
                        >
                            RSVP is required
                        </Label>
                        <p className="mt-0.5 text-xs text-muted-foreground">
                            If checked, participants must register before the
                            event. Otherwise, walk-ins are welcome.
                        </p>
                    </div>
                </div>
            </section>

            {/* Partners */}
            <section className="flex flex-col gap-3">
                <div>
                    <h3 className="text-sm font-semibold uppercase tracking-wider text-muted-foreground">
                        Partners
                    </h3>
                    <p className="mt-1 text-xs text-muted-foreground">
                        Hosts, sponsors, food, beverage, or support
                        organizations involved in this event.
                    </p>
                </div>

                <PartnerEditor
                    value={data.partners ?? []}
                    onChange={(partners) => setData('partners', partners)}
                />
            </section>

            {/* Accessibility */}
            <section className="flex flex-col gap-3">
                <div>
                    <h3 className="text-sm font-semibold uppercase tracking-wider text-muted-foreground">
                        Accessibility
                    </h3>
                    <p className="mt-1 text-xs text-muted-foreground">
                        Check everything that applies. These appear on the
                        event page so participants know what to expect.
                    </p>
                </div>

                <AccessibilityGrid
                    value={accessibilityValue}
                    onChange={(field, checked) => setData(field, checked)}
                />
            </section>
        </div>
    );
}
