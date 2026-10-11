import { useState, useEffect, useCallback } from 'react';
import { useSearchParams, useNavigate } from 'react-router-dom';
import { searchRooms } from '../../services/searchService';
import { formatCurrency } from '../../utils/formatCurrency';
import { getApiErrorMessage } from '../../utils/getApiErrorMessage';
import LoadingState from '../../components/booking/shared/LoadingState';
import ErrorState from '../../components/booking/shared/ErrorState';
import EmptyState from '../../components/booking/shared/EmptyState';
import Pagination from '../../components/booking/shared/Pagination';

function getTodayString(offsetDays = 0) {
    const d = new Date();
    d.setDate(d.getDate() + offsetDays);
    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return `${y}-${m}-${day}`;
}

export default function SearchResultPage() {
    const [searchParams, setSearchParams] = useSearchParams();
    const navigate = useNavigate();

    // Query params with safe defaults
    const checkIn = searchParams.get('check_in_date') || getTodayString(1);
    const checkOut = searchParams.get('check_out_date') || getTodayString(3);
    const guests = searchParams.get('guest_count') || '2';
    const keyword = searchParams.get('keyword') || '';
    const minPrice = searchParams.get('min_price') || '';
    const maxPrice = searchParams.get('max_price') || '';
    const sort = searchParams.get('sort') || 'price_asc';
    const page = parseInt(searchParams.get('page') || '1', 10);

    // Form inputs state
    const [formInputs, setFormInputs] = useState({
        check_in_date: checkIn,
        check_out_date: checkOut,
        guest_count: guests,
        keyword,
        min_price: minPrice,
        max_price: maxPrice,
        sort,
    });

    const [loading, setLoading] = useState(false);
    const [error, setError] = useState(null);
    const [result, setResult] = useState(null);

    const performSearch = useCallback(async () => {
        setLoading(true);
        setError(null);
        try {
            const params = {
                check_in_date: checkIn,
                check_out_date: checkOut,
                guest_count: Number(guests),
                page,
            };
            if (keyword) params.keyword = keyword;
            if (minPrice) params.min_price = Number(minPrice);
            if (maxPrice) params.max_price = Number(maxPrice);
            if (sort) params.sort = sort;

            const res = await searchRooms(params);
            setResult(res);
        } catch (err) {
            setError(getApiErrorMessage(err).message);
        } finally {
            setLoading(false);
        }
    }, [checkIn, checkOut, guests, keyword, minPrice, maxPrice, sort, page]);

    useEffect(() => {
        let isMounted = true;
        Promise.resolve().then(() => {
            if (isMounted) {
                performSearch();
            }
        });
        return () => {
            isMounted = false;
        };
    }, [performSearch]);

    const handleFormSubmit = (e) => {
        e.preventDefault();
        const nextParams = new URLSearchParams();
        nextParams.set('check_in_date', formInputs.check_in_date);
        nextParams.set('check_out_date', formInputs.check_out_date);
        nextParams.set('guest_count', String(formInputs.guest_count));
        if (formInputs.keyword) nextParams.set('keyword', formInputs.keyword);
        if (formInputs.min_price) nextParams.set('min_price', String(formInputs.min_price));
        if (formInputs.max_price) nextParams.set('max_price', String(formInputs.max_price));
        if (formInputs.sort) nextParams.set('sort', formInputs.sort);
        nextParams.set('page', '1');
        setSearchParams(nextParams);
    };

    const handlePageChange = (newPage) => {
        const nextParams = new URLSearchParams(searchParams);
        nextParams.set('page', String(newPage));
        setSearchParams(nextParams);
    };

    return (
        <div className="bk-container">
            <div className="bk-page-header">
                <h1 className="bk-page-title">Tìm kiếm phòng trống</h1>
                <p className="bk-page-subtitle">
                    Khám phá và đặt phòng nghỉ lý tưởng với mức giá tốt nhất trên StayHub
                </p>
            </div>

            {/* Search and Filters Form */}
            <form onSubmit={handleFormSubmit} className="bk-search-bar">
                <div className="bk-form-group" style={{ margin: 0 }}>
                    <label className="bk-label">Ngày nhận phòng</label>
                    <input
                        type="date"
                        className="bk-input"
                        required
                        value={formInputs.check_in_date}
                        onChange={(e) =>
                            setFormInputs({ ...formInputs, check_in_date: e.target.value })
                        }
                    />
                </div>

                <div className="bk-form-group" style={{ margin: 0 }}>
                    <label className="bk-label">Ngày trả phòng</label>
                    <input
                        type="date"
                        className="bk-input"
                        required
                        value={formInputs.check_out_date}
                        onChange={(e) =>
                            setFormInputs({ ...formInputs, check_out_date: e.target.value })
                        }
                    />
                </div>

                <div className="bk-form-group" style={{ margin: 0 }}>
                    <label className="bk-label">Số khách</label>
                    <input
                        type="number"
                        min="1"
                        className="bk-input"
                        required
                        value={formInputs.guest_count}
                        onChange={(e) =>
                            setFormInputs({ ...formInputs, guest_count: e.target.value })
                        }
                    />
                </div>

                <div className="bk-form-group" style={{ margin: 0 }}>
                    <label className="bk-label">Địa điểm / Khách sạn</label>
                    <input
                        type="text"
                        placeholder="Tên hoặc địa chỉ..."
                        className="bk-input"
                        value={formInputs.keyword}
                        onChange={(e) =>
                            setFormInputs({ ...formInputs, keyword: e.target.value })
                        }
                    />
                </div>

                <button type="submit" className="bk-btn bk-btn-primary" disabled={loading}>
                    {loading ? 'Đang tìm...' : '🔍 Tìm phòng'}
                </button>
            </form>

            {/* Main Content Layout with Sidebar Filters */}
            <div className="bk-search-layout">
                {/* Filters Sidebar */}
                <aside className="bk-filter-panel">
                    <h3 className="bk-filter-title">Bộ lọc nâng cao</h3>

                    <div className="bk-form-group">
                        <label className="bk-label">Sắp xếp theo</label>
                        <select
                            className="bk-select"
                            value={formInputs.sort}
                            onChange={(e) =>
                                setFormInputs({ ...formInputs, sort: e.target.value })
                            }
                        >
                            <option value="price_asc">Giá tăng dần</option>
                            <option value="price_desc">Giá giảm dần</option>
                            <option value="newest">Mới nhất</option>
                        </select>
                    </div>

                    <div className="bk-form-group">
                        <label className="bk-label">Khoảng giá (VND/đêm)</label>
                        <input
                            type="number"
                            placeholder="Giá tối thiểu..."
                            className="bk-input"
                            style={{ marginBottom: 8 }}
                            value={formInputs.min_price}
                            onChange={(e) =>
                                setFormInputs({ ...formInputs, min_price: e.target.value })
                            }
                        />
                        <input
                            type="number"
                            placeholder="Giá tối đa..."
                            className="bk-input"
                            value={formInputs.max_price}
                            onChange={(e) =>
                                setFormInputs({ ...formInputs, max_price: e.target.value })
                            }
                        />
                    </div>

                    <button
                        type="button"
                        className="bk-btn bk-btn-outline bk-btn-block"
                        onClick={handleFormSubmit}
                        disabled={loading}
                    >
                        Áp dụng bộ lọc
                    </button>
                </aside>

                {/* Rooms Result List */}
                <main>
                    {loading && <LoadingState message="Đang tìm kiếm phòng phù hợp..." />}

                    {error && (
                        <ErrorState
                            title="Lỗi tìm kiếm"
                            message={error}
                            onRetry={performSearch}
                        />
                    )}

                    {!loading && !error && result && result.data && result.data.length === 0 && (
                        <EmptyState
                            title="Không tìm thấy phòng phù hợp"
                            description="Vui lòng thử chọn khoảng ngày khác hoặc điều chỉnh bộ lọc tìm kiếm."
                        />
                    )}

                    {!loading && !error && result && result.data && result.data.length > 0 && (
                        <>
                            <div className="bk-grid">
                                {result.data.map((room) => (
                                    <div key={room.id} className="bk-card bk-card-hover">
                                        <div className="bk-card-header">
                                            <div>
                                                <h3 className="bk-card-title">{room.name}</h3>
                                                <p className="bk-page-subtitle">
                                                    {room.property?.name} • Phòng {room.room_number}
                                                </p>
                                            </div>
                                        </div>

                                        <div className="bk-card-body">
                                            <p style={{ margin: '0 0 8px 0', fontSize: 13, color: '#64748b' }}>
                                                📍 {room.property?.address}
                                            </p>
                                            <p style={{ margin: '0 0 10px 0' }}>
                                                👥 Sức chứa tối đa: <strong>{room.capacity} khách</strong>
                                            </p>
                                            {room.amenities && room.amenities.length > 0 && (
                                                <div style={{ marginTop: 8 }}>
                                                    {room.amenities.map((a) => (
                                                        <span key={a.id} className="bk-tag">
                                                            ✓ {a.name}
                                                        </span>
                                                    ))}
                                                </div>
                                            )}
                                        </div>

                                        <div className="bk-card-footer">
                                            <div>
                                                <span style={{ fontSize: 12, color: '#64748b' }}>Giá mỗi đêm:</span>
                                                <div className="bk-price-highlight">
                                                    {formatCurrency(room.price_per_night)}
                                                </div>
                                            </div>

                                            <button
                                                type="button"
                                                className="bk-btn bk-btn-primary"
                                                onClick={() => {
                                                    navigate(
                                                        `/bookings/create?room_id=${room.id}&check_in_date=${checkIn}&check_out_date=${checkOut}&guest_count=${guests}`
                                                    );
                                                }}
                                            >
                                                Đặt phòng
                                            </button>
                                        </div>
                                    </div>
                                ))}
                            </div>

                            {result.meta && (
                                <Pagination
                                    currentPage={result.meta.current_page}
                                    lastPage={result.meta.last_page}
                                    total={result.meta.total}
                                    perPage={result.meta.per_page}
                                    onPageChange={handlePageChange}
                                />
                            )}
                        </>
                    )}
                </main>
            </div>
        </div>
    );
}
