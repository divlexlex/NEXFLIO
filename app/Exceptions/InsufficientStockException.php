<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\Request;

class InsufficientStockException extends Exception
{
    public function __construct(
        public readonly string $itemName,
        public readonly int $requested,
        public readonly int $available,
    ) {
        parent::__construct(
            "Insufficient stock for {$itemName}: requested {$requested}, only {$available} on hand."
        );
    }

    public function render(Request $request)
    {
        if ($request->expectsJson()) {
            return response()->json([
                'message' => $this->getMessage(),
                'item' => $this->itemName,
                'requested' => $this->requested,
                'available' => $this->available,
            ], 422);
        }

        return back()->withErrors(['quantity' => $this->getMessage()]);
    }
}
