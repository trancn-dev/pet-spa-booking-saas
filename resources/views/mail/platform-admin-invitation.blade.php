Xin chào,

{{ $invitedBy->name }} mời bạn làm quản trị viên PawBook ({{ $invitation->role->value === 'super_admin' ? 'Super-admin' : 'Support' }}).

Đặt mật khẩu và bật xác thực 2 bước tại:
{{ $url }}

Liên kết dùng được một lần, hết hạn lúc {{ $invitation->expires_at?->timezone('Asia/Ho_Chi_Minh')->format('H:i d/m/Y') }}.
Nếu bạn không chờ lời mời này, hãy bỏ qua email.
