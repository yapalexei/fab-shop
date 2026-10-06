<?php

namespace App\Support;

use App\Enums\ShopResource;

/**
 * What a shop's AI agent (Shop::agents(), connected over MCP) may be allowed
 * to do. An agent has no role: its access is only the `{resource}.{action}`
 * grants stored as permission overrides on its membership, which
 * User::shopPermissions() already resolves - the same permission names the
 * web app uses, so one tool = one permission = one Gate ability.
 *
 * This list is the ceiling, not the default. It is deliberately smaller than
 * what a person with the same role could do: nothing here can delete, cancel,
 * send, convert or archive, and nothing touches the team, roles, billing,
 * add-ons, markup defaults or shop settings - those stay with the owner and
 * cannot be granted to an AI from any form, whatever is submitted.
 */
final class AgentAccess
{
    /**
     * Every grantable permission, by resource: the label shown to the owner
     * and which of view / create / update exist for it. A write action always
     * implies view (see overridesFor()).
     *
     * @return array<string, array{label: string, actions: array<int, string>}>
     */
    public static function grantable(): array
    {
        $rows = [
            [ShopResource::Customers, ['view', 'create', 'update']],
            [ShopResource::Quotes, ['view', 'create', 'update']],
            [ShopResource::Jobs, ['view', 'update']],
            [ShopResource::JobStages, ['view']],
            [ShopResource::Materials, ['view', 'create', 'update']],
            [ShopResource::Services, ['view', 'create', 'update']],
            [ShopResource::BusinessPartners, ['view', 'create', 'update']],
            [ShopResource::Equipment, ['view', 'create', 'update']],
            [ShopResource::Tooling, ['view', 'create', 'update']],
            [ShopResource::Financials, ['view']],
        ];

        return collect($rows)->mapWithKeys(fn (array $row) => [
            $row[0]->value => ['label' => $row[0]->label(), 'actions' => $row[1]],
        ])->all();
    }

    /**
     * Every permission name that can be granted, e.g. "quotes.create".
     *
     * @return array<int, string>
     */
    public static function allPermissions(): array
    {
        return collect(self::grantable())
            ->flatMap(fn (array $row, string $resource) => array_map(fn (string $action) => "{$resource}.{$action}", $row['actions']))
            ->values()
            ->all();
    }

    /**
     * `{resource}.view` for each resource named - for the common case of
     * granting read access to a list of resources.
     *
     * @param  array<int, string>  $resources
     * @return array<int, string>
     */
    public static function viewOf(array $resources): array
    {
        return array_map(fn (string $resource) => "{$resource}.view", $resources);
    }

    /**
     * What a newly created agent can do: look at the working records. No
     * writes, no money, no supplier pricing.
     *
     * @return array<int, string>
     */
    public static function defaults(): array
    {
        return self::viewOf([
            ShopResource::Customers->value,
            ShopResource::Quotes->value,
            ShopResource::Jobs->value,
            ShopResource::JobStages->value,
            ShopResource::Materials->value,
            ShopResource::Services->value,
            ShopResource::Equipment->value,
            ShopResource::Tooling->value,
        ]);
    }

    /**
     * The permission overrides for exactly these permissions. Anything not
     * grantable (unknown names, delete, team, settings...) is dropped, and a
     * create/update on a resource brings its view along - you can't change
     * what you can't see.
     *
     * @param  array<int, string>  $permissions
     * @return array<string, bool>
     */
    public static function overridesFor(array $permissions): array
    {
        $allowed = self::allPermissions();

        $granted = collect($permissions)
            ->filter(fn ($permission) => is_string($permission) && in_array($permission, $allowed, true))
            ->unique()
            ->values();

        $withViews = $granted->merge(
            $granted->map(fn (string $permission) => explode('.', $permission, 2)[0].'.view'),
        )->unique();

        return $withViews->mapWithKeys(fn (string $permission) => [$permission => true])->all();
    }

    /**
     * The grantable permissions an overrides map currently allows - for
     * showing the owner their checkboxes.
     *
     * @param  array<string, bool>  $overrides
     * @return array<int, string>
     */
    public static function permissionsFrom(array $overrides): array
    {
        return collect(self::allPermissions())
            ->filter(fn (string $permission) => ($overrides[$permission] ?? false) === true)
            ->values()
            ->all();
    }
}
