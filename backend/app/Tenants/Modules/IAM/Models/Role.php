<?php

namespace App\Tenants\Modules\IAM\Models;

use App\Tenants\Traits\Auditable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class Role extends Model
{
    use HasUuids, Auditable;

    protected $guarded = [];

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'role_permission');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_role_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_role_id');
    }

    /**
     * Resolve the full permission set this role grants, walking the parent
     * chain. Cycle-safe; duplicates are de-duplicated by permission id.
     */
    public function effectivePermissions(): Collection
    {
        $bag    = collect();
        $visited = [];
        $node    = $this;

        while ($node && ! isset($visited[$node->id])) {
            $visited[$node->id] = true;
            foreach ($node->permissions as $perm) {
                $bag->put($perm->id, $perm);
            }
            $node = $node->parent;
        }

        return $bag->values();
    }

    /**
     * True when $candidate is this role or appears anywhere in its descendant
     * tree. Used to prevent inheritance cycles.
     */
    public function isAncestorOf(self $candidate): bool
    {
        $node = $candidate->parent;
        $seen = [];
        while ($node && ! isset($seen[$node->id])) {
            if ($node->id === $this->id) {
                return true;
            }
            $seen[$node->id] = true;
            $node = $node->parent;
        }
        return false;
    }
}
