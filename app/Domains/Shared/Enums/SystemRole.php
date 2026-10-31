<?php

namespace App\Domains\Shared\Enums;

enum SystemRole: string
{
    case SuperAdmin = 'super_admin';
    case StoreAdmin = 'store_admin';
    case Finance = 'finance';
    case Partner = 'partner';
    case Customer = 'customer';

    /** @return list<SystemPermission> */
    public function permissions(): array
    {
        return match ($this) {
            self::SuperAdmin => SystemPermission::cases(),
            self::StoreAdmin => [SystemPermission::ManageCatalog, SystemPermission::ManageInventory, SystemPermission::ManageOrders, SystemPermission::ManagePartners, SystemPermission::ViewReports],
            self::Finance => [SystemPermission::VerifyPayments, SystemPermission::ManageRefunds, SystemPermission::ManageCommissionRules, SystemPermission::ViewWallets, SystemPermission::ReviewPayouts, SystemPermission::ViewReports, SystemPermission::ViewAudit],
            self::Partner => [SystemPermission::ViewOwnOrders, SystemPermission::ViewOwnReferrals, SystemPermission::ViewOwnCommissions, SystemPermission::ViewOwnWallet, SystemPermission::RequestOwnPayout],
            self::Customer => [SystemPermission::ViewOwnOrders],
        };
    }
}
