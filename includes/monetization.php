<?php
if (!defined('JOBMINGTON')) {
    die('Direct access not permitted');
}

// ═══════════════════════════════════════════════════════════════════
//  EMPLOYER PLANS
// ═══════════════════════════════════════════════════════════════════

function jm_employer_single_post_package(): array {
    return [
        'id'            => 'single_post',
        'name'          => 'Single Job Post',
        'price'         => PRICE_EMPLOYER_SINGLE_POST,
        'duration_days' => 30,
        'featured'      => false,
        'badge'         => '',
        'description'   => 'Post one role and start receiving applications from African talent.',
        'features'      => ['Live for 30 days', 'Full applicant dashboard', 'Company branding on listing'],
        'type'          => 'one_off',
    ];
}

function jm_employer_plans(): array {
    return [
        'basic' => [
            'id'             => 'basic',
            'name'           => 'Basic',
            'price_monthly'  => PRICE_EMPLOYER_BASIC_MONTHLY,
            'post_limit'     => EMPLOYER_BASIC_POST_LIMIT,
            'badge'          => 'Good for occasional hiring',
            'description'    => 'For companies that hire a few times a year.',
            'features'       => [
                EMPLOYER_BASIC_POST_LIMIT . ' active job posts',
                'Company profile page',
                'Applicant tracking dashboard',
                'Basic candidate filtering',
            ],
            'paystack_plan_code' => getenv('PAYSTACK_PLAN_EMPLOYER_BASIC') ?: '',
            'type'           => 'subscription',
        ],
        'pro' => [
            'id'             => 'pro',
            'name'           => 'Pro',
            'price_monthly'  => PRICE_EMPLOYER_PRO_MONTHLY,
            'post_limit'     => 0, // unlimited
            'badge'          => 'Most popular',
            'description'    => 'For teams that hire continuously.',
            'features'       => [
                'Unlimited job posts',
                '1 free featured job per month',
                'Candidate search & talent pool',
                'Priority listing support',
                'Advanced applicant filtering',
            ],
            'paystack_plan_code' => getenv('PAYSTACK_PLAN_EMPLOYER_PRO') ?: '',
            'type'           => 'subscription',
        ],
    ];
}

function jm_employer_plan(string $planId): array {
    return jm_employer_plans()[$planId] ?? [];
}

function jm_featured_addon(): array {
    return [
        'id'          => 'featured_addon',
        'name'        => 'Featured Job Boost',
        'price'       => PRICE_EMPLOYER_FEATURED_ADDON,
        'description' => 'Pin your listing at the top of search results with a Featured badge.',
    ];
}

// ═══════════════════════════════════════════════════════════════════
//  SEEKER PLANS
// ═══════════════════════════════════════════════════════════════════

function jm_seeker_plans(): array {
    return [
        'monthly' => [
            'id'          => 'monthly',
            'name'        => 'Premium Monthly',
            'price'       => PRICE_SEEKER_PREMIUM_MONTHLY,
            'interval'    => 'monthly',
            'badge'       => 'Flexible',
            'description' => 'Full access to all AI tools and premium perks. Cancel any time.',
            'features'    => [
                'Unlimited AI CV optimisation',
                'Unlimited cover letter generation',
                'Interview prep (unlimited sessions)',
                'Skills gap analysis with resource links',
                'Download CV as PDF & Word',
                'Priority application (shown higher to employers)',
                'Early job alerts (24 h before free users)',
                'Saved job searches & email digests',
            ],
            'paystack_plan_code' => getenv('PAYSTACK_PLAN_SEEKER_MONTHLY') ?: '',
            'type'        => 'subscription',
        ],
        'annual' => [
            'id'          => 'annual',
            'name'        => 'Premium Annual',
            'price'       => PRICE_SEEKER_PREMIUM_ANNUAL,
            'interval'    => 'annually',
            'badge'       => '2 months free',
            'description' => 'All Premium features, billed once a year. Best value.',
            'features'    => [
                'Everything in Premium Monthly',
                'Save ' . jm_format_ngn(PRICE_SEEKER_PREMIUM_MONTHLY * 12 - PRICE_SEEKER_PREMIUM_ANNUAL) . ' vs monthly',
            ],
            'paystack_plan_code' => getenv('PAYSTACK_PLAN_SEEKER_ANNUAL') ?: '',
            'type'        => 'subscription',
        ],
    ];
}

function jm_seeker_plan(string $planId): array {
    return jm_seeker_plans()[$planId] ?? [];
}

// ═══════════════════════════════════════════════════════════════════
//  CREDIT PACKS
// ═══════════════════════════════════════════════════════════════════

