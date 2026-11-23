import { useId, useRef, useState } from 'react';
import * as Dialog from '@radix-ui/react-dialog';
import { Flag, Paperclip, X } from 'lucide-react';
import { normalizeApiError } from '../../api/apiError.js';
import { Button } from '../ui/button.jsx';

const fieldClass = 'mt-1.5 w-full rounded-lg border border-border bg-background px-3 py-2 text-sm focus-visible:outline-2 focus-visible:outline-primary aria-invalid:border-destructive';
const sizeFormat = new Intl.NumberFormat('vi-VN', { maximumFractionDigits: 1 });

function evidenceError(file) {
  if (!file) return '';
  if (!/\.(pdf|jpe?g|png)$/i.test(file.name)) return 'Chọn tệp PDF, JPG hoặc PNG.';
  if (file.size < 1 || file.size > 2 * 1024 * 1024) return 'Tệp phải có dung lượng từ 1 byte đến 2 MiB.';
  return '';
}

export function HostReportDialog({ bookingCode, propertyName, reasons, onSubmit, focusFallback }) {
  const id = useId();
  const trigger = useRef(null);
  const fileInput = useRef(null);
  const sending = useRef(false);
  const [open, setOpen] = useState(false);
  const [busy, setBusy] = useState(false);
  const [reason, setReason] = useState('');
  const [description, setDescription] = useState('');
  const [evidence, setEvidence] = useState(null);
  const [errors, setErrors] = useState({});

  function changeOpen(next) {
    if (sending.current && !next) return;
    if (next) {
      setReason('');
      setDescription('');
      setEvidence(null);
      setErrors({});
    }
    setOpen(next);
  }

  async function submit(event) {
    event.preventDefault();
    if (sending.current) return;
    const form = event.currentTarget;
    const length = Array.from(description.trim()).length;
    const fieldErrors = {
      reason: reasons.some(({ value }) => value === reason) ? '' : 'Vui lòng chọn lý do báo cáo.',
      description: length < 20 ? 'Mô tả cần ít nhất 20 ký tự.' : length > 1000 ? 'Mô tả tối đa 1.000 ký tự.' : '',
      evidence: evidenceError(evidence),
    };
    const field = Object.keys(fieldErrors).find((name) => fieldErrors[name]);
    if (field) {
      setErrors(fieldErrors);
      form.elements.namedItem(field)?.focus();
      return;
    }
    sending.current = true;
    setBusy(true);
    setErrors({});
    try {
      await onSubmit({ reason, description, evidence });
    } catch (failure) {
      const error = normalizeApiError(failure);
      if (!error.isCancelled) {
        const field = ['reason', 'description', 'evidence'].find((name) => error.fields[name]?.length);
        setErrors({
          reason: error.fields.reason?.[0],
          description: error.fields.description?.[0],
          evidence: error.fields.evidence?.[0],
          form: field ? '' : error.status === 429 ? 'Bạn gửi quá nhanh. Vui lòng đợi một phút rồi thử lại.' : error.message,
        });
        if (field) form.elements.namedItem(field)?.focus();
      }
    } finally {
      sending.current = false;
      setBusy(false);
    }
  }

  return (
    <Dialog.Root open={open} onOpenChange={changeOpen}>
      <Dialog.Trigger asChild>
        <Button ref={trigger} variant="outline"><Flag aria-hidden="true" /> Báo cáo sự cố với Host</Button>
      </Dialog.Trigger>
      <Dialog.Portal>
        <Dialog.Overlay className="fixed inset-0 z-50 bg-black/55" />
        <Dialog.Content
          onCloseAutoFocus={(event) => {
            if (!trigger.current?.isConnected) {
              event.preventDefault();
              focusFallback();
            }
          }}
          className="fixed left-1/2 top-1/2 z-50 max-h-[90dvh] w-[calc(100%_-_2rem)] max-w-lg -translate-x-1/2 -translate-y-1/2 overflow-y-auto rounded-2xl border border-border bg-card p-5 shadow-xl focus:outline-none sm:p-6"
        >
          <div className="flex items-start justify-between gap-4">
            <div className="min-w-0">
              <Dialog.Title className="text-xl font-semibold text-primary">Báo cáo sự cố với Host</Dialog.Title>
              <Dialog.Description className="mt-1 break-words text-sm text-muted-foreground">
                {bookingCode} · {propertyName}
              </Dialog.Description>
            </div>
            <Dialog.Close asChild>
              <Button type="button" variant="ghost" size="icon" disabled={busy} aria-label="Đóng"><X aria-hidden="true" /></Button>
            </Dialog.Close>
          </div>
          <form noValidate onSubmit={submit} className="mt-5 space-y-4">
            <div>
              <label htmlFor={`${id}-reason`} className="text-sm font-medium">Lý do báo cáo</label>
              <select id={`${id}-reason`} name="reason" required className={fieldClass} value={reason}
                aria-invalid={Boolean(errors.reason)} aria-describedby={errors.reason ? `${id}-reason-error` : undefined}
                onChange={(event) => {
                  setReason(event.target.value);
                  setErrors((previous) => ({ ...previous, reason: '' }));
                }}>
                <option value="">Chọn lý do</option>
                {reasons.map(({ value, label }) => <option key={value} value={value}>{label}</option>)}
              </select>
              {errors.reason && <p role="alert" id={`${id}-reason-error`} className="mt-1 text-sm text-destructive">{errors.reason}</p>}
            </div>
            <div>
              <label htmlFor={`${id}-description`} className="text-sm font-medium">Mô tả sự việc</label>
              <textarea id={`${id}-description`} name="description" required rows={4} className={fieldClass} value={description}
                aria-invalid={Boolean(errors.description)} aria-describedby={errors.description ? `${id}-description-help ${id}-description-error` : `${id}-description-help`}
                onChange={(event) => {
                  setDescription(event.target.value);
                  setErrors((previous) => ({ ...previous, description: '' }));
                }} />
              <p id={`${id}-description-help`} className="mt-1 text-xs text-muted-foreground">20–1.000 ký tự. Mô tả sự việc và thời điểm xảy ra.</p>
              {errors.description && <p role="alert" id={`${id}-description-error`} className="mt-1 text-sm text-destructive">{errors.description}</p>}
            </div>
            <div className="rounded-xl border border-dashed border-border bg-muted/30 p-3">
              <label htmlFor={`${id}-evidence`} className="flex items-center gap-2 text-sm font-medium">
                <Paperclip className="size-4" aria-hidden="true" /> Chứng cứ đính kèm
              </label>
              <p id={`${id}-evidence-help`} className="mt-1 text-xs text-muted-foreground">Tùy chọn · Một tệp PDF, JPG hoặc PNG · Tối đa 2 MiB</p>
              <input ref={fileInput} id={`${id}-evidence`} name="evidence" type="file" accept=".pdf,.jpeg,.jpg,.png"
                className={`${fieldClass} file:mr-2 file:rounded-md file:border-0 file:bg-primary/10 file:px-2 file:py-1 file:font-medium file:text-primary`}
                aria-invalid={Boolean(errors.evidence)}
                aria-describedby={`${id}-evidence-help${errors.evidence ? ` ${id}-evidence-error` : ''}`}
                onChange={(event) => {
                  const file = event.target.files?.[0] ?? null;
                  setEvidence(file);
                  setErrors((previous) => ({ ...previous, evidence: evidenceError(file) }));
                }} />
              {evidence && <div className="mt-2 flex flex-wrap items-center justify-between gap-2">
                <span className="min-w-0 text-xs text-muted-foreground [overflow-wrap:anywhere]">
                  {evidence.name} · {evidence.size < 1024 ? `${evidence.size} B` : `${sizeFormat.format(evidence.size / 1024)} KiB`}
                </span>
                <Button size="sm" type="button" variant="ghost" onClick={() => {
                  fileInput.current.value = '';
                  setEvidence(null);
                  setErrors((previous) => ({ ...previous, evidence: '' }));
                  fileInput.current.focus();
                }}>Bỏ tệp</Button>
              </div>}
              {errors.evidence && <p role="alert" id={`${id}-evidence-error`} className="mt-1 text-sm text-destructive">{errors.evidence}</p>}
            </div>
            {errors.form && <p role="alert" className="text-sm text-destructive">{errors.form}</p>}
            <div className="flex flex-wrap justify-end gap-2">
              <Dialog.Close asChild><Button type="button" variant="outline" disabled={busy}>Hủy</Button></Dialog.Close>
              <Button type="submit" disabled={busy}>{busy ? 'Đang kiểm tra kết quả…' : 'Gửi báo cáo'}</Button>
            </div>
          </form>
        </Dialog.Content>
      </Dialog.Portal>
    </Dialog.Root>
  );
}
