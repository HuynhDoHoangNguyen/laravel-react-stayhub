<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Thời hạn nộp hoa hồng (Payment Window Hours)
    |--------------------------------------------------------------------------
    | Thời gian tối đa (tính theo giờ) để Host hoàn tất nghĩa vụ nộp hoa hồng
    | kể từ thời điểm Hóa đơn được Host xác nhận là PAID.
    */
    'payment_window_hours' => 72,

    /*
    |--------------------------------------------------------------------------
    | Tỷ lệ hoa hồng mặc định (Basis Points: 1000 = 10.00%)
    |--------------------------------------------------------------------------
    */
    'default_rate_bps' => 1000,

    /*
    |--------------------------------------------------------------------------
    | Tiền tố mã chuyển khoản đối soát (Reference Code Prefix)
    |--------------------------------------------------------------------------
    | Ví dụ: SHF-A1B2C3D4
    */
    'reference_prefix' => 'SHF',

    /*
    |--------------------------------------------------------------------------
    | Thông tin tài khoản ngân hàng chính thức nhận hoa hồng StayHub
    |--------------------------------------------------------------------------
    | Đọc từ biến môi trường. Khi chưa được cấu hình, giá trị là null và hệ
    | thống sẽ tạm ẩn hướng dẫn chuyển khoản để tránh hiển thị tài khoản giả.
    */
    'bank_info' => [
        'bank_name' => env('STAYHUB_BANK_NAME'),
        'account_number' => env('STAYHUB_BANK_ACCOUNT_NUMBER'),
        'account_holder' => env('STAYHUB_BANK_ACCOUNT_HOLDER'),
    ],
];
