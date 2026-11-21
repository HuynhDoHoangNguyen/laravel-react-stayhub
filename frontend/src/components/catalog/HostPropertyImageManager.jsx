import { useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';
import { ImagePlus, Images, Star, Trash2, Upload } from 'lucide-react';
import { hostPropertyService } from '../../services/hostPropertyService';
import CatalogImage from './CatalogImage';

export default function HostPropertyImageManager({ propertyId, onImagesChanged }) {
    const [attempt, setAttempt] = useState(0);
    const [state, setState] = useState({ status: 'loading', images: [] });
    const [file, setFile] = useState(null);
    const [preview, setPreview] = useState('');
    const [error, setError] = useState('');
    const [pending, setPending] = useState(false);
    const [uncertain, setUncertain] = useState(false);
    const [deleting, setDeleting] = useState(null);
    const active = useRef(true);
    const locked = useRef(false);
    const upload = useRef(null);
    const input = useRef(null);
    const title = useRef(null);
    const deleteTrigger = useRef(null);
    const confirmButton = useRef(null);
    const focusAfterReload = useRef(false);
    const button = 'inline-flex min-h-10 items-center justify-center gap-2 rounded-xl border px-3 py-2 text-sm font-medium shadow-sm transition hover:shadow-md focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 motion-reduce:transition-none';
    useEffect(() => {
        if (deleting !== null) confirmButton.current?.focus();
    }, [deleting]);
    useEffect(() => {
        if (focusAfterReload.current && state.status !== 'loading') {
            focusAfterReload.current = false;
            title.current?.focus();
        }
    }, [state.status]);
    useEffect(() => {
        active.current = true;
        return () => { active.current = false; upload.current?.abort(); };
    }, []);
    useEffect(() => () => { if (preview) URL.revokeObjectURL(preview); }, [preview]);
    useEffect(() => {
        const controller = new AbortController();
        let current = true;
        hostPropertyService.images(propertyId, { signal: controller.signal }).then(
            (images) => { if (current) setState({ status: 'ready', images }); },
            (failure) => {
                if (!current) return;
                const status = failure.response?.status || failure.status;
                const messages = { 401: 'Phiên đăng nhập đã hết hạn.', 403: 'Bạn không có quyền quản lý ảnh.',
                    404: 'Không tìm thấy cơ sở lưu trú.' };
                setState({ status: 'error', images: [], error: messages[status] || 'Không thể tải danh sách ảnh. Vui lòng thử lại.' });
            },
        );
        return () => { current = false; controller.abort(); };
    }, [propertyId, attempt]);

    function choose(event) {
        const selected = event.target.files?.[0];
        setError(''); setFile(null); setPreview('');
        if (!selected) return;
        if (!['image/jpeg', 'image/png', 'image/webp'].includes(selected.type) || selected.size > 5 * 1024 * 1024) {
            setError('Chỉ nhận ảnh JPG, PNG hoặc WebP tối đa 5 MiB.');
            event.target.value = '';
            return;
        }
        setFile(selected);
        setPreview(URL.createObjectURL(selected));
    }

    async function submit(event) {
        event.preventDefault();
        if (locked.current || uncertain || !file || state.status !== 'ready') return;
        locked.current = true; setPending(true); setError('');
        upload.current = new AbortController();
        try {
            await hostPropertyService.uploadImage(propertyId, file, { signal: upload.current.signal });
            if (!active.current) return;
            toast.success('Tải ảnh cơ sở lưu trú thành công.');
            onImagesChanged();
        } catch (failure) {
            if (!active.current) return;
            const status = failure.response?.status || failure.status;
            setUncertain(Boolean(failure.outcomeUnknown));
            const messages = { 401: 'Phiên đăng nhập đã hết hạn. Vui lòng đăng nhập lại.',
                403: 'Bạn không có quyền upload ảnh.', 404: 'Không tìm thấy cơ sở lưu trú.',
                409: 'Cơ sở đã đạt giới hạn 20 ảnh.', 419: 'Phiên bảo mật đã hết hạn. Vui lòng gửi lại.',
                422: 'Ảnh không hợp lệ. Chỉ nhận JPG, PNG, WebP tối đa 5 MiB và 4096 × 4096 pixel.' };
            setError(failure.outcomeUnknown
                ? 'Chưa xác định được kết quả upload. Vui lòng kiểm tra danh sách ảnh trước khi gửi lại.'
                : messages[status] || 'Không thể upload ảnh. Vui lòng thử lại.');
        } finally {
            if (active.current) { setPending(false); locked.current = false; }
        }
    }

    async function mutate(kind, imageId) {
        if (locked.current || uncertain || state.status !== 'ready') return;
        locked.current = true; setPending(true); setError('');
        upload.current = new AbortController();
        try {
            const options = { signal: upload.current.signal };
            if (kind === 'primary') {
                await hostPropertyService.makePrimaryImage(propertyId, imageId, options);
                if (!active.current) return;
                toast.success('Đã cập nhật ảnh đại diện cơ sở.');
            } else {
                const result = await hostPropertyService.deleteImage(propertyId, imageId, options);
                if (!active.current) return;
                if (result.cleanup_status === 'failed') toast.warning('Đã xóa ảnh khỏi danh sách nhưng chưa dọn được tệp. Cần xử lý cleanup thủ công.');
                else toast.success('Đã xóa ảnh khỏi cơ sở lưu trú.');
            }
            onImagesChanged();
        } catch (failure) {
            if (!active.current) return;
            const status = failure.response?.status || failure.status;
            setUncertain(Boolean(failure.outcomeUnknown) || status === 404);
            const messages = { 401: 'Phiên đăng nhập đã hết hạn.', 403: 'Bạn không có quyền quản lý ảnh.',
                404: 'Không tìm thấy hình ảnh cơ sở lưu trú. Vui lòng tải lại danh sách.',
                409: 'Ảnh không có URL công khai hợp lệ để làm ảnh đại diện.',
                419: 'Phiên bảo mật đã hết hạn. Vui lòng xác nhận lại.', 422: 'Dữ liệu thao tác ảnh không hợp lệ.' };
            setError(failure.outcomeUnknown
                ? 'Chưa xác định được kết quả thao tác ảnh. Vui lòng kiểm tra danh sách trước khi gửi lại.'
                : messages[status] || 'Không thể thực hiện thao tác ảnh. Vui lòng thử lại.');
        } finally {
            if (active.current) { setPending(false); locked.current = false; }
        }
    }

    function reload() {
        focusAfterReload.current = true;
        setDeleting(null);
        setUncertain(false); setError(''); setFile(null); setPreview('');
        if (input.current) input.current.value = '';
        setState({ status: 'loading', images: [] }); setAttempt((value) => value + 1);
    }

    return <section aria-labelledby="image-manager-title" aria-busy={pending || state.status === 'loading'} className="min-w-0 space-y-5 rounded-2xl border border-border bg-card p-5 shadow-sm sm:p-6">
        <div className="flex flex-wrap items-start justify-between gap-3">
            <div><h2 ref={title} tabIndex={-1} id="image-manager-title" className="flex items-center gap-2 rounded-lg text-lg font-semibold text-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary"><Images size={20} aria-hidden="true" />Hình ảnh cơ sở</h2>
                <p className="mt-1 text-sm text-muted-foreground">Chọn ảnh đại diện để cơ sở dễ nhận diện hơn.</p></div>
            {state.status === 'ready' && <span className="rounded-full bg-surface px-3 py-1.5 text-xs font-medium text-primary">{state.images.length} ảnh hiển thị</span>}
        </div>
        {state.status === 'loading' && <p role="status" className="rounded-xl bg-surface p-5 text-sm text-muted-foreground">Đang tải danh sách ảnh...</p>}
        {state.status === 'error' && <div role="alert" className="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800"><p>{state.error}</p><button type="button" onClick={reload} className={button + ' mt-3 border-border bg-card text-primary'}>Thử tải lại ảnh</button></div>}
        {state.status === 'ready' && <>
            {!state.images.length && <div className="flex flex-col items-center gap-2 rounded-2xl border border-dashed border-border bg-surface/50 px-5 py-8 text-center"><ImagePlus size={30} className="text-primary/60" aria-hidden="true" /><p className="font-medium text-primary">Chưa có hình ảnh cơ sở</p><p className="text-sm text-muted-foreground">Thêm ảnh đầu tiên bằng biểu mẫu bên dưới.</p></div>}
            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                {state.images.map((image, index) => <figure key={image.id} data-image-id={image.id} className={'min-w-0 overflow-hidden rounded-2xl border bg-card shadow-sm ' + (image.is_primary ? 'border-primary/40 ring-1 ring-primary/10' : 'border-border')}>
                    <div className="relative aspect-[4/3] overflow-hidden bg-surface"><CatalogImage src={image.url} alt={'Ảnh cơ sở ' + image.id} />
                        {image.is_primary && <span className="absolute left-3 top-3 inline-flex items-center gap-1.5 rounded-full bg-primary px-3 py-1.5 text-xs font-semibold text-primary-foreground shadow-md"><Star size={13} aria-hidden="true" />Ảnh đại diện</span>}
                    </div>
                    <figcaption className="px-4 pt-3 text-sm font-medium text-foreground">{image.is_primary ? 'Ảnh đại diện cơ sở' : 'Ảnh cơ sở ' + (index + 1)}</figcaption>
                    <div className="flex flex-wrap gap-2 p-4 pt-3">
                        {!image.is_primary && <button type="button" disabled={pending || uncertain || deleting !== null}
                            onClick={() => mutate('primary', image.id)} className={button + ' flex-1 border-border bg-surface/60 text-primary hover:bg-surface'}><Star size={15} aria-hidden="true" />Đặt đại diện</button>}
                        <button type="button" aria-label={'Xóa ảnh cơ sở ' + (index + 1)} aria-expanded={deleting === image.id} disabled={pending || uncertain || deleting !== null} onClick={(event) => { deleteTrigger.current = event.currentTarget; setDeleting(image.id); }}
                            className={button + ' border-red-200 bg-red-50 text-red-700 hover:bg-red-100'}><Trash2 size={15} aria-hidden="true" />Xóa</button>
                    </div>
                    {deleting === image.id && <div role="group" aria-label="Xác nhận xóa ảnh" className="space-y-3 border-t border-red-100 bg-red-50/70 p-4 text-sm">
                        <p className="font-medium text-red-800">Xóa ảnh này khỏi cơ sở?</p>
                        <div className="flex flex-wrap gap-2">
                            <button ref={confirmButton} type="button" disabled={pending || uncertain} onClick={() => mutate('delete', image.id)}
                                className={button + ' border-red-700 bg-red-700 text-white hover:bg-red-800'}>{pending ? 'Đang xử lý...' : 'Xác nhận xóa'}</button>
                            <button type="button" disabled={pending || uncertain} onClick={() => {
                                setDeleting(null);
                                requestAnimationFrame(() => { if (deleteTrigger.current?.isConnected) deleteTrigger.current.focus(); else title.current?.focus(); });
                            }} className={button + ' border-border bg-card text-primary'}>Hủy</button>
                        </div>
                    </div>}
                </figure>)}
            </div>
        </>}
        {error && <p id="image-upload-error" role="alert" className="rounded-xl border border-red-200 bg-red-50 p-4 text-sm leading-relaxed text-red-800">{error}</p>}
        <form onSubmit={submit} className="rounded-2xl border border-dashed border-primary/25 bg-surface/40 p-4 sm:p-5">
            <fieldset disabled={pending || uncertain || deleting !== null || state.status !== 'ready'} className="grid min-w-0 items-start gap-4 disabled:opacity-60 sm:grid-cols-[minmax(0,1fr)_auto]">
                <div className="min-w-0 space-y-3"><label htmlFor="property-image" className="flex items-center gap-2 text-sm font-semibold text-primary"><Upload size={16} aria-hidden="true" />Thêm ảnh cơ sở</label>
                <p id="property-image-hint" className="text-xs leading-5 text-muted-foreground">JPG, PNG hoặc WebP · Tối đa 5 MiB và 4096 × 4096 pixel mỗi ảnh. Tối đa 20 ảnh mỗi cơ sở, tải từng ảnh.</p>
                <input ref={input} id="property-image" name="image" type="file" accept="image/jpeg,image/png,image/webp"
                    onChange={choose} aria-describedby={'property-image-hint' + (error ? ' image-upload-error' : '')} className="block w-full min-w-0 max-w-full text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-card file:px-3 file:py-2 file:font-medium file:text-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary" />
                {file && preview && <img src={preview} alt="Ảnh xem trước" className="max-h-48 max-w-full rounded-xl border border-border object-contain shadow-sm" />}</div>
                <button type="submit" disabled={!file} className={button + ' border-primary bg-primary px-4 text-primary-foreground hover:bg-primary/90'}>
                    <Upload size={15} aria-hidden="true" />
                    {pending ? 'Đang tải ảnh...' : 'Tải ảnh lên'}
                </button>
            </fieldset>
        </form>
        {uncertain && <button type="button" onClick={reload} className={button + ' border-border bg-card text-primary'}>Kiểm tra danh sách ảnh</button>}
    </section>;
}
