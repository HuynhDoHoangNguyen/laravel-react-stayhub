import { useEffect, useState, useSyncExternalStore } from 'react';
import { useSearchParams } from 'react-router-dom';
import { FilterSidebar } from '../../components/search/FilterSidebar';
import { ResultsHeader } from '../../components/search/ResultsHeader';
import { ResultsGrid } from '../../components/search/ResultsGrid';
import { SearchSummaryBar } from '../../components/search/SearchSummaryBar';
import { roomSearchService, readRoomSearch, updateRoomSearch, createRoomSearchLoader } from '../../services/roomSearchService';
import {
  isAvailabilitySearch,
  parseAvailabilitySearchParams,
  searchAvailableRooms,
} from '../../services/roomAvailabilitySearchService';

function useRoomRequest(request) {
  const [loader] = useState(createRoomSearchLoader);
  const state = useSyncExternalStore(loader.subscribe, loader.getSnapshot);
  useEffect(() => { loader.load(request); return () => loader.cancel(); }, [loader, request]);
  return { ...state, retry: () => loader.load(request) };
}
const getOptions = (signal) => roomSearchService.filterOptions({ signal });

// Availability Room Results: executes real room search with dates, guests and keyword
function AvailabilityRoomResults({ search, navigate }) {
  const { params, checkIn, checkOut, adults, children } = parseAvailabilitySearchParams(search);
  const [request] = useState(() => (signal) => searchAvailableRooms(search, { signal }));
  const results = useRoomRequest(request);
  const options = useRoomRequest(getOptions);
  const [view, setView] = useState('grid');
  const errors = results.error?.response?.data?.errors ?? {};
  const meta = results.value?.meta;
  const change = (patch) => navigate(updateRoomSearch(search, patch));

  const forwardParams = new URLSearchParams({
    startDate: checkIn,
    endDate: checkOut,
    checkIn,
    checkOut,
    adults: String(adults),
    children: String(children),
  }).toString();

  return (
    <div className="grid items-start gap-8 lg:grid-cols-[280px_minmax(0,1fr)]">
      <FilterSidebar
        params={params}
        options={options.value}
        optionsLoading={options.loading}
        optionsError={options.error}
        onRetryOptions={options.retry}
        errors={errors}
        onApply={change}
        onReset={() => change({ min_price: null, max_price: null, guest_count: null, min_rating: null, room_type_ids: [], amenity_ids: [], sort: null, per_page: null })}
      />
      <section aria-label="Kết quả tìm phòng trống" className="min-w-0">
        <ResultsHeader
          meta={meta}
          sortBy={params.sort ?? 'price_asc'}
          onSortChange={(sort) => change({ sort })}
          view={view}
          onViewChange={setView}
        />
        {results.loading && <p role="status" className="mb-4 text-sm">Đang kiểm tra phòng trống khả dụng…</p>}
        {results.error && (
          <div role="alert" className="mb-6 rounded-2xl border border-red-200 bg-red-50 p-5 text-red-700">
            <p>{results.error.response?.status === 422 ? 'Thông tin tìm kiếm chưa hợp lệ. Vui lòng kiểm tra ngày và số khách.' : 'Không thể tải danh sách phòng khả dụng. Vui lòng thử lại.'}</p>
            {Object.entries(errors).map(([key, messages]) => (
              <p key={key} className="mt-2 text-sm">{key}: {Array.isArray(messages) ? messages.join(' ') : String(messages)}</p>
            ))}
            <button type="button" onClick={results.retry} className="mt-3 font-semibold underline">Thử lại</button>
          </div>
        )}
        {!results.error && (
          <ResultsGrid
            rooms={results.value?.data ?? []}
            isLoading={results.loading}
            view={view}
            searchParams={forwardParams}
            isAvailableDate={true}
          />
        )}
        {results.value?.data.length === 0 && (
          <div className="rounded-3xl border border-border bg-card p-10 text-center">
            <h3 className="font-display text-xl">{meta?.total > 0 ? 'Trang này không có kết quả' : 'Không có phòng trống phù hợp'}</h3>
            <p className="mt-3 text-sm text-muted-foreground">
              {meta?.total > 0
                ? 'Hãy quay lại trang đầu của danh sách.'
                : 'Không tìm thấy phòng trống khả dụng trong khoảng thời gian đã chọn. Bạn có thể đổi ngày hoặc giảm bớt bộ lọc.'}
            </p>
            {meta?.total > 0 && (
              <button type="button" onClick={() => change({ page: 1 })} className="mt-4 text-primary underline">Về trang đầu</button>
            )}
          </div>
        )}
        {meta && meta.last_page > 1 && (
          <nav aria-label="Phân trang phòng" className="mt-8 flex items-center justify-center gap-4">
            <button type="button" disabled={meta.current_page <= 1} onClick={() => change({ page: meta.current_page - 1 })} className="rounded-full border border-border px-4 py-2 disabled:opacity-40">Trang trước</button>
            <span className="text-sm">Trang {meta.current_page} / {meta.last_page}</span>
            <button type="button" disabled={meta.current_page >= meta.last_page} onClick={() => change({ page: meta.current_page + 1 })} className="rounded-full border border-border px-4 py-2 disabled:opacity-40">Trang sau</button>
          </nav>
        )}
      </section>
    </div>
  );
}

