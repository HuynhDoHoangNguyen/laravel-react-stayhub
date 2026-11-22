import { Button } from '../ui/button.jsx';

const fieldClass = 'min-w-0 rounded-lg border border-input bg-background px-2.5 py-2 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring';
const dates = [['from_date', 'Từ ngày'], ['to_date', 'Đến ngày']];

export default function AdminDateRangeFilter({ id, value, fields = {}, onChange, onApply, onReset, dateFields }) {
    return (
        <form className="rounded-xl border border-border bg-card p-3 sm:p-4" onSubmit={(event) => { event.preventDefault(); onApply(); }}>
            <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                {dateFields && <div className="grid min-w-0 content-start gap-1.5">
                    <label className="text-xs font-semibold" htmlFor={`${id}-date-field`}>Loại ngày</label>
                    <select id={`${id}-date-field`} className={fieldClass} value={value.date_field}
                        aria-invalid={Boolean(fields.date_field)} aria-describedby={fields.date_field ? `${id}-date-field-error` : undefined}
                        onChange={(event) => onChange('date_field', event.target.value)}>
                        {Object.entries(dateFields).map(([key, label]) => <option key={key} value={key}>{label}</option>)}
                    </select>
                    {fields.date_field && <p id={`${id}-date-field-error`} className="text-xs text-destructive">{fields.date_field.join(' ')}</p>}
                </div>}
                {dates.map(([key, label]) => <div key={key} className="grid min-w-0 content-start gap-1.5">
                    <label className="text-xs font-semibold" htmlFor={`${id}-${key}`}>{label}</label>
                    <input id={`${id}-${key}`} className={fieldClass} type="date" min="1000-01-01" max="9999-12-31" value={value[key]}
                        aria-invalid={Boolean(fields[key])} aria-describedby={fields[key] ? `${id}-${key}-error` : undefined}
                        onChange={(event) => onChange(key, event.target.value)} />
                    {fields[key] && <p id={`${id}-${key}-error`} className="text-xs text-destructive">{fields[key].join(' ')}</p>}
                </div>)}
                <div className="flex flex-wrap items-start gap-2 sm:self-end">
                    <Button type="submit">Áp dụng</Button>
                    <Button type="button" variant="outline" onClick={onReset}>Đặt lại</Button>
                </div>
            </div>
        </form>
    );
}
