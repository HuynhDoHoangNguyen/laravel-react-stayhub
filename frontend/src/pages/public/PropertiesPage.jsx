import { Link, useSearchParams } from 'react-router-dom';
import { useCatalogResource } from '../../hooks/useCatalogResource';
import { propertyService } from '../../services/propertyService';
import { catalogParams } from '../../services/catalog';
import { PropertyCard } from '../../components/landing/PropertyCard';

export default function PropertiesPage() {
    const [search] = useSearchParams();
    const params = catalogParams(search.toString());
    if (!params) return <div className="mx-auto max-w-3xl px-6 py-16"><h1 className="font-display text-3xl text-primary">Tham số danh sách không hợp lệ</h1>
        <p className="my-4">Trang phải là số nguyên dương, số cơ sở mỗi trang từ 1 đến 24. Danh sách này chưa lọc theo ngày hoặc số khách.</p><Link to="/properties" className="text-primary underline">Về danh sách cơ sở</Link></div>;
    return <PropertyList key={`${params.page}:${params.per_page}`} params={params} />;
}

function PropertyList({ params }) {
    const { status, result, error, retry } = useCatalogResource((options) => propertyService.getProperties(params, options));
    const [search, setSearch] = useSearchParams();
    const go = (page) => { const next = new URLSearchParams(search); next.set('page', page); setSearch(next); };
    return <div className="mx-auto max-w-[1360px] px-6 py-10 md:px-10">
        <h1 className="font-display text-4xl text-primary">Khám phá cơ sở lưu trú</h1>
        <p className="mt-3 text-sm text-muted-foreground">Giá mỗi đêm để tham khảo; chưa xác nhận phòng trống theo ngày.</p>
        {status === 'loading' && <p role="status" className="py-16">Đang tải danh sách...</p>}
        {status === 'error' && <div role="alert" className="my-8 rounded-2xl border border-border p-6"><p>{error.message}</p><button onClick={retry} className="mt-3 text-primary underline">Thử lại</button></div>}
        {status === 'ready' && <>
            {result.source === 'mock' && <p className="my-4 text-sm text-amber-800">Đang xem dữ liệu giả lập DEV.</p>}
            <p role="status" className="my-6 text-sm text-muted-foreground">{result.meta.total} cơ sở · Trang {result.meta.current_page}/{result.meta.last_page}</p>
            {result.data.length ? <div className="grid gap-8 sm:grid-cols-2 lg:grid-cols-3">{result.data.map((property) => <PropertyCard key={property.id} property={property} />)}</div>
                : <p className="rounded-3xl bg-surface p-10 text-center">Trang này chưa có cơ sở lưu trú. <Link className="text-primary underline" to="/properties">Về trang đầu</Link></p>}
            <nav aria-label="Phân trang cơ sở lưu trú" className="mt-8 flex flex-wrap gap-4">
                <button disabled={params.page <= 1} onClick={() => go(params.page - 1)} className="rounded-xl border border-border px-5 py-2 disabled:opacity-40">Trang trước</button>
                <button disabled={params.page >= result.meta.last_page} onClick={() => go(params.page + 1)} className="rounded-xl border border-border px-5 py-2 disabled:opacity-40">Trang sau</button>
            </nav>
        </>}
    </div>;
}
