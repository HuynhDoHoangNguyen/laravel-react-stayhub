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
