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
    */
    'bank_info' => [
        'bank_name' => 'Ngân hàng TMCP Ngoại thương Việt Nam (Vietcombank)',
        'account_number' => '1029384756',
        'account_holder' => 'CONG TY CP CONG NGHE STAYHUB',
    ],
];
