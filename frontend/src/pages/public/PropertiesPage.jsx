import { Navigate, useLocation } from 'react-router-dom';
import { legacyPropertiesTarget } from '../../utils/searchNavigation';

export default function PropertiesPage() {
    const location = useLocation();
    return <Navigate replace to={legacyPropertiesTarget(location.search)} />;
}
