import AccessibilityGrid, {
    type AccessibilityField,
} from '@/components/accessibility-grid';
import InputError from '@/components/input-error';
import PartnerEditor, { type Partner } from '@/components/partner-editor';

type FormData = {
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

const MAX_PARTNERS = 20;

export default function ExtrasStep({ data, setData, errors }: Props) {
    const accessibilityValue: Partial<Record<AccessibilityField, boolean>> = {};
    for (const field of ACCESSIBILITY_FIELDS) {
        accessibilityValue[field] = data[field] === true;
    }

    const partners = data.partners ?? [];
    const atCap = partners.length >= MAX_PARTNERS;

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

            {/* Partners */}
            <section className="flex flex-col gap-3">
                <div className="flex items-baseline justify-between">
                    <h3 className="text-sm font-semibold uppercase tracking-wider text-muted-foreground">
                        Partners
                    </h3>
                    <span className="text-xs text-muted-foreground">
                        {partners.length} / {MAX_PARTNERS}
                    </span>
                </div>
                <p className="text-xs text-muted-foreground">
                    Hosts, sponsors, food, beverage, or support organizations
                    involved in this event.
                </p>

                <PartnerEditor
                    value={partners}
                    onChange={(next) => setData('partners', next)}
                />

                {errors.partners && (
                    <InputError message={errors.partners} />
                )}

                {atCap && !errors.partners && (
                    <p className="text-xs text-muted-foreground">
                        You have reached the maximum of {MAX_PARTNERS}{' '}
                        partners. Remove one to add another.
                    </p>
                )}
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
