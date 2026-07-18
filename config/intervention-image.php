<?php

use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Drivers\Imagick\Driver as ImagickDriver;

// use Intervention\Image\Drivers\Vips\Driver as VipsDriver;

$driver = env('IMAGE_DRIVER', 'gd');

$driverClass = match (strtolower((string) $driver)) {
    'gd' => GdDriver::class,
    'imagick' => ImagickDriver::class,
    // 'vips' => VipsDriver::class,
    default => class_exists($driver) ? $driver : GdDriver::class,
};

return [

    /*
    |--------------------------------------------------------------------------
    | Image Driver
    |--------------------------------------------------------------------------
    */

    'driver' => $driverClass,

    /*
    |--------------------------------------------------------------------------
    | Configuration Options
    |--------------------------------------------------------------------------
    */

    'options' => [
        'autoOrientation' => true,
        'decodeAnimation' => true,
        'blendingColor' => 'ffffff',
        'strip' => false,
    ],
];
