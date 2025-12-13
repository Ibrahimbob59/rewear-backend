<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Address\CreateAddressRequest;
use App\Http\Requests\Address\UpdateAddressRequest;
use App\Http\Resources\AddressResource;
use App\Models\Address;
use App\Services\AddressService;
use Illuminate\Http\JsonResponse;

class AddressController extends Controller
{
    protected $addressService;

    public function __construct(AddressService $addressService)
    {
        $this->addressService = $addressService;
    }

    /**
     * GET /api/addresses
     * Get all addresses for authenticated user
     */
    public function index(): JsonResponse
    {
        try {
            $addresses = $this->addressService->getUserAddresses(auth()->id());

            return response()->json([
                'success' => true,
                'message' => 'Addresses retrieved successfully',
                'data' => AddressResource::collection($addresses),
                'meta' => [
                    'total' => $addresses->count(),
                ],
            ]);
        } catch (\Exception $e) {
            \Log::error('Failed to retrieve addresses', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve addresses',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * POST /api/addresses
     * Create a new address
     */
    public function store(CreateAddressRequest $request): JsonResponse
    {
        try {
            $data = $request->validated();
            $user = auth()->user();

            $address = $this->addressService->createAddress($data, $user);

            return response()->json([
                'success' => true,
                'message' => 'Address created successfully',
                'data' => new AddressResource($address),
            ], 201);
        } catch (\Exception $e) {
            \Log::error('Failed to create address', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create address',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * PUT /api/addresses/{id}
     * Update an address
     */
    public function update(UpdateAddressRequest $request, int $id): JsonResponse
    {
        try {
            $address = Address::findOrFail($id);

            // Authorization check (must be owner)
            if ($address->user_id !== auth()->id()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized to update this address',
                ], 403);
            }

            $data = $request->validated();
            $updatedAddress = $this->addressService->updateAddress($address, $data);

            return response()->json([
                'success' => true,
                'message' => 'Address updated successfully',
                'data' => new AddressResource($updatedAddress),
            ]);
        } catch (\Exception $e) {
            \Log::error('Failed to update address', [
                'address_id' => $id,
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update address',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * DELETE /api/addresses/{id}
     * Delete an address
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $address = Address::findOrFail($id);

            // Authorization check (must be owner)
            if ($address->user_id !== auth()->id()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized to delete this address',
                ], 403);
            }

            $this->addressService->deleteAddress($address);

            return response()->json([
                'success' => true,
                'message' => 'Address deleted successfully',
            ]);
        } catch (\Exception $e) {
            \Log::error('Failed to delete address', [
                'address_id' => $id,
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete address',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }
}
