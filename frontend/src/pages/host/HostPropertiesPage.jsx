import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { useAuth } from '../../hooks/useAuth';
import { hostPropertyService } from '../../services/hostPropertyService';

export default function HostPropertiesPage() {
    const { user } = useAuth();
    return <HostPropertyList key={user?.id ?? 'guest'} />;
}

function HostPropertyList() {
    const [attempt, setAttempt] = useState(0);
    const [state, setState] = useState({ status: 'loading', properties: [], error: '' });

    useEffect(() => {
        const controller = new AbortController();
        let current = true;
        hostPropertyService.list({ signal: controller.signal }).then(
            (properties) => {
                if (current) setState({ status: 'ready', properties, error: '' });
            },
            (error) => {
                if (!current) return;
                const status = error.response?.status;
                const messages = {
                    401: 'Phiên đăng nhập đã hết hạn. Vui lòng đăng nhập lại.',
                    403: 'Bạn không có quyền xem danh sách cơ sở lưu trú.',
                    404: 'Chưa thể tải danh sách cơ sở lưu trú. Vui lòng thử lại.',
                    422: 'Yêu cầu tải danh sách chưa hợp lệ. Vui lòng thử lại.',
                };
                setState({
                    status: 'error',
                    properties: [],
                    error: messages[status] || 'Không thể tải danh sách cơ sở lưu trú. Vui lòng thử lại.',
                });
            },
        );
        return () => { current = false; controller.abort(); };
    }, [attempt]);

    function retry() {
        setState({ status: 'loading', properties: [], error: '' });
        setAttempt((value) => value + 1);
    }

    return <main className="mx-auto w-full max-w-5xl px-4 py-8 sm:px-8">
        <h1 className="font-display text-3xl text-primary">Cơ sở lưu trú của tôi</h1>
        <Link to="/host/properties/create" className="mt-4 inline-block rounded-xl bg-primary px-4 py-2 text-primary-foreground">Thêm cơ sở lưu trú</Link>
        {state.status === 'loading' && <p role="status" className="mt-8">Đang tải danh sách cơ sở lưu trú...</p>}
        {state.status === 'error' && <div role="alert" className="mt-8 rounded-2xl border border-border bg-background p-6">
            <p>{state.error}</p>
            <button type="button" onClick={retry} className="mt-4 rounded-xl border border-border px-4 py-2 text-primary focus-visible:ring-2 focus-visible:ring-primary">Thử lại</button>
        </div>}
        {state.status === 'ready' && (state.properties.length
            ? <ul className="mt-8 grid gap-4 sm:grid-cols-2">
                {state.properties.map((property) => <li key={property.id} className="min-w-0 rounded-2xl border border-border bg-background p-6">
                    <h2 className="break-words text-lg font-semibold text-foreground">{property.name}</h2>
                    <Link to={'/host/properties/' + property.id} className="mt-3 inline-block text-primary underline">Xem chi tiết</Link>
                </li>)}
            </ul>
            : <p className="mt-8 rounded-2xl bg-surface p-8 text-center">Bạn chưa có cơ sở lưu trú nào.</p>)}
    </main>;
}
