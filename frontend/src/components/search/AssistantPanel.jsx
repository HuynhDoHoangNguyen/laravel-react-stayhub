import { SlidersHorizontal, Tag } from 'lucide-react';
import { useState } from 'react';

const suggestions = [
    { label: 'Giá thấp trước', patch: { sort: 'price_asc' } },
    { label: 'Dưới 1 triệu', patch: { min_price: null, max_price: '1000000' } },
    { label: 'Dưới 2 triệu', patch: { min_price: null, max_price: '2000000' } },
    { label: 'Cơ sở được đánh giá 4+', patch: { min_rating: '4' } },
];

// The result view is keyed by URL, keeping applied values consistent with the sidebar.
export function AssistantPanel({ params, onApply, errors = {} }) {
    const [prices, setPrices] = useState(() => ({ min_price: params.min_price ?? '', max_price: params.max_price ?? '' }));
    const fieldErrors = key => Array.isArray(errors[key]) ? errors[key] : [];
    const sort = params.sort ?? 'price_asc';
    return <aside aria-label="Tinh chỉnh tìm kiếm" className="h-fit w-full min-w-0 self-start lg:sticky lg:top-[120px]">
        <div id="stayhub-refine-panel" className="flex flex-col gap-4 rounded-3xl bg-card p-5 shadow-[0_10px_40px_-25px_rgba(17,26,19,0.2)] ring-1 ring-black/[0.04]">
            <header className="flex shrink-0 items-center gap-2">
                <div className="grid h-8 w-8 place-items-center rounded-full bg-primary/10 text-primary"><SlidersHorizontal className="h-4 w-4" aria-hidden /></div>
                <h2 className="text-[15px] font-semibold text-ink">Tinh chỉnh tìm kiếm</h2>
            </header>
            <p className="text-[12.5px] leading-relaxed text-muted-foreground">Điều chỉnh bộ lọc phòng theo giá mỗi đêm và đánh giá của cơ sở lưu trú.</p>
            <form noValidate onSubmit={event => { event.preventDefault(); onApply(prices); }} className="rounded-2xl bg-surface/50 p-4 ring-1 ring-black/[0.04]">
                <p className="text-[13px] font-semibold text-ink">Điều chỉnh nhanh</p>
                <label htmlFor="assistant-sort" className="mt-3 block text-[11px] font-medium uppercase tracking-wider text-muted-foreground">Sắp xếp theo</label>
                <select id="assistant-sort" value={sort} onChange={event => onApply({ sort: event.target.value })}
                    aria-invalid={fieldErrors('sort').length > 0} aria-describedby={fieldErrors('sort').length ? 'assistant-sort-error' : undefined}
                    className="mt-1.5 w-full rounded-xl border border-border bg-card px-3 py-2 text-[13px] text-ink focus:border-primary focus:outline-primary">
                    {!['price_asc', 'price_desc'].includes(sort) && <option value={sort}>Giá trị không hợp lệ</option>}
                    <option value="price_asc">Giá: Thấp đến Cao</option><option value="price_desc">Giá: Cao đến Thấp</option>
                </select>
                {fieldErrors('sort').length > 0 && <p id="assistant-sort-error" className="mt-2 text-xs text-red-600">{fieldErrors('sort').join(' ')}</p>}
                <div className="mt-3 grid grid-cols-2 gap-2">
                    {[['min_price', 'Tối thiểu (₫)'], ['max_price', 'Tối đa (₫)']].map(([key, label]) => <label key={key} className="block min-w-0">
                        <span className="text-[11px] font-medium uppercase tracking-wider text-muted-foreground">{label}</span>
                        <input aria-label={'Tinh chỉnh: ' + label} inputMode="decimal" value={prices[key]}
                            onChange={event => setPrices(current => ({ ...current, [key]: event.target.value }))}
                            aria-invalid={fieldErrors(key).length > 0} aria-describedby={fieldErrors(key).length ? 'assistant-' + key + '-error' : undefined}
                            placeholder={key === 'min_price' ? '0' : 'Bất kỳ'}
                            className="mt-1.5 w-full min-w-0 rounded-xl border border-border bg-card px-3 py-2 text-[13px] text-ink focus:border-primary focus:outline-primary" />
                        {fieldErrors(key).length > 0 && <span id={'assistant-' + key + '-error'} className="mt-2 block text-xs text-red-600">{fieldErrors(key).join(' ')}</span>}
                    </label>)}
                </div>
                <button type="submit" className="mt-3 w-full rounded-full bg-primary py-2.5 text-[12.5px] font-medium text-primary-foreground transition-colors hover:bg-primary-mid">Áp dụng giá</button>
            </form>
            <div>
                <p className="mb-2 flex items-center gap-1.5 text-[12px] font-medium text-muted-foreground"><Tag className="h-3.5 w-3.5" aria-hidden />Gợi ý chọn nhanh</p>
                <div className="flex flex-wrap gap-2">{suggestions.map(item => <button key={item.label} type="button" onClick={() => onApply(item.patch)}
                    className="rounded-full border border-border bg-background px-3 py-1.5 text-[11.5px] text-ink transition-colors hover:border-primary hover:text-primary">{item.label}</button>)}</div>
            </div>
            <p className="text-[11.5px] leading-relaxed text-muted-foreground">Đánh giá tính theo cơ sở. Giá hiển thị là giá tham khảo; danh sách chưa kiểm tra phòng trống theo ngày.</p>
        </div>
    </aside>;
}
