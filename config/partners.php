<?php

return [
    'referral_days' => 30,
    'return_window_days' => (int) env('COMMISSION_RETURN_DAYS', 7),
    'commission_budget_bps' => (int) env('COMMISSION_BUDGET_BPS', 3000),
    'minimum_payout_idr' => (int) env('MINIMUM_PAYOUT_IDR', 50000),
];
