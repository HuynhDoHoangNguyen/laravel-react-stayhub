import { AuthProvider } from './context/AuthContext';
import AppRouter from './routes/AppRouter';
import AuthFeedback from './components/common/AuthFeedback';

function App() {
  return (
    <AuthProvider>
            <AuthFeedback />
      <AppRouter />
    </AuthProvider>
  );
}

export default App;
