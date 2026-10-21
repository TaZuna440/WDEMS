import { useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

export default function TshirtPhotoGallery({ photos }: { photos: string[] }) {
    const [index, setIndex] = useState<number | null>(null);
    if (!photos.length) return null;
    const move = (delta: number) =>
        setIndex(
            (current) =>
                ((current ?? 0) + delta + photos.length) % photos.length,
        );
    return (
        <>
            <div className="flex flex-wrap gap-3">
                {photos.map((url, i) => (
                    <button
                        key={url}
                        type="button"
                        onClick={() => setIndex(i)}
                        aria-label={`Expand T-shirt photo ${i + 1}`}
                    >
                        <img
                            src={url}
                            alt={`T-shirt option ${i + 1}`}
                            className="border-border h-28 w-28 rounded-lg border object-contain"
                            loading="lazy"
                        />
                    </button>
                ))}
            </div>
            <Dialog
                open={index !== null}
                onOpenChange={(open) => {
                    if (!open) setIndex(null);
                }}
            >
                <DialogContent
                    className="sm:max-w-3xl"
                    onKeyDown={(event) => {
                        if (event.key === 'ArrowLeft') move(-1);
                        if (event.key === 'ArrowRight') move(1);
                    }}
                >
                    <DialogHeader>
                        <DialogTitle>
                            T-shirt photo {(index ?? 0) + 1} of {photos.length}
                        </DialogTitle>
                    </DialogHeader>
                    {index !== null && (
                        <img
                            src={photos[index]}
                            alt={`T-shirt option ${index + 1}`}
                            className="max-h-[65vh] w-full object-contain"
                        />
                    )}
                    {photos.length > 1 && (
                        <div className="flex justify-between">
                            <Button variant="outline" onClick={() => move(-1)}>
                                Previous
                            </Button>
                            <Button variant="outline" onClick={() => move(1)}>
                                Next
                            </Button>
                        </div>
                    )}
                </DialogContent>
            </Dialog>
        </>
    );
}
