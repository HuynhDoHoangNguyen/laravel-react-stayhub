import api from '../api/axios';

/**
 * Get host's properties list (ID and name) for dropdown selectors.
 *
 * @returns {Promise<{ success: boolean, message: string, data: { id: number, name: string }[] }>}
 */
export async function getHostProperties() {
    const response = await api.get('/api/host/properties');
    return response.data;
}

/**
 * Get host's property services with filters and pagination.
 *
 * @param {Record<string, any>} [params]
 * @returns {Promise<{ success: boolean, message: string, data: any[], meta: any }>}
 */
export async function getHostServices(params) {
    const response = await api.get('/api/host/services', { params });
    return response.data;
}

/**
 * Create a new service for a property owned by the host.
 *
 * @param {{ property_id: number, name: string, description?: string, unit: string, price: number, status?: boolean }} data
 * @returns {Promise<{ success: boolean, message: string, data: any }>}
 */
export async function createHostService(data) {
    const response = await api.post('/api/host/services', data);
    return response.data;
}

/**
 * Update a service's information and price.
 *
 * @param {number|string} serviceId
 * @param {{ name: string, description?: string, unit: string, price: number }} data
 * @returns {Promise<{ success: boolean, message: string, data: any }>}
 */
export async function updateHostService(serviceId, data) {
    const response = await api.put(`/api/host/services/${serviceId}`, data);
    return response.data;
}

/**
 * Toggle a service's status (active/inactive).
 *
 * @param {number|string} serviceId
 * @param {boolean} status
 * @returns {Promise<{ success: boolean, message: string, data: any }>}
 */
export async function setHostServiceStatus(serviceId, status) {
    const response = await api.patch(`/api/host/services/${serviceId}/status`, { status });
    return response.data;
}
