import { useEffect, useId, useRef, useState } from 'react';
import { hostRoomImageService } from '../../services/hostRoomImageService';
import { normalizeApiError } from '../../api/apiError';
import CatalogImage from './CatalogImage';

export function HostRoomImageView({ state, file, preview, pending, uncertain, error, success,
    inputRef, onChoose, onSubmit, onReload, deleting = null, onDeleteRequest, onDeleteCancel, onPrimary, onDeleteConfirm, warning, titleRef }) {
    const id = useId();
    const disabled = pending || uncertain || state.status !== 'ready';
    return <section aria-labelledby={id + '-title'} aria-busy={pending || state.status === 'loading'}
        className="min-w-0 space-y-5 rounded-2xl border border-border bg-card p-4 sm:p-6">
        <div className="flex flex-wrap items-center justify-between gap-3">
            <h2 ref={titleRef} tabIndex={-1} id={id + '-title'} className="text-xl font-semibold">Hình ảnh phòng</h2>
            <button type="button" disabled={pending || state.status === 'loading'} onClick={onReload}
                className="rounded-xl border border-border px-3 py-2 text-sm text-primary disabled:opacity-50">Tải lại danh sách</button>
        </div>
        {success && <p role="status" className="text-sm text-primary">{success}</p>}
        {warning && <p role="status" className="rounded-xl bg-muted p-3 text-sm">{warning}</p>}
        {state.status === 'loading' && <p role="status">Đang tải danh sách ảnh phòng...</p>}
        {state.status === 'error' && <p role="alert">{state.message}</p>}
        {state.status === 'ready' && <>
            <p className="text-sm text-muted-foreground">{state.images.length ? state.images.length + ' ảnh đang hiển thị.' : 'Chưa có hình ảnh phòng.'}</p>
            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                {state.images.map((image, index) => <figure key={image.id} className="min-w-0 space-y-2">
                    <div className="aspect-[4/3] overflow-hidden rounded-xl bg-muted">
                        <CatalogImage src={image.url} alt={'Ảnh phòng ' + (index + 1)} />
                    </div>
                    {image.is_primary && <figcaption className="text-sm font-medium text-primary">Ảnh đại diện</figcaption>}
                    <div className="flex flex-wrap gap-2">
                        {!image.is_primary && <button type="button" disabled={disabled || deleting !== null}
                            onClick={() => onPrimary(image.id)} className="rounded-xl border border-border px-3 py-2 text-sm text-primary disabled:opacity-50">Đặt làm ảnh đại diện</button>}
                        <button type="button" disabled={disabled || deleting !== null}
                            onClick={event => onDeleteRequest(image.id, event.currentTarget)} className="rounded-xl border border-border px-3 py-2 text-sm text-red-700 disabled:opacity-50">Xóa ảnh</button>
                    </div>
                    {deleting === image.id && <div role="group" aria-label={'Xác nhận xóa ảnh ' + (index + 1)} className="space-y-3 rounded-xl bg-muted p-3">
                        <p className="text-sm">Xóa ảnh này khỏi phòng?{image.is_primary ? ' Ảnh còn lại sẽ được chọn làm đại diện nếu có.' : ''}</p>
                        <div className="flex flex-wrap gap-2">
                            <button type="button" autoFocus disabled={disabled} onClick={() => onDeleteConfirm(image.id)}
                                className="rounded-xl bg-primary px-3 py-2 text-sm text-primary-foreground disabled:opacity-50">{pending ? 'Đang xử lý...' : 'Xác nhận xóa'}</button>
                            <button type="button" disabled={disabled} onClick={onDeleteCancel}
                                className="rounded-xl border border-border px-3 py-2 text-sm disabled:opacity-50">Hủy</button>
                        </div>
                    </div>}
                </figure>)}
            </div>
        </>}
        <p id={id + '-help'} className="text-sm text-muted-foreground">Mỗi lần một ảnh JPG, PNG hoặc WebP, tối đa 5 MiB và 4096 × 4096 pixel. Tối đa 20 ảnh mỗi phòng.</p>
        {error && <p id={id + '-error'} role="alert" className="text-sm text-red-700">{error}</p>}
        {uncertain && <div className="space-y-3 rounded-xl bg-muted p-4">
            <p className="text-sm">Vui lòng kiểm tra danh sách ảnh trước khi gửi tiếp để tránh tải trùng.</p>
            <button type="button" disabled={pending || state.status === 'loading'} onClick={onReload}
                className="rounded-xl border border-border px-3 py-2 text-sm text-primary disabled:opacity-50">Kiểm tra danh sách ảnh</button>
        </div>}
        <form onSubmit={onSubmit}>
            <fieldset disabled={disabled || deleting !== null} className="min-w-0 space-y-4 disabled:opacity-60">
                <label htmlFor={id + '-file'} className="block text-sm font-medium">Chọn ảnh phòng</label>
                <input ref={inputRef} id={id + '-file'} type="file" name="image" accept="image/jpeg,image/png,image/webp"
                    onChange={onChoose} aria-invalid={Boolean(error)}
                    aria-describedby={id + '-help' + (error ? ' ' + id + '-error' : '')}
                    className="block w-full min-w-0 max-w-full text-sm file:mr-3 file:rounded-xl file:border-0 file:bg-muted file:px-3 file:py-2" />
                {file && preview && <figure className="space-y-2">
                    <img src={preview} alt="Ảnh phòng xem trước" className="max-h-64 max-w-full rounded-xl object-contain" />
                    <figcaption className="break-all text-sm text-muted-foreground">{file.name}</figcaption>
                </figure>}
                <button type="submit" disabled={!file} className="rounded-xl bg-primary px-4 py-3 text-sm font-medium text-primary-foreground shadow-sm disabled:opacity-50">
                    {pending ? 'Đang tải ảnh...' : 'Tải ảnh lên'}
                </button>
            </fieldset>
        </form>
    </section>;
}

