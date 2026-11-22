import { useEffect, useRef, useState } from 'react';
import { Link } from 'react-router-dom';
import { formatHostDate } from '../../services/hostBookingService';
import { getHostEligibleBookings, parseInvoiceError } from '../../services/invoiceService';
import { Button } from '../ui/button';

export default function EligibleBookingPicker({ captureProfileAuth, isProfileAuthCurrent }) {
    const [searchInput, setSearchInput] = useState('');
    const [query, setQuery] = useState({ search: '', page: 1 });
    const [view, setView] = useState({ loading: true });
    const pending = useRef(null);

    useEffect(() => {
        const auth = captureProfileAuth();
        if (!isProfileAuthCurrent(auth)) return;
        const controller = new AbortController();
        pending.current = controller;
        const isCurrent = () => !controller.signal.aborted && isProfileAuthCurrent(auth);

        getHostEligibleBookings(query, { signal: controller.signal }).then(
            (result) => { if (isCurrent()) setView({ result }); },
            (error) => { if (isCurrent()) setView({ error: parseInvoiceError(error).message }); },
        );
        return () => {
            controller.abort();
            if (pending.current === controller) pending.current = null;
        };
    }, [query, captureProfileAuth, isProfileAuthCurrent]);

    function load(search, page) {
        pending.current?.abort();
        setView({ loading: true });
        setQuery({ search, page });
    }

    const { loading, result, error } = view;
    const page = result?.meta.current_page;
    const lastPage = result?.meta.last_page;

    return (
        <div className="mx-auto max-w-[960px] space-y-5 text-left">
            <Link to="/host/invoices" className="text-sm font-medium text-primary hover:underline">← Quay lại danh sách hóa đơn</Link>
            <h1 className="font-display text-3xl font-bold text-primary">Lập hóa đơn</h1>
            <section className="invoice-card space-y-4" aria-labelledby="eligible-bookings-title">
                <h2 id="eligible-bookings-title" className="font-display text-xl font-bold text-primary">Chọn đơn đặt phòng</h2>
                <form className="space-y-2" onSubmit={(event) => {
                    event.preventDefault();
                    load(searchInput.trim(), 1);
                }}>
                    <label htmlFor="eligible-booking-search" className="block text-sm font-semibold text-ink">Mã đặt phòng hoặc tên khách</label>
                    <div className="flex flex-col gap-2 sm:flex-row">
                        <input id="eligible-booking-search" type="search" value={searchInput} maxLength={255}
                            onChange={(event) => setSearchInput(event.target.value)}
                            className="min-w-0 flex-1 rounded-lg border border-border px-3 py-2 text-sm text-ink" />
                        <Button type="submit">Tìm kiếm</Button>
                        {query.search && <Button type="button" variant="outline" onClick={() => {
                            setSearchInput('');
                            load('', 1);
                        }}>Xóa tìm kiếm</Button>}
                    </div>
                </form>

                {loading && <p role="status" className="text-sm text-muted-foreground">Đang tải đơn đặt phòng…</p>}
                {error && <div className="space-y-2">
                    <p role="alert" className="invoice-alert error">{error}</p>
                    <Button type="button" variant="outline" onClick={() => load(query.search, query.page)}>Thử lại</Button>
                </div>}
                {result && (
                    <>
                        {result.data.length === 0 ? (
                            <p role="status" className="rounded-lg bg-surface p-4 text-sm text-muted-foreground">
                                {query.search ? 'Không tìm thấy đơn đủ điều kiện khớp với từ khóa.' : 'Không có đơn đặt phòng đủ điều kiện trên trang này.'}
                            </p>
                        ) : (
                            <ul className="divide-y divide-border/60">
                                {result.data.map((booking) => (
                                    <li key={booking.booking_id} className="flex flex-col gap-3 py-4 sm:flex-row sm:items-start sm:justify-between">
                                        <div className="min-w-0 space-y-1">
                                            <p className="break-words font-semibold text-primary">{booking.booking_code}</p>
                                            <p className="break-words text-sm text-ink">{booking.customer_name}</p>
                                            <p className="break-words text-sm text-muted-foreground">{booking.property_name} · {booking.room_name}</p>
                                            <p className="text-sm text-muted-foreground">{formatHostDate(booking.check_in_date)} – {formatHostDate(booking.check_out_date)}</p>
                                        </div>
                                        <Button asChild className="shrink-0 self-start">
                                            <Link to={`/host/bookings/${booking.booking_id}/create-invoice`} state={{ fromEligibleBookings: true }}
                                                aria-label={`Chọn đơn ${booking.booking_code}`}>Chọn đơn</Link>
                                        </Button>
                                    </li>
                                ))}
                            </ul>
                        )}
                        {(lastPage > 1 || page > 1) && (
                            <nav className="flex flex-wrap items-center gap-2" aria-label="Phân trang đơn đặt phòng">
                                <Button type="button" variant="outline" disabled={page <= 1}
                                    onClick={() => load(query.search, Math.min(page - 1, lastPage))}>Trang trước</Button>
                                <span className="text-sm text-muted-foreground">Trang {page} / {lastPage}</span>
                                <Button type="button" variant="outline" disabled={page >= lastPage}
                                    onClick={() => load(query.search, page + 1)}>Trang sau</Button>
                            </nav>
                        )}
                    </>
                )}
            </section>
        </div>
    );
}
