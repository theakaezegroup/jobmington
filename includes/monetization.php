<?php
if (!defined('JOBMINGTON')) {
    die('Direct access not permitted');
}

function jm_job_posting_packages(): array {
    return [
        'starter' => [
            'id' => 'starter',
            'name' => 'Start',
            'price' => 0,
            'duration_days' => 30,
            'featured' => false,
            'badge' => 'Free for now',
            'description' => 'Post one role and start receiving applications without paying upfront.',
            'features' => ['Live for 30 days', 'Manage applicants from your dashboard', 'Good for one simple hire'],
        ],
        'featured' => [
            'id' => 'featured',
            'name' => 'Reach more candidates',
            'price' => 15000,
            'duration_days' => 45,
            'featured' => true,
            'badge' => 'Most popular',
            'description' => 'Give an important role more attention across the site.',
            'features' => ['Live for 45 days', 'Shown in featured job areas', 'Better visibility in job search'],
        ],
        'hiring_boost' => [
            'id' => 'hiring_boost',
            'name' => 'Hire faster',
            'price' => 45000,
            'duration_days' => 60,
            'featured' => true,
            'badge' => 'For urgent hires',
            'description' => 'For roles that need extra attention and a cleaner shortlist.',
            'features' => ['Live for 60 days', 'Featured across the site', 'Priority help with the listing', 'Listing reviewed before it goes live'],
        ],
    ];
}

function jm_job_posting_package(string $packageId): array {
    $packages = jm_job_posting_packages();
    return $packages[$packageId] ?? $packages['starter'];
}

function jm_money_ngn(float $amount): string {
    if ($amount <= 0) {
        return 'Free';
    }

    return 'NGN ' . number_format($amount, 0);
}

function jm_job_payment_plan_value(int $jobId, string $packageId, string $packageName): string {
    return 'Job posting: ' . $packageName . ' | job:' . $jobId . ' | package:' . $packageId;
}

function jm_parse_job_payment_plan(string $plan): array {
    $jobId = 0;
    $packageId = 'starter';

    if (preg_match('/job:(\d+)/', $plan, $match)) {
        $jobId = (int) $match[1];
    }
    if (preg_match('/package:([a-z0-9_]+)/', $plan, $match)) {
        $packageId = $match[1];
    }

    return [$jobId, $packageId];
}

function jm_job_payment_reference(): string {
    return 'JMJOB-' . time() . '-' . bin2hex(random_bytes(5));
}
