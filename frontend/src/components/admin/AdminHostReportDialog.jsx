import { useEffect, useRef, useState } from 'react';
import * as Dialog from '@radix-ui/react-dialog';
import { CalendarDays, Download, FileText, Flag, X } from 'lucide-react';
import { normalizeApiError } from '../../api/apiError.js';
import { useAuth } from '../../hooks/useAuth';
import { adminHostReportService, formatReportTime, reportStatusLabels } from '../../services/adminHostReportService.js';
import { Button } from '../ui/button.jsx';

const formatDay = (value) => value.split('-').reverse().join('/');
const fieldClass = 'mt-1 w-full rounded-lg border border-input bg-background px-3 py-2 text-sm focus-visible:outline-2 focus-visible:outline-primary aria-invalid:border-destructive';
const statusTone = {
  SUBMITTED: 'bg-amber-100 text-amber-950',
  SUBSTANTIATED: 'bg-blue-100 text-blue-950',
  UNSUBSTANTIATED: 'bg-muted text-muted-foreground',
};
const evidenceType = { 'application/pdf': 'PDF', 'image/jpeg': 'JPG', 'image/png': 'PNG' };
const sizeFormat = new Intl.NumberFormat('vi-VN', { maximumFractionDigits: 1 });

export default function AdminHostReportDialog({ summary, onClose, onUpdated, returnFocus }) {
  const { captureProfileAuth, isProfileAuthCurrent } = useAuth();
  const [attempt, setAttempt] = useState(0);
  const [view, setView] = useState(null);
  const [decision, setDecision] = useState('');
  const [note, setNote] = useState('');
  const [fields, setFields] = useState({});
  const [message, setMessage] = useState('');
  const [busy, setBusy] = useState(false);
  const pending = useRef(null);
  const [downloading, setDownloading] = useState(false);
  const [downloadError, setDownloadError] = useState('');
  const pendingDownload = useRef(null);

  useEffect(() => {
    const controller = new AbortController();
    const auth = captureProfileAuth();
    const active = () => !controller.signal.aborted && isProfileAuthCurrent(auth);
    if (active()) {
      adminHostReportService.detail(summary.id, { signal: controller.signal }).then(
        (data) => { if (active()) setView({ auth, attempt, data }); },
        (failure) => { if (active()) setView({ auth, attempt, error: normalizeApiError(failure) }); },
      );
    }
    return () => controller.abort();
  }, [summary.id, attempt, captureProfileAuth, isProfileAuthCurrent]);
  useEffect(() => () => {
    pending.current?.abort();
    pendingDownload.current?.abort();
  }, []);

  const visible = view?.attempt === attempt && isProfileAuthCurrent(view.auth) ? view : null;
  const report = visible?.data;
  const status = report?.status ?? summary.status;

  async function downloadEvidence() {
    if (!report?.evidence || pendingDownload.current) return;
    const auth = captureProfileAuth();
    if (!isProfileAuthCurrent(auth)) return;
    const controller = new AbortController();
    const active = () => !controller.signal.aborted && isProfileAuthCurrent(auth);
    pendingDownload.current = controller;
    setDownloading(true);
    setDownloadError('');
    try {
      const blob = await adminHostReportService.download(report.id, report.evidence, { signal: controller.signal });
      if (active()) {
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = report.evidence.filename;
        document.body.append(link);
        link.click();
        link.remove();
        window.setTimeout(() => URL.revokeObjectURL(url), 1000);
      }
    } catch (failure) {
      const error = normalizeApiError(failure);
      if (active() && !error.isCancelled) {
        setDownloadError(error.status === 404 ? 'Không tìm thấy tệp chứng cứ.' : error.message);
      }
    } finally {
      pendingDownload.current = null;
      if (active()) setDownloading(false);
    }
  }

  async function submit(event) {
    event.preventDefault();
    if (!report || report.status !== 'SUBMITTED' || pending.current) return;
    const form = event.currentTarget;
    const text = note.trim();
    const invalid = {
      decision: decision ? '' : 'Vui lòng chọn kết luận.',
      note: !text ? 'Vui lòng nhập ghi chú xử lý.' : Array.from(text).length > 500 ? 'Ghi chú tối đa 500 ký tự.' : '',
    };
    if (invalid.decision || invalid.note) {
      setFields(invalid);
      form.elements.namedItem(invalid.decision ? 'decision' : 'note')?.focus();
      return;
    }
    const auth = captureProfileAuth();
    if (!isProfileAuthCurrent(auth)) return;
    const controller = new AbortController();
    const options = { signal: controller.signal };
    const active = () => !options.signal.aborted && isProfileAuthCurrent(auth);
    pending.current = controller;
    setBusy(true);
    setMessage('');
    setFields({});
    try {
      const data = await adminHostReportService.decide(report.id, { decision, note: text }, options);
      if (active()) {
        setView({ auth, attempt, data });
        setMessage('Đã lưu kết luận.');
        onUpdated();
      }
    } catch (failure) {
      const error = normalizeApiError(failure);
      if (!active() || error.isCancelled) return;
      if (error.status === 409 || error.status === 0 || error.status >= 500) {
        try {
          const data = await adminHostReportService.detail(report.id, options);
          if (active()) {
            setView({ auth, attempt, data });
            setMessage(data.status === 'SUBMITTED' ? 'Chưa thấy kết luận đã lưu. Bạn có thể thử lại.' : 'Đang hiển thị kết luận đã lưu trên máy chủ.');
            if (data.status !== 'SUBMITTED') onUpdated();
          }
        } catch (refreshFailure) {
          if (active()) {
            setView({ auth, attempt, error: normalizeApiError(refreshFailure) });
            setMessage('Hãy tải lại báo cáo để kiểm tra kết quả trước khi lưu tiếp.');
          }
        }
      } else {
        const field = ['decision', 'note'].find((name) => error.fields[name]?.length);
        if (field) {
          setFields({ decision: error.fields.decision?.[0], note: error.fields.note?.[0] });
          form.elements.namedItem(field)?.focus();
        } else {
          setMessage(error.message);
        }
      }
    } finally {
      if (pending.current === controller) pending.current = null;
      if (active()) setBusy(false);
    }
  }

  return <Dialog.Root open onOpenChange={(open) => { if (!open && !pending.current) onClose(); }}>
    <Dialog.Portal>
      <Dialog.Overlay className="fixed inset-0 z-50 bg-black/55" />
      <Dialog.Content className="fixed left-1/2 top-1/2 z-50 flex max-h-[90dvh] w-[calc(100%_-_2rem)] max-w-4xl -translate-x-1/2 -translate-y-1/2 flex-col overflow-hidden rounded-2xl border border-border bg-card shadow-xl focus:outline-none"
        onCloseAutoFocus={(event) => { event.preventDefault(); returnFocus(); }}>
        <div className="flex shrink-0 items-start gap-3 border-b border-border bg-card p-4 sm:p-6">
          <span className="grid size-10 shrink-0 place-items-center rounded-xl bg-primary/10 text-primary"><Flag className="size-5" aria-hidden="true" /></span>
          <div className="min-w-0 flex-1">
            <Dialog.Title className="text-xl font-semibold text-primary">Chi tiết báo cáo Host</Dialog.Title>
            <Dialog.Description className="mt-1 break-words text-sm text-muted-foreground">
              <strong className="font-semibold text-foreground">{summary.booking_code}</strong> · {summary.property_name}
            </Dialog.Description>
            <span className={`mt-2 inline-block rounded-full px-2.5 py-1 text-xs font-semibold ${statusTone[status]}`}>{reportStatusLabels[status]}</span>
          </div>
          <Dialog.Close asChild><Button type="button" variant="ghost" size="icon" aria-label="Đóng" disabled={busy}><X aria-hidden="true" /></Button></Dialog.Close>
        </div>
        <div className="min-h-0 overflow-y-auto bg-muted/20 p-4 sm:p-6">
        {!visible && <p role="status" className="text-sm text-muted-foreground">Đang tải báo cáo…</p>}
        {message && <p role={report && report.status !== 'SUBMITTED' ? 'status' : 'alert'} className="mb-3 rounded-lg bg-muted p-3 text-sm">{message}</p>}
        {visible?.error && <div className="space-y-3">
          <p role="alert" className="text-sm text-destructive">{visible.error.message}</p>
          {![401, 403, 404].includes(visible.error.status) &&
            <Button type="button" variant="outline" onClick={() => setAttempt((value) => value + 1)}>Tải lại báo cáo</Button>}
        </div>}
        {report && <div className="space-y-4 [overflow-wrap:anywhere]">
          <div className="grid items-start gap-4 md:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]">
            <section className="min-w-0 rounded-xl border border-border bg-card p-4 sm:p-5">
              <h3 className="flex items-center gap-2 font-semibold"><FileText className="size-4 text-primary" aria-hidden="true" /> Nội dung khách báo cáo</h3>
              <p className="mt-3 text-sm font-semibold text-primary">{report.reason_label}</p>
              <p className="mt-2 whitespace-pre-wrap text-sm leading-relaxed">{report.description}</p>
              <time className="mt-3 block text-xs text-muted-foreground" dateTime={report.submitted_at}>Gửi lúc {formatReportTime(report.submitted_at)}</time>
              <div className="mt-4 rounded-xl border border-border bg-muted/30 p-3">
                <h4 className="text-sm font-semibold">Chứng cứ đính kèm</h4>
                {report.evidence ? <div className="mt-2 flex flex-wrap items-center justify-between gap-3">
                  <div className="min-w-0 text-sm">
                    <p className="font-medium">{report.evidence.filename}</p>
                    <p className="text-xs text-muted-foreground">{evidenceType[report.evidence.mime]} · {report.evidence.size < 1024 ? `${report.evidence.size} B` : `${sizeFormat.format(report.evidence.size / 1024)} KiB`}</p>
                  </div>
                  <Button type="button" variant="outline" size="sm" onClick={downloadEvidence} disabled={downloading}>
                    <Download aria-hidden="true" /> {downloading ? 'Đang tải…' : 'Tải chứng cứ'}
                  </Button>
                </div> : <p className="mt-1 text-sm text-muted-foreground">Không có chứng cứ đính kèm</p>}
                {downloadError && <p role="alert" className="mt-2 text-sm text-destructive">{downloadError}</p>}
              </div>
            </section>
            <section className="min-w-0 rounded-xl border border-border bg-card p-4 sm:p-5">
              <h3 className="flex items-center gap-2 font-semibold"><CalendarDays className="size-4 text-primary" aria-hidden="true" /> Thông tin đơn</h3>
              <dl className="mt-3 grid gap-x-4 gap-y-3 text-sm sm:grid-cols-2 md:grid-cols-1">
              <div><dt className="text-muted-foreground">Khách đặt</dt><dd>{report.customer_name}</dd></div>
              <div><dt className="text-muted-foreground">Host</dt><dd>{report.host_name}</dd></div>
              <div><dt className="text-muted-foreground">Trạng thái hiện tại</dt><dd>{report.booking_status}</dd></div>
              <div><dt className="text-muted-foreground">Nhận và trả phòng dự kiến</dt><dd>{formatDay(report.check_in_date)} – {formatDay(report.check_out_date)}</dd></div>
              </dl>
            </section>
          </div>
          <section className="rounded-xl border border-border bg-card p-4 sm:p-5">
            <h3 className="font-semibold">Kết luận xử lý</h3>
            {report.status === 'SUBMITTED' ? <form noValidate onSubmit={submit} className="mt-3 space-y-3">
              <div>
                <label htmlFor="host-report-decision" className="text-sm font-medium">Kết luận</label>
                <select id="host-report-decision" name="decision" className={fieldClass} required value={decision}
                  aria-invalid={Boolean(fields.decision)} aria-describedby={fields.decision ? 'host-report-decision-error' : undefined}
                  onChange={(event) => { setDecision(event.target.value); setFields((value) => ({ ...value, decision: '' })); }}>
                  <option value="">Chọn kết luận</option>
                  <option value="SUBSTANTIATED">Có căn cứ</option>
                  <option value="UNSUBSTANTIATED">Chưa đủ căn cứ</option>
                </select>
                {fields.decision && <p id="host-report-decision-error" role="alert" className="text-sm text-destructive">{fields.decision}</p>}
              </div>
              <div>
                <label htmlFor="host-report-note" className="text-sm font-medium">Ghi chú nội bộ</label>
                <textarea id="host-report-note" name="note" rows={3} className={fieldClass} required value={note}
                  aria-invalid={Boolean(fields.note)} aria-describedby={fields.note ? 'host-report-note-help host-report-note-error' : 'host-report-note-help'}
                  onChange={(event) => { setNote(event.target.value); setFields((value) => ({ ...value, note: '' })); }} />
                <p id="host-report-note-help" className="text-xs text-muted-foreground">Tối đa 500 ký tự. Kết luận được lưu một lần.</p>
                {fields.note && <p id="host-report-note-error" role="alert" className="text-sm text-destructive">{fields.note}</p>}
              </div>
              <div className="flex justify-end"><Button type="submit" disabled={busy}>{busy ? 'Đang lưu…' : 'Lưu kết luận'}</Button></div>
            </form> : <div className="mt-2 space-y-1 text-sm">
              <p className="font-semibold text-primary">{reportStatusLabels[report.status]}</p>
              <p className="whitespace-pre-wrap">{report.review_note}</p>
              <p className="text-muted-foreground">{report.reviewer_name} · <time dateTime={report.reviewed_at}>{formatReportTime(report.reviewed_at)}</time></p>
            </div>}
          </section>
        </div>}
        </div>
      </Dialog.Content>
    </Dialog.Portal>
  </Dialog.Root>;
}
