<?php

declare(strict_types=1);

namespace App\Filament\Resources\Customers\Pages;

use App\Filament\Resources\Customers\CustomerResource;
use Filament\Resources\Pages\ListRecords;

class ListCustomers extends ListRecords
{
    protected static string $resource = CustomerResource::class;

    /** Nothing to create: a customer comes into being by posting a job, not by an admin adding one. */
    protected function getHeaderActions(): array
    {
        return [];
    }
}
