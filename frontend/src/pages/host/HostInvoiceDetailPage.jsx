import { useState, useEffect, useRef, useCallback } from 'react';
import { useParams, Link } from 'react-router-dom';
import { getHostInvoiceDetail, updateHostInvoicePaymentStatus, parseInvoiceError } from '../../services/invoiceService';
import InvoiceDetail from '../../components/invoice/InvoiceDetail';
import InvoicePaymentDialog from '../../components/invoice/InvoicePaymentDialog';
import { useAuth } from '../../hooks/useAuth';
import '../../components/invoice/Invoice.css';

const csrfMessage = 'Phiên bảo mật đã hết hạn. Nhấn “Kiểm tra lại hóa đơn” để tải trạng thái mới. Nếu không khôi phục được phiên, hãy tải lại trang hoặc đăng nhập lại trước khi xác nhận.';

const uncertainMessage = 'Chưa xác định được kết quả. Vui lòng kiểm tra lại hóa đơn.';

function HostInvoiceDetail({ invoiceId, user, captureAuth, isAuthCurrent }) {
    const [view, setView] = useState({ invoice: null, loading: true, error: null, paymentError: null,
        fields: {}, notice: null, operation: 'idle', dialog: false });
    const gate = useRef({ disposed: false, serial: 0, controller: null, locked: false });
    const triggerRef = useRef(null);
    const paymentPanelRef = useRef(null);
    const displayedAuth = useRef(null);

    const readInvoice = useCallback((recovery = false, preservedError = null) => {
        if (!user) return;
        const scope = gate.current;
        const auth = captureAuth();
        if (scope.disposed || !isAuthCurrent(auth)) return;
        scope.controller?.abort();
        const serial = ++scope.serial;
        const controller = new AbortController();
        scope.controller = controller;
        scope.locked = true;
        const current = () => !scope.disposed && scope.serial === serial && isAuthCurrent(auth);
        return getHostInvoiceDetail(invoiceId, { signal: controller.signal }).then((result) => {
            if (!current()) return;
            displayedAuth.current = auth;
            scope.locked = false;
            setView((previous) => ({ ...previous, invoice: result.data, loading: false, operation: 'idle',
                fields: {}, paymentError: preservedError,
                notice: recovery && result.data.payment_status === 'PAID'
                    ? 'Đã xác nhận trạng thái thanh toán.' : recovery ? 'Đã tải lại trạng thái hóa đơn.' : null }));
        }, (error) => {
            if (!current()) return;
            const parsed = parseInvoiceError(error);
            // A failed recovery GET never proves the earlier PATCH was rolled back.
            scope.locked = recovery;
            setView((previous) => ({ ...previous, loading: false, dialog: false,
                invoice: parsed.status === 404 ? null : previous.invoice,
                error: !recovery || parsed.status === 404 ? parsed.message : null,
                paymentError: recovery ? `${uncertainMessage} ${parsed.message}` : null,
                operation: recovery ? 'unknown' : 'idle' }));
        });
    }, [invoiceId, user, captureAuth, isAuthCurrent]);

    useEffect(() => {
        const scope = gate.current;
        scope.disposed = false;
        void readInvoice();
        return () => {
            scope.disposed = true;
            ++scope.serial;
            scope.controller?.abort();
        };
    }, [readInvoice]);

    function refreshInvoice(recovery = false, preservedError = null) {
        setView((previous) => ({ ...previous, loading: !recovery, error: null, dialog: false,
            operation: recovery ? 'checking' : 'idle', ...(recovery ? {} : { invoice: null, notice: null, paymentError: null, fields: {} }) }));
        return readInvoice(recovery, preservedError);
    }

    async function confirmPayment() {
        const scope = gate.current;
        if (scope.disposed || scope.locked || view.operation !== 'idle' || !view.dialog
            || view.invoice?.payment_status !== 'UNPAID' || !isAuthCurrent(displayedAuth.current ?? {})) return;
        scope.locked = true; // Synchronous guard, before React renders the disabled button.
        const serial = ++scope.serial;
        const auth = captureAuth();
        const controller = new AbortController();
        scope.controller = controller;
        const current = () => !scope.disposed && scope.serial === serial && isAuthCurrent(auth);
        setView((previous) => ({ ...previous, operation: 'submitting', notice: null, paymentError: null, fields: {} }));
        try {
            const result = await updateHostInvoicePaymentStatus(invoiceId, { signal: controller.signal });
            if (!current()) return;
            scope.locked = false;
            setView((previous) => ({ ...previous, invoice: result.data, operation: 'idle', dialog: false,
                notice: 'Đã xác nhận trạng thái thanh toán.' }));
        } catch (error) {
            if (!current()) return;
            const parsed = parseInvoiceError(error);
            const unknown = !parsed.status || parsed.status >= 500;
            const recover = unknown || parsed.status === 409;
            scope.locked = recover || parsed.status === 419;
            setView((previous) => ({ ...previous, dialog: false, operation: scope.locked ? 'unknown' : 'idle',
                invoice: parsed.status === 404 ? null : previous.invoice,
                error: parsed.status === 404 ? parsed.message : null,
                paymentError: parsed.status === 419 ? csrfMessage : unknown ? uncertainMessage : parsed.message, fields: parsed.fields ?? {} }));
            if (recover) await refreshInvoice(true, unknown ? null : parsed.message);
        }
    }

    const pending = view.operation === 'submitting' || view.operation === 'checking';
    return (
        <div className="invoice-host-detail">
            <Link to="/host/invoices" className="invoice-payment-back">← Quay lại danh sách hóa đơn</Link>
            {view.loading && <p role="status">Đang tải thông tin chi tiết hóa đơn…</p>}
            {view.error && <div className="invoice-alert error" role="alert">{view.error}</div>}
            {!view.loading && !view.invoice && view.error && (
                <button type="button" onClick={() => refreshInvoice()}>Tải lại hóa đơn</button>
            )}
            {view.notice && <div className="invoice-alert" role="status">{view.notice}</div>}
            {view.paymentError && <div className="invoice-alert error" role="alert">
                <div>{view.paymentError}</div>
                {Object.entries(view.fields).map(([field, messages]) => <div key={field}>{messages.join(' ')}</div>)}
            </div>}
            {!view.loading && view.invoice && <>
                <InvoiceDetail invoice={view.invoice} />
                <section ref={paymentPanelRef} tabIndex={-1} className="invoice-payment-panel" aria-label="Xác nhận thanh toán" aria-busy={pending}>
                    {view.invoice.payment_status === 'UNPAID' && <button
                        ref={triggerRef} type="button" className="invoice-payment-primary"
                        disabled={view.operation !== 'idle'}
                        onClick={() => {
                            if (!gate.current.locked && isAuthCurrent(displayedAuth.current ?? {})) {
                                setView((previous) => ({ ...previous, dialog: true }));
                            }
                        }}
                    >Đánh dấu đã thanh toán</button>}
                    {view.invoice.payment_status === 'PAID' && <p>Hóa đơn đã được thanh toán.</p>}
                    {view.invoice.payment_status === 'REFUNDED' && <p>Hóa đơn đã hoàn tiền, không thể xác nhận thanh toán.</p>}
                    {pending && <p role="status">{view.operation === 'checking' ? 'Đang kiểm tra lại trạng thái…' : 'Đang xác nhận thanh toán…'}</p>}
                    {view.operation === 'unknown' && <button type="button" onClick={() => refreshInvoice(true)}>Kiểm tra lại hóa đơn</button>}
                </section>
                <InvoicePaymentDialog open={view.dialog} pending={pending} invoice={view.invoice}
                    triggerRef={triggerRef} returnFocusRef={paymentPanelRef} onConfirm={confirmPayment}
                    onClose={() => { if (!gate.current.locked) setView((previous) => ({ ...previous, dialog: false })); }} />
            </>}
        </div>
    );
}

export default function HostInvoiceDetailPage() {
    const { invoiceId } = useParams();
    const { user, captureProfileAuth, isProfileAuthCurrent } = useAuth();
    return <HostInvoiceDetail key={`${invoiceId}:${user?.id}`} invoiceId={invoiceId} user={user}
        captureAuth={captureProfileAuth} isAuthCurrent={isProfileAuthCurrent} />;
}
