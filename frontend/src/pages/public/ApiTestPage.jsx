import { useEffect, useState } from 'react';
import api from '../../api/axios';

function ApiTestPage() {
    const [message, setMessage] = useState('Loading...');

    useEffect(() => {
        api.get('/test')
            .then((response) => {
                setMessage(response.data.message);
            })
            .catch((error) => {
                console.error(error);
                setMessage('Cannot connect to Laravel API');
            });
    }, []);

    return (
        <div>
            <h1>API Test</h1>
            <p>{message}</p>
        </div>
    );
}

export default ApiTestPage;