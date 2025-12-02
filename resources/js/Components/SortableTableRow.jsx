import React from 'react';
import { useSortable } from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';

export function SortableTableRow({ id, children, className }) {
    const {
        attributes,
        listeners,
        setNodeRef,
        transform,
        transition,
        isDragging,
    } = useSortable({ id });

    const style = {
        transform: CSS.Transform.toString(transform),
        transition,
        zIndex: isDragging ? 1 : 'auto',
        position: 'relative',
    };

    return (
        <tr
            ref={setNodeRef}
            style={style}
            className={`${className} ${isDragging ? 'bg-slate-50 shadow-lg opacity-50' : ''}`}
        >
            {children(attributes, listeners)}
        </tr>
    );
}
