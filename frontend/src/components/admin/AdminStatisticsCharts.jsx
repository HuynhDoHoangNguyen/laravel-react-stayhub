import { useState } from 'react';
import { CalendarDays, ChartColumn, Wallet } from 'lucide-react';
import { dashboardBarWidths } from '../../services/adminDashboardService.js';
import { formatStatisticsRevenue } from '../../services/statisticsService.js';

const counts = new Intl.NumberFormat('vi-VN');
const monthLabel = (month) => `${month.slice(5)}/${month.slice(0, 4)}`;
const formatBookings = (count) => `${counts.format(count)} đơn`;

function MonthlyChart({ id, icon: Icon, title, description, months, values, selectedIndex, formatValue, emptyMessage }) {
  const heights = dashboardBarWidths(values);
  const maximumIndex = heights.indexOf(100);

  return (
    <section className="admin-statistics__chart-card" aria-labelledby={id}>
      <header className="admin-statistics__chart-heading">
        <span className="admin-statistics__chart-icon"><Icon size={19} aria-hidden="true" /></span>
        <div>
          <h2 id={id}>{title}</h2>
          <p>{description}</p>
        </div>
      </header>
      <p className="admin-statistics__chart-value" aria-live="polite">
        <span>Tháng {monthLabel(months[selectedIndex])}</span>
        <strong>{formatValue(values[selectedIndex])}</strong>
      </p>
      <div className="admin-statistics__chart-plot" aria-hidden="true">
        <span className="admin-statistics__chart-limit">{formatValue(maximumIndex === -1 ? values[0] : values[maximumIndex])}</span>
        <div className="admin-statistics__chart-grid">
          {months.map((month, index) => (
            <div className="admin-statistics__chart-column" key={month}>
              <div className="admin-statistics__chart-track" title={`${monthLabel(month)} · ${formatValue(values[index])}`}>
                <span className="admin-statistics__chart-bar" data-selected={index === selectedIndex}
                  style={{ height: `${heights[index]}%` }} />
              </div>
              <span className="admin-statistics__chart-month" data-selected={index === selectedIndex}>{month.slice(5)}</span>
            </div>
          ))}
        </div>
      </div>
      {maximumIndex === -1 && <p className="admin-statistics__chart-empty">{emptyMessage}</p>}
    </section>
  );
}

export default function AdminStatisticsCharts({ monthly }) {
  const [selectedMonth, setSelectedMonth] = useState(null);
  const months = monthly.map(({ month }) => month);
  const selectedIndex = months.includes(selectedMonth) ? months.indexOf(selectedMonth) : months.length - 1;

  return (
    <section className="admin-statistics__charts" aria-label="Biểu đồ theo tháng">
      <div className="admin-statistics__chart-toolbar">
        <label htmlFor="statistics-chart-month">
          <CalendarDays size={16} aria-hidden="true" />
          Xem tháng
        </label>
        <select id="statistics-chart-month" value={months[selectedIndex]} onChange={(event) => setSelectedMonth(event.target.value)}>
          {months.map((month) => <option key={month} value={month}>{monthLabel(month)}</option>)}
        </select>
      </div>
      <div className="admin-statistics__charts-grid">
        <MonthlyChart id="statistics-commission-chart" icon={Wallet} title="Doanh thu nền tảng theo tháng"
          description="Hoa hồng đã đối soát · Theo ngày đối soát" months={months}
          values={monthly.map((row) => row.commission_revenue_total)} selectedIndex={selectedIndex}
          formatValue={formatStatisticsRevenue} emptyMessage="Chưa có doanh thu nền tảng trong kỳ." />
        <MonthlyChart id="statistics-bookings-chart" icon={ChartColumn} title="Đơn đặt phòng theo tháng"
          description="Theo ngày tạo · Gồm mọi trạng thái" months={months}
          values={monthly.map((row) => row.bookings_count)} selectedIndex={selectedIndex}
          formatValue={formatBookings} emptyMessage="Chưa có đơn đặt phòng trong kỳ." />
      </div>
    </section>
  );
}