// Keyed by URL so history navigation starts with the correct form and no stale results.
function RoomResults({ search, navigate }) {
  const parsed = readRoomSearch(search);
  const [request] = useState(() => (signal) => roomSearchService.list(search, { signal }));
  const results = useRoomRequest(request);
  const options = useRoomRequest(getOptions);
  const [view, setView] = useState('grid');
  const errors = results.error?.response?.data?.errors ?? {};
  const meta = results.value?.meta;
  const change = (patch) => navigate(updateRoomSearch(search, patch));
  return (
    <div className="grid items-start gap-8 lg:grid-cols-[280px_minmax(0,1fr)]">
      <FilterSidebar
        params={parsed.params}
        options={options.value}
        optionsLoading={options.loading}
        optionsError={options.error}
        onRetryOptions={options.retry}
        errors={errors}
        onApply={change}
        onReset={() => change({ min_price: null, max_price: null, guest_count: null, min_rating: null, room_type_ids: [], amenity_ids: [], sort: null, per_page: null })}
      />
      <section aria-label="Kết quả lọc phòng" className="min-w-0">
        <ResultsHeader meta={meta} sortBy={parsed.params.sort ?? 'price_asc'} onSortChange={(sort) => change({ sort })} view={view} onViewChange={setView} />
        {results.loading && <p role="status" className="mb-4 text-sm">Đang tải phòng…</p>}
        {results.error && (
          <div role="alert" className="mb-6 rounded-2xl border border-red-200 bg-red-50 p-5 text-red-700">
            <p>{results.error.response?.status === 422 ? 'Bộ lọc không hợp lệ. Vui lòng kiểm tra các trường bên dưới.' : 'Không tải được danh sách phòng. Vui lòng thử lại.'}</p>
            {Object.entries(errors).filter(([key]) => !['min_price', 'max_price', 'guest_count', 'min_rating', 'room_type_ids', 'amenity_ids'].some((field) => key === field || key.startsWith(`${field}.`))).map(([key, messages]) => <p key={key} className="mt-2 text-sm">{key}: {Array.isArray(messages) ? messages.join(' ') : String(messages)}</p>)}
            <button type="button" onClick={results.retry} className="mt-3 font-semibold underline">Thử lại</button>
          </div>
        )}
        {!results.error && <ResultsGrid rooms={results.value?.data ?? []} isLoading={results.loading} view={view} />}
        {results.value?.data.length === 0 && (
          <div className="rounded-3xl border border-border bg-card p-10 text-center">
            <h3 className="font-display text-xl">{meta?.total > 0 ? 'Trang này không có kết quả' : 'Không có phòng phù hợp'}</h3>
            <p className="mt-3 text-sm text-muted-foreground">{meta?.total > 0 ? 'Hãy quay lại trang đầu của danh sách.' : 'Bạn có thể mở rộng khoảng giá hoặc bỏ bớt bộ lọc.'}</p>
            {meta?.total > 0 && <button type="button" onClick={() => change({ page: 1 })} className="mt-4 text-primary underline">Về trang đầu</button>}
          </div>
        )}
        {meta && meta.last_page > 1 && (
          <nav aria-label="Phân trang phòng" className="mt-8 flex items-center justify-center gap-4">
            <button type="button" disabled={meta.current_page <= 1} onClick={() => change({ page: meta.current_page - 1 })} className="rounded-full border border-border px-4 py-2 disabled:opacity-40">Trang trước</button>
            <span className="text-sm">Trang {meta.current_page} / {meta.last_page}</span>
            <button type="button" disabled={meta.current_page >= meta.last_page} onClick={() => change({ page: meta.current_page + 1 })} className="rounded-full border border-border px-4 py-2 disabled:opacity-40">Trang sau</button>
          </nav>
        )}
      </section>
    </div>
  );
}

