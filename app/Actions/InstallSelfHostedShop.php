<?php

namespace App\Actions;

use App\Models\Shop;
use App\Models\User;
use App\Support\Edition;
use App\Support\SecurityLog;
use App\Support\ShopPermissions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use RuntimeException;
use Spatie\Permission\PermissionRegistrar;

/**
 * First-run setup of a self-hosted install: the shop owner's account and the
 * one shop. Used by both the /setup page and `php artisan selfhosted:install`.
 *
 * The shop is left mid-onboarding (team step next) so the owner lands in the
 * same short wizard cloud signups get - invite people, add a few catalog rows,
 * both skippable. No plan and no trial: Shop::hasFeature() grants everything
 * in a self-hosted install, so there is nothing to subscribe to.
 */
class InstallSelfHostedShop
{
    /** Arbitrary constant; only has to be the same for every caller. */
    private const LOCK_KEY = 7_310_001;

    /**
     * Validation for the setup form and the command's answers alike. The
     * email is lower-cased first (see prepare()) so the unique check isn't
     * fooled by capitals - Postgres compares case-sensitively.
     *
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'confirmed', Password::defaults()],
            'shop_name' => ['required', 'string', 'max:255'],
            'timezone' => ['nullable', 'timezone:all'],
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public static function prepare(array $input): array
    {
        if (isset($input['email']) && is_string($input['email'])) {
            $input['email'] = strtolower(trim($input['email']));
        }

        return $input;
    }

    /**
     * @param  array{name: string, email: string, password: string, shop_name: string, timezone?: string|null}  $data
     */
    public function __invoke(array $data): User
    {
        return DB::transaction(function () use ($data) {
            // Two people hitting /setup at once must not both become "the owner".
            DB::select('select pg_advisory_xact_lock(?)', [self::LOCK_KEY]);

            if (Edition::installed()) {
                throw new RuntimeException('This install is already set up.');
            }

            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
            ]);

            // There is no verification mail to wait for on a box the owner
            // just installed (and mail may not be configured yet). Not
            // fillable, so set directly - same as an accepted invitation.
            $user->email_verified_at = now();

            $shop = Shop::create([
                'name' => $data['shop_name'],
                'owner_id' => $user->id,
                'timezone' => $data['timezone'] ?? null,
                'onboarding_step' => 'team',
            ]);

            $shop->members()->attach($user);
            $user->current_shop_id = $shop->id;
            $user->save();

            app(PermissionRegistrar::class)->setPermissionsTeamId($shop->id);
            ShopPermissions::seedRoles($shop);
            $user->assignRole('Owner');

            SecurityLog::record('self-hosted install completed', $user);

            return $user;
        });
    }
}
