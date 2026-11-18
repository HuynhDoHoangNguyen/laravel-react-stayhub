import { LayoutGrid, List } from 'lucide-react';

export function ResultsHeader({ meta, sortBy, onSortChange, view, onViewChange }) {
    return <div className="mb-6 flex flex-wrap items-end justify-between gap-4">
        <div aria-live="polite">
            <h2 className="font-display text-2xl text-ink">{meta ? `${meta.total.toLocaleString('vi-VN')} phòng phù hợp` : 'Danh sách phòng'}</h2>
            {meta && <p className="mt-1 text-sm text-muted-foreground">{meta.from === null ? 'Không có phòng trên trang này.' : `Hiển thị ${meta.from}–${meta.to} trên tổng số ${meta.total} kết quả`}</p>}
        </div>
        <div className="flex flex-wrap items-center gap-3">
            <label className="text-sm">Sắp xếp <select value={sortBy} onChange={e => onSortChange(e.target.value)} className="ml-2 rounded-xl border border-border bg-card p-2">
                {!['price_asc', 'price_desc'].includes(sortBy) && <option value={sortBy}>Giá trị không hợp lệ</option>}
                <option value="price_asc">Giá tăng dần</option><option value="price_desc">Giá giảm dần</option>
            </select></label>
            <div className="inline-flex rounded-xl border border-border bg-card">
                {[['grid', LayoutGrid, 'Xem dạng lưới'], ['list', List, 'Xem dạng danh sách']].map(([value, Icon, label]) => <button key={value} type="button" aria-label={label} aria-pressed={view === value} onClick={() => onViewChange(value)} className={`rounded-xl p-2 ${view === value ? 'bg-primary text-primary-foreground' : ''}`}><Icon className="h-5 w-5" aria-hidden /></button>)}
            </div>
        </div>
    </div>;
}
