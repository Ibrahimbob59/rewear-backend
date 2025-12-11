<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Address\CreateAddressRequest;
use App\Http\Requests\Address\UpdateAddressRequest;
use App\Http\Resources\AddressResource;
use App\Models\Address;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AddressController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    /**
     * GET /api/addresses
     * Get user's addresses
     */
    public function index(): JsonResponse
    {
        try {
            $addresses = auth()->user()->addresses()->latest()->get();
            
            return response()->json([
                'success' => true,
                'message' => 'Addresses retrieved successfully',
                'data' => AddressResource::collection($addresses),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve addresses',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * GET /api/addresses/{id}
     * Get single address
     */
    public function show(Address $address): JsonResponse
    {
        try {
            // Check ownership
            if (!$address->isOwnedBy(auth()->id())) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access',
                ], 403);
            }
            
            return response()->json([
                'success' => true,
                'message' => 'Address retrieved successfully',
                'data' => new AddressResource($address),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve address',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * POST /api/addresses
     * Create new address
     */
    public function store(CreateAddressRequest $request): JsonResponse
    {
        try {
            $data = $request->validated();
            
            $address = auth()->user()->addresses()->create($data);
            
            // If set as default, update other addresses
            if ($address->is_default) {
                $address->setAsDefault();
            }
            
            return response()->json([
                'success' => true,
                'message' => 'Address created successfully',
                'data' => new AddressResource($address),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create address',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * PUT /api/addresses/{id}
     * Update address
     */
    public function update(UpdateAddressRequest $request, Address $address): JsonResponse
    {
        try {
            // Check ownership
            if (!$address->isOwnedBy(auth()->id())) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized action',
                ], 403);
            }
            
            $data = $request->validated();
            $address->update($data);
            
            // If set as default, update other addresses
            if (isset($data['is_default']) && $data['is_default']) {
                $address->setAsDefault();
            }
            
            return response()->json([
                'success' => true,
                'message' => 'Address updated successfully',
                'data' => new AddressResource($address),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update address',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * DELETE /api/addresses/{id}
     * Delete address
     */
    public function destroy(Address $address): JsonResponse
    {
        try {
            // Check ownership
            if (!$address->isOwnedBy(auth()->id())) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized action',
                ], 403);
            }
            
            // If this was default and there are other addresses, set another as default
            if ($address->is_default) {
                $otherAddress = auth()->user()->addresses()->where('id', '!=', $address->id)->first();
                if ($otherAddress) {
                    $otherAddress->setAsDefault();
                }
            }
            
            $address->delete();
            
            return response()->json([
                'success' => true,
                'message' => 'Address deleted successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete address',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * PATCH /api/addresses/{id}/default
     * Set address as default
     */
    public function setDefault(Address $address): JsonResponse
    {
        try {
            // Check ownership
            if (!$address->isOwnedBy(auth()->id())) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized action',
                ], 403);
            }
            
            $address->setAsDefault();
            
            return response()->json([
                'success' => true,
                'message' => 'Default address updated successfully',
                'data' => new AddressResource($address),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to set default address',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}






