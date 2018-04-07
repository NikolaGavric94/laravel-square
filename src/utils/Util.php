<?php

namespace Nikolag\Square\Utils;

use Illuminate\Support\Collection;

class Util
{
    /**
     * Check if source has product
     *
     * @param Collection $source
     * @param mixed $product
     * 
     * @return bool
     */
    public static function hasProduct(Collection $source, $product)
    {
        // Product is not found
        $found = false;
        // Go through all products
        $source->each(function ($curr) use (&$found, $product) {
            // Match two products
            if ($curr->product == $product) {
                // product matches
                $found = true;
                // to stop iterating
                return false;
            }
        });

        return $found;
    }
}
