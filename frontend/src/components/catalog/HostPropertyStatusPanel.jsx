import { useEffect, useRef, useState } from 'react';
import { ShieldCheck } from 'lucide-react';
import { hostPropertyService } from '../../services/hostPropertyService';

export default function HostPropertyStatusPanel({ property, onChanged, onReload }) {
    const [confirming, setConfirming] = useState(false);
    const [pending, setPending] = useState(false);
    const [needsReload, setNeedsReload] = useState(false);
    const [error, setError] = useState('');
    const [success, setSuccess] = useState('');
    const active = useRef(true);
    const locked = useRef(false);
    const request = useRef(null);
    const actionButton = useRef(null);
    const confirmButton = useRef(null);
    const button = 'inline-flex min-h-11 items-center justify-center rounded-xl px-4 py-2.5 text-sm font-semibold shadow-sm transition hover:shadow-md focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 motion-reduce:transition-none';
    useEffect(() => {
        if (confirming) confirmButton.current?.focus();
    }, [confirming]);
    useEffect(() => {
        active.current = true;
        return () => { active.current = false; request.current?.abort(); };
    }, []);

    const allowed = ['ACTIVE', 'INACTIVE'].includes(property.status);
    const target = property.status === 'ACTIVE' ? 'INACTIVE' : 'ACTIVE';
    const action = target === 'INACTIVE' ? 'Ngưng hoạt động' : 'Hoạt động trở lại';
    async function submit() {
        if (locked.current || needsReload || !allowed) return;
        locked.current = true;
        setPending(true); setError(''); setSuccess('');
        request.current = new AbortController();
        try {
            const updated = await hostPropertyService.changeStatus(property.id,
                { status: target, expected_status: property.status }, { signal: request.current.signal });
            if (!active.current) return;
            setConfirming(false);
            setSuccess('Cập nhật trạng thái cơ sở lưu trú thành công.');
            onChanged(updated);
            requestAnimationFrame(() => actionButton.current?.focus());
        } catch (failure) {
            if (!active.current) return;
            const status = failure.response?.status || failure.status;
            setNeedsReload(Boolean(failure.outcomeUnknown) || [404, 409].includes(status));
            const messages = { 401: 'Phiên đăng nhập đã hết hạn. Vui lòng đăng nhập lại.',
                403: 'Bạn không có quyền thay đổi trạng thái cơ sở.', 404: 'Không tìm thấy cơ sở lưu trú.',
                409: 'Trạng thái cơ sở đã thay đổi hoặc không cho phép thao tác này. Vui lòng tải lại chi tiết.',
                419: 'Phiên bảo mật đã hết hạn. Vui lòng xác nhận lại.',
                422: 'Thông tin trạng thái không hợp lệ. Vui lòng kiểm tra lại.' };
            setError(failure.outcomeUnknown
                ? 'Chưa xác định được kết quả thay đổi trạng thái. Vui lòng kiểm tra trạng thái thực tế trước khi gửi lại.'
                : messages[status] || 'Không thể thay đổi trạng thái cơ sở. Vui lòng thử lại.');
        } finally {
            if (active.current) { setPending(false); locked.current = false; }
        }
    }

    return <section aria-labelledby="property-status-title" aria-busy={pending} className="space-y-4 rounded-2xl border border-primary/15 bg-card p-5 shadow-sm sm:p-6">
        <div className="flex items-center gap-3"><span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-surface text-primary"><ShieldCheck size={20} aria-hidden="true" /></span>
            <h2 id="property-status-title" className="text-base font-semibold text-primary">Trạng thái hoạt động</h2></div>
        {allowed && <p className="text-sm leading-6 text-muted-foreground">{property.status === 'ACTIVE'
            ? 'Cơ sở đang hoạt động. Bạn có thể tạm ngưng khi cần.'
            : 'Cơ sở đang ngưng hoạt động. Bật lại để cơ sở được hiển thị công khai.'}</p>}
        {!allowed && <p className="rounded-xl bg-surface p-3 text-sm leading-6 text-muted-foreground">{property.status === 'BLOCKED'
            ? 'Cơ sở đã bị khóa. Host không thể tự mở khóa.'
            : 'Cơ sở đang chờ duyệt. Host không thể tự chuyển trạng thái.'}</p>}
        {success && <p role="status" className="rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-sm leading-6 text-emerald-800">{success}</p>}
        {error && <p role="alert" className="rounded-xl border border-red-200 bg-red-50 p-3 text-sm leading-6 text-red-800">{error}</p>}
        {needsReload ? <button type="button" onClick={onReload} className={button + ' w-full border border-border bg-surface text-primary'}>Tải lại chi tiết để kiểm tra trạng thái</button>
            : allowed && <>
                {!confirming ? <button ref={actionButton} type="button" onClick={() => { setConfirming(true); setSuccess(''); setError(''); }}
                    className={button + (target === 'INACTIVE' ? ' w-full border border-border bg-card text-primary hover:bg-surface' : ' w-full bg-primary text-primary-foreground hover:bg-primary/90')}>{action}</button>
                    : <div role="group" aria-label="Xác nhận thay đổi trạng thái" className="space-y-4 rounded-xl border border-border bg-surface/60 p-4">
                        <p className="text-sm leading-6 text-foreground">Bạn xác nhận {action.toLowerCase()} cơ sở này?</p>
                        <div className="flex flex-wrap gap-2">
                            <button ref={confirmButton} type="button" disabled={pending} onClick={submit}
                                className={button + ' flex-1 bg-primary text-primary-foreground hover:bg-primary/90'}>{pending ? 'Đang cập nhật...' : 'Xác nhận'}</button>
                            <button type="button" disabled={pending} onClick={() => {
                                setConfirming(false); setError('');
                                requestAnimationFrame(() => actionButton.current?.focus());
                            }} className={button + ' border border-border bg-card text-primary hover:bg-surface'}>Hủy</button>
                        </div>
                    </div>}
            </>}
    </section>;
}
