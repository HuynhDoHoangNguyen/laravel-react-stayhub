import api from '../api/axios';

export const hostCommissionService = {
    /**
     * Lấy danh sách hoa hồng có phân trang và lọc theo trạng thái.
     */
    async getCommissions(params = {}, options = {}) {
        const response = await api.get('/api/host/commissions', { params, ...options });
        return response.data;
    },

    /**
     * Lấy chi tiết khoản hoa hồng kèm thông tin tài khoản nhận tiền và lịch sử nộp đối soát.
     */
    async getCommissionDetail(id, options = {}) {
        const response = await api.get(`/api/host/commissions/${id}`, options);
        return response.data;
    },

    /**
     * Host nộp khai báo thông tin chuyển khoản đối soát.
     */
    async submitRemittance(id, payload, options = {}) {
        const response = await api.post(`/api/host/commissions/${id}/remittances`, payload, options);
        return response.data;
    },

    /**
     * Lấy báo cáo tổng hợp tài chính và hoa hồng của Host (RPT-01).
     */
    async getFinancialSummary(options = {}) {
        const response = await api.get('/api/host/commissions/dashboard/financial-summary', options);
        return response.data;
    },
};
