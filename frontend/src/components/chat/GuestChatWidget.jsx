import { useEffect, useId, useRef, useState } from 'react';
import { Link } from 'react-router-dom';
import ChatWidgetFrame, { ChatMessage, ChatRoomCards, ChatTranscript } from './ChatWidgetFrame.jsx';
import { guestChatbotService } from '../../services/chatbotService.js';

const destinations = {
    bookings: { path: '/customer/bookings', title: 'Xem đơn đặt phòng' },
    invoices: { path: '/customer/invoices', title: 'Xem hóa đơn' },
};

function ChatAction({ action, authenticated }) {
    if (action === 'search') return <Link className="stayhub-chat-action" to="/search">Tìm chỗ nghỉ</Link>;
    if (!action) return null;

    const { path, title } = destinations[action];
    const to = authenticated ? path : `/login?role=customer&returnTo=${encodeURIComponent(path)}`;
    return <Link className="stayhub-chat-action" to={to}>{authenticated ? title : `Đăng nhập để ${title.toLowerCase()}`}</Link>;
}

export function GuestChatConversation({ authenticated = false }) {
    const inputId = useId();
    const request = useRef(null);
    const [draft, setDraft] = useState('');
    const [turns, setTurns] = useState([]);
    const [sending, setSending] = useState(false);
    const [error, setError] = useState(null);
    const [waitUntil, setWaitUntil] = useState(0);
    const [seconds, setSeconds] = useState(0);

    useEffect(() => () => request.current?.abort(), []);
    useEffect(() => {
        if (!waitUntil) return undefined;
        const timer = setInterval(() => {
            const remaining = Math.max(0, Math.ceil((waitUntil - Date.now()) / 1000));
            setSeconds(remaining);
            if (!remaining) clearInterval(timer);
        }, 200);
        return () => clearInterval(timer);
    }, [waitUntil]);

    async function send(event) {
        event.preventDefault();
        const question = draft.trim();
        if (!question || request.current || Date.now() < waitUntil) return;

        const controller = new AbortController();
        const id = crypto.randomUUID();
        request.current = controller;
        setSending(true);
        setError(null);
        try {
            const result = await guestChatbotService.send({ request_id: id, message: question }, { signal: controller.signal });
            if (!controller.signal.aborted) {
                setTurns((previous) => [...previous, { id, question, ...result }]);
                setDraft('');
            }
        } catch (failure) {
            if (!controller.signal.aborted) {
                setError(failure.message);
                if (failure.retryAfter > 0) {
                    setWaitUntil(Date.now() + failure.retryAfter * 1000);
                    setSeconds(failure.retryAfter);
                }
            }
        } finally {
            if (!controller.signal.aborted) {
                request.current = null;
                setSending(false);
            }
        }
    }

    return (
        <>
            <ChatTranscript revision={turns.length}>
                {turns.length === 0 && <ChatMessage sender="assistant">Bạn cần tư vấn gì? Nếu muốn tìm chỗ nghỉ, bạn có thể cho mình biết số khách và ngân sách mỗi đêm.</ChatMessage>}
                {turns.map(({ id, question, reply, action, rooms }) => (
                    <div className="space-y-3" key={id}>
                        <ChatMessage sender="user">{question}</ChatMessage>
                        <ChatMessage sender="assistant" after={<><ChatRoomCards rooms={rooms} /><ChatAction action={action} authenticated={authenticated} /></>}>
                            {reply}
                        </ChatMessage>
                    </div>
                ))}
            </ChatTranscript>
            <form onSubmit={send} className="shrink-0 space-y-2 border-t border-border p-3 sm:p-4">
                {sending && <p role="status" className="text-sm text-muted-foreground">Trợ lý đang trả lời…</p>}
                {error && <p role="alert" className="text-sm text-destructive">{error}{seconds > 0 && ` Thử lại sau ${seconds} giây.`}</p>}
                <label htmlFor={inputId} className="text-sm font-medium">Câu hỏi</label>
                <textarea id={inputId} value={draft} onChange={(event) => setDraft(event.target.value)} disabled={sending}
                    maxLength={500} rows={2} className="w-full resize-none rounded-lg border border-input bg-background px-3 py-2 text-sm"
                    placeholder={authenticated ? 'Ví dụ: Tìm phòng cho 2 người dưới 800 nghìn' : 'Nhập câu hỏi chung về chuyến đi hoặc chỗ nghỉ'} />
                <div className="flex flex-wrap items-center justify-end gap-3">
                    {!authenticated && <Link className="stayhub-chat-action mr-auto text-xs" to="/login?role=customer&returnTo=%2Fcustomer">Đăng nhập để được hỗ trợ thêm</Link>}
                    <button type="submit" disabled={sending || !draft.trim() || seconds > 0}
                        className="rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground disabled:opacity-50">Gửi</button>
                </div>
            </form>
        </>
    );
}

export default function GuestChatWidget() {
    const [open, setOpen] = useState(false);
    return <ChatWidgetFrame open={open} onOpenChange={setOpen}>{open && <GuestChatConversation />}</ChatWidgetFrame>;
}
