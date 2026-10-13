import { useState } from "react";
import { uploadProfilePhoto } from "@/lib/api/users";

export function useUploadAvatar() {
  const [isLoading, setIsLoading] = useState(false);
  const mutateAsync = async (file) => {
    setIsLoading(true);
    try {
      return await uploadProfilePhoto(file);
    } finally {
      setIsLoading(false);
    }
  };
  return { mutateAsync, isLoading };
}
