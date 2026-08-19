<?php

use App\Traits\HasNotifications;
use Livewire\Component;

new class extends Component
{
    use HasNotifications;
}
?>

<div>
    <livewire:notification.pop-up-notif />
    <livewire:inspection::partials.machine-encoding />
</div>