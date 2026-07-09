import { useEffect, useRef, useState } from 'react';

/**
 * Horizontal scroll container with click-and-drag ("grab") panning — lets a
 * wide table be dragged left/right with the mouse. Falls back to normal
 * overflow scrolling; the grab cursor only appears when content overflows.
 */
export default function DragScroll({ children, className = '' }) {
    const ref = useRef(null);
    const drag = useRef({ down: false, startX: 0, scrollLeft: 0 });
    const [scrollable, setScrollable] = useState(false);

    useEffect(() => {
        const el = ref.current;
        if (!el) return undefined;
        const check = () => setScrollable(el.scrollWidth > el.clientWidth + 1);
        check();
        const ro = new ResizeObserver(check);
        ro.observe(el);
        return () => ro.disconnect();
    }, [children]);

    const onDown = (e) => {
        const el = ref.current;
        if (!el || !scrollable) return;
        drag.current = { down: true, startX: e.pageX - el.offsetLeft, scrollLeft: el.scrollLeft };
    };
    const onMove = (e) => {
        const el = ref.current;
        if (!el || !drag.current.down) return;
        e.preventDefault();
        const x = e.pageX - el.offsetLeft;
        el.scrollLeft = drag.current.scrollLeft - (x - drag.current.startX);
    };
    const end = () => { drag.current.down = false; };

    return (
        <div
            ref={ref}
            onMouseDown={onDown}
            onMouseMove={onMove}
            onMouseUp={end}
            onMouseLeave={end}
            className={`overflow-x-auto ${scrollable ? 'cursor-grab active:cursor-grabbing select-none' : ''} ${className}`}
        >
            {children}
        </div>
    );
}
