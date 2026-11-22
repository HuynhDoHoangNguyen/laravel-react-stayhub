import { useEffect, useRef, useState } from 'react';
import { Link, useLocation, useParams } from 'react-router-dom';
import CreateInvoiceButton from '../../components/invoice/CreateInvoiceButton';
import EligibleBookingPicker from '../../components/invoice/EligibleBookingPicker';
import InvoiceDetail from '../../components/invoice/InvoiceDetail';
import { Button } from '../../components/ui/button';
import { useAuth } from '../../hooks/useAuth';
import { formatHostDate } from '../../services/hostBookingService';
import { createHostInvoice, getHostInvoiceBookingContext, parseInvoiceError } from '../../services/invoiceService';
import '../../components/invoice/Invoice.css';

function BookingSummary({ context }) {
    const fields = [
        ['Mã đặt phòng', context.booking_code],
        ['Khách hàng', context.customer_name],
        ['Cơ sở lưu trú', context.property_name],
        ['Phòng', context.room_name],
        ['Nhận phòng', formatHostDate(context.check_in_date)],
        ['Trả phòng', formatHostDate(context.check_out_date)],
    ];

    return (
        <dl className="mt-4 grid gap-4 sm:grid-cols-2">
            {fields.map(([label, value]) => (
                <div key={label} className="min-w-0">
                    <dt className="text-sm text-muted-foreground">{label}</dt>
                    <dd className="break-words font-semibold text-ink">{value}</dd>
                </div>
            ))}
        </dl>
    );
}

