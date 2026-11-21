function imageData(item) {
    let url;
    try { url = new URL(item?.url); } catch { /* Rejected below. */ }
    if (!Number.isSafeInteger(item?.id) || item.id < 1 || typeof item.url !== 'string'
        || !url || !['http:', 'https:'].includes(url.protocol) || url.username || url.password
        || typeof item.is_primary !== 'boolean') {
        throw new Error('Phản hồi hình ảnh phòng không hợp lệ.');
    }
    return { id: item.id, url: item.url, is_primary: item.is_primary };
}

export function mapHostRoomImages(body) {
    if (body?.success !== true || typeof body.message !== 'string' || !Array.isArray(body.data)) {
        throw new Error('Phản hồi danh sách hình ảnh phòng không hợp lệ.');
    }
    const images = body.data.map(imageData);
    if (new Set(images.map(image => image.id)).size !== images.length) {
        throw new Error('Danh sách ảnh phòng có mã ảnh trùng.');
    }
    return images;
}

export function mapUploadedHostRoomImage(body) {
    if (body?.success !== true || typeof body.message !== 'string') {
        throw new Error('Phản hồi upload ảnh phòng không hợp lệ.');
    }
    return imageData(body.data);
}

export function mapPrimaryHostRoomImage(body, imageId) {
    const images = mapHostRoomImages(body);
    if (images.filter(image => image.is_primary).length !== 1
        || !images.some(image => image.id === Number(imageId) && image.is_primary)) {
        throw new Error('Ảnh đại diện phòng không khớp yêu cầu.');
    }
    return images;
}

export function mapDeletedHostRoomImage(body, imageId) {
    const data = body?.data;
    if (data?.deleted_image_id !== Number(imageId)
        || !['deleted', 'missing', 'not_managed', 'shared', 'failed'].includes(data.cleanup_status)) {
        throw new Error('Phản hồi xóa ảnh phòng không hợp lệ.');
    }
    const images = mapHostRoomImages({ ...body, data: data.images });
    if (images.some(image => image.id === Number(imageId))
        || (images.length > 0 && images.filter(image => image.is_primary).length !== 1)) {
        throw new Error('Danh sách ảnh sau xóa không hợp lệ.');
    }
    return { deleted_image_id: data.deleted_image_id, images, cleanup_status: data.cleanup_status };
}
