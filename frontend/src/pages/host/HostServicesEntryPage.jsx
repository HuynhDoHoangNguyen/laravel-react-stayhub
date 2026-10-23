import { useEffect, useState } from 'react';
import { Navigate, useNavigate } from 'react-router-dom';
import { Building2, RefreshCw } from 'lucide-react';
import { propertyServiceCatalogService } from '@/services/propertyServiceCatalogService';

export default function HostServicesEntryPage() {
  const navigate = useNavigate();
  const [state, setState] = useState({ loading: true, error: '', properties: [] });
  const [retry, setRetry] = useState(0);

  useEffect(() => {
    const controller = new AbortController();
    const timer = window.setTimeout(async () => {
      try {
        const properties = await propertyServiceCatalogService.getHostProperties({ signal: controller.signal });
        if (controller.signal.aborted) return;
        setState({ loading: false, error: '', properties });
        if (properties[0]?.id) navigate(`/host/properties/${properties[0].id}/services`, { replace: true });
      } catch (error) {
        if (!controller.signal.aborted) setState({ loading: false, error: error.message, properties: [] });
      }
    }, 0);
    return () => {
      window.clearTimeout(timer);
      controller.abort();
    };
  }, [navigate, retry]);

  if (state.loading) {
    return <div className="mx-auto max-w-2xl rounded-2xl border border-border/70 bg-white p-8 text-center shadow-xs"><div className="mx-auto h-8 w-8 animate-spin rounded-full border-2 border-primary/20 border-t-primary" /><p className="mt-3 text-sm text-muted-foreground">Đang tải cơ sở lưu trú…</p></div>;
  }

  if (state.properties.length > 0) return <Navigate to={`/host/properties/${state.properties[0].id}/services`} replace />;

  return (
    <section className="mx-auto max-w-2xl rounded-2xl border border-border/70 bg-white p-8 text-center shadow-xs">
      <div className="mx-auto grid h-12 w-12 place-items-center rounded-2xl bg-surface text-primary"><Building2 className="h-6 w-6" /></div>
      <h1 className="mt-4 font-display text-2xl font-bold text-primary">{state.error ? 'Không thể tải cơ sở lưu trú' : 'Bạn chưa có cơ sở lưu trú'}</h1>
      <p role={state.error ? 'alert' : undefined} className="mx-auto mt-2 max-w-md text-sm text-muted-foreground">{state.error || 'Hãy tạo cơ sở lưu trú trước khi quản lý danh mục dịch vụ.'}</p>
      {state.error && <button type="button" onClick={() => { setState((current) => ({ ...current, loading: true })); setRetry((current) => current + 1); }} className="mt-5 inline-flex items-center gap-2 rounded-xl bg-primary px-4 py-2.5 text-sm font-semibold text-primary-foreground"><RefreshCw className="h-4 w-4" /> Thử lại</button>}
    </section>
  );
}
