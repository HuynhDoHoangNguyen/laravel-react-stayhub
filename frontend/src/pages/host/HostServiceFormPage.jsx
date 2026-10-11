import { useState, useEffect, useCallback } from 'react';
import { useParams, useNavigate, Link } from 'react-router-dom';
import {
    getHostProperties,
    createHostService,
    updateHostService,
    getHostServices,
} from '../../services/propertyServiceCatalogService';
import { getApiErrorMessage } from '../../utils/getApiErrorMessage';
import LoadingState from '../../components/booking/shared/LoadingState';

export default function HostServiceFormPage() {
    const { id } = useParams();
    const navigate = useNavigate();
    const isEditMode = Boolean(id);

    const [properties, setProperties] = useState([]);
    const [loadingInitial, setLoadingInitial] = useState(true);

    const [formData, setFormData] = useState({
        property_id: '',
        name: '',
        description: '',
        unit: '',
        price: '',
        status: true,
    });

    const [submitting, setSubmitting] = useState(false);
    const [generalError, setGeneralError] = useState(null);
    const [fieldErrors, setFieldErrors] = useState({});

    const loadInitialData = useCallback(async () => {
        setLoadingInitial(true);
        setGeneralError(null);
        try {
            // 1. Load properties for select dropdown
            const propRes = await getHostProperties();
            const propsList = propRes.data || [];
            setProperties(propsList);

            // 2. If edit mode, load existing service details
            if (isEditMode) {
                const sListRes = await getHostServices();
                const found = sListRes.data?.find((s) => String(s.id) === String(id));
                if (found) {
                    setFormData({
                        property_id: String(found.property_id),
                        name: found.name,
                        description: found.description || '',
                        unit: found.unit,
                        price: String(found.price),
                        status: Boolean(found.status),
                    });
                } else {
                    setGeneralError('Không tìm thấy thông tin dịch vụ cần sửa.');
                }
            } else if (propsList.length > 0) {
                setFormData((prev) => ({
                    ...prev,
                    property_id: String(propsList[0].id),
                }));
            }
        } catch (err) {
            setGeneralError(getApiErrorMessage(err).message);
        } finally {
            setLoadingInitial(false);
        }
    }, [id, isEditMode]);

    useEffect(() => {
        let isMounted = true;
        Promise.resolve().then(() => {
            if (isMounted) {
                loadInitialData();
            }
        });
        return () => {
            isMounted = false;
        };
    }, [loadInitialData]);

    const handleSubmit = async (e) => {
        e.preventDefault();
        setSubmitting(true);
        setGeneralError(null);
        setFieldErrors({});

        try {
            if (isEditMode) {
                await updateHostService(id, {
                    name: formData.name.trim(),
                    description: formData.description.trim() || undefined,
                    unit: formData.unit.trim(),
                    price: Number(formData.price),
                });
            } else {
                await createHostService({
                    property_id: Number(formData.property_id),
                    name: formData.name.trim(),
                    description: formData.description.trim() || undefined,
                    unit: formData.unit.trim(),
                    price: Number(formData.price),
                    status: formData.status,
                });
            }
            navigate('/host/services');
        } catch (err) {
            const parsed = getApiErrorMessage(err);
            setGeneralError(parsed.message);
            if (parsed.fieldErrors) {
                setFieldErrors(parsed.fieldErrors);
            }
        } finally {
            setSubmitting(false);
        }
    };

    if (loadingInitial) {
        return (
            <div className="bk-container">
                <LoadingState message="Đang tải dữ liệu biểu mẫu dịch vụ..." />
            </div>
        );
    }

    return (
        <div className="bk-container" style={{ maxWidth: 720 }}>
            <div className="bk-page-header">
                <h1 className="bk-page-title">
                    {isEditMode ? 'Chỉnh sửa dịch vụ' : 'Tạo dịch vụ mới'}
                </h1>
                <p className="bk-page-subtitle">
                    {isEditMode
                        ? 'Cập nhật thông tin và đơn giá dịch vụ của cơ sở lưu trú'
                        : 'Thêm dịch vụ tiện ích mới cho khách hàng khi lưu trú'}
                </p>
            </div>

            {generalError && (
                <div className="bk-alert bk-alert-danger">
                    {generalError}
                </div>
            )}

            <form onSubmit={handleSubmit} className="bk-card">
                {!isEditMode && (
                    <div className="bk-form-group">
                        <label className="bk-label">Thuộc cơ sở lưu trú *</label>
                        <select
                            className="bk-select"
                            required
                            value={formData.property_id}
                            onChange={(e) =>
                                setFormData({ ...formData, property_id: e.target.value })
                            }
                            disabled={submitting}
                        >
                            {properties.map((p) => (
                                <option key={p.id} value={p.id}>
                                    {p.name}
                                </option>
                            ))}
                        </select>
                        {fieldErrors.property_id && (
                            <p className="bk-error-text">{fieldErrors.property_id[0]}</p>
                        )}
                    </div>
                )}

                <div className="bk-form-group">
                    <label className="bk-label">Tên dịch vụ *</label>
                    <input
                        type="text"
                        placeholder="Ví dụ: Giặt ủi nhanh, Bữa sáng Buffet..."
                        className="bk-input"
                        required
                        value={formData.name}
                        onChange={(e) => setFormData({ ...formData, name: e.target.value })}
                        disabled={submitting}
                    />
                    {fieldErrors.name && (
                        <p className="bk-error-text">{fieldErrors.name[0]}</p>
                    )}
                </div>

                <div className="bk-form-group">
                    <label className="bk-label">Mô tả chi tiết</label>
                    <textarea
                        rows={3}
                        placeholder="Mô tả nội dung, thời gian hoặc lưu ý phục vụ..."
                        className="bk-textarea"
                        value={formData.description}
                        onChange={(e) =>
                            setFormData({ ...formData, description: e.target.value })
                        }
                        disabled={submitting}
                    />
                    {fieldErrors.description && (
                        <p className="bk-error-text">{fieldErrors.description[0]}</p>
                    )}
                </div>

                <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 16 }}>
                    <div className="bk-form-group">
                        <label className="bk-label">Đơn vị tính *</label>
                        <input
                            type="text"
                            placeholder="Ví dụ: người, kg, suất, vé, ngày..."
                            className="bk-input"
                            required
                            value={formData.unit}
                            onChange={(e) =>
                                setFormData({ ...formData, unit: e.target.value })
                            }
                            disabled={submitting}
                        />
                        {fieldErrors.unit && (
                            <p className="bk-error-text">{fieldErrors.unit[0]}</p>
                        )}
                    </div>

                    <div className="bk-form-group">
                        <label className="bk-label">Đơn giá (VND) *</label>
                        <input
                            type="number"
                            min="0"
                            step="1000"
                            placeholder="Ví dụ: 50000"
                            className="bk-input"
                            required
                            value={formData.price}
                            onChange={(e) =>
                                setFormData({ ...formData, price: e.target.value })
                            }
                            disabled={submitting}
                        />
                        {fieldErrors.price && (
                            <p className="bk-error-text">{fieldErrors.price[0]}</p>
                        )}
                    </div>
                </div>

                <div className="bk-alert bk-alert-info" style={{ fontSize: 13, margin: '8px 0 20px 0' }}>
                    💡 <strong>Lưu ý:</strong> Việc thay đổi đơn giá chỉ áp dụng cho các lần thêm dịch vụ vào đơn đặt phòng trong tương lai. Các dòng dịch vụ đã chốt trong đơn cũ sẽ giữ nguyên đơn vị giá snapshot để bảo đảm tính toàn vẹn tài chính.
                </div>

                <div style={{ display: 'flex', justifyContent: 'flex-end', gap: 10 }}>
                    <Link to="/host/services" className="bk-btn bk-btn-outline">
                        Hủy bỏ
                    </Link>
                    <button
                        type="submit"
                        className="bk-btn bk-btn-primary"
                        disabled={submitting}
                    >
                        {submitting ? 'Đang lưu...' : (isEditMode ? 'Cập nhật dịch vụ' : 'Tạo dịch vụ')}
                    </button>
                </div>
            </form>
        </div>
    );
}
