import { useRef, useEffect } from 'react';

/**
 * Custom hook for drag-to-scroll functionality
 * Allows users to click and drag to scroll horizontally
 * 
 * @returns {Object} ref - Reference to attach to the scrollable element
 */
export default function useDragScroll() {
    const scrollRef = useRef(null);
    const isDragging = useRef(false);
    const startX = useRef(0);
    const scrollLeft = useRef(0);

    useEffect(() => {
        const element = scrollRef.current;
        if (!element) return;

        const handleMouseDown = (e) => {
            // Only enable drag on desktop (not on touch devices)
            if (e.type === 'touchstart') return;

            isDragging.current = true;
            startX.current = e.pageX - element.offsetLeft;
            scrollLeft.current = element.scrollLeft;

            // Change cursor to grabbing
            element.style.cursor = 'grabbing';
            element.style.userSelect = 'none';
        };

        const handleMouseMove = (e) => {
            if (!isDragging.current) return;

            e.preventDefault();
            const x = e.pageX - element.offsetLeft;
            const walk = (x - startX.current) * 2; // Scroll speed multiplier
            element.scrollLeft = scrollLeft.current - walk;
        };

        const handleMouseUp = () => {
            isDragging.current = false;
            element.style.cursor = 'grab';
            element.style.userSelect = 'auto';
        };

        const handleMouseLeave = () => {
            if (isDragging.current) {
                isDragging.current = false;
                element.style.cursor = 'grab';
                element.style.userSelect = 'auto';
            }
        };

        // Set initial cursor
        element.style.cursor = 'grab';

        // Add event listeners
        element.addEventListener('mousedown', handleMouseDown);
        element.addEventListener('mousemove', handleMouseMove);
        element.addEventListener('mouseup', handleMouseUp);
        element.addEventListener('mouseleave', handleMouseLeave);

        // Cleanup
        return () => {
            element.removeEventListener('mousedown', handleMouseDown);
            element.removeEventListener('mousemove', handleMouseMove);
            element.removeEventListener('mouseup', handleMouseUp);
            element.removeEventListener('mouseleave', handleMouseLeave);
        };
    }, []);

    return scrollRef;
}
