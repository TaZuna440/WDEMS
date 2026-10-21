import InputError from '@/components/input-error';
import PartnerEditor, { type Partner } from '@/components/partner-editor';

type FormData = {
    partners: Partner[];
};

type Props = {
    data: FormData;
    setData: (key: string, value: unknown) => void;
    errors: Record<string, string>;
};

const MAX_PARTNERS = 20;

export default function ExtrasStep({ data, setData, errors }: Props) {
    const partners = data.partners ?? [];
    const atCap = partners.length >= MAX_PARTNERS;

    return (
        <div className="flex flex-col gap-8">
            <div>
                <h2 className="text-foreground text-lg font-semibold">
                    Extras
                </h2>
                <p className="text-muted-foreground mt-1 text-sm">
                    Optional details. All of this can be edited later.
                </p>
            </div>

            {/* Partners */}
            <section className="flex flex-col gap-3">
                <div className="flex items-baseline justify-between">
                    <h3 className="text-muted-foreground text-sm font-semibold tracking-wider uppercase">
                        Partners
                    </h3>
                    <span className="text-muted-foreground text-xs">
                        {partners.length} / {MAX_PARTNERS}
                    </span>
                </div>
                <p className="text-muted-foreground text-xs">
                    Hosts, sponsors, food, beverage, or support organizations
                    involved in this event.
                </p>

                <PartnerEditor
                    value={partners}
                    onChange={(next) => setData('partners', next)}
                    errors={errors}
                />

                {errors.partners && <InputError message={errors.partners} />}

                {atCap && !errors.partners && (
                    <p className="text-muted-foreground text-xs">
                        You have reached the maximum of {MAX_PARTNERS} partners.
                        Remove one to add another.
                    </p>
                )}
            </section>
        </div>
    );
}
