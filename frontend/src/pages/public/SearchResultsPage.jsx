import { useEffect, useState, useSyncExternalStore } from 'react';
import { useSearchParams } from 'react-router-dom';
import { FilterSidebar } from '../../components/search/FilterSidebar';
import { ResultsHeader } from '../../components/search/ResultsHeader';
import { ResultsGrid } from '../../components/search/ResultsGrid';
import { SearchSummaryBar } from '../../components/search/SearchSummaryBar';
import { AssistantPanel } from '../../components/search/AssistantPanel';
import { EmptyState } from '../../components/search/EmptyState';
import { Pagination } from '../../components/search/Pagination';
import { BenefitsStrip } from '../../components/search/BenefitsStrip';
import { PropertyResultsPanel } from '../../components/search/PropertyResultsPanel';
import { readSearchNavigation, changeSearchTab } from '../../utils/searchNavigation';
import { normalizeApiError } from '../../api/apiError';
import { roomSearchService, readRoomSearch, updateRoomSearch, createRoomSearchLoader } from '../../services/roomSearchService';
import { isAvailabilitySearch, parseAvailabilitySearchParams, searchAvailableRooms } from '../../services/roomAvailabilitySearchService';

function useRoomRequest(request) {
    const [loader] = useState(createRoomSearchLoader);
    const state = useSyncExternalStore(loader.subscribe, loader.getSnapshot);
    useEffect(() => { loader.load(request); return () => loader.cancel(); }, [loader, request]);
    return { ...state, retry: () => loader.load(request) };
}
const getOptions = signal => roomSearchService.filterOptions({ signal });

// Keyed by URL so history navigation starts with the correct form and no stale results.
function RoomResults({ search, navigate, view, onViewChange }) {
    const available = isAvailabilitySearch(search);
    const searchUrl = new URLSearchParams(search);
    const hasLocation = Boolean(searchUrl.get('city') || searchUrl.get('keyword') || searchUrl.get('q'));
    const isRoomSearch = available || hasLocation;
    const availability = isRoomSearch ? parseAvailabilitySearchParams(search) : null;
    const params = isRoomSearch ? { ...availability.params,
        room_type_ids: availability.room_type_ids.map(String),
        amenity_ids: (availability.params.amenity_ids ?? []).map(String),
        min_rating: String(availability.min_rating) } : readRoomSearch(search).params;
    const [request] = useState(() => signal => isRoomSearch
        ? searchAvailableRooms(search, { signal }) : roomSearchService.list(search, { signal }));
    const checkoutChildren = available ? Math.min(Math.max(0, Number.isFinite(availability.children) ? availability.children : 0), availability.guests - 1) : 0;
    const forwardParams = available ? new URLSearchParams({ startDate: availability.checkIn, endDate: availability.checkOut,
        checkIn: availability.checkIn, checkOut: availability.checkOut,
        adults: String(availability.guests - checkoutChildren), children: String(checkoutChildren) }).toString() : '';
    const results = useRoomRequest(request);
    const options = useRoomRequest(getOptions);
    const errors = results.error?.response?.data?.errors ?? {};
    const failure = results.error ? normalizeApiError(results.error) : null;
    const meta = results.value?.meta;
    const change = patch => {
        const next = new URLSearchParams(search);
        // Replace legacy array aliases too, so clearing a selection cannot restore it.
        if (Object.hasOwn(patch, 'room_type_ids')) { next.delete('room_type_ids'); next.delete('room_type_id'); }
        if (Object.hasOwn(patch, 'amenity_ids')) next.delete('amenity_ids');
        navigate(updateRoomSearch(next.toString(), patch));
        if (Object.hasOwn(patch, 'page')) document.getElementById('results-header')?.scrollIntoView({
            behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'instant' : 'smooth', block: 'start',
        });
    };
    const reset = () => {
        navigate({});
    };
    return <div className="grid grid-cols-1 items-start gap-8 lg:grid-cols-[280px_minmax(0,1fr)_340px]">
        <FilterSidebar params={params} options={options.value} optionsLoading={options.loading} optionsError={options.error}
            onRetryOptions={options.retry} errors={errors} onApply={change} onReset={reset} />
        <section aria-label="Kết quả lọc phòng" className="min-w-0 space-y-6">
            <ResultsHeader meta={meta} sortBy={params.sort ?? 'price_asc'} availability={available} onSortChange={sort => change({ sort })} view={view} onViewChange={onViewChange} />
            <p className="text-[12.5px] leading-relaxed text-muted-foreground">{available
                ? `Tìm phòng trống từ ${availability.checkIn} đến ${availability.checkOut}, cho ${availability.guests} khách mỗi phòng.`
                : 'Lọc theo giá mỗi đêm, sức chứa, loại phòng, tiện nghi và đánh giá của cơ sở. Giá tham khảo, chưa kiểm tra phòng trống theo ngày.'}</p>
            {results.loading && <p role="status" className="text-sm text-muted-foreground">Đang tải phòng…</p>}
            {results.error && <div role="alert" className="rounded-3xl bg-card ring-1 ring-black/[0.04]">
                <EmptyState title={failure.status === 422 ? 'Bộ lọc không hợp lệ' : 'Không tải được danh sách phòng'}
                    message={failure.status === 422 ? 'Vui lòng kiểm tra các trường trong bộ lọc và bảng tinh chỉnh.' : failure.message}
                    onRetry={results.retry} />
                {Object.entries(errors).filter(([key]) => !['min_price', 'max_price', 'guest_count', 'min_rating', 'room_type_ids', 'amenity_ids'].some(field => key === field || key.startsWith(field + '.')))
                    .map(([key, messages]) => <p key={key} className="break-words px-6 pb-4 text-sm text-red-600">{key}: {Array.isArray(messages) ? messages.join(' ') : String(messages)}</p>)}
            </div>}
            {!results.error && (results.loading || results.value?.data.length > 0) && <ResultsGrid rooms={results.value?.data ?? []} isLoading={results.loading} view={view} searchParams={forwardParams} isAvailableDate={available} />}
            {results.value?.data.length === 0 && <EmptyState
                title={meta.total > 0 ? 'Trang này không có kết quả' : 'Không tìm thấy phòng phù hợp'}
                message={meta.total > 0 ? 'Hãy quay lại trang đầu của danh sách.' : 'Hãy thử nới rộng khoảng giá hoặc giảm bớt tiện nghi yêu cầu.'}
                onRetry={meta.total > 0 ? () => change({ page: 1 }) : reset}
                actionLabel={meta.total > 0 ? 'Về trang đầu' : 'Xóa bộ lọc'} />}
            {meta && <Pagination currentPage={meta.current_page} totalPages={meta.last_page} onPageChange={page => change({ page })} />}
        </section>
        <AssistantPanel params={params} errors={errors} onApply={change} availability={available} />
    </div>;
}

