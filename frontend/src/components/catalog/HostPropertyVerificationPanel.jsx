import { useEffect, useRef, useState } from 'react';
import { normalizeApiError } from '../../api/apiError.js';
import { useAuth } from '../../hooks/useAuth';
import { hostPropertyVerificationService } from '../../services/hostPropertyVerificationService.js';
import { Button } from '../ui/button.jsx';

const maxBytes = 2 * 1024 * 1024;
const resultLabels = {
    SUBMITTED: 'Đã gửi · Chờ Admin xem xét',
    APPROVED: 'Hồ sơ đã được duyệt',
    REJECTED: 'Hồ sơ bị từ chối',
    INVALIDATED: 'Cần xác minh lại',
};
const resultStyles = {
    SUBMITTED: 'bg-amber-50 text-amber-900',
    APPROVED: 'bg-emerald-50 text-emerald-900',
    REJECTED: 'bg-rose-50 text-rose-900',
    INVALIDATED: 'bg-amber-50 text-amber-900',
};
const formatDate = new Intl.DateTimeFormat('vi-VN', {
    dateStyle: 'short', timeStyle: 'short', timeZone: 'Asia/Ho_Chi_Minh',
});

export default function HostPropertyVerificationPanel({ propertyId, address }) {
    const { captureProfileAuth, isProfileAuthCurrent } = useAuth();
    const [readAttempt, setReadAttempt] = useState({ reconciling: false });
    const [view, setView] = useState(null);
    const [file, setFile] = useState(null);
    const [fileError, setFileError] = useState('');
    const [submitError, setSubmitError] = useState('');
    const [submitting, setSubmitting] = useState(false);
    const upload = useRef(null);

    useEffect(() => {
        const controller = new AbortController();
        const auth = captureProfileAuth();
        const current = () => !controller.signal.aborted && isProfileAuthCurrent(auth);
        if (current()) {
            hostPropertyVerificationService.current(propertyId, { signal: controller.signal }).then(
                (entry) => { if (current()) setView({ attempt: readAttempt, auth, entry }); },
                (failure) => {
                    const error = normalizeApiError(failure);
                    if (current() && !error.isCancelled) setView({ attempt: readAttempt, auth, error });
                },
            );
        }
        return () => controller.abort();
    }, [propertyId, readAttempt, captureProfileAuth, isProfileAuthCurrent]);

    useEffect(() => () => upload.current?.abort(), []);

    const visible = view?.attempt === readAttempt && isProfileAuthCurrent(view.auth) ? view : null;
    const entry = visible?.entry;
    const reconciledEmpty = visible && readAttempt.reconciling && entry === null;

    async function submit(event) {
        event.preventDefault();
        if (!visible || entry !== null || upload.current) return;

        setSubmitError('');
        if (!file || file.size === 0 || file.size > maxBytes || !/\.(pdf|jpe?g|png)$/i.test(file.name)) {
            setFileError(!file ? 'Chọn tệp hồ sơ để gửi.' : 'Chọn tệp PDF, JPG hoặc PNG dung lượng từ 1 byte đến 2 MiB.');
            event.currentTarget.elements.namedItem('document')?.focus();
            return;
        }
        const auth = captureProfileAuth();
        if (!isProfileAuthCurrent(auth)) return;
        const controller = new AbortController();
        upload.current = controller;
        const current = () => !controller.signal.aborted && isProfileAuthCurrent(auth);
        setSubmitting(true);
        setFileError('');

        try {
            const saved = await hostPropertyVerificationService.submit(propertyId, file, { signal: controller.signal });
            if (current()) {
                setFile(null);
                setView({ attempt: readAttempt, auth, entry: saved });
            }
        } catch (failure) {
            const error = normalizeApiError(failure);
            if (!current() || error.isCancelled) return;
            if (error.status === 409 || error.status === 0 || error.status >= 500) {
                setFile(null);
                setReadAttempt({ reconciling: true });
            } else if (error.status === 422 && error.fields.document?.length) {
                setFileError(error.fields.document.join(' '));
            } else {
                setSubmitError(error.status === 413 ? 'Tệp hồ sơ phải có dung lượng tối đa 2 MiB.' : error.message);
            }
        } finally {
            if (upload.current === controller) upload.current = null;
            if (current()) setSubmitting(false);
        }
    }

    return <section id="property-verification" aria-labelledby="property-verification-title"
        className="min-w-0 scroll-mt-6 space-y-4 rounded-2xl border border-border bg-card p-5 shadow-sm sm:p-6">
        <h2 id="property-verification-title" className="text-lg font-semibold text-primary">Xác minh cơ sở</h2>
        {!visible && <p role="status" className="text-sm text-muted-foreground">
            {readAttempt.reconciling ? 'Đang kiểm tra kết quả gửi hồ sơ…' : 'Đang tải hồ sơ xác minh…'}
        </p>}
        {visible?.error && <div className="space-y-3">
            <p role="alert" className="text-sm text-destructive">{visible.error.message}</p>
            {![401, 403, 404].includes(visible.error.status) &&
                <Button type="button" variant="outline" onClick={() => setReadAttempt({ reconciling: readAttempt.reconciling })}>Thử tải lại</Button>}
        </div>}
        {entry && <div className="space-y-3">
            <p role="status" className={`w-fit rounded-full px-3 py-1 text-sm font-medium ${resultStyles[entry.status]}`}>{resultLabels[entry.status]}</p>
            {entry.status === 'INVALIDATED' && <p className="text-sm text-muted-foreground">Địa chỉ cơ sở đã thay đổi sau khi hồ sơ được duyệt.</p>}
            <dl className="grid gap-3 text-sm sm:grid-cols-2">
                <div><dt className="text-muted-foreground">Địa chỉ lúc gửi</dt><dd className="break-words font-medium">{entry.address_snapshot}</dd></div>
                <div><dt className="text-muted-foreground">Thời điểm gửi</dt><dd className="font-medium">
                    <time dateTime={entry.submitted_at}>{formatDate.format(new Date(entry.submitted_at))}</time>
                </dd></div>
                {entry.reviewed_at && <div><dt className="text-muted-foreground">Thời điểm xử lý</dt><dd className="font-medium">
                    <time dateTime={entry.reviewed_at}>{formatDate.format(new Date(entry.reviewed_at))}</time>
                </dd></div>}
                {entry.rejection_reason && <div><dt className="text-muted-foreground">Lý do từ chối</dt>
                    <dd className="whitespace-pre-wrap font-medium [overflow-wrap:anywhere]">{entry.rejection_reason}</dd>
                </div>}
            </dl>
        </div>}
        {entry === null && <form onSubmit={submit} className="space-y-3">
            <p className="text-sm font-medium">Chưa gửi hồ sơ</p>
            <p className="break-words text-sm"><span className="text-muted-foreground">Địa chỉ cơ sở</span> · {address}</p>
            <p className="text-sm text-muted-foreground">Chọn giấy tờ thể hiện địa chỉ và quyền sử dụng hoặc vận hành cơ sở.</p>
            {reconciledEmpty && !submitting && <p role="status" className="text-sm">Chưa tìm thấy hồ sơ đã gửi. Bạn có thể chọn tệp và gửi lại.</p>}
            <div className="grid min-w-0 gap-1.5">
                <label htmlFor="verification-document" className="text-sm font-medium">Tệp hồ sơ</label>
                <input id="verification-document" name="document" type="file" accept=".pdf,.jpg,.jpeg,.png"
                    aria-describedby={fileError ? 'verification-file-help verification-file-error' : 'verification-file-help'}
                    aria-invalid={Boolean(fileError)} disabled={submitting}
                    className="w-full min-w-0 max-w-full rounded-lg border border-input bg-background px-3 py-2 text-sm focus-visible:ring-2 focus-visible:ring-ring"
                    onChange={(event) => { setFile(event.target.files[0] ?? null); setFileError(''); setSubmitError(''); }} />
                <p id="verification-file-help" className="text-sm text-muted-foreground">Một tệp PDF, JPG hoặc PNG, tối đa 2 MiB.</p>
                {fileError && <p id="verification-file-error" role="alert" className="text-sm text-destructive">{fileError}</p>}
            </div>
            {submitError && <p role="alert" className="text-sm text-destructive">{submitError}</p>}
            <Button type="submit" disabled={submitting}>{submitting ? 'Đang gửi hồ sơ…' : 'Gửi hồ sơ'}</Button>
        </form>}
    </section>;
}
