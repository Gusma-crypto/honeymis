<?php

return [
    'maximum_total_commission_bps' => 6000,
    'referral_first_order_bps' => 500,
    'generation_rates' => [400, 200, 100, 50, 50],
    'rank_requirements' => [
        'Member' => ['personal_sales' => 0, 'team_sales' => 0],
        'Bronze' => ['personal_sales' => 1_000_000, 'team_sales' => 3_000_000],
        'Silver' => ['personal_sales' => 3_000_000, 'team_sales' => 10_000_000],
        'Gold' => ['personal_sales' => 7_500_000, 'team_sales' => 25_000_000],
        'Platinum' => ['personal_sales' => 15_000_000, 'team_sales' => 60_000_000],
    ],
];
