import { useState, useEffect, useCallback } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import {
    getHostServices,
    setHostServiceStatus,
    getHostProperties,
} from '../../services/propertyServiceCatalogService';
import { formatCurrency } from '../../utils/formatCurrency';
import { getApiErrorMessage } from '../../utils/getApiErrorMessage';
import LoadingState from '../../components/booking/shared/LoadingState';
import ErrorState from '../../components/booking/shared/ErrorState';
import EmptyState from '../../components/booking/shared/EmptyState';
import Pagination from '../../components/booking/shared/Pagination';

export default function HostServiceListPage() {
    const [searchParams, setSearchParams] = useSearchParams();

    const propIdParam = searchParams.get('property_id') || '';
    const pageParam = parseInt(searchParams.get('page') || '1', 10);

    const [properties, setProperties] = useState([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);
    const [result, setResult] = useState(null);
    const [actionMsg, setActionMsg] = useState(null);
    const [togglingId, setTogglingId] = useState(null);

    // Fetch properties for filter dropdown
    useEffect(() => {
        let isMounted = true;
        getHostProperties()
            .then((res) => {
                if (isMounted) {
                    setProperties(res.data || []);
                }
            })
            .catch(() => {});
        return () => {
            isMounted = false;
        };
    }, []);

    const loadServices = useCallback(async () => {
        setLoading(true);
        setError(null);
        try {
            const params = { page: pageParam };
            if (propIdParam) {
                params.property_id = propIdParam;
            }
            const res = await getHostServices(params);
            setResult(res);
        } catch (err) {
            setError(getApiErrorMessage(err).message);
        } finally {
            setLoading(false);
        }
    }, [propIdParam, pageParam]);

    useEffect(() => {
        let isMounted = true;
        Promise.resolve().then(() => {
            if (isMounted) {
                loadServices();
            }
        });
        return () => {
            isMounted = false;
        };
    }, [loadServices]);

    const handlePropertyFilter = (e) => {
        const next = new URLSearchParams();
        if (e.target.value) next.set('property_id', e.target.value);
        next.set('page', '1');
        setSearchParams(next);
    };

    const handlePageChange = (newPage) => {
        const next = new URLSearchParams(searchParams);
        next.set('page', String(newPage));
        setSearchParams(next);
    };

    const handleToggleStatus = async (service) => {
        setTogglingId(service.id);
        setActionMsg(null);
        try {
            const newStatus = !service.status;
            await setHostServiceStatus(service.id, newStatus);
            setActionMsg({
                type: 'success',
                text: `Đã ${newStatus ? 'bật' : 'tắt'} dịch vụ "${service.name}".`,
            });
            loadServices();
        } catch (err) {
            const parsed = getApiErrorMessage(err);
            setActionMsg({ type: 'danger', text: parsed.message });
        } finally {
            setTogglingId(null);
        }
    };

    return (
        <div className="bk-container">
            <div className="bk-page-header" style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', flexWrap: 'wrap', gap: 12 }}>
                <div>
                    <h1 className="bk-page-title">Danh mục dịch vụ cơ sở</h1>
                    <p className="bk-page-subtitle">
                        Quản lý các dịch vụ đi kèm phục vụ khách lưu trú tại các cơ sở
                    </p>
                </div>

                <div style={{ display: 'flex', gap: 10 }}>
                    <Link to="/host/bookings" className="bk-btn bk-btn-outline bk-btn-sm">
                        Quản lý đặt phòng →
                    </Link>
                    <Link to="/host/services/create" className="bk-btn bk-btn-primary bk-btn-sm">
                        + Tạo dịch vụ mới
                    </Link>
                </div>
            </div>

            {actionMsg && (
                <div className={`bk-alert bk-alert-${actionMsg.type}`}>
                    {actionMsg.text}
                </div>
            )}

            {/* Filter by Property */}
            <div className="bk-card" style={{ marginBottom: 20, padding: 14, display: 'flex', alignItems: 'center', gap: 14 }}>
                <span style={{ fontSize: 14, fontWeight: 600, color: '#334155' }}>
                    Lọc theo cơ sở:
                </span>
                <select
                    className="bk-select"
                    style={{ maxWidth: 300 }}
                    value={propIdParam}
                    onChange={handlePropertyFilter}
                >
                    <option value="">Tất cả cơ sở lưu trú</option>
                    {properties.map((p) => (
                        <option key={p.id} value={p.id}>
                            {p.name}
                        </option>
                    ))}
                </select>
            </div>

            {/* Content Table */}
            {loading && <LoadingState message="Đang tải danh sách dịch vụ..." />}

            {error && (
                <ErrorState
                    title="Lỗi tải dữ liệu"
                    message={error}
                    onRetry={loadServices}
                />
            )}

            {!loading && !error && result && result.data && result.data.length === 0 && (
                <EmptyState
                    title="Chưa có dịch vụ nào"
                    description="Cơ sở lưu trú chưa có dịch vụ nào trong danh mục. Hãy tạo dịch vụ mới."
                    action={
                        <Link to="/host/services/create" className="bk-btn bk-btn-primary bk-btn-sm">
                            + Thêm dịch vụ đầu tiên
                        </Link>
                    }
                />
            )}

            {!loading && !error && result && result.data && result.data.length > 0 && (
                <div className="bk-card" style={{ padding: 0, overflowX: 'auto' }}>
                    <table className="bk-table">
                        <thead>
                            <tr>
                                <th>Tên dịch vụ</th>
                                <th>Cơ sở lưu trú</th>
                                <th>Đơn vị tính</th>
                                <th>Đơn giá</th>
                                <th>Trạng thái</th>
                                <th style={{ textAlign: 'right' }}>Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            {result.data.map((s) => (
                                <tr key={s.id}>
                                    <td>
                                        <strong>{s.name}</strong>
                                        {s.description && (
                                            <div style={{ fontSize: 12, color: '#64748b' }}>
                                                {s.description}
                                            </div>
                                        )}
                                    </td>
                                    <td>{s.property?.name || '---'}</td>
                                    <td>{s.unit}</td>
                                    <td>
                                        <strong>{formatCurrency(s.price)}</strong>
                                    </td>
                                    <td>
                                        <span
                                            className={`bk-status-badge ${
                                                s.status ? 'bk-status-success' : 'bk-status-neutral'
                                            }`}
                                            style={{
                                                color: s.status ? '#16a34a' : '#64748b',
                                                backgroundColor: s.status ? '#dcfce7' : '#f1f5f9',
                                            }}
                                        >
                                            {s.status ? 'Đang bật' : 'Tạm ngưng'}
                                        </span>
                                    </td>
                                    <td style={{ textAlign: 'right', whiteSpace: 'nowrap' }}>
                                        <button
                                            type="button"
                                            className={`bk-btn bk-btn-sm ${
                                                s.status ? 'bk-btn-outline' : 'bk-btn-primary'
                                            }`}
                                            style={{ marginRight: 6 }}
                                            disabled={togglingId === s.id}
                                            onClick={() => handleToggleStatus(s)}
                                        >
                                            {togglingId === s.id ? '...' : (s.status ? 'Tắt' : 'Bật')}
                                        </button>

                                        <Link
                                            to={`/host/services/${s.id}/edit`}
                                            className="bk-btn bk-btn-outline bk-btn-sm"
                                        >
                                            Sửa
                                        </Link>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>

                    {result.meta && (
                        <div style={{ padding: '0 16px 16px 16px' }}>
                            <Pagination
                                currentPage={result.meta.current_page}
                                lastPage={result.meta.last_page}
                                total={result.meta.total}
                                perPage={result.meta.per_page}
                                onPageChange={handlePageChange}
                            />
                        </div>
                    )}
                </div>
            )}
        </div>
    );
}