function InvoiceCreation({ bookingId, fromEligibleBookings, captureProfileAuth, isProfileAuthCurrent }) {
    const [readAttempt, setReadAttempt] = useState(0);
    const [view, setView] = useState({ phase: 'loading' });
    const pendingPost = useRef(null);
    const successRef = useRef(null);

    useEffect(() => () => pendingPost.current?.abort(), []);

    useEffect(() => {
        const controller = new AbortController();
        const auth = captureProfileAuth();
        if (!isProfileAuthCurrent(auth)) return;

        const current = () => !controller.signal.aborted && isProfileAuthCurrent(auth);
        getHostInvoiceBookingContext(bookingId, { signal: controller.signal }).then(
            (context) => { if (current()) setView({ phase: 'ready', context }); },
            (error) => {
                if (current()) setView({ phase: 'read-error', error: parseInvoiceError(error).message });
            },
        );
        return () => controller.abort();
    }, [bookingId, readAttempt, captureProfileAuth, isProfileAuthCurrent]);

    useEffect(() => {
        if (view.phase === 'success') successRef.current?.focus();
    }, [view.phase]);

    function checkBooking() {
        setView({ phase: 'loading' });
        setReadAttempt((previous) => previous + 1);
    }

    const { phase, context, error, createdInvoice } = view;
    const canCreate = context?.can_create === true;

    async function submitInvoice() {
        if (pendingPost.current || phase !== 'ready' || !canCreate) return;
        const auth = captureProfileAuth();
        if (!isProfileAuthCurrent(auth)) return;

        const controller = new AbortController();
        pendingPost.current = controller;
        const current = () => !controller.signal.aborted && isProfileAuthCurrent(auth);
        setView({ phase: 'submitting', context });

        try {
            const result = await createHostInvoice(bookingId, { signal: controller.signal });
            if (!current()) return;
            setView({ phase: 'success', createdInvoice: result.data });
        } catch (requestError) {
            if (!current()) return;
            const parsed = parseInvoiceError(requestError);
            if (parsed.status === 409) {
                checkBooking();
                return;
            }

            if (!parsed.status || parsed.status >= 500 || parsed.status === 419) {
                setView({
                    phase: 'recovery',
                    context,
                    error: parsed.status === 419
                        ? 'Phiên bảo mật đã hết hạn. Hãy kiểm tra lại đơn đặt phòng trước khi lập hóa đơn.'
                        : 'Chưa xác định được kết quả lập hóa đơn. Hãy kiểm tra lại trước khi gửi lần nữa.',
                });
                return;
            }

            const blocked = [401, 403, 404].includes(parsed.status);
            setView({
                phase: blocked ? 'blocked' : 'ready',
                context: blocked ? null : context,
                error: parsed.message,
            });
        } finally {
            if (pendingPost.current === controller) pendingPost.current = null;
        }
    }

    return (
        <div className="mx-auto max-w-[960px] space-y-5 text-left">
            <div className="flex flex-wrap gap-3">
                <Link to={`/host/bookings/${bookingId}`} className="text-sm font-medium text-primary hover:underline">← Quay lại đơn đặt phòng</Link>
                {fromEligibleBookings && !createdInvoice && <Link to="/host/invoices/create" className="text-sm font-medium text-primary hover:underline">Chọn đơn khác</Link>}
            </div>
            <h1 className="font-display text-3xl font-bold text-primary">Lập hóa đơn</h1>

            {createdInvoice ? (
                <div className="space-y-4">
                    <p ref={successRef} tabIndex={-1} className="invoice-alert success" role="status">Đã lập hóa đơn {createdInvoice.invoice_code}.</p>
                    <InvoiceDetail invoice={createdInvoice} />
                    <div className="flex flex-wrap gap-3">
                        <Button asChild><Link to={`/host/invoices/${createdInvoice.id}`}>Xem hóa đơn vừa lập</Link></Button>
                        <Button type="button" variant="outline" onClick={() => window.print()}>In hóa đơn</Button>
                        {fromEligibleBookings && <Button asChild variant="outline"><Link to="/host/invoices/create">Chọn đơn khác</Link></Button>}
                    </div>
                </div>
            ) : (
                <section className="invoice-card space-y-4" aria-busy={phase === 'loading' || phase === 'submitting'}>
                    <h2 className="font-display text-xl font-bold text-primary">Thông tin đơn đặt phòng</h2>

                    {phase === 'loading' && <p role="status" className="text-sm text-muted-foreground">Đang tải thông tin đơn đặt phòng…</p>}
                    {error && <p role="alert" className="invoice-alert error">{error}</p>}
                    {context && <BookingSummary context={context} />}

                    {phase === 'ready' && context?.invoice && (
                        <div className="space-y-3">
                            <p>Đơn đặt phòng đã có hóa đơn <strong>{context.invoice.invoice_code}</strong>.</p>
                            <Button asChild><Link to={`/host/invoices/${context.invoice.id}`}>Xem hóa đơn</Link></Button>
                        </div>
                    )}
                    {phase === 'ready' && context && !context.invoice && !context.can_create && (
                        <p className="text-sm text-muted-foreground">Có thể lập hóa đơn sau khi đơn đặt phòng hoàn tất.</p>
                    )}
                    {(phase === 'read-error' || phase === 'recovery' || phase === 'blocked') && (
                        <Button type="button" variant="outline" onClick={checkBooking}>Kiểm tra lại</Button>
                    )}
                    {canCreate && (phase === 'ready' || phase === 'submitting') && (
                        <CreateInvoiceButton onClick={submitInvoice} loading={phase === 'submitting'} />
                    )}
                </section>
            )}
        </div>
    );
}

export default function HostInvoiceCreationPage() {
    const { bookingId } = useParams();
    const location = useLocation();
    const { user, captureProfileAuth, isProfileAuthCurrent } = useAuth();
    if (!user) return null;

    if (!bookingId) {
        return <EligibleBookingPicker key={user.id} captureProfileAuth={captureProfileAuth} isProfileAuthCurrent={isProfileAuthCurrent} />;
    }

    return <InvoiceCreation key={`${user.id}:${bookingId}`} bookingId={bookingId}
        fromEligibleBookings={location.state?.fromEligibleBookings === true}
        captureProfileAuth={captureProfileAuth} isProfileAuthCurrent={isProfileAuthCurrent} />;
}
