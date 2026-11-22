import { useEffect, useId, useRef, useState } from 'react';
import ChatWidgetFrame, { ChatMessage, ChatTranscript } from './ChatWidgetFrame.jsx';
import { GuestChatConversation } from './GuestChatWidget.jsx';
import { chatbotService, publicChatEnabled } from '../../services/chatbotService.js';

export default function CustomerChatWidget() {
    const inputId = useId();
    const inFlight = useRef(null);
    const [open, setOpen] = useState(false);
    const [draft, setDraft] = useState('');
    const [turns, setTurns] = useState([]);
    const [sessionId, setSessionId] = useState(null);
    const [pending, setPending] = useState(null);
    const [sending, setSending] = useState(false);
    const [error, setError] = useState(null);
    const [retryUntil, setRetryUntil] = useState(0);
    const [retryRemaining, setRetryRemaining] = useState(0);
    const [confirmDiscard, setConfirmDiscard] = useState(false);
    const [showRoomAdvice, setShowRoomAdvice] = useState(false);

    useEffect(() => () => inFlight.current?.abort(), []);

    useEffect(() => {
        if (!retryUntil) return undefined;

        const timer = window.setInterval(() => {
            const remaining = Math.max(0, Math.ceil((retryUntil - Date.now()) / 1000));
            setRetryRemaining(remaining);
            if (remaining === 0) window.clearInterval(timer);
        }, 200);

        return () => window.clearInterval(timer);
    }, [retryUntil]);

    function changeOpen(value) {
        if (!value && inFlight.current) {
            inFlight.current.abort();
            inFlight.current = null;
            setSending(false);
            setError({ message: 'Chưa biết lượt gửi đã được lưu hay chưa. Hãy thử lại để khôi phục kết quả.', retryable: true });
        }
        if (!value) {
            setConfirmDiscard(false);
            setShowRoomAdvice(false);
        }
        setOpen(value);
    }

    async function send(event) {
        event.preventDefault();
        if (inFlight.current || !draft.trim() || confirmDiscard || Date.now() < retryUntil
            || (pending && error && !error.retryable)) {
            return;
        }

        const payload = pending ?? {
            request_id: crypto.randomUUID(),
            session_id: sessionId,
            message: draft.trim(),
        };
        const controller = new AbortController();
        inFlight.current = controller;
        setPending(payload);
        setSending(true);
        setError(null);
        setRetryUntil(0);
        setRetryRemaining(0);

        try {
            const result = await chatbotService.send(payload, { signal: controller.signal });
            if (controller.signal.aborted || inFlight.current !== controller) {
                return;
            }

            setTurns((previous) => previous.some((turn) => turn.request_id === result.request_id)
                ? previous
                : [...previous, result]);
            setSessionId(result.session_id);
            setPending(null);
            setDraft('');
        } catch (failure) {
            if (!controller.signal.aborted && inFlight.current === controller) {
                setError(failure);
                if (failure.retryAfter > 0) {
                    setRetryUntil(Date.now() + failure.retryAfter * 1000);
                    setRetryRemaining(failure.retryAfter);
                }
            }
        } finally {
            if (inFlight.current === controller) {
                inFlight.current = null;
                setSending(false);
            }
        }
    }

    function startNewQuestion() {
        if (error?.status === 404) {
            setSessionId(null);
            setTurns([]);
        }
        setPending(null);
        setError(null);
        setRetryUntil(0);
        setRetryRemaining(0);
        setConfirmDiscard(false);
    }

    return (
        <ChatWidgetFrame open={open} onOpenChange={changeOpen} headerAction={publicChatEnabled && !pending && (
            <button type="button" onClick={() => setShowRoomAdvice(!showRoomAdvice)} className="stayhub-chat-action self-start px-4 py-2 text-sm">
                {showRoomAdvice ? 'Về cuộc trò chuyện' : 'Gợi ý chỗ nghỉ'}
            </button>
        )}>
            {showRoomAdvice ? <GuestChatConversation authenticated /> : <>
                <ChatTranscript revision={turns.length}>
                    {turns.length === 0 && <ChatMessage sender="assistant">Bạn cần tư vấn gì về chuyến lưu trú?</ChatMessage>}
                    {turns.map(({ request_id, user_message, assistant_message }) => (
                    <div key={request_id} className="space-y-3">
                        <ChatMessage sender="user">{user_message.message}</ChatMessage>
                        <ChatMessage sender="assistant">{assistant_message.message}</ChatMessage>
                    </div>
                    ))}
                </ChatTranscript>
        <form onSubmit={send} className="shrink-0 space-y-2 border-t border-border p-3 sm:p-4">
            {sending && <p role="status" className="text-sm text-muted-foreground">Trợ lý đang trả lời…</p>}
            {error && (
                <p role="alert" className="text-sm text-destructive">
                    {error.message}
                    {retryRemaining > 0 && (' Thử lại sau ' + retryRemaining + ' giây.')}
                </p>
            )}
            <label htmlFor={inputId} className="text-sm font-medium">Câu hỏi</label>
            <textarea
                id={inputId}
                value={draft}
                onChange={(event) => setDraft(event.target.value)}
                readOnly={Boolean(pending)}
                disabled={sending}
                maxLength={2000}
                rows={2}
                className="w-full resize-none rounded-lg border border-input bg-background px-3 py-2 text-sm focus-visible:ring-2 focus-visible:ring-ring"
            />
            {confirmDiscard && (
                <p role="status" className="text-sm text-muted-foreground">
                    Lượt này có thể đã được lưu. Nếu bỏ lượt, bạn sẽ không thể khôi phục kết quả từ widget.
                </p>
            )}
            <div className="flex flex-wrap justify-end gap-2">
                {confirmDiscard ? (
                    <>
                        <button type="button" onClick={() => setConfirmDiscard(false)} className="rounded-md px-3 py-2 text-sm font-medium text-primary">
                            Giữ lượt gửi
                        </button>
                        <button type="button" onClick={startNewQuestion} className="rounded-md px-3 py-2 text-sm font-medium text-destructive">
                            Xác nhận bỏ lượt
                        </button>
                    </>
                ) : (
                    <>
                        {pending && !sending && ![401, 403].includes(error?.status) && (
                            <button type="button" onClick={error?.retryable ? () => setConfirmDiscard(true) : startNewQuestion} className="rounded-md px-3 py-2 text-sm font-medium text-primary focus-visible:ring-2 focus-visible:ring-ring">
                                {error?.retryable ? 'Bỏ lượt này' : 'Bắt đầu lượt mới'}
                            </button>
                        )}
                        {!(pending && error && !error.retryable) && (
                            <button type="submit" disabled={sending || !draft.trim() || retryRemaining > 0} className="rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground focus-visible:ring-2 focus-visible:ring-ring disabled:opacity-50">
                                {pending && !sending ? 'Thử lại' : 'Gửi'}
                            </button>
                        )}
                    </>
                )}
            </div>
        </form>
            </>}
        </ChatWidgetFrame>
    );
}
