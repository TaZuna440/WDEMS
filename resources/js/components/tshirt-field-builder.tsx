import { useEffect, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import InputError from '@/components/input-error';
import TshirtPhotoGallery from '@/components/tshirt-photo-gallery';

export type TshirtPhoto = { path: string; url: string; size: number };
export type TshirtDraft = {
    tshirt_enabled: boolean;
    tshirt_label: string;
    tshirt_required: boolean;
    tshirt_choices: string[];
    tshirt_existing_photos: string[];
    tshirt_photos: File[];
};
const defaults = ['Small', 'Medium', 'Large'];

export function validateTshirt(
    data: TshirtDraft,
    savedPhotos: TshirtPhoto[],
): Record<string, string> {
    if (!data.tshirt_enabled) return {};
    const errors: Record<string, string> = {};
    if (data.tshirt_label.trim().length < 2 || data.tshirt_label.length > 50)
        errors.tshirt_label = 'Use a label between 2 and 50 characters.';
    if (
        data.tshirt_choices.length > 6 ||
        defaults.some((option) => !data.tshirt_choices.includes(option))
    )
        errors.tshirt_choices =
            'Keep Small, Medium, and Large, with up to three custom choices.';
    if (
        data.tshirt_choices.some(
            (option) => !option.trim() || option.length > 50,
        ) ||
        new Set(
            data.tshirt_choices.map((option) => option.trim().toLowerCase()),
        ).size !== data.tshirt_choices.length
    )
        errors.tshirt_choices =
            'Use distinct, nonempty choices of at most 50 characters.';
    const kept = savedPhotos.filter((photo) =>
        data.tshirt_existing_photos.includes(photo.path),
    );
    if (kept.length + data.tshirt_photos.length > 3)
        errors.tshirt_photos = 'Use at most three photos.';
    if (
        kept.reduce((sum, photo) => sum + photo.size, 0) +
            data.tshirt_photos.reduce((sum, file) => sum + file.size, 0) >
        25 * 1024 * 1024
    )
        errors.tshirt_photos = 'Photos must total 25 MB or less.';
    if (
        data.tshirt_photos.some(
            (file) => !['image/jpeg', 'image/png'].includes(file.type),
        )
    )
        errors.tshirt_photos = 'Use JPG, JPEG, or PNG photos.';
    return errors;
}

export default function TshirtFieldBuilder({
    data,
    photos,
    errors,
    disabled,
    onChange,
}: {
    data: TshirtDraft;
    photos: TshirtPhoto[];
    errors: Record<string, string>;
    disabled: boolean;
    onChange: (patch: Partial<TshirtDraft>) => void;
}) {
    const [dragged, setDragged] = useState<number | null>(null);
    const [previews, setPreviews] = useState<string[]>([]);
    useEffect(() => {
        const urls = data.tshirt_photos.map((file) =>
            URL.createObjectURL(file),
        );
        setPreviews(urls);
        return () => urls.forEach((url) => URL.revokeObjectURL(url));
    }, [data.tshirt_photos]);
    const keptPhotos = photos.filter((photo) =>
        data.tshirt_existing_photos.includes(photo.path),
    );
    const hasData =
        keptPhotos.length + data.tshirt_photos.length > 0 ||
        data.tshirt_choices.length > 3 ||
        data.tshirt_label !== 'T-shirt Size';
    const move = (from: number, to: number) => {
        if (disabled || to < 0 || to >= data.tshirt_choices.length) return;
        const choices = [...data.tshirt_choices];
        choices.splice(to, 0, choices.splice(from, 1)[0]);
        onChange({ tshirt_choices: choices });
    };
    return (
        <section className="glass-panel space-y-4 rounded-xl p-5">
            <label className="flex items-center gap-3 font-semibold">
                <Checkbox
                    checked={data.tshirt_enabled}
                    disabled={disabled || (data.tshirt_enabled && hasData)}
                    onCheckedChange={(value) =>
                        onChange({ tshirt_enabled: value === true })
                    }
                />
                T-shirt selection
            </label>
            <p className="text-muted-foreground text-xs">
                Separate from your five custom fields. Remove photos and custom
                choices, and restore the default label before disabling.
            </p>
            <InputError message={errors.tshirt_enabled} />
            {data.tshirt_enabled && (
                <>
                    <Label htmlFor="tshirt-label">Field label</Label>
                    <Input
                        id="tshirt-label"
                        value={data.tshirt_label}
                        disabled={disabled}
                        maxLength={50}
                        onChange={(e) =>
                            onChange({ tshirt_label: e.target.value })
                        }
                    />
                    <InputError message={errors.tshirt_label} />
                    <label className="flex items-center gap-2 text-sm">
                        <Checkbox
                            checked={data.tshirt_required}
                            disabled={disabled}
                            onCheckedChange={(value) =>
                                onChange({ tshirt_required: value === true })
                            }
                        />
                        Required selection
                    </label>
                    <p className="text-muted-foreground text-sm">
                        Drag choices to reorder, or use the up/down buttons.
                    </p>
                    {data.tshirt_choices.map((choice, index) => (
                        <div
                            key={index}
                            className="flex items-center gap-2"
                            draggable={!disabled}
                            onDragStart={() => setDragged(index)}
                            onDragOver={(event) => event.preventDefault()}
                            onDrop={(event) => {
                                event.preventDefault();
                                if (dragged !== null) move(dragged, index);
                                setDragged(null);
                            }}
                        >
                            <span aria-hidden="true">⠿</span>
                            <Input
                                aria-label={`Choice ${index + 1}`}
                                value={choice}
                                disabled={disabled || defaults.includes(choice)}
                                maxLength={50}
                                onChange={(e) => {
                                    // Reserved sizes already have their own immutable choice.
                                    if (
                                        defaults.some(
                                            (size) =>
                                                size.toLowerCase() ===
                                                e.target.value
                                                    .trim()
                                                    .toLowerCase(),
                                        )
                                    )
                                        return;
                                    onChange({
                                        tshirt_choices: data.tshirt_choices.map(
                                            (value, i) =>
                                                i === index
                                                    ? e.target.value
                                                    : value,
                                        ),
                                    });
                                }}
                            />
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                disabled={disabled || index === 0}
                                aria-label={`Move choice ${index + 1} up`}
                                onClick={() => move(index, index - 1)}
                            >
                                ↑
                            </Button>
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                disabled={
                                    disabled ||
                                    index === data.tshirt_choices.length - 1
                                }
                                aria-label={`Move choice ${index + 1} down`}
                                onClick={() => move(index, index + 1)}
                            >
                                ↓
                            </Button>
                            {!defaults.includes(choice) && (
                                <Button
                                    type="button"
                                    variant="ghost"
                                    disabled={disabled}
                                    onClick={() =>
                                        onChange({
                                            tshirt_choices:
                                                data.tshirt_choices.filter(
                                                    (_, i) => i !== index,
                                                ),
                                        })
                                    }
                                >
                                    Remove
                                </Button>
                            )}
                        </div>
                    ))}
                    <InputError message={errors.tshirt_choices} />
                    {!disabled && (
                        <Button
                            type="button"
                            variant="outline"
                            disabled={data.tshirt_choices.length >= 6}
                            onClick={() =>
                                onChange({
                                    tshirt_choices: [
                                        ...data.tshirt_choices,
                                        '',
                                    ],
                                })
                            }
                        >
                            Add choice ({data.tshirt_choices.length}/6)
                        </Button>
                    )}
                    <Label htmlFor="tshirt-photos">
                        Photos (optional, up to three; 25 MB total)
                    </Label>
                    {!disabled && (
                        <Input
                            id="tshirt-photos"
                            type="file"
                            multiple
                            accept="image/jpeg,image/png"
                            onChange={(e) => {
                                onChange({
                                    tshirt_photos: [
                                        ...data.tshirt_photos,
                                        ...Array.from(e.target.files ?? []),
                                    ],
                                });
                                e.target.value = '';
                            }}
                        />
                    )}
                    <TshirtPhotoGallery
                        photos={[
                            ...keptPhotos.map((photo) => photo.url),
                            ...previews,
                        ]}
                    />
                    {!disabled && (
                        <div className="flex flex-wrap gap-2">
                            {keptPhotos.map((photo, index) => (
                                <Button
                                    type="button"
                                    key={photo.path}
                                    variant="ghost"
                                    onClick={() =>
                                        onChange({
                                            tshirt_existing_photos:
                                                data.tshirt_existing_photos.filter(
                                                    (path) =>
                                                        path !== photo.path,
                                                ),
                                        })
                                    }
                                >
                                    Remove saved photo {index + 1}
                                </Button>
                            ))}
                            {data.tshirt_photos.map((file, index) => (
                                <Button
                                    type="button"
                                    key={index}
                                    variant="ghost"
                                    onClick={() =>
                                        onChange({
                                            tshirt_photos:
                                                data.tshirt_photos.filter(
                                                    (_, i) => i !== index,
                                                ),
                                        })
                                    }
                                >
                                    Remove {file.name}
                                </Button>
                            ))}
                        </div>
                    )}
                    {Object.entries(errors)
                        .filter(
                            ([key]) =>
                                key.startsWith('tshirt_photos') ||
                                key.startsWith('tshirt_existing_photos') ||
                                key.startsWith('tshirt_choices.'),
                        )
                        .map(([key, message]) => (
                            <InputError key={key} message={message} />
                        ))}
                </>
            )}
        </section>
    );
}
