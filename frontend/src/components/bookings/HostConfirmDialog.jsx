import { useEffect, useRef } from 'react';
import { Card } from '@/components/ui/card';
import { Button } from '@/components/ui/button';

export function HostConfirmDialog({ open, description, confirmLabel, busy = false, onCancel, onConfirm }) {
  const dialogRef = useRef(null);
  const cancelRef = useRef(null);
  const busyRef = useRef(busy);
  const onCancelRef = useRef(onCancel);

  useEffect(() => {
    busyRef.current = busy;
  }, [busy]);

  useEffect(() => {
    onCancelRef.current = onCancel;
  }, [onCancel]);

  useEffect(() => {
    if (!open) return undefined;
    const returnFocusTarget = document.activeElement;
    cancelRef.current?.focus();
    const handleKeyDown = (event) => {
      if (event.key === 'Escape' && !busyRef.current) {
        onCancelRef.current();
        return;
      }
      if (event.key !== 'Tab') return;
      const focusable = dialogRef.current?.querySelectorAll('button:not(:disabled)');
      if (!focusable?.length) return;
      const first = focusable[0];
      const last = focusable[focusable.length - 1];
      if (event.shiftKey && document.activeElement === first) {
        event.preventDefault();
        last.focus();
      } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault();
        first.focus();
      }
    };
    document.addEventListener('keydown', handleKeyDown);
    return () => {
      document.removeEventListener('keydown', handleKeyDown);
      if (returnFocusTarget?.isConnected) returnFocusTarget.focus();
      else document.querySelector('[data-dialog-focus-fallback]')?.focus();
    };
  }, [open]);

  if (!open) return null;

  return (
    <div
      className="fixed inset-0 z-50 grid place-items-center bg-slate-950/50 p-4"
      onMouseDown={(event) => {
        if (event.target === event.currentTarget && !busy) onCancel();
      }}
    >
      <Card
        ref={dialogRef}
        role="dialog"
        aria-modal="true"
        aria-labelledby="host-confirm-title"
        aria-describedby="host-confirm-description"
        className="w-full max-w-md rounded-2xl p-6 shadow-xl"
      >
        <h2 id="host-confirm-title" className="font-display text-xl font-bold text-primary">Xác nhận thao tác</h2>
        <p id="host-confirm-description" className="mt-2 text-sm leading-relaxed text-muted-foreground">{description}</p>
        <div className="mt-6 flex flex-col-reverse justify-end gap-2 sm:flex-row">
          <Button ref={cancelRef} variant="outline" type="button" disabled={busy} onClick={onCancel}>Quay lại</Button>
          <Button type="button" disabled={busy} onClick={onConfirm} className="bg-rose-700 text-white hover:bg-rose-800">
            {busy ? 'Đang xử lý…' : confirmLabel}
          </Button>
        </div>
      </Card>
    </div>
  );
}