function jm_credit_packs(): array {
    return [
        'single' => [
            'id'          => 'single',
            'name'        => '1 Credit',
            'credits'     => 1,
            'price'       => PRICE_CREDITS_SINGLE,
            'badge'       => '',
            'description' => 'Unlock one AI tool use.',
            'savings'     => 0,
        ],
        'pack_5' => [
            'id'          => 'pack_5',
            'name'        => '5 Credits',
            'credits'     => 5,
            'price'       => PRICE_CREDITS_PACK_5,
            'badge'       => 'Popular',
            'description' => 'Best for a focused job search sprint.',
            'savings'     => (PRICE_CREDITS_SINGLE * 5) - PRICE_CREDITS_PACK_5,
        ],
        'pack_10' => [
            'id'          => 'pack_10',
            'name'        => '10 Credits',
            'credits'     => 10,
            'price'       => PRICE_CREDITS_PACK_10,
            'badge'       => 'Best value',
            'description' => 'Stock up and use tools whenever you need.',
            'savings'     => (PRICE_CREDITS_SINGLE * 10) - PRICE_CREDITS_PACK_10,
        ],
    ];
}

function jm_credit_pack(string $packId): array {
    return jm_credit_packs()[$packId] ?? [];
}

// ═══════════════════════════════════════════════════════════════════
//  BUNDLES
// ═══════════════════════════════════════════════════════════════════

function jm_bundles(): array {
    return [
        'job_toolkit' => [
            'id'          => 'job_toolkit',
            'name'        => 'Job Application Toolkit',
            'price'       => PRICE_BUNDLE_JOB_TOOLKIT,
            'credits'     => 4, // covers cv + cover letter + interview prep (2 credits)
            'badge'       => 'Save ' . jm_format_ngn(
                PRICE_CREDITS_SINGLE
                + PRICE_CREDITS_SINGLE
                + (PRICE_CREDITS_SINGLE * 2)
                - PRICE_BUNDLE_JOB_TOOLKIT
            ),
            'description' => 'Everything you need for one strong application.',
            'includes'    => ['1 CV optimisation', '1 Cover letter', '1 Interview prep session'],
        ],
    ];
}

// ═══════════════════════════════════════════════════════════════════
//  AI TOOLS CATALOGUE
// ═══════════════════════════════════════════════════════════════════

function jm_ai_tools(): array {
    return [
        'cv_optimizer' => [
            'id'           => 'cv_optimizer',
            'name'         => 'CV Optimiser',
            'credit_cost'  => TOOL_COST_CV_OPTIMIZER,
            'ngn_price'    => PRICE_CREDITS_SINGLE * TOOL_COST_CV_OPTIMIZER,
            'free_preview' => true,  // shows score only for free
            'description'  => 'Get a detailed ATS score and actionable improvement tips.',
            'is_free'      => false,
        ],
        'cover_letter' => [
            'id'           => 'cover_letter',
            'name'         => 'Cover Letter Generator',
            'credit_cost'  => TOOL_COST_COVER_LETTER,
            'ngn_price'    => PRICE_CREDITS_SINGLE * TOOL_COST_COVER_LETTER,
            'free_preview' => false,
            'description'  => 'AI-written cover letter tailored to the job description.',
            'is_free'      => false,
        ],
        'interview_prep' => [
            'id'           => 'interview_prep',
            'name'         => 'Interview Prep',
            'credit_cost'  => TOOL_COST_INTERVIEW_PREP,
            'ngn_price'    => PRICE_CREDITS_SINGLE * TOOL_COST_INTERVIEW_PREP,
            'free_preview' => false,
            'description'  => '5 likely questions + model answers for your target role.',
            'is_free'      => false,
        ],
        'skills_gap' => [
            'id'           => 'skills_gap',
            'name'         => 'Skills Gap Analyser',
            'credit_cost'  => TOOL_COST_SKILLS_GAP_REPORT,
            'ngn_price'    => PRICE_CREDITS_SINGLE * TOOL_COST_SKILLS_GAP_REPORT,
            'free_preview' => true,  // free scan, paid for full report
            'description'  => 'See the gap between your profile and your target role, with resource links.',
            'is_free'      => false,
        ],
        'salary_analyzer' => [
            'id'           => 'salary_analyzer',
            'name'         => 'Salary Analyser',
            'credit_cost'  => 0,
            'ngn_price'    => 0,
            'free_preview' => false,
            'description'  => 'Market salary ranges for your role and location.',
            'is_free'      => true,
        ],
        'tax_calculator' => [
            'id'           => 'tax_calculator',
            'name'         => 'Tax Calculator',
            'credit_cost'  => 0,
            'ngn_price'    => 0,
            'free_preview' => false,
            'description'  => 'Estimate your take-home pay after Nigerian income tax.',
            'is_free'      => true,
        ],
    ];
}

function jm_ai_tool(string $toolId): array {
    return jm_ai_tools()[$toolId] ?? [];
}

// ═══════════════════════════════════════════════════════════════════
//  LEGACY: Job posting packages (kept for backward compat)
// ═══════════════════════════════════════════════════════════════════

