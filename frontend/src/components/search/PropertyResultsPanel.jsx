import { useState } from 'react';
import { SlidersHorizontal, Star } from 'lucide-react';
import { useCatalogResource } from '../../hooks/useCatalogResource';
import { propertyService } from '../../services/propertyService';
import { ResultsHeader } from './ResultsHeader';
import { ResultsGrid } from './ResultsGrid';
import { EmptyState } from './EmptyState';
import { Pagination } from './Pagination';

const ratingOptions = [['', 'Tất cả'], ['3', 'Từ 3 điểm'], ['4', 'Từ 4 điểm'], ['4.5', 'Từ 4,5 điểm']];

export function PropertyResultsPanel({ params, keyword = '', guestCount = null, minRating = '', onPageChange, onFilterChange, view, onViewChange }) {
    const [locationDraft, setLocationDraft] = useState(keyword);
    const [ratingDraft, setRatingDraft] = useState(minRating);
    const parsedGuests = guestCount != null && Number.isInteger(Number(guestCount)) && Number(guestCount) >= 1
        ? Number(guestCount)
        : null;
    const hasFilter = Boolean(keyword || minRating) || parsedGuests !== null;
    const resetFilters = () => {
        setLocationDraft('');
        setRatingDraft('');
        onFilterChange();
    };
    const { status, result, error, retry } = useCatalogResource(options =>
        propertyService.searchPaginatedProperties({
            ...params,
            keyword,
            guest_count: parsedGuests,
            min_rating: minRating,
        }, options)
    );
    const items = result?.data ?? [];
    const meta = result?.meta;
    const outOfRange = meta?.current_page > meta?.last_page;

    return <div className="grid grid-cols-1 items-start gap-8 lg:grid-cols-[280px_minmax(0,1fr)_340px]">
        <aside aria-label="Bộ lọc cơ sở lưu trú" className="min-w-0 rounded-3xl bg-card shadow-[0_10px_40px_-25px_rgba(17,26,19,0.2)] ring-1 ring-black/[0.04] lg:sticky lg:top-[120px]">
            <div className="flex flex-wrap items-center justify-between gap-2 border-b border-border/60 px-5 py-4">
                <h2 className="flex items-center gap-2 text-xs font-semibold uppercase tracking-widest"><SlidersHorizontal className="h-4 w-4" aria-hidden />Bộ lọc cơ sở</h2>
                <button type="button" className="text-xs text-primary underline-offset-4 hover:underline" onClick={resetFilters}>Xóa bộ lọc</button>
            </div>
            <form className="space-y-5 p-5" onSubmit={event => {
                event.preventDefault();
                onFilterChange({ keyword: locationDraft, minRating: ratingDraft });
            }}>
                <label className="block">
                    <span className="text-xs font-semibold uppercase tracking-widest">Địa điểm</span>
                    <input className="mt-2 w-full rounded-xl border border-border bg-background px-3 py-2 text-sm focus:outline-primary"
                        value={locationDraft} onChange={event => setLocationDraft(event.target.value)} maxLength={255} placeholder="Ví dụ: Đà Nẵng" />
                </label>
                <fieldset className="min-w-0 border-t border-border/60 pt-4">
                    <legend className="float-left mb-3 w-full text-xs font-semibold uppercase tracking-widest">Điểm đánh giá của khách</legend>
                    <div className="clear-both flex flex-wrap gap-2">
                        {ratingOptions.map(([value, label]) => <button key={value} type="button" aria-pressed={ratingDraft === value}
                            onClick={() => setRatingDraft(value)}
                            className={`inline-flex items-center gap-1 rounded-full border px-3 py-1.5 text-xs transition-colors ${ratingDraft === value ? 'border-primary bg-primary text-primary-foreground' : 'border-border bg-background hover:border-primary'}`}>
                            {label}{value && <Star className="h-3 w-3 fill-current" aria-hidden />}
                        </button>)}
                    </div>
                </fieldset>
                <button type="submit" className="w-full rounded-full bg-primary px-4 py-2.5 text-sm font-medium text-primary-foreground">Áp dụng bộ lọc</button>
            </form>
        </aside>
        <section aria-label="Danh sách cơ sở lưu trú" className="min-w-0 space-y-6">
            <ResultsHeader meta={meta} kind="properties" view={view} onViewChange={onViewChange} />
            {result?.source === 'mock' && <p role="status" className="text-sm text-amber-800">Dữ liệu giả lập DEV.</p>}
            {status === 'loading' && <p role="status" className="text-sm">Đang tải cơ sở lưu trú…</p>}
            {status === 'error' && <div role="alert"><EmptyState
                title={error.status === 422 ? 'Bộ lọc không hợp lệ' : 'Không tải được cơ sở lưu trú'}
                message={error.status === 422 ? 'Vui lòng kiểm tra địa điểm và điểm đánh giá đã chọn.' : error.message}
                onRetry={error.status === 422 ? resetFilters : retry} actionLabel={error.status === 422 ? 'Xóa bộ lọc' : 'Thử lại'} /></div>}
            {status !== 'error' && <ResultsGrid properties={items} isLoading={status === 'loading'} view={view} />}
            {status === 'ready' && items.length === 0 && (
                <EmptyState
                    title={!outOfRange && hasFilter ? 'Không tìm thấy cơ sở lưu trú phù hợp' : 'Trang này chưa có cơ sở lưu trú'}
                    message={outOfRange ? 'Hãy quay lại trang đầu của danh sách.' : 'Hãy thử thay đổi điều kiện tìm kiếm.'}
                    onRetry={outOfRange ? () => onPageChange(1) : undefined}
                    actionLabel="Về trang đầu"
                />
            )}
            {meta && <Pagination currentPage={meta.current_page} totalPages={meta.last_page} onPageChange={onPageChange} showSinglePage />}
        </section>
        <aside aria-label="Thông tin danh sách cơ sở" className="min-w-0 rounded-3xl bg-card p-5 ring-1 ring-black/[0.04] lg:sticky lg:top-[120px]">
            <h2 className="text-[15px] font-semibold">Thông tin lưu trú</h2>
            <p className="mt-3 text-sm text-muted-foreground">Giá từ mỗi đêm là giá tham khảo. Danh sách chưa kiểm tra phòng trống theo ngày; cơ sở chưa có giá vẫn được hiển thị.</p>
        </aside>
    </div>;
}
