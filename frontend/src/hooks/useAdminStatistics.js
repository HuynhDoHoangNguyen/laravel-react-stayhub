import { useEffect, useState } from 'react';
import { createStatisticsLoader } from '../services/statisticsService.js';

export function useAdminStatistics({ user, captureProfileAuth, isProfileAuthCurrent }) {
  const [loader] = useState(() => createStatisticsLoader());
  const [request, setRequest] = useState(() => ({ params: {} }));
  const [view, setView] = useState(null);
  useEffect(() => loader.start(request.params, {
    captureAuth: captureProfileAuth, isAuthCurrent: isProfileAuthCurrent,
    success: (result, auth) => setView({ result, error: null, request, auth }),
    failure: (error, auth) => setView({ result: null, error, request, auth }),
  }), [loader, request, user, captureProfileAuth, isProfileAuthCurrent]);
  const visible = view?.request === request && isProfileAuthCurrent(view.auth) ? view : null;
  function apply(params) {
    loader.invalidate();
    setRequest({ params: { ...params } });
  }
  return { result: visible?.result, error: visible?.error, loading: visible === null,
    params: request.params, apply, reload: () => apply(request.params) };
}
