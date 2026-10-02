import { useState } from 'react';
import api from '../../api/axios';

function LoginTestPage() {
    const [message, setMessage] = useState('');
    const [user, setUser] = useState(null);

    const login = async () => {
        try {
            await api.get('/sanctum/csrf-cookie');

            await api.post('/login', {
                email: 'customer@stayhub.test',
                password: '12345678',
            });

            const response = await api.get('/api/me');

            setUser(response.data.user);
            setMessage('Đăng nhập thành công');
        } catch (error) {
            console.error(error);
            setMessage('Đăng nhập thất bại');
        }
    };

    const logout = async () => {
        try {
            await api.post('/logout');

            setUser(null);
            setMessage('Đăng xuất thành công');
        } catch (error) {
            console.error(error);
            setMessage('Đăng xuất thất bại');
        }
    };

    return (
        <div>
            <h1>Sanctum Authentication Test</h1>

            <button onClick={login}>
                Login Test
            </button>

            <button onClick={logout}>
                Logout
            </button>

            <p>{message}</p>

            {user && (
                <div>
                    <p>ID: {user.id}</p>
                    <p>Name: {user.name}</p>
                    <p>Email: {user.email}</p>
                </div>
            )}
        </div>
    );
}

export default LoginTestPage;