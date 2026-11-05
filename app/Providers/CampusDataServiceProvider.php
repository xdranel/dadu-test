<?php

namespace App\Providers;

use App\Contracts\CampusDataGrabServiceInterface;
use App\Services\ImportedCampusDataGrabService;
use Illuminate\Support\ServiceProvider;

class CampusDataServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(CampusDataGrabServiceInterface::class, ImportedCampusDataGrabService::class);
    }
}
