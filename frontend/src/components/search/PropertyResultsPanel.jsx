import { useCatalogResource } from '../../hooks/useCatalogResource';
import { propertyService } from '../../services/propertyService';
import { ResultsHeader } from './ResultsHeader';
import { ResultsGrid } from './ResultsGrid';
import { EmptyState } from './EmptyState';
import { Pagination } from './Pagination';

export function PropertyResultsPanel({ params, keyword = '', guestCount = null, onPageChange, onShowRooms, view, onViewChange }) {
    const trimmedKeyword = (keyword ?? '').trim();
    const parsedGuests = guestCount != null && Number.isInteger(Number(guestCount)) && Number(guestCount) >= 1
        ? Number(guestCount)
        : null;
    const hasFilter = Boolean(trimmedKeyword) || parsedGuests !== null;
    const { status, result, error, retry } = useCatalogResource(options =>
        propertyService.searchPaginatedProperties({
            ...params,
            keyword: trimmedKeyword,
            guest_count: parsedGuests,
        }, options)
    );
    const items = result?.data ?? [];
    const meta = result?.meta;

    return <div className="grid grid-cols-1 items-start gap-8 lg:grid-cols-[280px_minmax(0,1fr)_340px]">
        <aside className="min-w-0 rounded-3xl bg-card p-5 ring-1 ring-black/[0.04] lg:sticky lg:top-[120px]">
            <h2 className="text-[15px] font-semibold">Khám phá cơ sở lưu trú</h2>
            <p className="mt-3 text-sm leading-relaxed text-muted-foreground">
                {hasFilter
                    ? `Đang lọc cơ sở lưu trú${trimmedKeyword ? ` tại "${trimmedKeyword}"` : ''}${parsedGuests ? ` cho từ ${parsedGuests} khách/phòng` : ''}. Để lọc chi tiết theo khoảng giá, loại phòng và tiện nghi, hãy chuyển sang tab Phòng.`
                    : 'Danh sách cơ sở sử dụng phân trang riêng. Bộ lọc giá, sức chứa, loại phòng, tiện nghi và đánh giá của tab Phòng được giữ trên địa chỉ nhưng không áp dụng tại đây.'}
            </p>
            <button type="button" onClick={onShowRooms} className="mt-4 rounded-full bg-primary px-4 py-2 text-sm text-primary-foreground">Chuyển sang lọc phòng</button>
        </aside>
        <section aria-label="Danh sách cơ sở lưu trú" className="min-w-0 space-y-6">
            <ResultsHeader meta={meta} kind="properties" view={view} onViewChange={onViewChange} />
            {result?.source === 'mock' && <p role="status" className="text-sm text-amber-800">Dữ liệu giả lập DEV.</p>}
            {status === 'loading' && <p role="status" className="text-sm">Đang tải cơ sở lưu trú…</p>}
            {status === 'error' && <div role="alert"><EmptyState title="Không tải được cơ sở lưu trú" message={error.message} onRetry={retry} /></div>}
            {status !== 'error' && <ResultsGrid properties={items} isLoading={status === 'loading'} view={view} />}
            {status === 'ready' && items.length === 0 && (
                <EmptyState
                    title={hasFilter ? 'Không tìm thấy cơ sở lưu trú phù hợp' : 'Trang này chưa có cơ sở lưu trú'}
                    message={hasFilter
                        ? `Không có cơ sở lưu trú nào khớp với điều kiện tìm kiếm${trimmedKeyword ? ` "${trimmedKeyword}"` : ''}${parsedGuests ? ` (${parsedGuests} khách)` : ''}.`
                        : 'Bạn có thể quay lại trang đầu để tiếp tục khám phá.'}
                    onRetry={() => onPageChange(1)}
                    actionLabel="Về trang đầu"
                />
            )}
            {meta && <Pagination currentPage={meta.current_page} totalPages={meta.last_page} onPageChange={onPageChange} />}
        </section>
        <aside aria-label="Thông tin danh sách cơ sở" className="min-w-0 rounded-3xl bg-card p-5 ring-1 ring-black/[0.04] lg:sticky lg:top-[120px]">
            <h2 className="text-[15px] font-semibold">Thông tin lưu trú</h2>
            <p className="mt-3 text-sm text-muted-foreground">Giá từ mỗi đêm là giá tham khảo. Danh sách chưa kiểm tra phòng trống theo ngày; cơ sở chưa có giá vẫn được hiển thị.</p>
        </aside>
    </div>;
}
