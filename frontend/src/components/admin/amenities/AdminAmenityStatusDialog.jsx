import * as Dialog from '@radix-ui/react-dialog';

export default function AdminAmenityStatusDialog({ amenity, desiredStatus }) {
  return <>
    <Dialog.Title className="text-lg font-semibold">
      {desiredStatus ? 'Kích hoạt lại tiện nghi' : 'Ngưng sử dụng tiện nghi'}
    </Dialog.Title>
    <Dialog.Description className="mt-2 text-sm text-muted-foreground">
      {desiredStatus
        ? 'Các phòng còn liên kết sẽ hiển thị lại tiện nghi khi đủ điều kiện công khai.'
        : 'Tiện nghi sẽ ẩn với khách và không thể gán mới. Các liên kết hiện có vẫn được giữ.'}
    </Dialog.Description>
    {amenity && <dl className="mt-4 flex flex-wrap gap-x-6 gap-y-2 text-sm">
      <div className="min-w-0">
        <dt className="text-muted-foreground">Tiện nghi</dt>
        <dd className="font-medium [overflow-wrap:anywhere]">{amenity.name}</dd>
      </div>
      <div>
        <dt className="text-muted-foreground">Phòng đang gán</dt>
        <dd className="font-medium">{new Intl.NumberFormat('vi-VN').format(amenity.rooms_count)}</dd>
      </div>
    </dl>}
  </>;
}
