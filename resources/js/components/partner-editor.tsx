import { Plus, Trash2, Users } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

export type Partner = {
    name: string;
    type: string;
};

const PARTNER_TYPES: { value: string; label: string }[] = [
    { value: 'host', label: 'Host' },
    { value: 'sponsor', label: 'Sponsor' },
    { value: 'food', label: 'Food' },
    { value: 'beverage', label: 'Beverage' },
    { value: 'medical', label: 'Medical' },
    { value: 'organization', label: 'Organization' },
    { value: 'media', label: 'Media' },
    { value: 'logistics', label: 'Logistics' },
    { value: 'other', label: 'Other' },
];

type Props = {
    value: Partner[];
    onChange: (partners: Partner[]) => void;
};

export default function PartnerEditor({ value, onChange }: Props) {
    const addPartner = () => {
        onChange([...value, { name: '', type: 'host' }]);
    };

    const removePartner = (index: number) => {
        onChange(value.filter((_, i) => i !== index));
    };

    const updatePartner = (index: number, patch: Partial<Partner>) => {
        onChange(
            value.map((partner, i) =>
                i === index ? { ...partner, ...patch } : partner,
            ),
        );
    };

    return (
        <div className="flex flex-col gap-3">
            {value.length === 0 ? (
                <div className="flex flex-col items-center justify-center rounded-lg border border-dashed border-white/10 py-8 text-center">
                    <Users className="mb-2 h-8 w-8 text-muted-foreground/40" />
                    <p className="text-sm text-muted-foreground">
                        No partners yet.
                    </p>
                    <p className="mt-0.5 text-xs text-muted-foreground">
                        Add hosts, sponsors, food, or beverage providers.
                    </p>
                </div>
            ) : (
                <div className="flex flex-col gap-2">
                    {value.map((partner, index) => (
                        <div
                            key={index}
                            className="flex items-start gap-2 rounded-lg border border-white/10 bg-white/[0.02] p-2"
                        >
                            <div className="flex-1">
                                <Input
                                    value={partner.name}
                                    onChange={(e) =>
                                        updatePartner(index, {
                                            name: e.target.value,
                                        })
                                    }
                                    placeholder="Partner name, e.g. Barangay San Roque"
                                />
                            </div>

                            <div className="w-40 shrink-0">
                                <Select
                                    value={partner.type}
                                    onValueChange={(next) =>
                                        updatePartner(index, { type: next })
                                    }
                                >
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {PARTNER_TYPES.map((t) => (
                                            <SelectItem
                                                key={t.value}
                                                value={t.value}
                                            >
                                                {t.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>

                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                onClick={() => removePartner(index)}
                                className="shrink-0 text-destructive hover:bg-destructive/10 hover:text-destructive"
                                aria-label={`Remove partner ${index + 1}`}
                            >
                                <Trash2 className="h-4 w-4" />
                            </Button>
                        </div>
                    ))}
                </div>
            )}

            <Button
                type="button"
                variant="outline"
                onClick={addPartner}
                className="w-fit"
            >
                <Plus className="mr-2 h-4 w-4" />
                Add Partner
            </Button>
        </div>
    );
}
