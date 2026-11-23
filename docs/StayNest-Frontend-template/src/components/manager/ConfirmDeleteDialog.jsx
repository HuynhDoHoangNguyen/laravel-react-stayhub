import { AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent, AlertDialogDescription, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle, } from "@/components/ui/alert-dialog";
export function ConfirmDeleteDialog({ open, onOpenChange, title, description, confirmLabel = "Delete", loading, onConfirm, }) {
    return (<AlertDialog open={open} onOpenChange={onOpenChange}>
      <AlertDialogContent className="rounded-2xl border-border/60 bg-background">
        <AlertDialogHeader>
          <AlertDialogTitle className="font-display text-2xl text-primary">
            {title}
          </AlertDialogTitle>
          <AlertDialogDescription className="text-[13.5px] text-muted-foreground">
            {description}
          </AlertDialogDescription>
        </AlertDialogHeader>
        <AlertDialogFooter>
          <AlertDialogCancel className="rounded-xl">Cancel</AlertDialogCancel>
          <AlertDialogAction onClick={(e) => {
            e.preventDefault();
            onConfirm();
        }} disabled={loading} className="rounded-xl bg-destructive text-destructive-foreground hover:bg-destructive/90">
            {loading ? "Working…" : confirmLabel}
          </AlertDialogAction>
        </AlertDialogFooter>
      </AlertDialogContent>
    </AlertDialog>);
}
