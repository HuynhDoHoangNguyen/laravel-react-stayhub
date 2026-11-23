import { useEffect, useRef, useState } from 'react';
import { normalizeApiError } from '../../api/apiError.js';
import AdminHostReportDialog from '../../components/admin/AdminHostReportDialog.jsx';
import AdminPageHeader from '../../components/admin/AdminPageHeader.jsx';
import AdminPagination from '../../components/admin/AdminPagination.jsx';
import { Button } from '../../components/ui/button.jsx';
import { useAuth } from '../../hooks/useAuth';
import { adminHostReportService, formatReportTime, reportStatusLabels } from '../../services/adminHostReportService.js';

const initialQuery = { status: 'SUBMITTED', booking_code: '', page: 1 };
const card = 'min-w-0 rounded-2xl border border-border bg-card p-4 shadow-sm sm:p-5';
const control = 'h-10 w-full min-w-0 rounded-lg border border-input bg-background px-3 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring';
const tones = {
  SUBMITTED: 'bg-amber-100 text-amber-900',
  SUBSTANTIATED: 'bg-blue-100 text-blue-900',
  UNSUBSTANTIATED: 'bg-muted text-muted-foreground',
};

function AdminHostReportList({ user, captureAuth, isAuthCurrent }) {
  const [draft, setDraft] = useState('');
  const [query, setQuery] = useState(initialQuery);
  const [view, setView] = useState(null);
  const [selected, setSelected] = useState(null);
  const filter = useRef(null);
  const trigger = useRef(null);

  useEffect(() => {
    const controller = new AbortController();
    const auth = captureAuth();
    const active = () => !controller.signal.aborted && isAuthCurrent(auth);
    if (active()) {
      adminHostReportService.list(query, { signal: controller.signal }).then(
        (data) => { if (active()) setView({ auth, query, data }); },
        (failure) => { if (active()) setView({ auth, query, error: normalizeApiError(failure) }); },
      );
    }
    return () => controller.abort();
  }, [query, user, captureAuth, isAuthCurrent]);

  const visible = view?.query === query && isAuthCurrent(view.auth) ? view : null;
  const result = visible?.data;
  const error = visible?.error;
  const update = (patch) => setQuery((previous) => ({ ...previous, ...patch }));

  return <section className="mx-auto w-full max-w-7xl space-y-5" aria-labelledby="admin-host-reports-title">
    <AdminPageHeader id="admin-host-reports-title" title="Báo cáo Host" description="Tiếp nhận và xem xét sự cố khách gửi từ đơn đặt phòng." />
    <form className={`${card} grid items-end gap-3 sm:grid-cols-[2fr_1fr_auto]`} onSubmit={(event) => {
      event.preventDefault();
      update({ booking_code: draft.trim(), page: 1 });
    }}>
      <label className="grid gap-1.5 text-sm font-medium">
        Mã đơn đặt phòng
        <input ref={filter} className={control} type="search" value={draft} maxLength={50}
          onChange={(event) => setDraft(event.target.value)} />
      </label>
      <div className="grid gap-1.5 text-sm font-medium">
        <label htmlFor="admin-host-report-status">Trạng thái</label>
        <select id="admin-host-report-status" className={control} value={query.status}
          onChange={(event) => update({ status: event.target.value, page: 1 })}>
          {Object.entries(reportStatusLabels).map(([value, label]) => <option key={value} value={value}>{label}</option>)}
          <option value="ALL">Tất cả</option>
        </select>
      </div>
      <Button className="h-10" type="submit">Tìm kiếm</Button>
    </form>
    <div aria-busy={!visible} className="space-y-3">
      {!visible && <p role="status" className={`${card} text-sm text-muted-foreground`}>Đang tải báo cáo…</p>}
      {error && <div role="alert" className={`${card} space-y-2`}>
        <p>{error.message}</p>
        {Object.values(error.fields).flat().map((message) => <p key={message}>{message}</p>)}
        {![401, 403, 422].includes(error.status) &&
          <Button type="button" variant="outline" onClick={() => update({})}>Thử lại</Button>}
      </div>}
      {result && <>
        <p role="status" className={result.data.length ? 'text-sm text-muted-foreground' : `${card} text-sm text-muted-foreground`}>
          {result.data.length ? `Có ${result.meta.total} báo cáo phù hợp.` : 'Không có báo cáo phù hợp trên trang này.'}
        </p>
        <div className="grid gap-3 lg:grid-cols-2">
          {result.data.map((report) => <article key={report.id} className={`${card} [overflow-wrap:anywhere]`}>
            <div className="flex flex-wrap items-start justify-between gap-2">
              <div>
                <h2 className="font-semibold">{report.booking_code}</h2>
                <p className="text-sm text-muted-foreground">{report.property_name} · {report.host_name}</p>
              </div>
              <span className={`rounded-full px-2.5 py-1 text-xs font-medium ${tones[report.status]}`}>{reportStatusLabels[report.status]}</span>
            </div>
            <p className="mt-3 text-sm">{report.reason_label}</p>
            <div className="mt-3 flex flex-wrap items-center justify-between gap-2">
              <time dateTime={report.submitted_at} className="text-xs text-muted-foreground">{formatReportTime(report.submitted_at)}</time>
              <Button type="button" size="sm" variant="outline" aria-label={`Xem báo cáo ${report.booking_code}`} onClick={(event) => {
                trigger.current = event.currentTarget;
                setSelected(report);
              }}>Xem báo cáo</Button>
            </div>
          </article>)}
        </div>
        <AdminPagination label="Phân trang báo cáo Host" currentPage={result.meta.current_page} lastPage={result.meta.last_page}
          onPageChange={(page) => update({ page })} />
      </>}
    </div>
    {selected && <AdminHostReportDialog summary={selected} onClose={() => setSelected(null)} onUpdated={() => update({ page: 1 })}
      returnFocus={() => (trigger.current?.isConnected ? trigger.current : filter.current)?.focus()} />}
  </section>;
}

export default function AdminHostReportsPage() {
  const { user, isLoggingOut, captureProfileAuth, isProfileAuthCurrent } = useAuth();
  if (!user || isLoggingOut) return null;
  return <AdminHostReportList key={user.id} user={user} captureAuth={captureProfileAuth} isAuthCurrent={isProfileAuthCurrent} />;
}
