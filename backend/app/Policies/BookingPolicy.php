<?php

namespace App\Policies;

use App\Models\Booking;
use App\Models\User;
use App\Enums\UserRole;
use Illuminate\Auth\Access\Response;

class BookingPolicy
{
    /**
     * Determine whether the user can view the booking.
     */
    public function view(User $user, Booking $booking): Response
    {
        if ($user->id === $booking->customer_id) {
            return Response::allow();
        }

        $hostId = $booking->room?->property?->host_id;
        if ($hostId && $user->id === $hostId) {
            return Response::allow();
        }

        return Response::denyWithStatus(404, 'Không tìm thấy thông tin đặt phòng.');
    }

    /**
     * Determine whether the host can manage (confirm/reject/checkIn/checkOut) the booking.
     */
    public function manageHost(User $user, Booking $booking): Response
    {
        $role = $user->role instanceof UserRole ? $user->role : UserRole::tryFrom((string) $user->role);
        if ($role !== UserRole::HOST) {
            return Response::denyWithStatus(403, 'Bạn không có quyền truy cập.');
        }

        $hostId = $booking->room?->property?->host_id;
        if ($hostId && $user->id === $hostId) {
            return Response::allow();
        }

        return Response::denyWithStatus(404, 'Không tìm thấy thông tin đặt phòng.');
    }

    /**
     * Determine whether the user can cancel the booking.
     */
    public function cancel(User $user, Booking $booking): Response
    {
        if ($user->id === $booking->customer_id) {
            return Response::allow();
        }

        $hostId = $booking->room?->property?->host_id;
        if ($hostId && $user->id === $hostId) {
            return Response::allow();
        }

        return Response::denyWithStatus(404, 'Không tìm thấy thông tin đặt phòng.');
    }
}
