<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\Item;

class ItemOwnership
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Get item from route parameter
        $itemId = $request->route('item');
        
        if ($itemId instanceof Item) {
            $item = $itemId;
        } else {
            $item = Item::find($itemId);
        }
        
        if (!$item) {
            return response()->json([
                'success' => false,
                'message' => 'Item not found',
            ], 404);
        }
        
        // Check if user owns this item
        if (!$item->isOwnedBy(auth()->id())) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to perform this action',
            ], 403);
        }
        
        return $next($request);
    }
}