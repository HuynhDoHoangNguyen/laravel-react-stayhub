import { hostDashboardBarWidths } from '../../services/hostDashboardService.js';
import { formatVnd } from '../../utils/formatVnd.js';

export default function HostDashboardRevenueChart({ rows }) {
  const widths = hostDashboardBarWidths(rows.map((row) => row.revenue_total));
  return <section aria-label="Doanh thu theo tháng" className="host-dashboard__chart">
    <p>Số tiền hiển thị làm tròn đến đồng; độ dài cột dựa trên giá trị gốc.</p>
    <div role="img" aria-label="Biểu đồ doanh thu từng tháng; giá trị tương đương trong bảng bên dưới.">
      {rows.map((row, index) => <div key={row.month} className="host-dashboard__bar-row" aria-hidden="true">
        <span>{row.month}</span><span className="host-dashboard__bar-track"><span style={{ width: `${widths[index]}%` }} /></span>
        <span>{formatVnd(row.revenue_total)}</span>
      </div>)}
    </div>
    {rows.every((row) => row.revenue_total === '0.00') && <p>Chưa có doanh thu được ghi nhận trong kỳ này.</p>}
    <div className="host-dashboard__table-wrap" tabIndex={0} role="region" aria-label="Bảng doanh thu theo tháng">
      <table><caption>Doanh thu đã thanh toán theo tháng — VND</caption>
        <thead><tr><th scope="col">Tháng</th><th scope="col">Doanh thu</th></tr></thead>
        <tbody>{rows.map((row) => <tr key={row.month}><th scope="row">{row.month}</th><td>{formatVnd(row.revenue_total)}</td></tr>)}</tbody>
      </table>
    </div>
  </section>;
}