export default function SearchResultsPage() {
  const [searchParams, setSearchParams] = useSearchParams();
  const search = searchParams.toString();
  const hasDates = isAvailabilitySearch(search);
  const parsedAvailability = hasDates ? parseAvailabilitySearchParams(search) : null;
  const unsupported = hasDates ? [] : readRoomSearch(search).unsupported;

  return (
    <>
      <SearchSummaryBar
        city={searchParams.get('city') ?? searchParams.get('keyword') ?? ''}
        startDate={searchParams.get('startDate') ?? searchParams.get('check_in_date') ?? ''}
        endDate={searchParams.get('endDate') ?? searchParams.get('check_out_date') ?? ''}
        roomsCount={searchParams.get('roomsCount') ?? 1}
        adults={searchParams.get('adults') ?? 2}
        children={searchParams.get('children') ?? 0}
      />
      <div className="mx-auto max-w-7xl px-4 py-10 sm:px-6">
        <header className="mb-8">
          <h1 className="font-display text-3xl text-ink md:text-4xl">
            {hasDates ? 'Phòng trống khả dụng' : 'Tìm phòng phù hợp'}
          </h1>
          <p className="mt-3 max-w-3xl text-sm text-muted-foreground">
            {hasDates
              ? `Danh sách các phòng còn trống sẵn sàng đón khách từ ngày ${parsedAvailability?.checkIn} đến ngày ${parsedAvailability?.checkOut}.`
              : 'Lọc theo giá mỗi đêm, sức chứa, loại phòng, tiện nghi và đánh giá của cơ sở. Danh sách chưa kiểm tra phòng trống theo ngày; giá hiển thị là giá tham khảo.'}
          </p>
        </header>
        {unsupported.length ? (
          <section role="alert" className="rounded-3xl border border-border bg-card p-8">
            <h2 className="font-display text-xl">Tìm kiếm theo ngày hoặc địa điểm chưa được hỗ trợ</h2>
            <p className="mt-3 text-sm text-muted-foreground">
              Địa chỉ hiện tại có bộ lọc chưa được hỗ trợ ({unsupported.join(', ')}). Chưa có kết quả xác nhận khả dụng cho yêu cầu này.
            </p>
            <button
              type="button"
              className="mt-5 rounded-full bg-primary px-5 py-3 font-semibold text-primary-foreground"
              onClick={() => setSearchParams(updateRoomSearch(search, {}, { clearUnsupported: true }))}
            >
              Chuyển sang lọc phòng cơ bản
            </button>
          </section>
        ) : hasDates ? (
          <AvailabilityRoomResults key={search} search={search} navigate={setSearchParams} />
        ) : (
          <RoomResults key={search} search={search} navigate={setSearchParams} />
        )}
      </div>
    </>
  );
}
