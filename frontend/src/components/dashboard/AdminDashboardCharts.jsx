import { useState } from 'react';
import { Link } from 'react-router-dom';
import { ArrowUpRight, ChartColumn, ChartNoAxesCombined, ChevronDown } from 'lucide-react';
import { dashboardBarWidths, formatDashboardRevenue } from '../../services/adminDashboardService';
import './AdminDashboardCharts.css';

const labels = { PENDING: 'Chờ xác nhận', CONFIRMED: 'Đã xác nhận', CHECKED_IN: 'Đã nhận phòng', COMPLETED: 'Đã hoàn thành', CANCELLED: 'Đã hủy', REJECTED: 'Từ chối' };
const counts = new Intl.NumberFormat('vi-VN');
const monthLabel = (month) => `${month.slice(5)}/${month.slice(0, 4)}`;

function DataTable({ rows, valueLabel, caption }) {
  return <details className="admin-dashboard__chart-data">
    <summary>Xem số liệu <ChevronDown size={16} aria-hidden="true" /></summary>
    <table><caption className="sr-only">{caption}</caption>
      <thead><tr><th scope="col">Chỉ tiêu</th><th scope="col">{valueLabel}</th></tr></thead>
      <tbody>{rows.map((row) => <tr key={row.key}><th scope="row">{row.label}</th><td>{row.display}</td></tr>)}</tbody>
    </table>
  </details>;
}

function BookingChart({ statuses }) {
  const rows = statuses.map((row) => ({ key: row.status, label: labels[row.status], value: row.count, display: counts.format(row.count) }));
  const heights = dashboardBarWidths(rows.map((row) => row.value));
  const maximum = Math.max(...rows.map((row) => row.value));
  return <section className="admin-dashboard__chart-card">
    <header className="admin-dashboard__chart-heading">
      <span className="admin-dashboard__chart-icon"><ChartColumn size={21} aria-hidden="true" /></span>
      <div><h2>Đơn đặt phòng theo trạng thái</h2><p>Toàn thời gian · 6 trạng thái</p></div>
    </header>
    <p className="admin-dashboard__chart-description">Tổng số đơn khớp với chỉ tiêu đơn đặt phòng phía trên.</p>
    {maximum === 0 && <p className="admin-dashboard__chart-empty">Chưa có đơn đặt phòng trong hệ thống.</p>}
    <div className="admin-dashboard__column-chart" role="img" aria-label="Biểu đồ cột đơn đặt phòng theo sáu trạng thái; số liệu đầy đủ bên dưới.">
      <div className="admin-dashboard__chart-scale" aria-hidden="true"><span>{counts.format(maximum)}</span><span>0</span></div>
      <div className="admin-dashboard__columns" aria-hidden="true">{rows.map((row, index) => <div className="admin-dashboard__column-item" key={row.key}>
        <span className="admin-dashboard__column-count">{row.display}</span>
        <div className="admin-dashboard__column-track"><div className={`admin-dashboard__column admin-dashboard__status-${row.key.toLowerCase()}`} style={{ height: `${heights[index]}%` }} title={`${row.label}: ${row.display} đơn`} /></div>
        <span className="admin-dashboard__column-label">{row.label}</span>
      </div>)}</div>
    </div>
    <DataTable rows={rows} valueLabel="Số đơn" caption="Số đơn đặt phòng theo trạng thái" />
  </section>;
}

function RevenueChart({ revenue }) {
  const [selectedMonth, setSelectedMonth] = useState(null);
  const matchingIndex = revenue.findIndex((row) => row.month === selectedMonth);
  const selectedIndex = matchingIndex < 0 ? revenue.length - 1 : matchingIndex;
  const rows = revenue.map((row) => ({ key: row.month, label: monthLabel(row.month), value: row.total_amount, display: formatDashboardRevenue(row.total_amount) }));
  // Money remains decimal strings; only bounded presentation ratios become coordinates.
  const heights = dashboardBarWidths(rows.map((row) => row.value));
  const points = heights.map((height, index) => ({ x: 16 + index * 568 / (rows.length - 1), y: 178 - height * 1.5 }));
  const line = points.map((point) => `${point.x},${point.y}`).join(' ');
  const selected = rows[selectedIndex];
  return <section className="admin-dashboard__chart-card">
    <header className="admin-dashboard__chart-heading">
      <span className="admin-dashboard__chart-icon admin-dashboard__chart-icon--revenue"><ChartNoAxesCombined size={21} aria-hidden="true" /></span>
      <div><h2>Doanh thu 12 tháng gần nhất</h2><p>{monthLabel(revenue[0].month)} – {monthLabel(revenue.at(-1).month)} · Giờ Việt Nam</p></div>
    </header>
    <p className="admin-dashboard__chart-description">Tháng hiện tại tính đến thời điểm báo cáo. KPI doanh thu là toàn thời gian.</p>
    <div className="admin-dashboard__revenue-value"><span>Tháng {selected.label}</span><strong>{selected.display}</strong></div>
    {heights.every((height) => height === 0) && <p className="admin-dashboard__chart-empty">Chưa có doanh thu trong 12 tháng này.</p>}
    <svg className="admin-dashboard__line-chart" viewBox="0 0 600 200" role="img" aria-label="Biểu đồ đường doanh thu theo tháng; chọn tháng hoặc mở số liệu bên dưới để xem giá trị chính xác.">
      <g aria-hidden="true">
        {[28, 78, 128, 178].map((y) => <line className="admin-dashboard__gridline" key={y} x1="16" x2="584" y1={y} y2={y} />)}
        <polygon className="admin-dashboard__line-area" points={`16,178 ${line} 584,178`} />
        <polyline className="admin-dashboard__revenue-line" points={line} />
        <line className="admin-dashboard__selection-line" x1={points[selectedIndex].x} x2={points[selectedIndex].x} y1="20" y2="178" />
        {points.map((point, index) => <circle key={rows[index].key} className={`admin-dashboard__revenue-point${index === selectedIndex ? ' is-selected' : ''}`} cx={point.x} cy={point.y} r={index === selectedIndex ? 6 : 4}>
          <title>{`${rows[index].label}: ${rows[index].display}`}</title>
        </circle>)}
      </g>
    </svg>
    <div className="admin-dashboard__months" role="group" aria-label="Chọn tháng xem doanh thu">
      {rows.map((row) => <button key={row.key} type="button" aria-pressed={row.key === selected.key} aria-label={`Tháng ${row.label}: ${row.display}`} onClick={() => setSelectedMonth(row.key)}>{row.key.slice(5)}</button>)}
    </div>
    <div className="admin-dashboard__chart-footer"><span>Chọn tháng để xem doanh thu</span><Link to="/admin/invoices" className="admin-dashboard__link">Xem hóa đơn <ArrowUpRight size={15} aria-hidden="true" /></Link></div>
    <DataTable rows={rows} valueLabel="Doanh thu" caption="Doanh thu theo tháng, đơn vị VND" />
  </section>;
}

export default function AdminDashboardCharts({ statuses, revenue }) {
  return <div className="admin-dashboard__charts"><BookingChart statuses={statuses} /><RevenueChart revenue={revenue} /></div>;
}
