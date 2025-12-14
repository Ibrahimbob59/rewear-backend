<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Address\CreateAddressRequest;
use App\Http\Requests\Address\UpdateAddressRequest;
use App\Http\Resources\AddressResource;
use App\Models\Address;
use App\Services\AddressService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class AddressController extends Controller
{
    protected AddressService $addressService;

    public function __construct(AddressService $addressService)
    {
        $this->addressService = $addressService;
    }

    /**
     * @OA\Get(
     *     path="/api/addresses",
     *     tags={"Addresses"},
     *     summary="Get user's addresses",
     *     description="Get all delivery addresses for the authenticated user",
     *     security={{"bearerAuth": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="Addresses retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Addresses retrieved successfully"),
     *             @OA\Property(property="data", type="array",
     *                 @OA\Items(type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="label", type="string", example="Home"),
     *                     @OA\Property(property="full_name", type="string", example="John Doe"),
     *                     @OA\Property(property="phone", type="string", example="+9611234567"),
     *                     @OA\Property(property="address_line1", type="string", example="123 Main Street"),
     *                     @OA\Property(property="city", type="string", example="Beirut"),
     *                     @OA\Property(property="country", type="string", example="Lebanon"),
     *                     @OA\Property(property="is_default", type="boolean", example=true)
     *                 )
     *             ),
     *             @OA\Property(property="meta", type="object",
     *                 @OA\Property(property="total", type="integer", example=3)
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
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
            Log::error('Failed to retrieve addresses', [
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
     * @OA\Post(
     *     path="/api/addresses",
     *     tags={"Addresses"},
     *     summary="Create a new address",
     *     description="Create a new delivery address for the authenticated user",
     *     security={{"bearerAuth": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"full_name","phone","address_line1","city","country"},
     *             @OA\Property(property="label", type="string", maxLength=50, example="Home", description="Address label (e.g., Home, Office)"),
     *             @OA\Property(property="full_name", type="string", maxLength=255, example="John Doe", description="Recipient's full name"),
     *             @OA\Property(property="phone", type="string", maxLength=20, example="+9611234567", description="Recipient's phone number"),
     *             @OA\Property(property="address_line1", type="string", maxLength=500, example="123 Main Street", description="Street address"),
     *             @OA\Property(property="address_line2", type="string", maxLength=500, example="Apartment 4B", description="Additional address info (optional)"),
     *             @OA\Property(property="city", type="string", maxLength=100, example="Beirut", description="City"),
     *             @OA\Property(property="state", type="string", maxLength=100, example="Beirut", description="State/Province (optional)"),
     *             @OA\Property(property="postal_code", type="string", maxLength=20, example="1107", description="Postal/ZIP code (optional)"),
     *             @OA\Property(property="country", type="string", maxLength=100, example="Lebanon", description="Country"),
     *             @OA\Property(property="latitude", type="number", format="float", example=33.8886, description="Latitude (-90 to 90)"),
     *             @OA\Property(property="longitude", type="number", format="float", example=35.4955, description="Longitude (-180 to 180)"),
     *             @OA\Property(property="is_default", type="boolean", example=false, description="Set as default address")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Address created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Address created successfully"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=422, description="Validation error")
     * )
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
            Log::error('Failed to create address', [
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
     * @OA\Put(
     *     path="/api/addresses/{id}",
     *     tags={"Addresses"},
     *     summary="Update an address",
     *     description="Update a delivery address (must be owner)",
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Address ID",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="label", type="string", example="Updated Home"),
     *             @OA\Property(property="full_name", type="string", example="John Doe"),
     *             @OA\Property(property="phone", type="string", example="+9611234567"),
     *             @OA\Property(property="address_line1", type="string", example="456 New Street"),
     *             @OA\Property(property="city", type="string", example="Beirut"),
     *             @OA\Property(property="is_default", type="boolean", example=true, description="Set as default address")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Address updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Address updated successfully"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=403, description="Unauthorized to update this address"),
     *     @OA\Response(response=404, description="Address not found"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function update(UpdateAddressRequest $request, int $id): JsonResponse
    {
        try {
            $address = Address::find($id);

            if (!$address) {
                return response()->json([
                    'success' => false,
                    'message' => 'Address not found',
                ], 404);
            }

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
            Log::error('Failed to update address', [
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
     * @OA\Delete(
     *     path="/api/addresses/{id}",
     *     tags={"Addresses"},
     *     summary="Delete an address",
     *     description="Delete a delivery address (must be owner)",
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Address ID",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Address deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Address deleted successfully")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=403, description="Unauthorized to delete this address"),
     *     @OA\Response(response=404, description="Address not found")
     * )
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $address = Address::find($id);

            if (!$address) {
                return response()->json([
                    'success' => false,
                    'message' => 'Address not found',
                ], 404);
            }

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
            Log::error('Failed to delete address', [
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
