import { useEffect, useRef, useState } from 'react';
import { CheckCircle2 } from 'lucide-react';
import { normalizeApiError } from '../../api/apiError.js';
import { useAuth } from '../../hooks/useAuth';
import { hostReportService } from '../../services/hostReportService.js';
import { Button } from '../ui/button.jsx';
import { HostReportDialog } from './HostReportDialog.jsx';

const submittedDate = new Intl.DateTimeFormat('vi-VN', {
  dateStyle: 'short', timeStyle: 'short', timeZone: 'Asia/Ho_Chi_Minh',
});
const displayDay = (value) => value.split('-').reverse().join('/');

export function HostReportPanel({ bookingId, bookingCode, propertyName }) {
  const { captureProfileAuth, isProfileAuthCurrent } = useAuth();
  const [attempt, setAttempt] = useState(0);
  const [view, setView] = useState(null);
  const pendingSubmit = useRef(null);
  const panel = useRef(null);

  useEffect(() => {
    const controller = new AbortController();
    const auth = captureProfileAuth();
    const current = () => !controller.signal.aborted && isProfileAuthCurrent(auth);
    if (current()) {
      hostReportService.current(bookingId, { signal: controller.signal }).then(
        (data) => { if (current()) setView({ auth, attempt, data }); },
        (error) => { if (current()) setView({ auth, attempt, error: normalizeApiError(error) }); },
      );
    }
    return () => controller.abort();
  }, [bookingId, attempt, captureProfileAuth, isProfileAuthCurrent]);

  useEffect(() => () => pendingSubmit.current?.abort(), []);

  const visible = view?.attempt === attempt && isProfileAuthCurrent(view.auth) ? view : null;
  const data = visible?.data;
  const error = visible?.error;

  async function submit(input) {
    const auth = captureProfileAuth();
    if (!data?.canSubmit || !isProfileAuthCurrent(auth) || pendingSubmit.current) return;
    const controller = new AbortController();
    const options = { signal: controller.signal };
    const current = () => !options.signal.aborted && isProfileAuthCurrent(auth);
    pendingSubmit.current = controller;

    try {
      const report = await hostReportService.submit(bookingId, input, options);
      if (current()) {
        setView({ auth, attempt, data: { ...data, canSubmit: false, reasons: [], report } });
      }
    } catch (failure) {
      const problem = normalizeApiError(failure);
      if (!current() || problem.isCancelled) return;
      if (problem.status !== 409 && problem.status !== 0 && problem.status < 500) throw problem;

      let refreshed;
      try {
        refreshed = await hostReportService.current(bookingId, options);
      } catch (refreshFailure) {
        if (current()) setView({ auth, attempt, error: normalizeApiError(refreshFailure), uncertain: true });
        return;
      }
      if (!current()) return;
      setView({ auth, attempt, data: refreshed });
      if (refreshed.canSubmit) {
        throw { ...problem, message: 'Chưa thấy báo cáo được lưu. Bạn có thể gửi lại.' };
      }
    } finally {
      if (pendingSubmit.current === controller) pendingSubmit.current = null;
    }
  }

  if (data?.blockedReason === 'BOOKING_STATUS') return null;

  return (
    <section ref={panel} tabIndex={-1} aria-label="Báo cáo sự cố với Host"
      className="min-w-0 space-y-3 rounded-2xl border border-border/60 bg-card p-5 shadow-xs">
      <h2 className="text-base font-semibold text-primary">Báo cáo sự cố với Host</h2>
      {!visible && <p role="status" className="text-sm text-muted-foreground">Đang kiểm tra quyền gửi báo cáo…</p>}
      {error && (
        <>
          <p role="alert" className="text-sm text-destructive">
            {visible.uncertain ? 'Chưa kiểm tra được kết quả gửi. Vui lòng kiểm tra lại trước khi gửi tiếp.' : error.message}
          </p>
          {![401, 403, 404].includes(error.status) &&
            <Button variant="outline" onClick={() => setAttempt((previous) => previous + 1)}>Kiểm tra lại</Button>}
        </>
      )}
      {data?.report && (
        <div role="status" className="flex items-start gap-2 rounded-xl bg-emerald-50 p-3 text-emerald-900">
          <CheckCircle2 className="mt-0.5 size-4 shrink-0" aria-hidden="true" />
          <div>
            <p className="text-sm font-semibold">Đã gửi báo cáo</p>
            <time className="text-xs" dateTime={data.report.submitted_at}>{submittedDate.format(new Date(data.report.submitted_at))}</time>
          </div>
        </div>
      )}
      {data?.blockedReason === 'DEADLINE_PASSED' &&
        <p className="text-sm text-muted-foreground">Đã hết hạn gửi báo cáo vào cuối ngày {displayDay(data.deadline)}.</p>}
      {data?.canSubmit && (
        <>
          <p className="text-sm text-muted-foreground">Có thể gửi đến hết ngày {displayDay(data.deadline)} theo giờ Việt Nam.</p>
          <HostReportDialog bookingCode={bookingCode} propertyName={propertyName} reasons={data.reasons}
            onSubmit={submit} focusFallback={() => panel.current?.focus()} />
        </>
      )}
    </section>
  );
}
