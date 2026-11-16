import { Link } from 'react-router-dom';
import { PropertyCarousel } from './PropertyCarousel';
import { propertyService } from '../../services/propertyService';
import { useCatalogResource } from '../../hooks/useCatalogResource';

export function CuratedStays() {
    const { status, result, error, retry } = useCatalogResource((options) => propertyService.getFeaturedProperties(options));
    return <section className="bg-background px-6 py-16 md:px-10"><div className="mx-auto max-w-[1360px]">
        <div className="mb-8 flex flex-wrap items-end justify-between gap-4">
            <div><p className="text-xs uppercase tracking-[0.22em] text-accent">StayHub</p><h2 className="mt-2 font-display text-3xl text-primary">Khám phá chỗ nghỉ</h2></div>
            <Link to="/properties" className="text-sm font-semibold text-primary">Khám phá tất cả →</Link>
        </div>
        {status === 'loading' && <p role="status" className="rounded-3xl bg-surface p-12">Đang tải chỗ nghỉ...</p>}
        {status === 'error' && <div role="alert" className="rounded-3xl border border-border p-8"><p>{error.message}</p><button className="mt-3 text-primary underline" onClick={retry}>Thử lại</button></div>}
        {status === 'ready' && <>
            {result.source === 'mock' && <p className="mb-4 text-xs text-amber-800">Dữ liệu giả lập DEV.</p>}
            {result.data.length ? <PropertyCarousel properties={result.data} /> : <p className="rounded-3xl bg-surface p-12 text-center">Chưa có cơ sở lưu trú để hiển thị.</p>}
        </>}
    </div></section>;
}
