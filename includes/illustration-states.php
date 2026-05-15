<?php
if (!defined('JOBMINGTON')) {
    die('Direct access not permitted');
}

if (!function_exists('jm_state_escape')) {
    function jm_state_escape(?string $value): string {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('jm_illustration_states')) {
    function jm_illustration_states(): array {
        static $states = null;

        if ($states !== null) {
            return $states;
        }

        $states = [
            [
                'key' => 'new_job_alert',
                'title' => 'New Job Alert',
                'phrase' => 'A new job matching your preferences is here.',
                'image' => 'assets/images/icons/New Job Alert.png',
                'alt' => 'Illustration of a service bell revealing job alert envelopes.',
                'type' => 'notification',
            ],
            [
                'key' => 'application_submitted',
                'title' => 'Application Submitted',
                'phrase' => 'Your application has been sent successfully.',
                'image' => 'assets/images/icons/Application Submitted.png',
                'alt' => 'Illustration of an application document with a success check.',
                'type' => 'success',
            ],
            [
                'key' => 'application_received',
                'title' => 'Application Received',
                'phrase' => 'The employer has received your application.',
                'image' => 'assets/images/icons/Application Received.png',
                'alt' => 'Illustration of an envelope containing a checked application message.',
                'type' => 'notification',
            ],
            [
                'key' => 'job_posted_successfully',
                'title' => 'Job Posted Successfully',
                'phrase' => 'Your job is now live and visible to candidates.',
                'image' => 'assets/images/icons/Job Posted Successfully.png',
                'alt' => 'Illustration of a hand holding a megaphone.',
                'type' => 'success',
            ],
            [
                'key' => 'job_activated',
                'title' => 'Job Activated',
                'phrase' => 'Your payment was successful and the job is now active.',
                'image' => 'assets/images/icons/Job Activated.png',
                'alt' => 'Illustration of a thumbs-up hand with a star.',
                'type' => 'success',
            ],
            [
                'key' => 'payment_successful',
                'title' => 'Payment Successful',
                'phrase' => 'Your payment was processed successfully.',
                'image' => 'assets/images/icons/Payment Successful.png',
                'alt' => 'Illustration of a wallet with successful payment check marks.',
                'type' => 'success',
            ],
            [
                'key' => 'account_verified',
                'title' => 'Account Verified',
                'phrase' => 'Your account has been verified successfully.',
                'image' => 'assets/images/icons/Account Verified.png',
                'alt' => 'Illustration of a verified shield with a lock.',
                'type' => 'success',
            ],
            [
                'key' => 'new_notification',
                'title' => 'New Notification',
                'phrase' => 'You have a new notification waiting for you.',
                'image' => 'assets/images/icons/New Notification.png',
                'alt' => 'Illustration of a ringing notification bell with one unread alert.',
                'type' => 'notification',
            ],
            [
                'key' => 'job_expired',
                'title' => 'Job Expired',
                'phrase' => 'This job posting has expired and is no longer active.',
                'image' => 'assets/images/icons/Job Expired.png',
                'alt' => 'Illustration of an hourglass showing an expired job posting.',
                'type' => 'warning',
            ],
            [
                'key' => 'access_restricted',
                'title' => 'Access Restricted',
                'phrase' => 'You don’t have permission to access this page.',
                'image' => 'assets/images/icons/Access Restricted.png',
                'alt' => 'Illustration of a hand holding a restricted access sign.',
                'type' => 'error',
            ],
            [
                'key' => 'no_jobs_found',
                'title' => 'No Jobs Found',
                'phrase' => 'We couldn’t find any jobs that match your search.',
                'image' => 'assets/images/icons/No Jobs Found.png',
                'alt' => 'Illustration of a magnifying glass showing no job results.',
                'type' => 'empty',
            ],
            [
                'key' => 'no_applications_yet',
                'title' => 'No Applications Yet',
                'phrase' => 'You haven’t applied to any jobs yet.',
                'image' => 'assets/images/icons/No Applications Yet.png',
                'alt' => 'Illustration of an empty open box with a paper plane.',
                'type' => 'empty',
            ],
            [
                'key' => 'profile_incomplete',
                'title' => 'Profile Incomplete',
                'phrase' => 'Complete your profile to unlock more opportunities.',
                'image' => 'assets/images/icons/Profile Incomplete.png',
                'alt' => 'Illustration of a user profile with an add symbol.',
                'type' => 'prompt',
            ],
            [
                'key' => 'insufficient_balance',
                'title' => 'Insufficient Balance',
                'phrase' => 'You don’t have enough balance to complete this action.',
                'image' => 'assets/images/icons/Insufficient Balance.png',
                'alt' => 'Illustration of a wallet with an insufficient balance warning.',
                'type' => 'warning',
            ],
            [
                'key' => 'all_good',
                'title' => 'All Good!',
                'phrase' => 'Everything is up to date. You’re all set.',
                'image' => 'assets/images/icons/All Good!.png',
                'alt' => 'Illustration of an OK hand sign with a success check bubble.',
                'type' => 'success',
            ],
        ];

        return $states;
    }
}

if (!function_exists('jm_illustration_state')) {
    function jm_illustration_state(string $key): ?array {
        foreach (jm_illustration_states() as $state) {
            if ($state['key'] === $key) {
                return $state;
            }
        }

        return null;
    }
}

if (!function_exists('jm_state_asset_url')) {
    function jm_state_asset_url(string $path): string {
        if ($path === '' || str_starts_with($path, 'http://') || str_starts_with($path, 'https://') || str_starts_with($path, '/')) {
            return $path;
        }

        $segments = array_map('rawurlencode', explode('/', str_replace('\\', '/', $path)));
        return '/jobmington/' . implode('/', $segments);
    }
}

if (!function_exists('jm_illustration_state_card')) {
    function jm_illustration_state_card($stateOrKey, array $options = []): string {
        $state = is_array($stateOrKey) ? $stateOrKey : jm_illustration_state((string) $stateOrKey);
        if (!$state) {
            return '';
        }

        $titleTag = strtolower((string) ($options['title_tag'] ?? 'h2'));
        if (!in_array($titleTag, ['h1', 'h2', 'h3', 'h4', 'h5', 'h6'], true)) {
            $titleTag = 'h2';
        }

        $element = strtolower((string) ($options['element'] ?? 'article'));
        if (!in_array($element, ['article', 'div', 'section'], true)) {
            $element = 'article';
        }

        $classes = ['jm-state-card', 'jm-state-type-' . preg_replace('/[^a-z0-9_-]/i', '', (string) ($state['type'] ?? 'default'))];
        if (!empty($options['compact'])) {
            $classes[] = 'jm-state-card-compact';
        }
        if (!empty($options['inline'])) {
            $classes[] = 'jm-state-card-inline';
        }
        if (!empty($options['class'])) {
            $classes[] = (string) $options['class'];
        }

        $actions = trim((string) ($options['actions'] ?? ''));
        $ariaLive = !empty($options['aria_live']) ? ' aria-live="' . jm_state_escape((string) $options['aria_live']) . '"' : '';
        $imageUrl = jm_state_asset_url((string) $state['image']);
        $key = jm_state_escape((string) $state['key']);

        $html = '<' . $element . ' class="' . jm_state_escape(implode(' ', $classes)) . '" data-state-key="' . $key . '"' . $ariaLive . '>';
        $html .= '<div class="jm-state-visual"><img src="' . jm_state_escape($imageUrl) . '" alt="' . jm_state_escape((string) $state['alt']) . '" loading="lazy"></div>';
        $html .= '<div class="jm-state-copy">';
        $html .= '<' . $titleTag . '>' . jm_state_escape((string) $state['title']) . '</' . $titleTag . '>';
        $html .= '<p>' . jm_state_escape((string) $state['phrase']) . '</p>';
        if ($actions !== '') {
            $html .= '<div class="jm-state-actions">' . $actions . '</div>';
        }
        $html .= '</div>';
        $html .= '</' . $element . '>';

        return $html;
    }
}

if (!function_exists('jm_empty_state_card')) {
    function jm_empty_state_card(string $key, array $options = []): string {
        $options['class'] = trim('jm-state-card-empty ' . ($options['class'] ?? ''));
        return jm_illustration_state_card($key, $options);
    }
}

if (!function_exists('jm_notification_state_card')) {
    function jm_notification_state_card(string $key, array $options = []): string {
        $options['class'] = trim('jm-state-card-notification ' . ($options['class'] ?? ''));
        return jm_illustration_state_card($key, $options);
    }
}

if (!function_exists('jm_render_illustration_state')) {
    function jm_render_illustration_state(string $key, array $options = []): void {
        echo jm_illustration_state_card($key, $options);
    }
}
?>
