import { useRef } from 'react';
import * as Dialog from '@radix-ui/react-dialog';
import { formatVnd } from '../../services/invoiceService';

export default function InvoicePaymentDialog({ open, pending, invoice, onClose, onConfirm, triggerRef, returnFocusRef }) {
    const cancelRef = useRef(null);

    return (
        <Dialog.Root open={open} onOpenChange={(next) => { if (!next && !pending) onClose(); }}>
            <Dialog.Portal>
                <Dialog.Overlay className="invoice-payment-overlay" />
                <Dialog.Content className="invoice-payment-dialog" aria-busy={pending}
                    onOpenAutoFocus={(event) => { event.preventDefault(); cancelRef.current?.focus(); }}
                    onCloseAutoFocus={(event) => { event.preventDefault(); (triggerRef.current ?? returnFocusRef.current)?.focus(); }}
                    onEscapeKeyDown={(event) => { if (pending) event.preventDefault(); }}
                    onInteractOutside={(event) => { if (pending) event.preventDefault(); }}>
                    <Dialog.Title>Xác nhận đã nhận đủ tiền</Dialog.Title>
                    <Dialog.Description>
                        Bạn xác nhận đã nhận đủ {formatVnd(invoice.total_amount)} cho hóa đơn {invoice.invoice_code}?
                        Thao tác này ghi nhận thanh toán trên StayHub, không thực hiện chuyển tiền.
                    </Dialog.Description>
                    <div className="invoice-payment-actions">
                        <button ref={cancelRef} type="button" onClick={onClose} disabled={pending}>Quay lại</button>
                        <button type="button" className="invoice-payment-primary" onClick={onConfirm} disabled={pending}>
                            {pending ? 'Đang xác nhận…' : 'Xác nhận đã nhận đủ tiền'}
                        </button>
                    </div>
                </Dialog.Content>
            </Dialog.Portal>
        </Dialog.Root>
    );
}
