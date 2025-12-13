<?php

namespace App\Services;

use App\Models\Address;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Collection;

class AddressService
{
    /**
     * Get all addresses for a user
     *
     * @param int $userId
     * @return Collection
     */
    public function getUserAddresses(int $userId): Collection
    {
        return Address::where('user_id', $userId)
            ->orderBy('is_default', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get a single address
     *
     * @param int $addressId
     * @param int $userId
     * @return Address|null
     */
    public function getAddress(int $addressId, int $userId): ?Address
    {
        return Address::where('id', $addressId)
            ->where('user_id', $userId)
            ->first();
    }

    /**
     * Create a new address
     *
     * @param array $data
     * @param User $user
     * @return Address
     */
    public function createAddress(array $data, User $user): Address
    {
        DB::beginTransaction();

        try {
            // If this should be default, unset other defaults
            if (!empty($data['is_default']) && $data['is_default']) {
                $this->unsetDefaultAddresses($user->id);
            }

            // If this is the first address, make it default
            $isFirstAddress = Address::where('user_id', $user->id)->count() === 0;
            if ($isFirstAddress) {
                $data['is_default'] = true;
            }

            // Create address
            $address = $user->addresses()->create([
                'label' => $data['label'] ?? 'Home',
                'full_name' => $data['full_name'],
                'phone' => $data['phone'],
                'address_line1' => $data['address_line1'],
                'address_line2' => $data['address_line2'] ?? null,
                'city' => $data['city'],
                'state' => $data['state'] ?? null,
                'postal_code' => $data['postal_code'] ?? null,
                'country' => $data['country'],
                'latitude' => $data['latitude'] ?? null,
                'longitude' => $data['longitude'] ?? null,
                'is_default' => $data['is_default'] ?? false,
            ]);

            DB::commit();

            Log::info('Address created', [
                'address_id' => $address->id,
                'user_id' => $user->id,
            ]);

            return $address;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create address', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Update an address
     *
     * @param Address $address
     * @param array $data
     * @return Address
     */
    public function updateAddress(Address $address, array $data): Address
    {
        DB::beginTransaction();

        try {
            // If setting as default, unset other defaults
            if (!empty($data['is_default']) && $data['is_default']) {
                $this->unsetDefaultAddresses($address->user_id, $address->id);
            }

            // Update address
            $address->update(array_filter($data, function ($value) {
                return $value !== null;
            }));

            DB::commit();

            Log::info('Address updated', [
                'address_id' => $address->id,
                'user_id' => $address->user_id,
            ]);

            return $address->fresh();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update address', [
                'address_id' => $address->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Delete an address
     *
     * @param Address $address
     * @return bool
     */
    public function deleteAddress(Address $address): bool
    {
        DB::beginTransaction();

        try {
            $userId = $address->user_id;
            $wasDefault = $address->is_default;

            // Delete the address
            $address->delete();

            // If this was the default, make another one default
            if ($wasDefault) {
                $nextAddress = Address::where('user_id', $userId)->first();
                if ($nextAddress) {
                    $nextAddress->update(['is_default' => true]);
                }
            }

            DB::commit();

            Log::info('Address deleted', [
                'address_id' => $address->id,
                'user_id' => $userId,
            ]);

            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to delete address', [
                'address_id' => $address->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Set an address as default
     *
     * @param Address $address
     * @return Address
     */
    public function setAsDefault(Address $address): Address
    {
        DB::beginTransaction();

        try {
            // Unset all other defaults for this user
            $this->unsetDefaultAddresses($address->user_id, $address->id);

            // Set this one as default
            $address->update(['is_default' => true]);

            DB::commit();

            Log::info('Address set as default', [
                'address_id' => $address->id,
                'user_id' => $address->user_id,
            ]);

            return $address->fresh();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to set default address', [
                'address_id' => $address->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Get default address for user
     *
     * @param int $userId
     * @return Address|null
     */
    public function getDefaultAddress(int $userId): ?Address
    {
        return Address::where('user_id', $userId)
            ->where('is_default', true)
            ->first();
    }

    /**
     * Unset all default addresses for a user
     *
     * @param int $userId
     * @param int|null $exceptId
     */
    protected function unsetDefaultAddresses(int $userId, ?int $exceptId = null): void
    {
        $query = Address::where('user_id', $userId)
            ->where('is_default', true);

        if ($exceptId) {
            $query->where('id', '!=', $exceptId);
        }

        $query->update(['is_default' => false]);
    }
}
