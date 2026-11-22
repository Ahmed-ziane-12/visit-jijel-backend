<?php

use App\Services\CloudinaryService;
use Cloudinary\Api\ApiResponse;
use Cloudinary\Api\Upload\UploadApi;
use Cloudinary\Cloudinary;

it('treats an already deleted cloudinary asset as success', function () {
    $uploadApi = Mockery::mock(UploadApi::class);
    $uploadApi->shouldReceive('destroy')
        ->once()
        ->with('jijel/businesses/already-gone')
        ->andReturn(new ApiResponse(['result' => 'not found'], []));

    $cloudinary = Mockery::mock(Cloudinary::class);
    $cloudinary->shouldReceive('uploadApi')->andReturn($uploadApi);

    $service = new CloudinaryService($cloudinary);

    expect($service->destroy('jijel/businesses/already-gone'))->toBeTrue();
});

it('reports an unconfigured service without constructing a client', function () {
    $service = new CloudinaryService;

    expect($service->isConfigured())->toBeFalse();
});
