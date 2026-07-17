import { Loader2 } from 'lucide-react';
import Modal from '@/Components/Modal';
import SecondaryButton from '@/Components/SecondaryButton';
import DangerButton from '@/Components/DangerButton';

/**
 * Small confirm wrapper around the shared headlessui Modal (see
 * resources/js/Components/Modal.jsx) — used for destructive row actions
 * (e.g. Borang 14 revert) that must not fire without an explicit second click.
 */
export default function ConfirmDialog({
    open,
    title,
    children,
    confirmLabel = 'Sahkan',
    cancelLabel = 'Batal',
    busy = false,
    onClose,
    onConfirm,
}) {
    return (
        <Modal show={open} onClose={() => { if (!busy) onClose?.(); }} maxWidth="md">
            <div className="p-6">
                <h2 className="text-lg font-medium text-slate-900">{title}</h2>
                <div className="mt-2 text-sm text-slate-600">{children}</div>
                <div className="mt-6 flex justify-end space-x-3">
                    <SecondaryButton onClick={onClose} disabled={busy}>{cancelLabel}</SecondaryButton>
                    <DangerButton onClick={onConfirm} disabled={busy} className="inline-flex items-center gap-1.5">
                        {busy && <Loader2 className="h-3.5 w-3.5 animate-spin" />}
                        {confirmLabel}
                    </DangerButton>
                </div>
            </div>
        </Modal>
    );
}
