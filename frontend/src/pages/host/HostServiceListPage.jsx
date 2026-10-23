import { useCallback, useEffect, useRef, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { ArrowLeft, ChevronLeft, ChevronRight, CirclePlus, Pencil, RefreshCw, Search, X } from 'lucide-react';
import { toast } from 'sonner';
import { propertyServiceCatalogService } from '@/services/propertyServiceCatalogService';
import { formatHostVnd } from '@/services/hostBookingService';

const PER_PAGE = 10;
const EMPTY_FORM = { name: '', description: '', unit: '', price: '' };

export default function HostServiceListPage() {
  const { propertyId } = useParams();
  const [property, setProperty] = useState(null);
  const [services, setServices] = useState([]);
  const [filters, setFilters] = useState({ keyword: '', status: '' });
  const [applied, setApplied] = useState(filters);
  const [page, setPage] = useState(1);
  const [meta, setMeta] = useState({ last_page: 1, current_page: 1, total: 0 });
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [reloadKey, setReloadKey] = useState(0);
  const [formOpen, setFormOpen] = useState(false);
  const [editingService, setEditingService] = useState(null);
  const [form, setForm] = useState(EMPTY_FORM);
  const [formError, setFormError] = useState('');
  const [saving, setSaving] = useState(false);
  const [busyServiceId, setBusyServiceId] = useState(null);
  const mutationLock = useRef(false);

  const loadServices = useCallback(async (signal) => {
    setLoading(true);
    setError('');
    try {
      const properties = await propertyServiceCatalogService.getHostProperties({ signal });
      const currentProperty = properties.find((item) => String(item.id) === String(propertyId));
      if (!currentProperty) throw new Error('Không tìm thấy cơ sở lưu trú trong tài khoản Host hiện tại.');
      const result = await propertyServiceCatalogService.list({
        propertyId,
        page,
        perPage: PER_PAGE,
        status: applied.status,
        keyword: applied.keyword,
      }, { signal });
      if (signal.aborted) return;
      setProperty(currentProperty);
      setServices(result?.data || []);
      setMeta(result?.meta || { last_page: 1, current_page: page, total: 0 });
    } catch (loadError) {
      if (loadError.name !== 'CanceledError' && loadError.name !== 'AbortError') {
        setError(loadError.message || 'Không thể tải danh mục dịch vụ.');
        setServices([]);
      }
    } finally {
      if (!signal.aborted) setLoading(false);
    }
  }, [propertyId, page, applied]);

  useEffect(() => {
    const controller = new AbortController();
    const start = window.setTimeout(() => loadServices(controller.signal), 0);
    return () => {
      window.clearTimeout(start);
      controller.abort();
    };
  }, [loadServices, reloadKey]);

  const openCreate = () => {
    setEditingService(null);
    setForm(EMPTY_FORM);
    setFormError('');
    setFormOpen(true);
  };

  const openEdit = (service) => {
    setEditingService(service);
    setForm({
      name: service.name || '',
      description: service.description || '',
      unit: service.unit || '',
      price: String(service.price ?? ''),
    });
    setFormError('');
    setFormOpen(true);
  };

  const applyFilters = (event) => {
    event.preventDefault();
    setError('');
    setLoading(true);
    setPage(1);
    setApplied(filters);
  };

  const saveService = async (event) => {
    event.preventDefault();
    if (mutationLock.current) return;
    mutationLock.current = true;
    setSaving(true);
    setFormError('');
    const payload = {
      name: form.name.trim(),
      description: form.description.trim() || null,
      unit: form.unit.trim(),
      price: form.price,
    };
    try {
      if (editingService) {
        await propertyServiceCatalogService.update(editingService.id, payload);
      } else {
        await propertyServiceCatalogService.create({ ...payload, property_id: Number(propertyId), status: true });
      }
      setFormOpen(false);
      toast.success(editingService ? 'Đã cập nhật dịch vụ.' : 'Đã tạo dịch vụ mới.');
      setLoading(true);
      setReloadKey((current) => current + 1);
    } catch (saveError) {
      setFormError(saveError.message || 'Không thể lưu dịch vụ.');
    } finally {
      mutationLock.current = false;
      setSaving(false);
    }
  };

  const toggleStatus = async (service) => {
    if (mutationLock.current) return;
    mutationLock.current = true;
    setBusyServiceId(service.id);
    setError('');
    try {
      await propertyServiceCatalogService.setStatus(service.id, !service.status);
      toast.success(service.status ? 'Đã tắt dịch vụ.' : 'Đã bật dịch vụ.');
      setReloadKey((current) => current + 1);
    } catch (toggleError) {
      setError(toggleError.message || 'Không thể cập nhật trạng thái dịch vụ.');
    } finally {
      mutationLock.current = false;
      setBusyServiceId(null);
    }
  };

  const currentPage = Number(meta.current_page) || page;
  const lastPage = Math.max(1, Number(meta.last_page) || 1);
  const total = Number(meta.total) || 0;

  return (
    <div className="mx-auto max-w-[1400px] space-y-6 text-left">
      <Link to="/host/services" className="inline-flex items-center gap-2 text-sm font-medium text-muted-foreground hover:text-primary"><ArrowLeft className="h-4 w-4" /> Danh mục dịch vụ</Link>
      <header className="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
        <div>
          <p className="text-xs font-semibold uppercase tracking-[0.22em] text-accent">Quản lý cơ sở lưu trú</p>
          <h1 className="mt-1 font-display text-3xl font-bold text-primary sm:text-4xl">Dịch vụ</h1>
          <p className="mt-2 text-sm text-muted-foreground">{property?.name || 'Danh mục dịch vụ của cơ sở'}</p>
        </div>
        <button type="button" onClick={openCreate} className="inline-flex h-10 items-center justify-center gap-2 rounded-xl bg-primary px-4 text-sm font-semibold text-primary-foreground hover:bg-primary-mid"><CirclePlus className="h-4 w-4" /> Thêm dịch vụ</button>
      </header>

      <form onSubmit={applyFilters} className="grid gap-3 rounded-2xl border border-border/70 bg-white p-4 shadow-xs sm:grid-cols-[minmax(0,1fr)_200px_auto]">
        <label className="relative">
          <span className="sr-only">Tìm dịch vụ</span>
          <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
          <input value={filters.keyword} onChange={(event) => setFilters((current) => ({ ...current, keyword: event.target.value }))} placeholder="Tìm theo tên dịch vụ" className="h-10 w-full rounded-xl border border-border pl-9 pr-3 text-sm outline-none focus:border-primary" />
        </label>
        <label>
          <span className="sr-only">Lọc trạng thái dịch vụ</span>
          <select value={filters.status} onChange={(event) => setFilters((current) => ({ ...current, status: event.target.value }))} className="h-10 w-full rounded-xl border border-border bg-white px-3 text-sm outline-none focus:border-primary">
            <option value="">Tất cả dịch vụ</option><option value="true">Đang bật</option><option value="false">Đang tắt</option>
          </select>
        </label>
        <button type="submit" className="inline-flex h-10 items-center justify-center gap-2 rounded-xl border border-border bg-white px-4 text-sm font-semibold text-ink hover:border-primary/40 hover:text-primary"><Search className="h-4 w-4" /> Lọc</button>
      </form>

      {error && <div role="alert" className="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800"><span>{error}</span><button type="button" onClick={() => { setLoading(true); setReloadKey((current) => current + 1); }} className="inline-flex items-center gap-1.5 font-semibold"><RefreshCw className="h-3.5 w-3.5" /> Thử lại</button></div>}

      <div className="flex items-center justify-between text-sm text-muted-foreground"><span>{loading ? 'Đang tải danh mục…' : `${total} dịch vụ`}</span>{!loading && total > 0 && <span>Trang {currentPage}/{lastPage}</span>}</div>

      {loading ? (
        <div className="space-y-3">{[1, 2, 3].map((item) => <div key={item} className="h-28 animate-pulse rounded-2xl bg-white/80" />)}</div>
      ) : !error && services.length === 0 ? (
        <section className="rounded-2xl border border-dashed border-border bg-white p-12 text-center">
          <h2 className="font-display text-xl font-bold text-primary">Chưa có dịch vụ phù hợp</h2>
          <p className="mt-1 text-sm text-muted-foreground">Thêm dịch vụ mới hoặc thay đổi bộ lọc để xem danh mục.</p>
          <button type="button" onClick={openCreate} className="mt-4 inline-flex items-center gap-2 rounded-xl bg-primary px-4 py-2.5 text-sm font-semibold text-primary-foreground"><CirclePlus className="h-4 w-4" /> Thêm dịch vụ đầu tiên</button>
        </section>
      ) : !error && (
        <div className="space-y-3">
          {services.map((service) => (
            <article key={service.id} className="rounded-2xl border border-border/70 bg-white p-4 shadow-xs sm:p-5">
              <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
                <div className="min-w-0 flex-1">
                  <div className="flex flex-wrap items-center gap-2">
                    <h2 className="font-display text-lg font-bold text-primary">{service.name}</h2>
                    <span className={`rounded-full border px-2.5 py-1 text-[11px] font-semibold ${service.status ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-slate-200 bg-slate-100 text-slate-700'}`}>{service.status ? 'Đang bật' : 'Đang tắt'}</span>
                  </div>
                  {service.description && <p className="mt-1 text-sm text-muted-foreground">{service.description}</p>}
                  <p className="mt-2 text-xs text-muted-foreground">Đơn vị: {service.unit}</p>
                </div>
                <div className="flex flex-wrap items-center gap-2 sm:justify-end">
                  <strong className="mr-2 text-sm text-primary">{formatHostVnd(service.price)} / {service.unit}</strong>
                  <button type="button" onClick={() => openEdit(service)} className="inline-flex items-center gap-1.5 rounded-lg border border-border px-3 py-2 text-xs font-semibold text-ink hover:border-primary/40 hover:text-primary"><Pencil className="h-3.5 w-3.5" /> Chỉnh sửa</button>
                  <button type="button" disabled={busyServiceId === service.id} onClick={() => toggleStatus(service)} className={`min-w-24 rounded-lg px-3 py-2 text-xs font-semibold disabled:opacity-50 ${service.status ? 'border border-rose-200 bg-rose-50 text-rose-800 hover:bg-rose-100' : 'bg-emerald-700 text-white hover:bg-emerald-800'}`}>
                    {busyServiceId === service.id ? 'Đang lưu…' : service.status ? 'Tắt dịch vụ' : 'Bật dịch vụ'}
                  </button>
                </div>
              </div>
            </article>
          ))}
        </div>
      )}

      {!loading && !error && lastPage > 1 && <nav aria-label="Phân trang dịch vụ" className="flex items-center justify-center gap-4 py-2">
        <button type="button" disabled={currentPage <= 1} onClick={() => { setLoading(true); setPage((current) => Math.max(1, current - 1)); }} className="inline-flex items-center gap-1 rounded-lg border border-border bg-white px-3 py-2 text-sm disabled:opacity-40"><ChevronLeft className="h-4 w-4" /> Trước</button>
        <span className="text-sm text-muted-foreground">{currentPage} / {lastPage}</span>
        <button type="button" disabled={currentPage >= lastPage} onClick={() => { setLoading(true); setPage((current) => Math.min(lastPage, current + 1)); }} className="inline-flex items-center gap-1 rounded-lg border border-border bg-white px-3 py-2 text-sm disabled:opacity-40">Sau <ChevronRight className="h-4 w-4" /></button>
      </nav>}

      {formOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-slate-950/45 p-4" onMouseDown={(event) => { if (event.target === event.currentTarget && !saving) setFormOpen(false); }}>
          <section role="dialog" aria-modal="true" aria-labelledby="service-form-title" className="my-auto w-full max-w-xl rounded-2xl border border-border/70 bg-white p-5 shadow-xl sm:p-6">
            <div className="flex items-start justify-between gap-3">
              <div><p className="text-xs font-semibold uppercase tracking-[0.2em] text-accent">{property?.name || 'Dịch vụ cơ sở'}</p><h2 id="service-form-title" className="mt-1 font-display text-2xl font-bold text-primary">{editingService ? 'Chỉnh sửa dịch vụ' : 'Thêm dịch vụ mới'}</h2></div>
              <button type="button" aria-label="Đóng biểu mẫu" disabled={saving} onClick={() => setFormOpen(false)} className="grid h-9 w-9 place-items-center rounded-full text-muted-foreground hover:bg-surface"><X className="h-4 w-4" /></button>
            </div>
            <form onSubmit={saveService} className="mt-5 space-y-4">
              <label className="block text-sm font-semibold text-ink">Tên dịch vụ
                <input required maxLength={255} value={form.name} onChange={(event) => setForm((current) => ({ ...current, name: event.target.value }))} className="mt-1.5 h-11 w-full rounded-xl border border-border px-3 text-sm font-normal outline-none focus:border-primary" placeholder="Ví dụ: Giặt ủi nhanh" />
              </label>
              <label className="block text-sm font-semibold text-ink">Mô tả <span className="font-normal text-muted-foreground">(không bắt buộc)</span>
                <textarea rows={3} value={form.description} onChange={(event) => setForm((current) => ({ ...current, description: event.target.value }))} className="mt-1.5 w-full rounded-xl border border-border p-3 text-sm font-normal outline-none focus:border-primary" placeholder="Thông tin thêm về dịch vụ" />
              </label>
              <div className="grid gap-4 sm:grid-cols-2">
                <label className="block text-sm font-semibold text-ink">Đơn vị tính
                  <input required maxLength={50} value={form.unit} onChange={(event) => setForm((current) => ({ ...current, unit: event.target.value }))} className="mt-1.5 h-11 w-full rounded-xl border border-border px-3 text-sm font-normal outline-none focus:border-primary" placeholder="lượt, kg, người…" />
                </label>
                <label className="block text-sm font-semibold text-ink">Giá dịch vụ (VND)
                  <input type="number" required min="0" max="100000000" step="0.01" value={form.price} onChange={(event) => setForm((current) => ({ ...current, price: event.target.value }))} className="mt-1.5 h-11 w-full rounded-xl border border-border px-3 text-sm font-normal outline-none focus:border-primary" placeholder="0" />
                </label>
              </div>
              {formError && <p role="alert" className="rounded-lg bg-rose-50 px-3 py-2 text-sm text-rose-800">{formError}</p>}
              {editingService && <p className="text-xs text-muted-foreground">Thay đổi giá chỉ áp dụng cho dịch vụ phát sinh sau này; booking cũ giữ nguyên giá snapshot.</p>}
              <div className="flex justify-end gap-2 border-t border-border/60 pt-4">
                <button type="button" disabled={saving} onClick={() => setFormOpen(false)} className="rounded-xl border border-border bg-white px-4 py-2.5 text-sm font-semibold text-ink">Hủy</button>
                <button type="submit" disabled={saving} className="inline-flex min-w-32 items-center justify-center gap-2 rounded-xl bg-primary px-4 py-2.5 text-sm font-semibold text-primary-foreground disabled:opacity-50">{saving ? 'Đang lưu…' : editingService ? 'Lưu thay đổi' : 'Tạo dịch vụ'}</button>
              </div>
            </form>
          </section>
        </div>
      )}
    </div>
  );
}
