import api from '../api/axios';
import {
    mapAgreementAcceptancesResponse,
    mapAgreementAcceptResponse,
    mapAgreementCurrentResponse,
} from '../mappers/commissionMapper';

export const hostAgreementService = {
    async getCurrentAgreement(options = {}) {
        const response = await api.get('/api/host/partner-agreements/current', options);
        return mapAgreementCurrentResponse(response.data);
    },

    async getAcceptances(options = {}) {
        const response = await api.get('/api/host/partner-agreements/acceptances', options);
        return mapAgreementAcceptancesResponse(response.data);
    },

    async acceptAgreement(agreementId, payload, options = {}) {
        const response = await api.post(`/api/host/partner-agreements/${agreementId}/accept`, payload, options);
        return mapAgreementAcceptResponse(response.data);
    },
};
