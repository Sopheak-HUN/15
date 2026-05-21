<?php

use App\Tenants\Modules\IAM\Models\Permission;
use App\Tenants\Modules\IAM\Models\Role;
use Illuminate\Database\Eloquent\Collection;

/*
|--------------------------------------------------------------------------
| Role inheritance — effectivePermissions() & cycle detection
|--------------------------------------------------------------------------
| These tests exercise the in-memory permission-merge and cycle-detection
| logic without touching the database, by stubbing the model relations
| via Mockery partials. The full integration path (parent_role_id column,
| FK constraints, FK cascade) is covered by the tenancy-isolation suite
| in tests/Feature/IAM once a test tenant DB is wired up.
*/

function makeRole(string $id, array $permissions = [], ?Role $parent = null): Role
{
    $role     = new Role();
    $role->id = $id;

    $permModels = collect($permissions)->map(function (string $permId) {
        $p     = new Permission();
        $p->id = $permId;
        return $p;
    });

    $role->setRelation('permissions', new Collection($permModels->all()));
    $role->setRelation('parent', $parent);
    return $role;
}

it('returns only direct permissions when there is no parent', function () {
    $role = makeRole('r1', ['p1', 'p2']);

    $ids = $role->effectivePermissions()->pluck('id')->all();
    expect($ids)->toEqualCanonicalizing(['p1', 'p2']);
});

it('merges parent permissions on top of direct ones', function () {
    $parent = makeRole('parent', ['p:parent', 'p:shared']);
    $child  = makeRole('child',  ['p:child',  'p:shared'], $parent);

    $ids = $child->effectivePermissions()->pluck('id')->all();
    expect($ids)->toEqualCanonicalizing(['p:child', 'p:shared', 'p:parent']);
});

it('walks the parent chain recursively', function () {
    $gp    = makeRole('gp',     ['p:gp']);
    $par   = makeRole('parent', ['p:parent'], $gp);
    $child = makeRole('child',  ['p:child'],  $par);

    expect($child->effectivePermissions()->pluck('id')->all())
        ->toEqualCanonicalizing(['p:child', 'p:parent', 'p:gp']);
});

it('detects cycles in the parent chain without infinite looping', function () {
    // Manually link a→b→a. effectivePermissions() must short-circuit on revisit.
    $a = makeRole('a', ['p:a']);
    $b = makeRole('b', ['p:b']);
    $a->setRelation('parent', $b);
    $b->setRelation('parent', $a);

    $ids = $a->effectivePermissions()->pluck('id')->all();
    expect($ids)->toEqualCanonicalizing(['p:a', 'p:b']);
});

it('flags ancestor relationships used to reject inheritance cycles', function () {
    $a = makeRole('a');
    $b = makeRole('b', [], $a);   // b → a
    $c = makeRole('c', [], $b);   // c → b → a

    expect($a->isAncestorOf($c))->toBeTrue();
    expect($b->isAncestorOf($c))->toBeTrue();
    expect($c->isAncestorOf($a))->toBeFalse();
});
