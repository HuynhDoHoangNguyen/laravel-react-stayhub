const STATUS_MESSAGES = {
  401: 'Vui lòng đăng nhập để tiếp tục.',
  403: 'Tài khoản hiện tại không có quyền thực hiện thao tác này.',
  404: 'Không tìm thấy thông tin đơn đặt phòng hoặc bạn không có quyền truy cập.',
  409: 'Phòng vừa được đặt trong khoảng ngày này. Vui lòng chọn ngày hoặc phòng khác.',
  422: 'Yêu cầu không thể thực hiện. Vui lòng kiểm tra trạng thái đơn đặt phòng.',
};

export function bookingDisplayError(error, fallback, { notFoundMessage, useStatusMessages = true } = {}) {
  const status = error?.response?.status ?? error?.status;
  if (status === 404 && notFoundMessage) return notFoundMessage;
  if (error?.status === 400 && error.message) return error.message;

  const serverMessage = error?.response?.data?.message;
  if (serverMessage) return serverMessage;

  return (useStatusMessages ? STATUS_MESSAGES[status] : null) || fallback;
}
