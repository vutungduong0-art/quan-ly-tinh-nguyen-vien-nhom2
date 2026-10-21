<?php
declare(strict_types=1);
final class RegistrationPolicy {
    public function decide(int $capacity, int $registered,
        bool $open, bool $waitingEnabled): string {
        // 1. Kiểm tra tính hợp lệ của dữ liệu đầu vào
        if ($capacity <= 0 || $registered < 0 || $registered > $capacity) {
            return 'rejected';
        }

        // 2. Kiểm tra trạng thái đóng/mở của sự kiện
        if (!$open) {
            return 'rejected';
        }

        // 3. Sự kiện mở và còn chỗ
        if ($registered < $capacity) {
            return 'confirmed';
        }

        // 4. Sự kiện mở, hết chỗ: xét danh sách chờ
        if ($waitingEnabled) {
            return 'waiting';
        }

        return 'rejected';
    }
}
