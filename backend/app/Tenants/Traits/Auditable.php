<?php

namespace App\Tenants\Traits;

use Illuminate\Database\Eloquent\Model;
use App\Tenants\Modules\IAM\Models\AuditLog;

trait Auditable
{
    public static function bootAuditable()
    {
        static::created(function (Model $model) {
            $model->audit('created');
        });

        static::updated(function (Model $model) {
            $model->audit('updated');
        });

        static::deleted(function (Model $model) {
            $model->audit('deleted');
        });
    }

    protected function audit($action)
    {
        // Fallbacks for testing/console commands
        $tenantId = function_exists('tenant') && tenant('id') ? tenant('id') : 'system';
        $userId = auth()->id() ?? 'system';

        AuditLog::create([
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'action' => $action,
            'auditable_type' => get_class($this),
            // `getKey()` honors the model's $primaryKey — works for both the
            // default `id` column and 1:1 tables like EmployeeSpouse /
            // EmployeeEmergencyContact whose PK is `employee_id`.
            'auditable_id' => $this->getKey(),
            'old_values' => $action === 'updated' ? json_encode($this->getOriginal()) : null,
            'new_values' => $action !== 'deleted' ? json_encode($this->getAttributes()) : null,
        ]);
    }
}
