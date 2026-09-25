import {
    closestCenter,
    DndContext,
    type DragEndEvent,
    PointerSensor,
    useSensor,
    useSensors,
} from '@dnd-kit/core';
import {
    arrayMove,
    SortableContext,
    useSortable,
    verticalListSortingStrategy,
} from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import { GripVertical } from 'lucide-react';
import type { CSSProperties, ReactNode } from 'react';

type SortableItemProps = {
    id: string;
    disabled?: boolean;
    children: (handle: ReactNode) => ReactNode;
};

function SortableItem({ id, disabled = false, children }: SortableItemProps) {
    const {
        attributes,
        listeners,
        setNodeRef,
        transform,
        transition,
        isDragging,
    } = useSortable({ id, disabled });

    const style: CSSProperties = {
        transform: CSS.Transform.toString(transform),
        transition,
        opacity: isDragging ? 0.5 : 1,
        zIndex: isDragging ? 10 : undefined,
    };

    const handle = (
        <button
            type="button"
            {...attributes}
            {...listeners}
            disabled={disabled}
            aria-label="Drag to reorder"
            title={disabled ? 'Reordering is locked' : 'Drag to reorder'}
            className="mt-1 flex h-8 w-6 shrink-0 cursor-grab items-center justify-center rounded text-muted-foreground transition-colors hover:bg-white/5 hover:text-foreground active:cursor-grabbing disabled:cursor-not-allowed disabled:opacity-40"
        >
            <GripVertical className="h-4 w-4" />
        </button>
    );

    return (
        <div ref={setNodeRef} style={style}>
            {children(handle)}
        </div>
    );
}

type Props<T extends { id: string }> = {
    items: T[];
    onReorder: (items: T[]) => void;
    disabled?: boolean;
    children: (item: T, index: number, handle: ReactNode) => ReactNode;
};

export default function SortableFieldList<T extends { id: string }>({
    items,
    onReorder,
    disabled = false,
    children,
}: Props<T>) {
    const sensors = useSensors(
        useSensor(PointerSensor, {
            activationConstraint: { distance: 6 },
        }),
    );

    const handleDragEnd = (event: DragEndEvent) => {
        const { active, over } = event;
        if (!over || active.id === over.id) return;

        const oldIndex = items.findIndex((item) => item.id === active.id);
        const newIndex = items.findIndex((item) => item.id === over.id);
        if (oldIndex === -1 || newIndex === -1) return;

        onReorder(arrayMove(items, oldIndex, newIndex));
    };

    return (
        <DndContext
            sensors={sensors}
            collisionDetection={closestCenter}
            onDragEnd={handleDragEnd}
        >
            <SortableContext
                items={items.map((item) => item.id)}
                strategy={verticalListSortingStrategy}
            >
                <div className="flex flex-col gap-2">
                    {items.map((item, index) => (
                        <SortableItem
                            key={item.id}
                            id={item.id}
                            disabled={disabled}
                        >
                            {(handle) => children(item, index, handle)}
                        </SortableItem>
                    ))}
                </div>
            </SortableContext>
        </DndContext>
    );
}
