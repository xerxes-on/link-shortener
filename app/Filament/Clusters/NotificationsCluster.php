<?php

namespace App\Filament\Clusters;

use Filament\Clusters\Cluster;

class NotificationsCluster extends Cluster
{
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-bell';

    protected static ?string $navigationLabel = 'Notifications';

    protected static \UnitEnum|string|null $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 30;

    protected static ?string $slug = 'notifications';
}
