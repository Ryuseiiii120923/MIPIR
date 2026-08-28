<?php

use App\Traits\HasNotifications;
use Illuminate\Support\Facades\Http;
use Livewire\Component;

new class extends Component
{
    use HasNotifications;

    public function generateExcel()
    {
        return $this->redirect(route('inspection.xbar.download', ['ppf' => 1764898]));
    }

    public function goToLink()
    {
        try {
            $response = Http::post('http://192.168.3.11:8989/api/teams-notify', [
                'title' => '🔔 Approval Required',
                'message' => 'Please go to this URL',
                'url' => 'https://192.168.3.11:8585',
                'button_text' => 'Go to Molding In Process',
                'channels' => ['default', 'mondi'],
            ]);

            if ($response->successful()) {
                $this->notifyReload('success', 'Updated Successfully');
            } else {
                $this->notifyReload('failed', 'Failed to send Teams notification');
            }
        } catch (\Throwable $e) {
            $this->notifyReload('failed', 'Teams notification error: ' . $e->getMessage());
        }
    }
};
?>

<div>
    Dashboard <br>

    <button wire:click="generateExcel">Generate Excel</button>
    <br>
    <button wire:click="goToLink">Link</button>
</div>