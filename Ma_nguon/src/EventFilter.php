<?php
declare(strict_types=1);

/**
 * Lọc danh sách hoạt động tình nguyện kết hợp 3 điều kiện (AND)
 */
function filterEvents(
    array $events,
    string $query,
    string $category = '',
    bool $availableOnly = false
): array {
    $result = [];

    foreach ($events as $event) {
        // Điều kiện 1: Khớp từ khóa tìm kiếm
        $matchesQuery = ($query === '') 
            || (stripos($event['title'], $query) !== false);

        // Điều kiện 2: Khớp lĩnh vực hoạt động
        $matchesCategory = ($category === '') 
            || ($event['category'] === $category);

        // Điều kiện 3: Hoạt động còn suất tuyển TNV (capacity > registered)
        $matchesAvailable = !$availableOnly 
            || ($event['capacity'] > $event['registered']);

        // Kết hợp cả 3 điều kiện bằng toán tử AND
        if ($matchesQuery && $matchesCategory && $matchesAvailable) {
            $result[] = $event;
        }
    }

    return $result;
}
