import { useEffect, useRef, useState } from 'react';
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

    return <section aria-labelledby="property-status-title" className="space-y-4 rounded-2xl border border-border p-6">
        <h2 id="property-status-title" className="text-xl font-semibold">Trạng thái hoạt động</h2>
        {!allowed && <p>{property.status === 'BLOCKED'
            ? 'Cơ sở đã bị khóa. Host không thể tự mở khóa.'
            : 'Cơ sở đang chờ duyệt. Host không thể tự chuyển trạng thái.'}</p>}
        {success && <p role="status">{success}</p>}
        {error && <p role="alert">{error}</p>}
        {needsReload ? <button type="button" onClick={onReload} className="text-primary underline">Tải lại chi tiết để kiểm tra trạng thái</button>
            : allowed && <>
                {!confirming ? <button type="button" onClick={() => { setConfirming(true); setSuccess(''); setError(''); }}
                    className="rounded-xl border border-border px-4 py-3 text-primary">{action}</button>
                    : <div className="space-y-4">
                        <p>Bạn xác nhận {action.toLowerCase()} cơ sở này?</p>
                        <div className="flex flex-wrap gap-3">
                            <button type="button" disabled={pending} onClick={submit}
                                className="rounded-xl bg-primary px-4 py-3 text-primary-foreground disabled:opacity-60">{pending ? 'Đang cập nhật...' : 'Xác nhận'}</button>
                            <button type="button" disabled={pending} onClick={() => { setConfirming(false); setError(''); }}
                                className="rounded-xl border border-border px-4 py-3 disabled:opacity-60">Hủy</button>
                        </div>
                    </div>}
            </>}
    </section>;
}