export default function SearchResultsPage() {
    const [searchParams, setSearchParams] = useSearchParams();
    const [view, setView] = useState(() => {
        try { return sessionStorage.getItem('stayhub_search_view') === 'list' ? 'list' : 'grid'; }
        catch { return 'grid'; }
    });
    useEffect(() => {
        try { sessionStorage.setItem('stayhub_search_view', view); }
        catch { /* The view still works when browser storage is unavailable. */ }
    }, [view]);
    const search = searchParams.toString();
    const navigation = readSearchNavigation(search);
    const hasDates = isAvailabilitySearch(navigation.roomSearch);
    const searchUrl = new URLSearchParams(navigation.roomSearch);
    const hasLocation = Boolean(searchUrl.get('city') || searchUrl.get('keyword') || searchUrl.get('q'));
    const isRoomSearch = hasDates || hasLocation;
    const unsupported = navigation.tab === 'rooms' && !isRoomSearch
        ? readRoomSearch(navigation.roomSearch).unsupported : [];
    const switchTab = tab => setSearchParams(changeSearchTab(search, tab));
    return <div className="min-h-screen bg-background">
        <SearchSummaryBar city={searchParams.get('city') ?? searchParams.get('keyword') ?? searchParams.get('q') ?? ''} startDate={searchParams.get('startDate') ?? searchParams.get('check_in_date') ?? ''}
            endDate={searchParams.get('endDate') ?? searchParams.get('check_out_date') ?? ''} roomsCount={searchParams.get('roomsCount') ?? 1}
            adults={searchParams.get('adults') ?? searchParams.get('guest_count') ?? 2} children={searchParams.get('children') ?? 0} />
        <div className="mx-auto max-w-[1440px] px-6 pb-16 pt-8 md:px-10">
            <h1 className="sr-only">Khám phá phòng và cơ sở lưu trú</h1>
            <nav aria-label="Loại kết quả khám phá" className="mb-8 flex flex-wrap gap-3">
                {[['rooms', 'Phòng'], ['properties', 'Cơ sở lưu trú']].map(([tab, label]) => <button key={tab} type="button" aria-current={navigation.tab === tab ? 'page' : undefined}
                    onClick={() => switchTab(tab)} className={['rounded-full border px-5 py-2.5 text-sm font-medium', navigation.tab === tab ? 'border-primary bg-primary text-primary-foreground' : 'border-border bg-card text-ink'].join(' ')}>{label}</button>)}
            </nav>
            {navigation.error ? <section role="alert"><EmptyState title="Tab tìm kiếm không hợp lệ" message={navigation.error} onRetry={() => switchTab('rooms')} actionLabel="Về tab Phòng" /></section>
                : unsupported.length ? <section role="alert" className="rounded-3xl bg-card p-8 ring-1 ring-black/[0.04]">
                <h2 className="font-display text-xl">Tìm kiếm theo ngày hoặc địa điểm chưa được hỗ trợ</h2>
                <p className="mt-3 text-sm text-muted-foreground">Địa chỉ hiện tại có bộ lọc chưa được hỗ trợ ({unsupported.join(', ')}). Chưa có kết quả xác nhận khả dụng cho yêu cầu này.</p>
                <button type="button" className="mt-5 rounded-full bg-primary px-5 py-3 font-semibold text-primary-foreground"
                    onClick={() => setSearchParams(updateRoomSearch(navigation.roomSearch, {}, { clearUnsupported: true }))}>Chuyển sang lọc phòng cơ bản</button>
            </section> : navigation.tab === 'properties' ? navigation.propertyParams
                ? <PropertyResultsPanel key={search} params={navigation.propertyParams} onPageChange={page => { const next = new URLSearchParams(search); next.set('page', String(page)); setSearchParams(next); }}
                    onShowRooms={() => switchTab('rooms')} view={view} onViewChange={setView} />
                : <section role="alert"><EmptyState title="Tham số danh sách cơ sở không hợp lệ" message="Trang phải là số nguyên dương; số cơ sở mỗi trang từ 1 đến 24." /></section>
                : <RoomResults key={navigation.roomSearch} search={navigation.roomSearch} navigate={setSearchParams} view={view} onViewChange={setView} />}
        </div>
        <BenefitsStrip />
    </div>;
}
