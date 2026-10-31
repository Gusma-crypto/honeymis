<?php

namespace App\Domains\Shared\Enums;

enum SystemPermission: string
{
    case ManageCatalog = 'catalog.manage';
    case ManageInventory = 'inventory.manage';
    case ManageOrders = 'orders.manage';
    case ViewOwnOrders = 'orders.view-own';
    case VerifyPayments = 'payments.verify';
    case ManageRefunds = 'refunds.manage';
    case ManagePartners = 'partners.manage';
    case ViewOwnReferrals = 'referrals.view-own';
    case ManageCommissionRules = 'commission-rules.manage';
    case ViewOwnCommissions = 'commissions.view-own';
    case ViewWallets = 'wallets.view';
    case ViewOwnWallet = 'wallets.view-own';
    case RequestOwnPayout = 'payouts.request-own';
    case ReviewPayouts = 'payouts.review';
    case ViewReports = 'reports.view';
    case ViewAudit = 'audit.view';
    case ManageUserAccess = 'users.manage-access';
}
