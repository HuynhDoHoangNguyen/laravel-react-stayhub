import { useEffect, useState } from 'react';
import { checkRoomAvailability } from '@/services/availabilityService';
import { REASON_TRANSLATIONS, getApiErrorMessage } from '@/utils/getApiErrorMessage';
import { formatDate } from '@/utils/formatDate';
import { Skeleton } from '@/components/ui/skeleton';

export default function AvailabilityChecker({ roomId, checkInDate, checkOutDate, guestCount }) {
  const [loading,setLoading]=useState(false); const [result,setResult]=useState(null); const [error,setError]=useState('');
  useEffect(()=>{
    if(!roomId||!checkInDate||!checkOutDate||!guestCount)return undefined;
    let active=true;
    Promise.resolve().then(()=>{if(active){setLoading(true);setError('');setResult(null);}});
    checkRoomAvailability(roomId,{check_in_date:checkInDate,check_out_date:checkOutDate,guest_count:Number(guestCount)})
      .then(response=>{if(active)setResult(response.data);})
      .catch(requestError=>{if(active)setError(getApiErrorMessage(requestError).message);})
      .finally(()=>{if(active)setLoading(false);});
    return ()=>{active=false;};
  },[roomId,checkInDate,checkOutDate,guestCount]);
  if(!roomId||!checkInDate||!checkOutDate)return null;
  if(loading)return <div className="my-4 space-y-2" role="status" aria-live="polite"><span className="sr-only">Đang kiểm tra tình trạng phòng</span><Skeleton className="h-12 w-full rounded-xl"/></div>;
  if(error)return <div className="my-4 rounded-xl border border-destructive/30 bg-destructive/5 p-4 text-sm text-destructive" role="alert"><strong>Không thể kiểm tra phòng:</strong> {error}</div>;
  if(!result)return null;
  const {available,reasons=[],blocked_ranges=[]}=result;
  return <div className="my-4 space-y-3" aria-live="polite">
    <div className={`rounded-xl border p-4 text-sm ${available?'border-primary/20 bg-primary/5 text-primary':'border-destructive/30 bg-destructive/5 text-destructive'}`} role="status">
      <strong>{available?'Phòng khả dụng':'Phòng không khả dụng'}:</strong> {available?'Phòng hiện có thể đặt theo khoảng ngày đã chọn.':'Phòng không khả dụng trong khoảng ngày này.'}
      {!available&&reasons.length>0&&<ul className="mt-2 list-inside list-disc">{reasons.map((reason,index)=><li key={`${reason}-${index}`}>{REASON_TRANSLATIONS[reason]||reason}</li>)}</ul>}
    </div>
    {blocked_ranges.length>0&&<div className="rounded-xl border border-accent/40 bg-accent-pale/40 p-4 text-sm text-foreground"><strong>Lịch bận của phòng:</strong><ul className="mt-2 list-inside list-disc">{blocked_ranges.map((range,index)=><li key={`${range.start_date}-${range.end_date}-${index}`}>Từ {formatDate(range.start_date)} đến {formatDate(range.end_date)} ({range.type==='BOOKING'?'Khách đã đặt':'Bảo trì'})</li>)}</ul></div>}
  </div>;
}
