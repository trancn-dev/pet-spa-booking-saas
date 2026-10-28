Xin chào,

{{ $trial ? 'Bản dùng thử' : 'Gói' }} PawBook của {{ $shopName }} hết hạn ngày {{ $expiresOn }} (còn {{ $daysLeft }} ngày).

Sau ngày hết hạn shop vẫn dùng bình thường thêm {{ config('platform.grace_days') }} ngày, rồi sẽ bị tạm khóa: nhân viên chỉ xem được dữ liệu, Site ngừng nhận lịch.
Liên hệ PawBook để gia hạn; shop mở lại ngay sau khi gia hạn.