export default function HostRoomImageManager({ roomId }) {
    return <ImageManager key={roomId} roomId={roomId} />;
}

function ImageManager({ roomId }) {
    const [state, setState] = useState({ status: 'loading', images: [] });
    const [attempt, setAttempt] = useState(0);
    const [file, setFile] = useState(null);
    const [preview, setPreview] = useState('');
    const [pending, setPending] = useState(false);
    const [uncertain, setUncertain] = useState(false);
    const [error, setError] = useState('');
    const [success, setSuccess] = useState('');
    const [warning, setWarning] = useState('');
    const [deleting, setDeleting] = useState(null);
    const input = useRef(null);
    const active = useRef(false);
    const locked = useRef(false);
    const mutation = useRef(null);
    const deleteTrigger = useRef(null);
    const title = useRef(null);

    useEffect(() => {
        active.current = true;
        return () => { active.current = false; mutation.current?.abort(); };
    }, []);
    useEffect(() => () => { if (preview) URL.revokeObjectURL(preview); }, [preview]);
    useEffect(() => {
        const controller = new AbortController();
        let current = true;
        hostRoomImageService.list(roomId, { signal: controller.signal }).then(
            images => {
                if (!current) return;
                setState({ status: 'ready', images });
                setUncertain(false);
            },
            failure => {
                if (!current) return;
                setState({ status: 'error', images: [], message: normalizeApiError(failure).message });
            },
        );
        return () => { current = false; controller.abort(); };
    }, [roomId, attempt]);

    function clearFile() {
        setFile(null); setPreview('');
        if (input.current) input.current.value = '';
    }
    function reload() {
        if (locked.current) return;
        clearFile(); setError(''); setDeleting(null);
        setState({ status: 'loading', images: [] });
        setAttempt(value => value + 1);
    }
    function choose(event) {
        const selected = event.target.files?.[0];
        setError(''); setSuccess(''); setFile(null); setPreview('');
        if (!selected) return;
        if (!['image/jpeg', 'image/png', 'image/webp'].includes(selected.type)
            || selected.size === 0 || selected.size > 5 * 1024 * 1024) {
            setError('Chỉ nhận ảnh JPG, PNG hoặc WebP tối đa 5 MiB.');
            event.target.value = '';
            return;
        }
        setFile(selected); setPreview(URL.createObjectURL(selected));
    }
    async function submit(event) {
        event.preventDefault();
        if (locked.current || uncertain || deleting !== null || state.status !== 'ready' || !file) return;
        locked.current = true; setPending(true); setError(''); setSuccess(''); setWarning('');
        mutation.current = new AbortController();
        try {
            await hostRoomImageService.upload(roomId, file, { signal: mutation.current.signal });
            if (!active.current) return;
            clearFile();
            setSuccess('Tải ảnh phòng thành công.');
            // Block another upload until the refreshed list confirms current state.
            setUncertain(true);
            setState({ status: 'loading', images: [] });
            setAttempt(value => value + 1);
        } catch (failure) {
            if (!active.current) return;
            const normalized = normalizeApiError(failure);
            setUncertain(Boolean(failure.outcomeUnknown));
            setError(failure.outcomeUnknown ? 'Chưa xác định được kết quả upload.'
                : normalized.fields.image?.join(' ') || normalized.message);
        } finally {
            if (active.current) { setPending(false); locked.current = false; }
        }
    }
    async function mutate(kind, imageId) {
        if (locked.current || uncertain || state.status !== 'ready') return;
        if (kind === 'delete' && deleting !== imageId) return;
        locked.current = true; setPending(true); setError(''); setSuccess(''); setWarning('');
        mutation.current = new AbortController();
        try {
            const options = { signal: mutation.current.signal };
            const result = kind === 'primary' ? await hostRoomImageService.makePrimary(roomId, imageId, options)
                : await hostRoomImageService.deleteImage(roomId, imageId, options);
            if (!active.current) return;
            setState({ status: 'ready', images: kind === 'primary' ? result : result.images });
            setDeleting(null);
            requestAnimationFrame(() => title.current?.focus());
            setSuccess(kind === 'primary' ? 'Đã cập nhật ảnh đại diện phòng.' : 'Đã xóa ảnh khỏi phòng.');
            if (kind === 'delete' && result.cleanup_status === 'failed') {
                setWarning('Ảnh đã được xóa khỏi danh sách nhưng chưa dọn được tệp. Không cần xóa lại; cần kiểm tra cleanup.');
            }
        } catch (failure) {
            if (!active.current) return;
            const normalized = normalizeApiError(failure);
            setUncertain(Boolean(failure.outcomeUnknown) || normalized.status === 404);
            setError(failure.outcomeUnknown ? 'Chưa xác định được kết quả thao tác ảnh.' : normalized.message);
        } finally {
            if (active.current) { setPending(false); locked.current = false; }
        }
    }
    return <HostRoomImageView state={state} file={file} preview={preview} pending={pending} uncertain={uncertain}
        error={error} success={success} warning={warning} deleting={deleting} inputRef={input} titleRef={title} onChoose={choose} onSubmit={submit} onReload={reload}
        onPrimary={imageId => mutate('primary', imageId)} onDeleteConfirm={imageId => mutate('delete', imageId)}
        onDeleteRequest={(imageId, trigger) => { deleteTrigger.current = trigger; setDeleting(imageId); }}
        onDeleteCancel={() => { setDeleting(null); requestAnimationFrame(() => deleteTrigger.current?.focus()); }} />;
}
