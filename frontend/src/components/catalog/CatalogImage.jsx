import { useState } from 'react';
import { ImageOff } from 'lucide-react';

export default function CatalogImage({ src, alt, className = '' }) {
    const [failed, setFailed] = useState(null);
    return src && failed !== src
        ? <img src={src} alt={alt} loading="lazy" onError={() => setFailed(src)} className={`h-full w-full object-cover ${className}`} />
        : <div role="img" aria-label={src ? 'Ảnh chưa khả dụng' : 'Chưa có ảnh'} className={`flex h-full min-h-40 w-full flex-col items-center justify-center gap-2 bg-surface text-muted-foreground ${className}`}>
            <ImageOff className="h-8 w-8" aria-hidden /><span className="text-xs">{src ? 'Ảnh chưa khả dụng' : 'Chưa có ảnh'}</span>
        </div>;
}
