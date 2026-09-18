import { useEffect, useState } from 'react';
import { Plus, X } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

type QuestionType =
    | 'SHORT_ANSWER'
    | 'PARAGRAPH'
    | 'MULTIPLE_CHOICE'
    | 'CHECKBOX'
    | 'DROP_DOWN';

export type QuestionDraft = {
    title: string;
    type: string;
    required: boolean;
    options: string[];
};

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    mode: 'add' | 'edit';
    initialValue?: QuestionDraft | null;
    onSave: (data: QuestionDraft) => void;
};

const TYPE_OPTIONS: { value: QuestionType; label: string }[] = [
    { value: 'SHORT_ANSWER', label: 'Short answer' },
    { value: 'PARAGRAPH', label: 'Paragraph' },
    { value: 'MULTIPLE_CHOICE', label: 'Multiple choice' },
    { value: 'CHECKBOX', label: 'Checkbox' },
    { value: 'DROP_DOWN', label: 'Dropdown' },
];

const CHOICE_TYPES = ['MULTIPLE_CHOICE', 'CHECKBOX', 'DROP_DOWN'];

export default function QuestionEditorDialog({
    open,
    onOpenChange,
    mode,
    initialValue = null,
    onSave,
}: Props) {
    const [title, setTitle] = useState('');
    const [type, setType] = useState<QuestionType>('SHORT_ANSWER');
    const [required, setRequired] = useState(true);
    const [options, setOptions] = useState<string[]>(['', '']);
    const [error, setError] = useState<string | null>(null);

    useEffect(() => {
        if (!open) return;

        if (initialValue) {
            setTitle(initialValue.title);
            setType((initialValue.type as QuestionType) ?? 'SHORT_ANSWER');
            setRequired(initialValue.required);
            setOptions(
                initialValue.options.length > 0
                    ? [...initialValue.options]
                    : ['', ''],
            );
        } else {
            setTitle('');
            setType('SHORT_ANSWER');
            setRequired(true);
            setOptions(['', '']);
        }

        setError(null);
    }, [open, initialValue]);

    const isChoice = CHOICE_TYPES.includes(type);

    const submit = () => {
        const trimmedTitle = title.trim();

        if (trimmedTitle === '') {
            setError('Please enter a question title.');
            return;
        }

        const cleanedOptions = isChoice
            ? options.map((o) => o.trim()).filter((o) => o !== '')
            : [];

        if (isChoice && cleanedOptions.length < 2) {
            setError('Choice questions require at least two options.');
            return;
        }

        onSave({
            title: trimmedTitle,
            type,
            required,
            options: cleanedOptions,
        });

        onOpenChange(false);
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>
                        {mode === 'edit' ? 'Edit Question' : 'Add Question'}
                    </DialogTitle>
                    <DialogDescription>
                        Changes are staged locally. Click Save Changes on the page to push them to Google.
                    </DialogDescription>
                </DialogHeader>

                <div className="flex flex-col gap-4">
                    <div className="flex flex-col gap-2">
                        <Label htmlFor="q-title">Question title</Label>
                        <Input
                            id="q-title"
                            value={title}
                            onChange={(e) => setTitle(e.target.value)}
                            placeholder="e.g. T-shirt size"
                            maxLength={255}
                        />
                    </div>

                    <div className="flex flex-col gap-2">
                        <Label htmlFor="q-type">Type</Label>
                        <Select
                            value={type}
                            onValueChange={(v) => setType(v as QuestionType)}
                        >
                            <SelectTrigger id="q-type">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {TYPE_OPTIONS.map((opt) => (
                                    <SelectItem key={opt.value} value={opt.value}>
                                        {opt.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>

                    <div className="flex items-center gap-2">
                        <Checkbox
                            id="q-required"
                            checked={required}
                            onCheckedChange={(v) => setRequired(v === true)}
                        />
                        <Label htmlFor="q-required" className="cursor-pointer text-sm font-normal">
                            Required
                        </Label>
                    </div>

                    {isChoice && (
                        <div className="flex flex-col gap-2">
                            <Label>Options</Label>
                            <div className="flex flex-col gap-2">
                                {options.map((opt, i) => (
                                    <div key={i} className="flex items-center gap-2">
                                        <Input
                                            value={opt}
                                            onChange={(e) =>
                                                setOptions(options.map((v, idx) =>
                                                    idx === i ? e.target.value : v,
                                                ))
                                            }
                                            placeholder={`Option ${i + 1}`}
                                            maxLength={255}
                                        />
                                        {options.length > 2 && (
                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="icon"
                                                className="h-9 w-9 shrink-0"
                                                onClick={() =>
                                                    setOptions(options.filter((_, idx) => idx !== i))
                                                }
                                            >
                                                <X className="h-4 w-4" />
                                            </Button>
                                        )}
                                    </div>
                                ))}
                            </div>
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                className="mt-1 w-fit"
                                onClick={() => setOptions([...options, ''])}
                            >
                                <Plus className="mr-2 h-4 w-4" />
                                Add option
                            </Button>
                        </div>
                    )}

                    {error && <p className="text-sm text-destructive">{error}</p>}
                </div>

                <DialogFooter className="gap-2">
                    <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
                        Cancel
                    </Button>
                    <Button type="button" onClick={submit}>
                        {mode === 'edit' ? 'Update' : 'Add'}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