function jm_job_posting_packages(): array {
    return [
        'starter' => [
            'id'            => 'starter',
            'name'          => 'Standard Post',
            'price'         => PRICE_EMPLOYER_SINGLE_POST,
            'duration_days' => 30,
            'featured'      => false,
            'badge'         => '',
            'description'   => 'Post one role and start receiving applications.',
            'features'      => ['Live for 30 days', 'Full applicant dashboard', 'Company branding'],
        ],
        'featured' => [
            'id'            => 'featured',
            'name'          => 'Featured Post',
            'price'         => PRICE_EMPLOYER_SINGLE_POST + PRICE_EMPLOYER_FEATURED_ADDON,
            'duration_days' => 45,
            'featured'      => true,
            'badge'         => 'Most popular',
            'description'   => 'Give your role maximum visibility across the site.',
            'features'      => ['Live for 45 days', 'Featured badge & pinned placement', 'Company branding'],
        ],
        'hiring_boost' => [
            'id'            => 'hiring_boost',
            'name'          => 'Hiring Boost',
            'price'         => PRICE_EMPLOYER_SINGLE_POST + (PRICE_EMPLOYER_FEATURED_ADDON * 3),
            'duration_days' => 60,
            'featured'      => true,
            'badge'         => 'For urgent hires',
            'description'   => 'Maximum reach for roles that need to be filled fast.',
            'features'      => ['Live for 60 days', 'Featured across all site areas', 'Listing reviewed before going live', 'Priority support'],
        ],
    ];
}

function jm_job_posting_package(string $packageId): array {
    $packages = jm_job_posting_packages();
    return $packages[$packageId] ?? $packages['starter'];
}

// ═══════════════════════════════════════════════════════════════════
//  FORMATTING HELPERS
// ═══════════════════════════════════════════════════════════════════

function jm_format_ngn(float $amount): string {
    if ($amount <= 0) {
        return 'Free';
    }
    return '₦' . number_format($amount, 0);
}

// Alias kept for backward compat
function jm_money_ngn(float $amount): string {
    return jm_format_ngn($amount);
}

function jm_ngn_to_usd(float $ngn): string {
    $usd = $ngn / NGN_USD_RATE;
    if ($usd < 1) {
        return '<$1';
    }
    return '$' . number_format($usd, 0);
}

function jm_format_ngn_with_usd(float $ngn): string {
    return jm_format_ngn($ngn) . ' <span class="jm-usd-hint">≈ ' . jm_ngn_to_usd($ngn) . '</span>';
}

// ═══════════════════════════════════════════════════════════════════
//  TRANSACTION REFERENCE HELPERS
// ═══════════════════════════════════════════════════════════════════

function jm_job_payment_plan_value(int $jobId, string $packageId, string $packageName): string {
    return 'Job posting: ' . $packageName . ' | job:' . $jobId . ' | package:' . $packageId;
}

function jm_parse_job_payment_plan(string $plan): array {
    $jobId     = 0;
    $packageId = 'starter';
    if (preg_match('/job:(\d+)/', $plan, $m))         $jobId     = (int) $m[1];
    if (preg_match('/package:([a-z0-9_]+)/', $plan, $m)) $packageId = $m[1];
    return [$jobId, $packageId];
}

function jm_job_payment_reference(): string {
    return 'JMJOB-' . time() . '-' . bin2hex(random_bytes(5));
}

function jm_seeker_premium_reference(): string {
    return 'JMPREM-' . time() . '-' . bin2hex(random_bytes(5));
}

function jm_credits_reference(): string {
    return 'JMCRED-' . time() . '-' . bin2hex(random_bytes(5));
}

function jm_bundle_reference(): string {
    return 'JMBNDL-' . time() . '-' . bin2hex(random_bytes(5));
}

// ═══════════════════════════════════════════════════════════════════
//  TRANSACTION TYPE CONSTANTS (stored in transactions.type column)
// ═══════════════════════════════════════════════════════════════════
if (!defined('TXN_TYPE_SEEDS_PURCHASE'))       define('TXN_TYPE_SEEDS_PURCHASE',      'seeds_purchase');
if (!defined('TXN_TYPE_SEEKER_PREMIUM'))       define('TXN_TYPE_SEEKER_PREMIUM',      'seeker_premium');
if (!defined('TXN_TYPE_SEEKER_CREDITS'))       define('TXN_TYPE_SEEKER_CREDITS',      'seeker_credits');
if (!defined('TXN_TYPE_EMPLOYER_POST'))        define('TXN_TYPE_EMPLOYER_POST',       'employer_post');
if (!defined('TXN_TYPE_EMPLOYER_SUB'))         define('TXN_TYPE_EMPLOYER_SUB',        'employer_subscription');
if (!defined('TXN_TYPE_FEATURED_ADDON'))       define('TXN_TYPE_FEATURED_ADDON',      'featured_addon');
if (!defined('TXN_TYPE_BUNDLE'))               define('TXN_TYPE_BUNDLE',              'bundle');
