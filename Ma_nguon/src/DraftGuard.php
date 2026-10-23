<?php
declare(strict_types=1);

/**
 * Kiểm tra tính hợp lệ của bản nháp trước khi hoàn tất đăng ký:
 * - Thời gian sống (TTL) phải lớn hơn 0
 * - Phải có bản nháp trong session với đầy đủ dữ liệu (data, created_at, event_id)
 * - Sự kiện được yêu cầu phải khớp với sự kiện trong bản nháp
 * - Thời điểm tạo (created_at) không được ở tương lai so với thời điểm kiểm tra ($now)
 * - Tuổi của bản nháp ($now - created_at) phải nhỏ hơn $ttl (đúng mốc hoặc quá mốc thì từ chối)
 */
function canComplete(array $session, int $requestedEventId, int $now, int $ttl): bool {
    if ($ttl <= 0) {
        return false;
    }

    if (!isset($session['draft']['data']) || !is_array($session['draft']['data'])) {
        return false;
    }

    if (!isset($session['draft']['created_at']) || !is_int($session['draft']['created_at'])) {
        return false;
    }

    $createdAt = $session['draft']['created_at'];
    if ($createdAt > $now) {
        return false;
    }

    if ($now - $createdAt >= $ttl) {
        return false;
    }

    if (!isset($session['draft']['data']['event_id'])) {
        return false;
    }

    if ((int)$session['draft']['data']['event_id'] !== $requestedEventId) {
        return false;
    }

    return true;
}

