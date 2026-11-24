<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class ErpAccessSeeder extends Seeder
{
    public function run(): void
    {
        /*
        |--------------------------------------------------------------------------
        | 1. ERP Roles
        |--------------------------------------------------------------------------
        */

        $roles = [
            'admin' => 'Administrator',
            'requester' => 'Requester',
            'buyer' => 'Buyer',
            'purchase-manager' => 'Purchase Manager',
            'warehouse-officer' => 'Warehouse Officer',
            'qc-inspector' => 'QC Inspector',
            'qc-manager' => 'QC Manager',
            'inventory-manager' => 'Inventory Manager',
            'accounts-officer' => 'Accounts Officer',
            'name' => 'Finance Manager',
            'slug' => 'finance-manager',


        ];

        foreach ($roles as $slug => $name) {
            Role::updateOrCreate(
                ['slug' => $slug],
                ['name' => $name]
            );
        }

        /*
        |--------------------------------------------------------------------------
        | 2. ERP Permissions
        |--------------------------------------------------------------------------
        |
        | The permissions table uses:
        | - name: Human-readable label
        | - slug: Permission identifier
        |
        */

        $permissions = [
            // Purchase Requisitions
            'requisition.create',
            'requisition.view',

            // Purchase Orders
            'po.create',
            'po.view',
            'po.edit',
            'po.submit',
            'po.cancel',
            'po.approve',
            'po.reject',

            // Goods Receipt Notes
            'grn.create',
            'grn.view',

            // Quality Control
            'qc.view',
            'qc.inspect',
            'qc.create',
            'qc.submit',
            'qc.approve',
            'qc.reject',

            // Inventory
            'stock.view',
            'stock.adjust',

            // Supplier Invoices
            'invoice.create',
            'invoice.approve',
            'invoice.view',

            // Payments
            'payment.record',

            // User Administration
            'users.manage',
            'stock.post',
        ];

        foreach ($permissions as $slug) {
            Permission::firstOrCreate(
                ['slug' => $slug],
                [
                    'name' => ucwords(
                        str_replace(['.', '-'], ' ', $slug)
                    ),
                ]
            );
        }

        /*
        |--------------------------------------------------------------------------
        | 3. Role Permission Assignments
        |--------------------------------------------------------------------------
        |
        | Each role receives the permissions appropriate to its
        | responsibilities.
        |
        | sync() is used only once per role.
        |
        */

        $assignments = [

            // Requester
            'requester' => [
                'requisition.create',
                'requisition.view',
            ],

            // Buyer
            'buyer' => [
                'requisition.view',
                'po.create',
                'po.view',
                'po.edit',
                'po.submit',
                'po.cancel',
                'grn.view',
                'qc.view',
            ],

            // Purchase Manager
            'purchase-manager' => [
                'po.view',
                'po.approve',
                'po.reject',
                'grn.view',
                'qc.view',
            ],

            // Warehouse Officer
            'warehouse-officer' => [
                'po.view',
                'grn.create',
                'grn.view',
                'qc.view',
                'stock.post',
                'stock.view',


            ],

            // QC Inspector
            'qc-inspector' => [
                'grn.view',
                'qc.view',
                'qc.inspect',
                'qc.create',
                'qc.submit',
            ],

            // QC Manager
            'qc-manager' => [
                'grn.view',
                'qc.view',
                'qc.approve',
                'qc.reject',
            ],

            // Inventory Manager
            'inventory-manager' => [
                'stock.view',
                'stock.adjust',
                'stock.post',

            ],

            // Accounts Officer
            'accounts-officer' => [
                'po.view',
                'grn.view',
                'invoice.create',
                'invoice.approve',
                'payment.record',
                
            ],

            // Administrator
            'admin' => $permissions,
        ];

        /*
        |--------------------------------------------------------------------------
        | 4. Synchronize Role Permissions
        |--------------------------------------------------------------------------
        |
        | Every role is synchronized exactly once.
        |
        | This prevents later assignments from accidentally removing
        | QC permissions, which caused the previous 403 errors.
        |
        */

        foreach ($assignments as $roleSlug => $permissionSlugs) {
            $role = Role::where('slug', $roleSlug)
                ->firstOrFail();

            $permissionIds = Permission::whereIn(
                'slug',
                $permissionSlugs
            )
                ->pluck('id')
                ->all();

            $role->permissions()->sync($permissionIds);
        }
    }
}
