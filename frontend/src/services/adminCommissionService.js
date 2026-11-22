import api from '../api/axios';
import {
    mapAdminCommissionSummaryResponse,
    mapCommissionListResponse,
    mapRemittanceActionResponse,
    mapRemittanceQueueResponse,
} from '../mappers/commissionMapper';

export const adminCommissionService = {
    /**
     * Lấy danh sách toàn bộ hoa hồng hệ thống dành cho Admin.
     */
    async getAdminCommissions(params = {}, options = {}) {
        const response = await api.get('/api/admin/commissions', { params, ...options });
        return mapCommissionListResponse(response.data);
    },

    /**
     * Lấy hàng chờ các khoản đối soát do Host nộp (Remittance Queue).
     */
    async getRemittanceQueue(params = {}, options = {}) {
        const response = await api.get('/api/admin/commission-remittances', { params, ...options });
        return mapRemittanceQueueResponse(response.data);
    },

    /**
     * Admin xác minh giao dịch đối soát dựa trên sao kê ngân hàng thật.
     */
    async verifyRemittance(id, payload, options = {}) {
        const response = await api.post(`/api/admin/commission-remittances/${id}/verify`, payload, options);
        return mapRemittanceActionResponse(response.data);
    },

    /**
     * Admin từ chối giao dịch đối soát kèm lý do bắt buộc.
     */
    async rejectRemittance(id, payload, options = {}) {
        const response = await api.post(`/api/admin/commission-remittances/${id}/reject`, payload, options);
        return mapRemittanceActionResponse(response.data);
    },

    /**
     * Lấy báo cáo tổng hợp đối soát hoa hồng toàn hệ thống (RPT-02).
     */
    async getCommissionSummary(options = {}) {
        const response = await api.get('/api/admin/dashboard/commission-summary', options);
        return mapAdminCommissionSummaryResponse(response.data);
    },
};
