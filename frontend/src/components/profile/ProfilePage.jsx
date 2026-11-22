import { useEffect, useState, useSyncExternalStore } from 'react';
import { Link } from 'react-router-dom';
import { useAuth } from '../../hooks/useAuth';
import { useHostApproval } from '../../hooks/useHostApproval';
import { profileService } from '../../services/profileService';
import { createProfileEditor } from '../../services/profile';
import { ProfileHeader } from './ProfileHeader';
import { PersonalInfoCard } from './PersonalInfoCard';
import { AccountSecurityCard } from './AccountSecurityCard';
import { ProfileQuickActions } from './ProfileQuickActions';
import ProfileForm from './ProfileForm';
import ChangePasswordForm from './ChangePasswordForm';

export default function ProfilePage() {
    const { user, isLoggingOut } = useAuth();
    if (isLoggingOut) return <p role="status">Đang đăng xuất...</p>;
    return user ? <ProfileEditorPage key={user.id} role={user.role} /> : null;
}

function ProfileEditorPage({ role }) {
    const { captureProfileAuth, isProfileAuthCurrent, updateProfileUser } = useAuth();
    const { isPendingHost } = useHostApproval();
    const [editor] = useState(() => createProfileEditor(profileService, {
        capture: captureProfileAuth, isCurrent: isProfileAuthCurrent, commit: updateProfileUser,
    }));
    const state = useSyncExternalStore(editor.subscribe, editor.getSnapshot);
    useEffect(() => {
        void editor.load().catch(() => {});
        return () => editor.cancelRequests();
    }, [editor]);
    const reload = () => { void editor.load().catch(() => {}); };
    const home = { CUSTOMER: '/customer', HOST: '/host', ADMIN: '/admin' }[role];
    return (
        <div className="mx-auto max-w-[1360px] pb-20 pt-2 text-left">
            {!isPendingHost && <Link to={home} className="text-xs font-semibold text-primary">← Quay lại tổng quan</Link>}
            {isPendingHost && (
                <div role="status" className="my-4 rounded-2xl border border-amber-500/40 bg-amber-50/90 p-5 text-amber-950 shadow-xs">
                    <p className="text-xs font-bold uppercase tracking-wider text-amber-800">
                        ⏳ Tài khoản Đối tác đang chờ Quản trị viên phê duyệt
                    </p>
                    <p className="mt-1.5 text-sm leading-relaxed">
                        Bạn đã đăng ký tài khoản Đối tác và đồng ý với Hợp đồng Đối tác (mức hoa hồng nền tảng 10%). Hiện tại bạn chỉ có thể xem và hoàn thiện <strong>Hồ sơ cá nhân</strong> của chính mình. Sau khi Quản trị viên phê duyệt tài khoản trên trang Quản lý người dùng, toàn bộ chức năng Quản lý cơ sở lưu trú, Đặt phòng, Hóa đơn và Hoa hồng sẽ được mở khóa.
                    </p>
                </div>
            )}
            {state.notice && <p role="status" className="my-5 rounded-xl border border-primary/20 bg-primary/5 p-4 text-sm text-primary">{state.notice}</p>}
            {state.error && <div role="alert" className="my-5 rounded-xl border border-destructive/30 bg-destructive/5 p-4 text-sm text-destructive">
                <p>{state.error.message}</p>
                {state.error.operation === 'read' && <button type="button" disabled={state.pending} onClick={reload} className="mt-3 font-semibold underline">Thử tải lại hồ sơ</button>}
            </div>}
            {state.needsReload && <div className="my-4 rounded-xl border border-border p-4 text-sm">
                <p>Hãy đối chiếu thông tin trên máy chủ trước khi tiếp tục lưu.</p>
                <button type="button" disabled={state.pending} onClick={reload} className="mt-2 font-semibold text-primary underline">Tải lại hồ sơ (bỏ bản nháp)</button>
            </div>}
            {state.loading && <div role="status" className="mt-6 animate-pulse rounded-3xl bg-surface p-12">Đang tải hồ sơ...</div>}
            {state.saved && <>
                <div className="mt-6"><ProfileHeader user={state.saved}
                    onAvatarSelected={(file) => { void editor.uploadAvatar(file).catch(() => {}); }}
                    avatarDisabled={state.pending || state.needsReload} avatarPending={state.pending && state.pendingOperation === 'avatar'}
                    avatarError={state.error?.operation === 'avatar' ? state.error.fields?.avatar?.[0] : undefined} /></div>
                <section className="mt-8 grid gap-8 lg:grid-cols-[minmax(0,1fr)_320px]">
                    <div className="min-w-0 space-y-8">
                        <PersonalInfoCard user={state.saved} disabled={state.pending} onEdit={state.editing ? undefined : editor.edit}>
                            {state.editing ? <ProfileForm editor={editor} state={state} /> : null}
                        </PersonalInfoCard>
                        <ChangePasswordForm editor={editor} state={state} />
                        <AccountSecurityCard user={state.saved} />
                    </div>
                    <aside className="min-w-0">
                        {role === 'CUSTOMER' ? <ProfileQuickActions /> : <p className="rounded-3xl border border-border bg-white p-6 text-sm text-muted-foreground">Bạn đang quản lý hồ sơ cá nhân của mình.</p>}
                    </aside>
                </section>
            </>}
        </div>
    );
}
