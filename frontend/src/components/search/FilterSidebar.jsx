import { useState } from 'react';
import { ChevronDown, SlidersHorizontal, Star } from 'lucide-react';

const vnd = value => `${new Intl.NumberFormat('vi-VN', { maximumFractionDigits: 2 }).format(value)} ₫`;
const ratings = ['', '3', '4', '4.5', '5'];

function FilterSection({ title, children }) {
    return <details open className="group border-b border-border/60 py-5 first:pt-0 last:border-0">
        <summary className="flex cursor-pointer list-none items-center justify-between gap-3 text-xs font-semibold uppercase tracking-widest text-ink [&::-webkit-details-marker]:hidden">
            {title}<ChevronDown className="h-4 w-4 shrink-0 -rotate-90 transition-transform group-open:rotate-0" aria-hidden />
        </summary>
        <div className="mt-4">{children}</div>
    </details>;
}

export function FilterSidebar({ params, options, optionsLoading, optionsError, onRetryOptions, errors = {}, onApply, onReset }) {
    const [draft, setDraft] = useState(() => ({ min_price: params.min_price ?? '', max_price: params.max_price ?? '',
        guest_count: params.guest_count ?? '', min_rating: params.min_rating ?? '',
        room_type_ids: params.room_type_ids ?? [], amenity_ids: params.amenity_ids ?? [] }));
    const setField = (key, value) => setDraft(current => ({ ...current, [key]: value }));
    const fieldErrors = key => Object.entries(errors).filter(([name]) => name === key || name.startsWith(`${key}.`))
        .flatMap(([, messages]) => Array.isArray(messages) ? messages : [messages]);
    const errorText = key => fieldErrors(key).length > 0 && <p id={`${key}-error`} className="mt-2 text-xs text-red-600">{fieldErrors(key).join(' ')}</p>;
    const toggle = (key, value) => setDraft(current => ({ ...current,
        [key]: current[key].includes(value) ? current[key].filter(id => id !== value) : [...current[key], value] }));
    const minimum = /^\d+(?:\.\d{1,2})?$/.test(draft.min_price) ? Math.min(Number(draft.min_price), 9999999999.99) : 0;
    const maximum = /^\d+(?:\.\d{1,2})?$/.test(draft.max_price) ? Math.min(Number(draft.max_price), 9999999999.99) : null;
    // The range is a convenient coarse upper bound. Manual decimal inputs keep the full API domain.
    const ceiling = Math.ceil(Math.max(20000000, minimum + 50000, maximum ?? 0) / 50000) * 50000;
    const selectionCount = draft.room_type_ids.length + draft.amenity_ids.length
        + ['min_price', 'max_price', 'guest_count', 'min_rating'].filter(key => draft[key] !== '').length;

    return <aside aria-label="Bộ lọc phòng" className="flex max-h-[75dvh] flex-col overflow-hidden rounded-3xl border border-border bg-card shadow-[0_10px_40px_-25px_rgba(17,26,19,0.2)] lg:sticky lg:top-24 lg:max-h-[calc(100dvh-7rem)]">
        <div className="flex shrink-0 items-center justify-between gap-3 border-b border-border/60 px-5 py-4">
            <h2 className="flex items-center gap-2 text-xs font-semibold uppercase tracking-widest"><SlidersHorizontal className="h-4 w-4" aria-hidden />Bộ lọc phòng</h2>
            <button type="button" className="whitespace-nowrap text-xs text-primary underline-offset-4 hover:underline" onClick={onReset}>Xóa bộ lọc</button>
        </div>
        <form noValidate onSubmit={event => { event.preventDefault(); onApply(draft); }} className="flex min-h-0 flex-1 flex-col">
            <div data-filter-scroll tabIndex={0} role="region" aria-label="Nội dung bộ lọc, cuộn để xem thêm"
                className="min-h-0 flex-1 overflow-y-auto overscroll-contain px-5 py-5 [scrollbar-gutter:stable] focus-visible:outline-primary">
                <FilterSection title="Giá mỗi đêm">
                    <p className="mb-3 text-sm font-semibold text-primary">{vnd(minimum)} – {maximum === null ? 'Không giới hạn' : vnd(maximum)}</p>
                    <input type="range" aria-label="Điều chỉnh giá tối đa mỗi đêm" min="0" max={ceiling} step="50000"
                        value={Math.max(minimum, maximum ?? ceiling)}
                        onChange={event => setField('max_price', String(Math.max(minimum, Number(event.target.value))))}
                        className="h-2 w-full cursor-pointer accent-primary" />
                    <div className="mt-2 flex justify-between text-[11px] text-muted-foreground"><span>{vnd(0)}</span><span>{vnd(ceiling)}</span></div>
                    <div className="mt-4 grid grid-cols-2 gap-2">
                        {[['min_price', 'Giá tối thiểu / đêm (VND)', 'Tối thiểu (₫)'], ['max_price', 'Giá tối đa / đêm (VND)', 'Tối đa (₫)']].map(([key, label, shortLabel]) => <label key={key} className="min-w-0 rounded-xl border border-border bg-background px-3 py-2">
                            <span className="text-[10px] uppercase tracking-wider text-muted-foreground">{shortLabel}</span>
                            <input id={key} aria-label={label} inputMode="decimal" value={draft[key]} onChange={event => setField(key, event.target.value)}
                                aria-invalid={fieldErrors(key).length > 0} aria-describedby={fieldErrors(key).length ? `${key}-error` : undefined}
                                className="mt-1 w-full min-w-0 bg-transparent text-sm font-medium focus:outline-none" placeholder={key === 'min_price' ? '0' : 'Bất kỳ'} />
                        </label>)}
                    </div>
                    {errorText('min_price')}{errorText('max_price')}
                    <div className="mt-3 flex flex-wrap gap-2">
                        {[['Dưới 1 triệu', '1000000'], ['Dưới 2 triệu', '2000000'], ['Không giới hạn', '']].map(([label, value]) => <button key={label} type="button" onClick={() => setDraft(current => ({ ...current, min_price: '', max_price: value }))}
                            className="rounded-full border border-border px-2.5 py-1 text-[11px] text-muted-foreground hover:border-primary hover:text-primary">{label}</button>)}
                    </div>
                    <p className="mt-3 text-[11px] leading-relaxed text-muted-foreground">Giá tham khảo mỗi đêm. Có thể nhập số lẻ bằng dấu chấm, tối đa 2 chữ số thập phân.</p>
                </FilterSection>
                <FilterSection title="Đánh giá khách hàng">
                    <div className="flex flex-wrap gap-2" role="group" aria-label="Điểm đánh giá tối thiểu của cơ sở">
                        {ratings.map(value => <button key={value} type="button" aria-pressed={draft.min_rating === value} onClick={() => setField('min_rating', value)}
                            className={`inline-flex items-center gap-1 rounded-full border px-3 py-1.5 text-xs transition-colors ${draft.min_rating === value ? 'border-primary bg-primary text-primary-foreground' : 'border-border bg-background hover:border-primary'}`}>
                            {value ? <>{value === '5' ? '5' : `${value}+`}<Star className="h-3 w-3 fill-current" aria-hidden /></> : 'Tất cả'}
                        </button>)}
                    </div>
                    <p className="mt-3 text-[11px] leading-relaxed text-muted-foreground">Điểm trung bình của cơ sở lưu trú (thang 5). Chọn mức sao sẽ loại cơ sở chưa có đánh giá.</p>
                    {errorText('min_rating')}
                </FilterSection>
                <FilterSection title="Sức chứa">
                    <label htmlFor="guest_count" className="text-sm">Số khách mỗi phòng</label>
                    <input id="guest_count" inputMode="numeric" value={draft.guest_count} onChange={event => setField('guest_count', event.target.value)}
                        aria-invalid={fieldErrors('guest_count').length > 0} aria-describedby={fieldErrors('guest_count').length ? 'guest_count-error' : undefined}
                        className="mt-2 w-full rounded-xl border border-border bg-background px-3 py-2 text-sm focus:outline-primary" placeholder="Ví dụ: 2" />
                    {errorText('guest_count')}
                </FilterSection>
                {optionsLoading && <p role="status" className="py-4 text-sm">Đang tải loại phòng và tiện nghi…</p>}
                {optionsError && <div role="alert" className="py-4 text-sm text-red-600">Không tải được danh mục bộ lọc. <button type="button" className="underline" onClick={onRetryOptions}>Thử lại danh mục</button></div>}
                {[['room_type_ids', 'Loại phòng', options?.room_types], ['amenity_ids', 'Tiện nghi trong phòng', options?.amenities]].map(([key, label, items]) => <FilterSection key={key} title={label}>
                    <fieldset disabled={optionsLoading || !options} aria-describedby={fieldErrors(key).length ? `${key}-error` : undefined}>
                        <legend className="sr-only">{label}</legend>
                        <div className="space-y-3">
                            {items?.map(item => <label key={item.id} className="flex cursor-pointer items-start gap-2.5 text-sm leading-relaxed">
                                <input type="checkbox" className="mt-1 accent-primary" checked={draft[key].includes(String(item.id))} onChange={() => toggle(key, String(item.id))} />
                                <span>{key === 'room_type_ids' ? `${item.property.name} · ${item.name}` : item.name}</span>
                            </label>)}
                            {items?.length === 0 && <p className="text-sm text-muted-foreground">Chưa có lựa chọn phù hợp.</p>}
                            {draft[key].filter(value => !items?.some(item => String(item.id) === value)).map(value => <label key={value} className="flex gap-2 text-sm"><input type="checkbox" checked onChange={() => toggle(key, value)} /><span>Mã {value} (không có trong danh mục hiện tại)</span></label>)}
                        </div>
                        <p className="mt-3 text-[11px] text-muted-foreground">{key === 'room_type_ids' ? 'Thuộc một trong các loại đã chọn.' : 'Phải có đủ tất cả tiện nghi đã chọn.'}</p>
                        {errorText(key)}
                    </fieldset>
                </FilterSection>)}
            </div>
            <div className="shrink-0 border-t border-border/60 bg-card px-5 py-4">
                <button type="submit" className="w-full rounded-full bg-primary px-4 py-3 text-sm font-semibold text-primary-foreground">Áp dụng bộ lọc{selectionCount > 0 ? ` (${selectionCount})` : ''}</button>
            </div>
        </form>
    </aside>;
}
