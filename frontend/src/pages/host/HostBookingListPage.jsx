import { useCallback, useEffect, useState } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import { getHostBookings, confirmBooking, rejectBooking } from '@/services/hostBookingService';
import { getHostProperties } from '@/services/propertyServiceCatalogService';
import { formatCurrency } from '@/utils/formatCurrency';
import { formatDate } from '@/utils/formatDate';
import { getApiErrorMessage } from '@/utils/getApiErrorMessage';
import StatusBadge from '@/components/booking/shared/StatusBadge';
import ConfirmDialog from '@/components/booking/shared/ConfirmDialog';
import LoadingState from '@/components/booking/shared/LoadingState';
import ErrorState from '@/components/booking/shared/ErrorState';
import EmptyState from '@/components/booking/shared/EmptyState';
import Pagination from '@/components/booking/shared/Pagination';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';

const statuses = [['ALL','Tất cả trạng thái'],['PENDING','Chờ xác nhận'],['CONFIRMED','Đã xác nhận'],['CHECKED_IN','Đang lưu trú'],['COMPLETED','Đã hoàn thành'],['CANCELLED','Đã hủy'],['REJECTED','Đã từ chối']];
const field = 'h-10 rounded-xl';

export default function HostBookingListPage() {
  const [params, setParams] = useSearchParams();
  const [properties, setProperties] = useState([]);
  const [filters, setFilters] = useState({ status: params.get('status') || 'ALL', property_id: params.get('property_id') || 'ALL', start_date: params.get('start_date') || '', end_date: params.get('end_date') || '', keyword: params.get('keyword') || '' });
  const [result, setResult] = useState(null); const [loading, setLoading] = useState(true); const [error, setError] = useState('');
  const [dialog, setDialog] = useState({ open: false, type: '', booking: null, loading: false, error: '' });
  useEffect(() => { getHostProperties().then(r => setProperties(r.data || [])).catch(() => {}); }, []);
  const load = useCallback(async () => {
    setLoading(true); setError('');
    try { const q = Object.fromEntries(params); if (q.status === 'ALL') delete q.status; if (q.property_id === 'ALL') delete q.property_id; delete q.page; const r = await getHostBookings({ ...q, page: params.get('page') || 1 }); setResult(r); }
    catch (e) { setError(e.response?.status === 403 ? 'Bạn không có quyền' : getApiErrorMessage(e).message); } finally { setLoading(false); }
  }, [params]);
  useEffect(() => { load(); }, [load]);
  const apply = (e) => { e.preventDefault(); const next = new URLSearchParams(); Object.entries(filters).forEach(([k,v]) => { if (v && v !== 'ALL') next.set(k,v); }); next.set('page','1'); setParams(next); };
  const pageChange = (page) => { const next = new URLSearchParams(params); next.set('page', String(page)); setParams(next); };
  const act = async () => { setDialog(d => ({ ...d, loading: true, error: '' })); try { if (dialog.type === 'confirm') await confirmBooking(dialog.booking.id); else await rejectBooking(dialog.booking.id); setDialog({ open:false, type:'', booking:null, loading:false, error:'' }); await load(); } catch (e) { setDialog(d => ({ ...d, loading:false, error:e.response?.status === 403 ? 'Bạn không có quyền' : getApiErrorMessage(e).message })); } };
  return <main className="mx-auto max-w-7xl space-y-6 p-4 sm:p-6">
    <header className="flex flex-wrap items-end justify-between gap-4"><div><p className="text-xs font-medium uppercase tracking-[.2em] text-primary">Host · Đặt phòng</p><h1 className="mt-1 font-display text-3xl text-primary">Quản lý đặt phòng</h1><p className="mt-1 text-sm text-muted-foreground">Theo dõi và xử lý booking tại các cơ sở của bạn.</p></div><Button asChild variant="outline" className="rounded-xl"><Link to="/host/services">Quản lý dịch vụ</Link></Button></header>
    <form onSubmit={apply} className="grid gap-4 rounded-2xl border bg-card p-4 sm:grid-cols-2 lg:grid-cols-6">
      <div className="space-y-2"><Label htmlFor="booking-status">Trạng thái</Label><Select value={filters.status} onValueChange={v=>setFilters({...filters,status:v})}><SelectTrigger id="booking-status" className={field}><SelectValue/></SelectTrigger><SelectContent>{statuses.map(([v,l])=><SelectItem key={v} value={v}>{l}</SelectItem>)}</SelectContent></Select></div>
      <div className="space-y-2"><Label htmlFor="booking-property">Cơ sở</Label><Select value={filters.property_id} onValueChange={v=>setFilters({...filters,property_id:v})}><SelectTrigger id="booking-property" className={field}><SelectValue/></SelectTrigger><SelectContent><SelectItem value="ALL">Tất cả cơ sở</SelectItem>{properties.map(p=><SelectItem key={p.id} value={String(p.id)}>{p.name}</SelectItem>)}</SelectContent></Select></div>
      <div className="space-y-2"><Label htmlFor="booking-start">Từ ngày</Label><Input id="booking-start" type="date" value={filters.start_date} onChange={e=>setFilters({...filters,start_date:e.target.value})} className={field}/></div>
      <div className="space-y-2"><Label htmlFor="booking-end">Đến ngày</Label><Input id="booking-end" type="date" value={filters.end_date} onChange={e=>setFilters({...filters,end_date:e.target.value})} className={field}/></div>
      <div className="space-y-2 lg:col-span-2"><Label htmlFor="booking-keyword">Từ khóa</Label><Input id="booking-keyword" value={filters.keyword} onChange={e=>setFilters({...filters,keyword:e.target.value})} placeholder="Mã booking, tên hoặc email khách" className={field}/></div>
      <Button type="submit" className="rounded-xl lg:col-span-6 lg:justify-self-end">Lọc danh sách</Button>
    </form>
    {loading && <LoadingState message="Đang tải danh sách booking…"/>}{!loading && error && <ErrorState title="Không tải được booking" message={error} onRetry={load}/>}
    {!loading && !error && result?.data?.length === 0 && <EmptyState title="Chưa có booking phù hợp" description="Thử đổi trạng thái, cơ sở, khoảng ngày hoặc từ khóa."/>}
    {!loading && !error && !!result?.data?.length && <section className="overflow-hidden rounded-2xl border bg-card"><div className="overflow-x-auto"><Table className="min-w-[1050px]"><TableHeader className="bg-muted/60"><TableRow>{['Mã','Khách','Phòng','Check-in','Check-out','Tổng tiền','Trạng thái','Thao tác'].map(x=><TableHead key={x} className="whitespace-nowrap px-4">{x}</TableHead>)}</TableRow></TableHeader><TableBody>{result.data.map(b=><TableRow key={b.id}>
      <TableCell className="px-4 font-medium"><Link className="text-primary underline-offset-4 hover:underline" to={`/host/bookings/${b.id}`}>{b.booking_code}</Link></TableCell><TableCell className="px-4">{b.customer?.name || 'Khách'}<div className="text-xs text-muted-foreground">{b.customer?.email}</div></TableCell><TableCell className="px-4">{b.room?.name || `Phòng ${b.room?.room_number || ''}`}<div className="text-xs text-muted-foreground">{b.room?.property?.name}</div></TableCell><TableCell className="px-4">{formatDate(b.check_in_date)}</TableCell><TableCell className="px-4">{formatDate(b.check_out_date)}</TableCell><TableCell className="px-4 font-medium">{formatCurrency(b.room_total)}</TableCell><TableCell className="px-4"><StatusBadge status={b.status}/></TableCell><TableCell className="whitespace-nowrap px-4"><div className="flex gap-2">{b.status==='PENDING'&&<><Button size="sm" className="rounded-lg" onClick={()=>setDialog({open:true,type:'confirm',booking:b,loading:false,error:''})}>Xác nhận</Button><Button size="sm" variant="destructive" className="rounded-lg" onClick={()=>setDialog({open:true,type:'reject',booking:b,loading:false,error:''})}>Từ chối</Button></>}<Button asChild size="sm" variant="outline" className="rounded-lg"><Link to={`/host/bookings/${b.id}`}>Chi tiết</Link></Button></div></TableCell>
    </TableRow>)}</TableBody></Table></div><div className="p-4">{result.meta&&<Pagination {...{currentPage:result.meta.current_page,lastPage:result.meta.last_page,total:result.meta.total,perPage:result.meta.per_page,onPageChange:pageChange}}/>}</div></section>}
    <ConfirmDialog isOpen={dialog.open} title={dialog.type==='reject'?'Từ chối booking':'Xác nhận booking'} message={dialog.error || (dialog.type==='reject'?`Từ chối booking ${dialog.booking?.booking_code}?`:`Xác nhận booking ${dialog.booking?.booking_code}?`)} confirmText={dialog.type==='reject'?'Từ chối':'Xác nhận'} isDanger={dialog.type==='reject'} loading={dialog.loading} onConfirm={act} onCancel={()=>setDialog({open:false,type:'',booking:null,loading:false,error:''})}/>
  </main>;
}
