<?php

declare(strict_types=1);

final class ComparisonService
{
    public function compare(array $snapshot, array $config = []): array
    {
        $checker = new EligibilityChecker();
        $baseline = (new RegistrationOrderAllocator($checker))->allocate($snapshot, $config['baseline'] ?? []);
        $improved = (new ScarcityBalancedAllocator($checker))->allocate($snapshot, $config['improved'] ?? []);
        return [
            'dataset' => $snapshot['dataset'] ?? [],
            'snapshot_sha256' => Snapshot::hash($snapshot),
            'generated_at' => gmdate('c'),
            'baseline' => $baseline,
            'improved' => $improved,
        ];
    }
}
