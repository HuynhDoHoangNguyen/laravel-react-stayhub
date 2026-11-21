import api from '../api/axios';

export const hostAgreementService = {
    async getCurrentAgreement(options = {}) {
        const response = await api.get('/api/host/partner-agreements/current', options);
        return response.data;
    },

    async acceptAgreement(agreementId, payload, options = {}) {
        const response = await api.post(`/api/host/partner-agreements/${agreementId}/accept`, payload, options);
        return response.data;
    },
};
