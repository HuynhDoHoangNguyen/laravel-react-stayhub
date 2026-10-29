import { useEffect, useRef, useState } from 'react';
import * as Dialog from '@radix-ui/react-dialog';
import CatalogImage from '../catalog/CatalogImage';

export function PropertyGallery({ photos = [], name = 'Chỗ nghỉ' }) {
    const [active, setActive] = useState(0);
    const [open, setOpen] = useState(false);
    const opener = useRef(null);
    const move = (step) => setActive((index) => (index + step + photos.length) % photos.length);
    useEffect(() => {
        if (!open || photos.length < 2) return;
        const key = (event) => {
            if (event.key === 'ArrowRight') setActive((index) => (index + 1) % photos.length);
            if (event.key === 'ArrowLeft') setActive((index) => (index - 1 + photos.length) % photos.length);
        };
        window.addEventListener('keydown', key); return () => window.removeEventListener('keydown', key);
    }, [open, photos.length]);
    if (!photos.length) return <div className="h-72 overflow-hidden rounded-3xl"><CatalogImage alt={name} /></div>;
    const src = photos[active] || photos[0];
    return <section aria-label="Ảnh cơ sở lưu trú" className="space-y-3">
        <button ref={opener} type="button" onClick={() => setOpen(true)} aria-label="Xem ảnh phóng to" className="block aspect-[16/9] w-full overflow-hidden rounded-3xl focus-visible:ring-2 focus-visible:ring-primary"><CatalogImage src={src} alt={`${name} — ảnh ${active + 1}`} /></button>
        {photos.length > 1 && <div className="flex flex-wrap items-center gap-3">
            <button onClick={() => move(-1)} className="rounded-xl border border-border px-4 py-2" aria-label="Ảnh trước">←</button>
            <span className="text-sm" aria-live="polite">{active + 1}/{photos.length}</span>
            <button onClick={() => move(1)} className="rounded-xl border border-border px-4 py-2" aria-label="Ảnh sau">→</button>
            {photos.slice(0, 8).map((photo, index) => <button key={index} onClick={() => setActive(index)} aria-label={`Xem ảnh ${index + 1}`} aria-pressed={active === index} className="h-14 w-20 overflow-hidden rounded-lg border border-border"><CatalogImage src={photo} alt="" /></button>)}
        </div>}
        <Dialog.Root open={open} onOpenChange={setOpen}><Dialog.Portal>
            <Dialog.Overlay className="fixed inset-0 z-50 bg-black/60" />
            <Dialog.Content onCloseAutoFocus={(event) => { event.preventDefault(); opener.current?.focus(); }}
                className="fixed left-1/2 top-1/2 z-50 max-h-[90vh] w-[calc(100%_-_2rem)] max-w-4xl -translate-x-1/2 -translate-y-1/2 space-y-4 overflow-y-auto rounded-3xl bg-background p-6 shadow-xl">
            <Dialog.Title className="pr-10 font-display text-xl text-primary">{name}</Dialog.Title><Dialog.Description className="text-sm text-muted-foreground">Ảnh {active + 1}/{photos.length}</Dialog.Description>
            <Dialog.Close aria-label="Đóng ảnh" className="absolute right-5 top-4 rounded-xl border border-border px-3 py-1 text-xl">×</Dialog.Close>
            <div className="h-[55vh]"><CatalogImage src={src} alt={`${name} — ảnh ${active + 1}`} className="object-contain" /></div>
            {photos.length > 1 && <div className="flex justify-between"><button onClick={() => move(-1)} className="rounded-xl border px-4 py-2">Ảnh trước</button><button onClick={() => move(1)} className="rounded-xl border px-4 py-2">Ảnh sau</button></div>}
        </Dialog.Content></Dialog.Portal></Dialog.Root>
    </section>;
}
