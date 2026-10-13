import { Outlet } from "react-router-dom";
import { ManagerShell } from "@/components/manager/ManagerShell";
import { RequireManager } from "@/components/manager/RequireManager";
export default function ManageLayout() {
    return (<RequireManager>
      <ManagerShell>
        <Outlet />
      </ManagerShell>
    </RequireManager>);
}
