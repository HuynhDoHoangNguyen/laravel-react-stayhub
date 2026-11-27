import { useEffect, useRef } from 'react';
import * as Dialog from '@radix-ui/react-dialog';
import { Building2, X } from 'lucide-react';
import { Link } from 'react-router-dom';
import receptionist from '../../assets/stayhub-receptionist.webp';
import { formatVnd } from '../../utils/formatVnd.js';
import './chatWidget.css';

export function ChatMessage({ sender, children, after }) {
    const assistant = sender === 'assistant';
    return (
        <div className={`stayhub-chat-message stayhub-chat-message--${sender}`} data-sender={sender}>
            {assistant && <img className="stayhub-chat-avatar" src={receptionist} alt="" />}
            <div className="stayhub-chat-message-body">
                <span className="stayhub-chat-sender" aria-hidden="true">{assistant ? 'Trợ lý StayHub' : 'Bạn'}</span>
                <p className="stayhub-chat-bubble"><span className="sr-only">{assistant ? 'Trợ lý: ' : 'Bạn: '}</span>{children}</p>
                {after}
            </div>
        </div>
    );
}

export function ChatRoomCards({ rooms }) {
    return rooms.map(({ propertyId, propertyName, roomId, roomName }) => (
        <div className="stayhub-chat-card" key={roomId}>
            <div className="stayhub-chat-card-heading">
                <Building2 size={16} aria-hidden="true" />
                <div><strong>{roomName}</strong><span>{propertyName}</span></div>
            </div>
            <div className="stayhub-chat-card-links">
                <Link to={`/properties/${propertyId}`}>Xem khách sạn</Link>
                <Link to={`/rooms/${roomId}`}>Xem phòng</Link>
            </div>
        </div>
    ));
}

export function ChatDestinationCards({ destinations }) {
    return destinations.map(({ city, reason, properties }) => (
        <div className="stayhub-chat-card" key={city}>
            <div className="stayhub-chat-card-heading">
                <Building2 size={16} aria-hidden="true" />
                <div><strong>{city}</strong><span>{reason}</span></div>
            </div>
            <ul className="space-y-2 pt-2">
                {properties.map(({ propertyId, propertyName, startingPrice }) => (
                    <li className="text-sm" key={propertyId}>
                        <Link className="stayhub-chat-action break-words" to={`/properties/${propertyId}`}>{propertyName}</Link>
                        <div className="text-xs text-muted-foreground">Từ {formatVnd(startingPrice)}/đêm</div>
                    </li>
                ))}
            </ul>
        </div>
    ));
}

export function ChatTranscript({ children, revision }) {
    const log = useRef(null);
    useEffect(() => {
        const element = log.current;
        if (element) element.scrollTop = element.scrollHeight;
    }, [revision]);
    return <div ref={log} className="stayhub-chat-log" role="log" aria-label="Tin nhắn">{children}</div>;
}

export default function ChatWidgetFrame({ open, onOpenChange, headerAction, children }) {
    return (
        <Dialog.Root open={open} onOpenChange={onOpenChange}>
            <Dialog.Trigger asChild>
                <button type="button" className="stayhub-chat-launcher" aria-label="Trợ lý StayHub. Bạn cần tư vấn gì?">
                    <span className="sr-only">Trợ lý StayHub</span>
                    <span className="stayhub-chat-launcher-prompt" data-prompt="Bạn cần tư vấn gì?" aria-hidden="true" />
                    <img src={receptionist} alt="" width="96" height="96" />
                </button>
            </Dialog.Trigger>
            <Dialog.Portal>
                <Dialog.Overlay className="fixed inset-0 z-50 bg-black/35" />
                <Dialog.Content className="stayhub-chat-dialog">
                    <header className="stayhub-chat-header">
                        <img className="stayhub-chat-avatar" src={receptionist} alt="" />
                        <Dialog.Title>Trợ lý StayHub</Dialog.Title>
                        <Dialog.Close asChild>
                            <button type="button" aria-label="Đóng trợ lý"><X size={19} aria-hidden="true" /></button>
                        </Dialog.Close>
                    </header>
                    <Dialog.Description className="sr-only">Hỏi đáp tư vấn chung về lưu trú.</Dialog.Description>
                    {headerAction}
                    {children}
                </Dialog.Content>
            </Dialog.Portal>
        </Dialog.Root>
    );
}
