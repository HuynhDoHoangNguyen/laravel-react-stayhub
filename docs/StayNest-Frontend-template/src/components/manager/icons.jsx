/**
 * Custom duotone icon set for the manager console.
 * Each icon pairs a soft filled shape with a crisp stroke overlay,
 * giving them a warm, hand-drafted character that reads as premium
 * rather than generic line-icon.
 */
const S = {
    stroke: "currentColor",
    strokeWidth: 1.6,
    strokeLinecap: "round",
    strokeLinejoin: "round",
    fill: "none",
};
const FILL = {
    fill: "currentColor",
    fillOpacity: 0.14,
    stroke: "none",
};
function wrap(children, props) {
    const { size = 24, className, ...rest } = props;
    return (<svg viewBox="0 0 24 24" width={size} height={size} className={className} {...rest}>
      {children}
    </svg>);
}
/* ---------- Chrome ---------- */
export function PanelToggle(props) {
    return wrap(<>
      <rect x="3.5" y="5" width="6" height="14" rx="1.6" {...FILL}/>
      <rect x="3.5" y="5" width="17" height="14" rx="2" {...S}/>
      <path d="M9.5 5v14" {...S}/>
      <circle cx="6.5" cy="9" r="0.6" fill="currentColor"/>
      <circle cx="6.5" cy="12" r="0.6" fill="currentColor"/>
    </>, props);
}
export function BackIcon(props) {
    return wrap(<>
      <circle cx="12" cy="12" r="9.5" {...FILL}/>
      <path d="M14 7l-5 5 5 5" {...S}/>
      <path d="M9 12h9" {...S}/>
    </>, props);
}
export function SignOutIcon(props) {
    return wrap(<>
      <path d="M4 5a2 2 0 0 1 2-2h5v18H6a2 2 0 0 1-2-2z" {...FILL}/>
      <path d="M11 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h5" {...S}/>
      <path d="M16 8l4 4-4 4" {...S}/>
      <path d="M9 12h11" {...S}/>
    </>, props);
}
/* ---------- Nav ---------- */
export function HotelIcon(props) {
    return wrap(<>
      {/* building body */}
      <path d="M5 20V9.5l7-4.5 7 4.5V20z" {...FILL}/>
      <path d="M4 20h16" {...S}/>
      <path d="M5 20V9.5l7-4.5 7 4.5V20" {...S}/>
      {/* door */}
      <rect x="10" y="14" width="4" height="6" rx="0.5" {...S}/>
      {/* windows */}
      <rect x="7" y="11" width="2" height="2" rx="0.3" fill="currentColor"/>
      <rect x="15" y="11" width="2" height="2" rx="0.3" fill="currentColor"/>
      {/* flag */}
      <path d="M12 5V2.5" {...S}/>
      <path d="M12 2.5l2.2 0.8L12 4.1z" fill="currentColor"/>
    </>, props);
}
export function BookingsIcon(props) {
    return wrap(<>
      <rect x="4" y="5" width="16" height="16" rx="2.4" {...FILL}/>
      <rect x="4" y="5" width="16" height="16" rx="2.4" {...S}/>
      <path d="M4 10h16" {...S}/>
      <path d="M8.5 3v4" {...S}/>
      <path d="M15.5 3v4" {...S}/>
      <circle cx="9" cy="14.5" r="1.1" fill="currentColor"/>
      <path d="M12.5 14.5h4" {...S}/>
      <path d="M8 18h8" {...S}/>
    </>, props);
}
export function RefundIcon(props) {
    return wrap(<>
      <circle cx="12" cy="12" r="9" {...FILL}/>
      <circle cx="12" cy="12" r="9" {...S}/>
      {/* rupee-style R */}
      <path d="M9 7.5h6" {...S}/>
      <path d="M9 10h6" {...S}/>
      <path d="M9.5 7.5c2.5 0 3.5 1 3.5 2.5S12 12.5 10 12.5H9l5 4" {...S}/>
    </>, props);
}
export function ReportsIcon(props) {
    return wrap(<>
      <path d="M4 20h16" {...S}/>
      <rect x="5.5" y="12" width="3" height="8" rx="0.8" {...FILL}/>
      <rect x="5.5" y="12" width="3" height="8" rx="0.8" {...S}/>
      <rect x="10.5" y="8" width="3" height="12" rx="0.8" {...FILL}/>
      <rect x="10.5" y="8" width="3" height="12" rx="0.8" {...S}/>
      <rect x="15.5" y="4" width="3" height="16" rx="0.8" {...FILL}/>
      <rect x="15.5" y="4" width="3" height="16" rx="0.8" {...S}/>
      {/* trend spark */}
      <path d="M6 6l4 -2 4 1 4 -3" {...S} strokeDasharray="0"/>
      <circle cx="18" cy="2" r="1" fill="currentColor"/>
    </>, props);
}
export function SettingsIcon(props) {
    return wrap(<>
      {/* rotated square + circle, softer than gear cliche */}
      <path d="M12 3l3 2 3.5-0.5L18 8l2 3-2 3 0.5 3.5L15 17l-3 2-3-2-3.5 0.5L6 14l-2-3 2-3-0.5-3.5L9 5z" {...FILL}/>
      <path d="M12 3l3 2 3.5-0.5L18 8l2 3-2 3 0.5 3.5L15 17l-3 2-3-2-3.5 0.5L6 14l-2-3 2-3-0.5-3.5L9 5z" {...S}/>
      <circle cx="12" cy="11" r="2.6" {...S}/>
    </>, props);
}
/* ---------- Header controls ---------- */
export function SearchIcon(props) {
    return wrap(<>
      <circle cx="10.5" cy="10.5" r="6.5" {...FILL}/>
      <circle cx="10.5" cy="10.5" r="6.5" {...S}/>
      <path d="M15.5 15.5l5 5" {...S}/>
      <path d="M7 10.5a3.5 3.5 0 0 1 3.5-3.5" {...S} strokeOpacity="0.6"/>
    </>, props);
}
export function BellIcon(props) {
    return wrap(<>
      <path d="M5 17c1.5-1.5 2-3.5 2-6a5 5 0 0 1 10 0c0 2.5 0.5 4.5 2 6z" {...FILL}/>
      <path d="M5 17c1.5-1.5 2-3.5 2-6a5 5 0 0 1 10 0c0 2.5 0.5 4.5 2 6z" {...S}/>
      <path d="M5 17h14" {...S}/>
      <path d="M10.5 20a1.8 1.8 0 0 0 3 0" {...S}/>
      <circle cx="12" cy="4" r="1" fill="currentColor"/>
    </>, props);
}
/* ---------- Actions ---------- */
export function PlusIcon(props) {
    return wrap(<>
      <circle cx="12" cy="12" r="9" {...FILL}/>
      <circle cx="12" cy="12" r="9" {...S}/>
      <path d="M12 7.5v9" {...S}/>
      <path d="M7.5 12h9" {...S}/>
    </>, props);
}
export function StarIcon(props) {
    return wrap(<>
      <path d="M12 3.5l2.6 5.3 5.9 0.8-4.3 4.1 1 5.8L12 16.8 6.8 19.5l1-5.8L3.5 9.6l5.9-0.8z" {...FILL}/>
      <path d="M12 3.5l2.6 5.3 5.9 0.8-4.3 4.1 1 5.8L12 16.8 6.8 19.5l1-5.8L3.5 9.6l5.9-0.8z" {...S}/>
      <circle cx="12" cy="11" r="0.9" fill="currentColor"/>
    </>, props);
}
export function MapPinIcon(props) {
    return wrap(<>
      <path d="M12 21c-4-5-6-8-6-11a6 6 0 0 1 12 0c0 3-2 6-6 11z" {...FILL}/>
      <path d="M12 21c-4-5-6-8-6-11a6 6 0 0 1 12 0c0 3-2 6-6 11z" {...S}/>
      <circle cx="12" cy="10" r="2.2" fill="currentColor"/>
      <ellipse cx="12" cy="21.5" rx="2.5" ry="0.6" {...S} strokeOpacity="0.5"/>
    </>, props);
}
export function TrashIcon(props) {
    return wrap(<>
      <path d="M6 7h12l-1 13a2 2 0 0 1-2 2H9a2 2 0 0 1-2-2z" {...FILL}/>
      <path d="M6 7h12l-1 13a2 2 0 0 1-2 2H9a2 2 0 0 1-2-2z" {...S}/>
      <path d="M4 7h16" {...S}/>
      <path d="M9.5 7V5a1.5 1.5 0 0 1 1.5-1.5h2A1.5 1.5 0 0 1 14.5 5v2" {...S}/>
      <path d="M10 11v7" {...S}/>
      <path d="M14 11v7" {...S}/>
    </>, props);
}
export function ConsoleIcon(props) {
    return wrap(<>
      <rect x="3" y="4" width="18" height="14" rx="2.2" {...FILL}/>
      <rect x="3" y="4" width="18" height="14" rx="2.2" {...S}/>
      <path d="M7 9l2.5 2.5L7 14" {...S}/>
      <path d="M12 14h5" {...S}/>
      <path d="M9 21h6" {...S}/>
      <path d="M12 18v3" {...S}/>
    </>, props);
}
