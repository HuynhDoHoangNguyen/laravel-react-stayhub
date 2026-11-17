import { useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';
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
        setDeleting(null);
        setUncertain(false); setError(''); setFile(null); setPreview('');
        if (input.current) input.current.value = '';
        setState({ status: 'loading', images: [] }); setAttempt((value) => value + 1);
    }

    return <section aria-labelledby="image-manager-title" className="space-y-4 rounded-2xl border border-border p-6">
        <h2 id="image-manager-title" className="text-xl font-semibold">Quản lý hình ảnh</h2>
        {state.status === 'loading' && <p role="status">Đang tải danh sách ảnh...</p>}
        {state.status === 'error' && <div role="alert"><p>{state.error}</p><button type="button" onClick={reload} className="mt-3 text-primary underline">Thử tải lại ảnh</button></div>}
        {state.status === 'ready' && <>
            <p>{state.images.length ? state.images.length + ' ảnh đang hiển thị.' : 'Chưa có hình ảnh.'}</p>
            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                {state.images.map((image) => <figure key={image.id} data-image-id={image.id} className="min-w-0 space-y-3">
                    <div className="aspect-[4/3] overflow-hidden rounded-xl"><CatalogImage src={image.url} alt={'Ảnh cơ sở ' + image.id} /></div>
                    {image.is_primary && <figcaption className="mt-2 text-sm">Ảnh đại diện</figcaption>}
                    <div className="flex flex-wrap gap-3">
                        {!image.is_primary && <button type="button" disabled={pending || uncertain || deleting !== null}
                            onClick={() => mutate('primary', image.id)} className="text-primary underline disabled:opacity-60">Đặt làm ảnh đại diện</button>}
                        <button type="button" disabled={pending || uncertain} onClick={() => setDeleting(image.id)}
                            className="text-red-700 underline disabled:opacity-60">Xóa ảnh</button>
                    </div>
                    {deleting === image.id && <div role="group" aria-label="Xác nhận xóa ảnh" className="space-y-3">
                        <p>Bạn xác nhận xóa ảnh này khỏi cơ sở?</p>
                        <div className="flex flex-wrap gap-3">
                            <button type="button" disabled={pending || uncertain} onClick={() => mutate('delete', image.id)}
                                className="rounded-xl border border-border px-3 py-2 disabled:opacity-60">{pending ? 'Đang xử lý...' : 'Xác nhận xóa'}</button>
                            <button type="button" disabled={pending || uncertain} onClick={() => setDeleting(null)}
                                className="rounded-xl border border-border px-3 py-2 disabled:opacity-60">Hủy xóa</button>
                        </div>
                    </div>}
                </figure>)}
            </div>
        </>}
        <p className="text-sm text-muted-foreground">Mỗi lần một ảnh JPG, PNG hoặc WebP, tối đa 5 MiB và 4096 × 4096 pixel. Tối đa 20 ảnh mỗi cơ sở.</p>
        {error && <p id="image-upload-error" role="alert">{error}</p>}
        <form onSubmit={submit} className="space-y-4">
            <fieldset disabled={pending || uncertain || deleting !== null || state.status !== 'ready'} className="space-y-4 disabled:opacity-60">
                <label htmlFor="property-image" className="block">Chọn ảnh cơ sở</label>
                <input ref={input} id="property-image" name="image" type="file" accept="image/jpeg,image/png,image/webp"
                    onChange={choose} aria-describedby={error ? 'image-upload-error' : undefined} className="block w-full min-w-0 max-w-full" />
                {file && preview && <img src={preview} alt="Ảnh xem trước" className="max-h-64 max-w-full rounded-xl object-contain" />}
                <button type="submit" disabled={!file} className="rounded-xl bg-primary px-4 py-3 text-primary-foreground disabled:opacity-60">
                    {pending ? 'Đang tải ảnh...' : 'Tải ảnh lên'}
                </button>
            </fieldset>
        </form>
        {uncertain && <button type="button" onClick={reload} className="text-primary underline">Kiểm tra danh sách ảnh</button>}
    </section>;
}
