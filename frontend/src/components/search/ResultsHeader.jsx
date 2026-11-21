import { LayoutGrid, List } from 'lucide-react';

export function ResultsHeader({ meta, sortBy, onSortChange, view, onViewChange, kind = 'rooms', availability = false }) {
    const unit = kind === 'properties' ? 'cơ sở lưu trú' : 'phòng phù hợp';
    return <div id="results-header" className="mb-6 flex scroll-mt-28 flex-wrap items-end justify-between gap-4">
        <div aria-live="polite">
            <h2 className="font-display text-[26px] leading-tight text-ink md:text-[30px]">{meta ? meta.total.toLocaleString('vi-VN') + ' ' + unit : kind === 'properties' ? 'Danh sách cơ sở lưu trú' : 'Danh sách phòng'}</h2>
            {meta && <p className="mt-1 text-[12.5px] text-muted-foreground">{meta.from == null ? 'Trang ' + meta.current_page + ' / ' + meta.last_page : 'Hiển thị ' + meta.from + '–' + meta.to + ' trên tổng số ' + meta.total.toLocaleString('vi-VN') + ' kết quả'}</p>}
        </div>
        <div className="flex flex-wrap items-center gap-3">
            {kind === 'rooms' && <label className="inline-flex items-center gap-2 text-[12.5px] text-muted-foreground">Sắp xếp
                <span className="relative">
                    <select value={sortBy} onChange={event => onSortChange(event.target.value)} className="max-w-full appearance-none rounded-xl border border-border bg-card py-2 pl-3 pr-9 text-[13px] font-medium text-ink focus:border-primary focus:outline-primary">
                        {!(availability ? ['price_asc', 'price_desc', 'newest'] : ['price_asc', 'price_desc']).includes(sortBy) && <option value={sortBy}>Giá trị không hợp lệ</option>}
                        <option value="price_asc">Giá: Thấp đến Cao</option><option value="price_desc">Giá: Cao đến Thấp</option>
                        {availability && <option value="newest">Mới nhất</option>}
                    </select>
                    <span aria-hidden className="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground">▾</span>
                </span>
            </label>}
            <div className="inline-flex overflow-hidden rounded-xl border border-border bg-card">
                {[['grid', LayoutGrid, 'Xem dạng lưới'], ['list', List, 'Xem dạng danh sách']].map(([value, Icon, label]) => <button key={value} type="button" aria-label={label} aria-pressed={view === value} onClick={() => onViewChange(value)}
                    className={['grid h-9 w-9 place-items-center transition-colors', view === value ? 'bg-primary text-primary-foreground' : 'text-ink hover:bg-surface'].join(' ')}><Icon className="h-4 w-4" aria-hidden /></button>)}
            </div>
        </div>
    </div>;
}
