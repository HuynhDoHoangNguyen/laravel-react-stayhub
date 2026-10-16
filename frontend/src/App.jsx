import AppRouter from './routes/AppRouter';
import { Toaster } from 'sonner';

function App() {
  return (
    <>
      <AppRouter />
      {/* TEMP-M2-WIRING: shared toast mount for the frontend foundation. */}
      <Toaster />
    </>
  );
}

export default App;
