import { ShieldCheck } from 'lucide-react';

export default function PropertyVerificationBadge({ verified }) {
    if (verified !== true) return null;

    return <span className="inline-flex items-center gap-1.5 rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-xs font-semibold leading-tight text-emerald-800">
        <ShieldCheck size={14} className="shrink-0" aria-hidden="true" />
        Đã xác minh
    </span>;
}
