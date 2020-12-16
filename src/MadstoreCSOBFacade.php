<?php

namespace Madnest\MadstoreCSOB;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Madnest\MadstoreCSOB\Skeleton\SkeletonClass
 */
class MadstoreCSOBFacade extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'madstore-stripe';
    }
}
