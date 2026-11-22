import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { ArrowRight, Building2, CircleAlert, Plus, RefreshCw } from 'lucide-react';
import { useAuth } from '../../hooks/useAuth';
import { hostPropertyService } from '../../services/hostPropertyService';

export default function HostPropertiesPage() {
    const { user } = useAuth();
    return <HostPropertyList key={user?.id ?? 'guest'} />;
}

function HostPropertyList() {
    const [attempt, setAttempt] = useState(0);
    const [state, setState] = useState({ status: 'loading', properties: [], error: '' });

    useEffect(() => {
        const controller = new AbortController();
        let current = true;
        hostPropertyService.list({ signal: controller.signal }).then(
            (properties) => {
                if (current) setState({ status: 'ready', properties, error: '' });
            },
            (error) => {
                if (!current) return;
                const status = error.response?.status;
                const messages = {
                    401: 'Phiên đăng nhập đã hết hạn. Vui lòng đăng nhập lại.',
                    403: 'Bạn không có quyền xem danh sách cơ sở lưu trú.',
                    404: 'Chưa thể tải danh sách cơ sở lưu trú. Vui lòng thử lại.',
                    422: 'Yêu cầu tải danh sách chưa hợp lệ. Vui lòng thử lại.',
                };
                setState({
                    status: 'error',
                    properties: [],
                    error: messages[status] || 'Không thể tải danh sách cơ sở lưu trú. Vui lòng thử lại.',
                });
            },
        );
        return () => { current = false; controller.abort(); };
    }, [attempt]);

    function retry() {
        setState({ status: 'loading', properties: [], error: '' });
        setAttempt((value) => value + 1);
    }

    const primaryButton = 'inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-primary px-5 py-3 text-sm font-semibold text-primary-foreground shadow-md transition hover:bg-primary/90 hover:shadow-lg focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 motion-reduce:transition-none';
    return <main className="mx-auto w-full max-w-6xl space-y-6 px-4 py-6 sm:px-8 sm:py-8">
        <header className="relative overflow-hidden rounded-3xl border border-border bg-card p-5 shadow-sm sm:p-7">
            <div className="absolute inset-y-0 left-0 w-1 bg-primary" aria-hidden="true" />
            <div className="flex flex-col gap-5 sm:flex-row sm:items-center sm:justify-between">
                <div className="min-w-0"><p className="mb-2 flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-muted-foreground"><Building2 size={15} aria-hidden="true" />Quản lý lưu trú</p>
                    <h1 className="break-words font-display text-2xl text-primary sm:text-3xl">Cơ sở lưu trú của tôi</h1>
                    <p className="mt-2 text-sm leading-6 text-muted-foreground">Quản lý thông tin, hình ảnh và phòng tại từng cơ sở.</p></div>
                <Link to="/host/properties/create" className={primaryButton + ' shrink-0'}><Plus size={18} aria-hidden="true" />Thêm cơ sở</Link>
            </div>
        </header>
        {state.status === 'loading' && <div className="rounded-2xl border border-border bg-card p-6 shadow-sm">
            <p role="status" className="flex items-center gap-2 text-sm text-muted-foreground"><RefreshCw size={16} className="animate-spin motion-reduce:animate-none" aria-hidden="true" />Đang tải danh sách cơ sở lưu trú...</p>
            <div aria-hidden="true" className="mt-5 grid gap-4 sm:grid-cols-2">{[0, 1].map((item) => <div key={item} className="h-36 animate-pulse rounded-2xl bg-surface motion-reduce:animate-none" />)}</div>
        </div>}
        {state.status === 'error' && <div role="alert" className="rounded-2xl border border-red-200 bg-card p-6 shadow-sm">
            <div className="flex items-start gap-3"><CircleAlert size={20} className="mt-0.5 shrink-0 text-red-700" aria-hidden="true" /><div><h2 className="font-semibold text-foreground">Chưa thể tải danh sách</h2><p className="mt-1 text-sm leading-6 text-muted-foreground">{state.error}</p></div></div>
            <button type="button" onClick={retry} className="mt-5 inline-flex min-h-11 items-center gap-2 rounded-xl border border-border bg-surface px-4 py-2 text-sm font-semibold text-primary shadow-sm hover:shadow-md focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary"><RefreshCw size={16} aria-hidden="true" />Thử lại</button>
        </div>}
        {state.status === 'ready' && (state.properties.length
            ? <section aria-labelledby="property-list-title" className="space-y-4">
                <div className="flex flex-wrap items-center justify-between gap-2"><h2 id="property-list-title" className="text-base font-semibold text-primary">Danh sách cơ sở</h2><span className="rounded-full border border-border bg-card px-3 py-1.5 text-xs font-medium text-muted-foreground">{state.properties.length} cơ sở</span></div>
                <ul className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    {state.properties.map((property) => <li key={property.id} className="group flex min-w-0 flex-col rounded-2xl border border-border bg-card p-5 shadow-sm transition-shadow hover:shadow-md motion-reduce:transition-none sm:p-6">
                        <span className="mb-4 flex h-12 w-12 items-center justify-center rounded-2xl border border-primary/10 bg-surface text-primary"><Building2 size={23} aria-hidden="true" /></span>
                        <h3 className="mb-5 break-words text-lg font-semibold leading-7 text-foreground">{property.name}</h3>
                        <div className="mt-auto border-t border-border pt-4"><Link to={'/host/properties/' + property.id} aria-label={'Xem chi tiết cơ sở ' + property.name} className="inline-flex min-h-11 w-full items-center justify-between gap-3 rounded-xl border border-border bg-surface/60 px-4 py-2.5 text-sm font-semibold text-primary shadow-sm transition hover:bg-primary hover:text-primary-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 motion-reduce:transition-none">Xem chi tiết<ArrowRight size={16} className="shrink-0" aria-hidden="true" /></Link></div>
                    </li>)}
                </ul>
            </section>
            : <section className="flex flex-col items-center rounded-3xl border border-dashed border-border bg-card px-5 py-12 text-center shadow-sm">
                <span className="mb-4 flex h-16 w-16 items-center justify-center rounded-2xl bg-surface text-primary"><Building2 size={30} aria-hidden="true" /></span>
                <h2 className="text-lg font-semibold text-primary">Chưa có cơ sở lưu trú</h2><p className="mb-6 mt-2 max-w-sm text-sm leading-6 text-muted-foreground">Thêm cơ sở đầu tiên để bắt đầu quản lý thông tin, hình ảnh và phòng.</p>
                <Link to="/host/properties/create" className={primaryButton}><Plus size={17} aria-hidden="true" />Thêm cơ sở đầu tiên</Link>
            </section>)}
    </main>;
}
