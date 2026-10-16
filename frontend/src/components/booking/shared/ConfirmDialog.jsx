import { AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent, AlertDialogDescription, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle } from '@/components/ui/alert-dialog';

export default function ConfirmDialog({ isOpen, title = 'Xác nhận thao tác', message = 'Bạn có chắc chắn muốn thực hiện hành động này?', confirmText = 'Xác nhận', cancelText = 'Hủy bỏ', isDanger = false, loading = false, onConfirm, onCancel }) {
  return <AlertDialog open={isOpen} onOpenChange={(open) => { if (!open && !loading) onCancel?.(); }}>
    <AlertDialogContent>
      <AlertDialogHeader>
        <AlertDialogTitle>{title}</AlertDialogTitle>
        <AlertDialogDescription>{message}</AlertDialogDescription>
      </AlertDialogHeader>
      <AlertDialogFooter>
        <AlertDialogCancel disabled={loading}>{cancelText}</AlertDialogCancel>
        <AlertDialogAction disabled={loading} onClick={(event) => { event.preventDefault(); onConfirm?.(); }} className={isDanger ? 'bg-destructive text-destructive-foreground hover:bg-destructive/90' : ''}>
          {loading ? 'Đang xử lý...' : confirmText}
        </AlertDialogAction>
      </AlertDialogFooter>
    </AlertDialogContent>
  </AlertDialog>;
}
