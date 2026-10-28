import api from '../api/axios';
import { createProfileService } from './profile';

export const profileService = createProfileService(api);
