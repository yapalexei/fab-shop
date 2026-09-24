<?php

namespace App\Http\Controllers\Concerns;

use App\Enums\ShopResource;
use Illuminate\Routing\Controllers\Middleware;

/**
 * Maps a resource controller's methods onto that resource's shop
 * permissions. edit() needs only "view" - it doubles as the read-only detail
 * page, and the form hides its save button without "update".
 */
trait AuthorizesShopResource
{
    abstract protected static function shopResource(): ShopResource;

    /**
     * @return array<int, Middleware>
     */
    public static function middleware(): array
    {
        $resource = static::shopResource();

        return [
            new Middleware("can:{$resource->permission('view')}", only: ['index', 'show', 'edit']),
            new Middleware("can:{$resource->permission('create')}", only: ['create', 'store']),
            new Middleware("can:{$resource->permission('update')}", only: ['update']),
            new Middleware("can:{$resource->permission('delete')}", only: ['destroy']),
        ];
    }
}
