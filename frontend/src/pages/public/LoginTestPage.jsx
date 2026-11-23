import { useState } from 'react';
import { Link } from 'react-router-dom';
import { useAuth } from '../../hooks/useAuth';

function LoginTestPage() {
    const [email, setEmail] = useState('host1@stayhub.com');
    const [password, setPassword] = useState('12345678');
    const [message, setMessage] = useState('');
    const { user, login, logout } = useAuth();
    const [loading, setLoading] = useState(false);

    const handleLogin = async (customEmail, customPass) => {
        const loginEmail = customEmail || email;
        const loginPass = customPass || password;

        setLoading(true);
        setMessage('');
        try {
            const authenticatedUser = await login({
                email: loginEmail,
                password: loginPass,
            });
            if (authenticatedUser) setMessage(`Đăng nhập thành công với vai trò: ${authenticatedUser.role}`);
        } catch (error) {
            setMessage(error.message);
        } finally {
            setLoading(false);
        }
    };

    const handleLogout = async () => {
        setLoading(true);
        try {
            const confirmed = await logout();
            setMessage(confirmed ? 'Phiên đăng nhập đã kết thúc.' : 'Chưa xác nhận được đăng xuất. Vui lòng thử lại.');
        } catch (error) {
            setMessage(error.message);
        } finally {
            setLoading(false);
        }
    };

    return (
        <div style={{ maxWidth: '640px', margin: '40px auto', padding: '24px', background: '#ffffff', borderRadius: '16px', border: '1px solid #e9e4db', textAlign: 'left', fontFamily: 'sans-serif' }}>
            <h1 style={{ fontSize: '24px', color: '#1a2e20', marginBottom: '8px' }}>StayHub Authentication & Test Portal</h1>
            <p style={{ color: '#718096', fontSize: '14px', marginBottom: '24px' }}>
                Đăng nhập nhanh các tài khoản Seeder để kiểm thử phân quyền và giao diện Hóa đơn Host / Customer.
            </p>

            {/* Quick account presets */}
            <div style={{ marginBottom: '20px' }}>
                <div style={{ fontSize: '13px', fontWeight: 600, color: '#1a2e20', marginBottom: '8px' }}>Chọn nhanh tài khoản test:</div>
                <div style={{ display: 'flex', gap: '8px', flexWrap: 'wrap' }}>
                    <button
                        type="button"
                        onClick={() => { setEmail('host1@stayhub.com'); setPassword('12345678'); handleLogin('host1@stayhub.com', '12345678'); }}
                        style={{ padding: '8px 14px', borderRadius: '8px', border: '1px solid #1a2e20', background: '#1a2e20', color: '#fff', fontSize: '13px', cursor: 'pointer' }}
                    >
                        Host 1 (Phạm Minh Anh)
                    </button>
                    <button
                        type="button"
                        onClick={() => { setEmail('host2@stayhub.com'); setPassword('12345678'); handleLogin('host2@stayhub.com', '12345678'); }}
                        style={{ padding: '8px 14px', borderRadius: '8px', border: '1px solid #785a28', background: '#faf8f5', color: '#785a28', fontSize: '13px', cursor: 'pointer' }}
                    >
                        Host 2 (Vũ Hoàng Nam)
                    </button>
                    <button
                        type="button"
                        onClick={() => { setEmail('customer1@stayhub.com'); setPassword('12345678'); handleLogin('customer1@stayhub.com', '12345678'); }}
                        style={{ padding: '8px 14px', borderRadius: '8px', border: '1px solid #047857', background: '#ecfdf5', color: '#047857', fontSize: '13px', cursor: 'pointer' }}
                    >
                        Customer 1 (Nguyễn Văn An)
                    </button>
                    <button
                        type="button"
                        onClick={() => { setEmail('customer2@stayhub.com'); setPassword('12345678'); handleLogin('customer2@stayhub.com', '12345678'); }}
                        style={{ padding: '8px 14px', borderRadius: '8px', border: '1px solid #047857', background: '#ecfdf5', color: '#047857', fontSize: '13px', cursor: 'pointer' }}
                    >
                        Customer 2 (Trần Thị Bình)
                    </button>
                </div>
            </div>

            {/* Manual Form */}
            <div style={{ background: '#faf8f5', padding: '16px', borderRadius: '12px', border: '1px solid #e9e4db', marginBottom: '20px' }}>
                <div style={{ marginBottom: '12px' }}>
                    <label style={{ display: 'block', fontSize: '12px', fontWeight: 600, color: '#718096', marginBottom: '4px' }}>Email</label>
                    <input
                        type="email"
                        value={email}
                        onChange={(e) => setEmail(e.target.value)}
                        style={{ width: '100%', padding: '8px 12px', borderRadius: '8px', border: '1px solid #cbd5e0', fontSize: '14px', boxSizing: 'border-box' }}
                    />
                </div>
                <div style={{ marginBottom: '16px' }}>
                    <label style={{ display: 'block', fontSize: '12px', fontWeight: 600, color: '#718096', marginBottom: '4px' }}>Mật khẩu (mặc định 12345678)</label>
                    <input
                        type="password"
                        value={password}
                        onChange={(e) => setPassword(e.target.value)}
                        style={{ width: '100%', padding: '8px 12px', borderRadius: '8px', border: '1px solid #cbd5e0', fontSize: '14px', boxSizing: 'border-box' }}
                    />
                </div>
                <div style={{ display: 'flex', gap: '10px' }}>
                    <button
                        type="button"
                        disabled={loading}
                        onClick={() => handleLogin()}
                        style={{ padding: '10px 20px', borderRadius: '8px', background: '#1a2e20', color: '#fff', border: 'none', fontWeight: 600, cursor: 'pointer' }}
                    >
                        {loading ? 'Đang xử lý...' : 'Đăng nhập'}
                    </button>
                    <button
                        type="button"
                        disabled={loading}
                        onClick={handleLogout}
                        style={{ padding: '10px 16px', borderRadius: '8px', background: '#fff', color: '#c53030', border: '1px solid #feb2b2', fontWeight: 600, cursor: 'pointer' }}
                    >
                        Đăng xuất
                    </button>
                </div>
            </div>

            {/* Status Message */}
            {message && (
                <div style={{ padding: '12px 16px', borderRadius: '8px', background: user ? '#f0fff4' : '#fff5f5', color: user ? '#276749' : '#c53030', marginBottom: '20px', fontSize: '14px' }}>
                    {message}
                </div>
            )}

            {/* Logged in User info & Links */}
            {user && (
                <div style={{ background: '#ffffff', border: '1px solid #6ee7b7', padding: '16px', borderRadius: '12px' }}>
                    <h3 style={{ fontSize: '16px', color: '#047857', margin: '0 0 10px' }}>Đang đăng nhập: {user.name}</h3>
                    <div style={{ fontSize: '13px', color: '#4a5568', lineHeight: '1.6', marginBottom: '14px' }}>
                        <div><strong>ID:</strong> {user.id} | <strong>Vai trò:</strong> {user.role}</div>
                        <div><strong>Email:</strong> {user.email} | <strong>Điện thoại:</strong> {user.phone}</div>
                    </div>

                    <div style={{ borderTop: '1px solid #e2e8f0', paddingTop: '12px' }}>
                        <div style={{ fontSize: '13px', fontWeight: 600, marginBottom: '8px' }}>Chuyển đến màn hình kiểm thử:</div>
                        {user.role === 'HOST' ? (
                            <div style={{ display: 'flex', gap: '8px', flexWrap: 'wrap' }}>
                                <Link to="/host/invoices" style={{ textDecoration: 'none', background: '#1a2e20', color: '#fff', padding: '6px 14px', borderRadius: '6px', fontSize: '13px' }}>
                                    → Danh sách Hóa đơn Host (/host/invoices)
                                </Link>
                                <Link to="/host/invoices/create" style={{ textDecoration: 'none', background: '#faf8f5', color: '#1a2e20', border: '1px solid #e9e4db', padding: '6px 14px', borderRadius: '6px', fontSize: '13px' }}>
                                    → Lập hóa đơn (/host/invoices/create)
                                </Link>
                                <Link to="/host" style={{ textDecoration: 'none', background: '#faf8f5', color: '#1a2e20', border: '1px solid #e9e4db', padding: '6px 14px', borderRadius: '6px', fontSize: '13px' }}>
                                    → Host Console (/host)
                                </Link>
                            </div>
                        ) : (
                            <div style={{ display: 'flex', gap: '8px', flexWrap: 'wrap' }}>
                                <Link to="/customer/invoices" style={{ textDecoration: 'none', background: '#1a2e20', color: '#fff', padding: '6px 14px', borderRadius: '6px', fontSize: '13px' }}>
                                    → Hóa đơn của tôi (/customer/invoices)
                                </Link>
                                <Link to="/customer" style={{ textDecoration: 'none', background: '#faf8f5', color: '#1a2e20', border: '1px solid #e9e4db', padding: '6px 14px', borderRadius: '6px', fontSize: '13px' }}>
                                    → Trang Khách hàng (/customer)
                                </Link>
                            </div>
                        )}
                    </div>
                </div>
            )}
        </div>
    );
}

export default LoginTestPage;
