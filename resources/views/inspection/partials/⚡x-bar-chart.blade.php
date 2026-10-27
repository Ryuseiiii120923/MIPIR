<?php

use App\Inspection\Services\XBarChartService;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    public string $partNo = '';
    public string $dimItem = '';
    public int|string $moldDie = 0;
    public int $limit = 25;

    public function mount(string $partNo, string $dimItem, int|string $moldDie = 0): void
    {
        $this->partNo  = $partNo;
        $this->dimItem = $dimItem;
        $this->moldDie = $moldDie;
    }

    #[Computed]
    public function chart(): array
    {
        return app(XBarChartService::class)
            ->build($this->partNo, $this->dimItem, $this->moldDie, $this->limit);
    }

    public function updatedLimit(): void
    {
        unset($this->chart);

        $this->dispatch('xbar-updated', data: $this->chart)->self();
    }
};
?>

<div
    x-data="{
        fitSpec: false,
        data: null,

        init() {
            this.data = @js($this->chart);
            this.$nextTick(() => this.draw(this.data));
            $wire.$on('xbar-updated', ({ data }) => {
                this.data = data;
                this.draw(data);
            });
        },

        line(label, data, color, dash) {
            return { label, data, borderColor: color, backgroundColor: color, borderDash: dash, borderWidth: 1.5, pointRadius: 0, pointStyle: 'line', tension: 0 };
        },

        // Y axis: sakop lang ang data + control limits (parang manual scale ng Excel)
        range(values) {
            const v = values.filter(x => x !== null && x !== undefined);
            if (!v.length) return null;

            let lo = Math.min(...v);
            let hi = Math.max(...v);
            let pad = (hi - lo) * 0.25;
            if (pad === 0) pad = Math.abs(hi) * 0.05 || 1;

            return { min: lo - pad, max: hi + pad };
        },

        draw(d) {
            const canvas = this.$refs.canvasX;

            if (canvas) Chart.getChart(canvas)?.destroy();
            if (!d || !d.means.length || !canvas) return;

            const ooc    = new Set(d.outOfControl);
            const colors = d.means.map((_, i) => ooc.has(i) ? '#dc2626' : '#006400');
            const range  = this.range([...d.means, ...d.x.ucl, ...d.x.lcl, ...d.x.cl]);

            const datasets = [
                { label: 'X-bar', data: d.means, borderColor: '#006400', backgroundColor: '#006400', tension: 0, pointRadius: 4, pointBackgroundColor: colors },
                this.line('CSLx', d.x.csl, '#0033CC', []),
                this.line('USLx', d.x.usl, '#FF0000', []),
                this.line('LSLx', d.x.lsl, '#FF0000', [10, 4]),
                this.line('CCLx', d.x.cl, '#0000FF', [6, 4]),
                this.line('UCLx', d.x.ucl, '#0000FF', [2, 2]),
                this.line('LCLx', d.x.lcl, '#0000FF', [2, 2]),
            ].filter(ds => ds.data.some(v => v !== null));

            new Chart(canvas, {
                type: 'line',
                data: { labels: d.labels, datasets },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: { mode: 'index', intersect: false },
                    scales: { y: range && !this.fitSpec ? { min: range.min, max: range.max } : {} },
                    plugins: {
                        title: { display: true, text: 'X-bar chart' },
                        legend: { labels: { usePointStyle: true, boxWidth: 30, font: { size: 10 } } },
                    },
                },
            });
        },
    }"
>
    @if (empty($this->chart['means']))
        <p class="text-sm text-gray-500">There is no saved X-bar data for this dimension yet.</p>
    @else
        @php $s = $this->chart['stats']; @endphp

        <div class="mb-2 flex items-center justify-end gap-4">
            <label class="flex items-center gap-1.5 text-xs text-gray-600">
                <input type="checkbox" x-model="fitSpec" x-on:change="$nextTick(() => draw(data))" class="rounded border-gray-300">
                Include spec limits in scale
            </label>

            <select wire:model.live="limit" class="rounded border-0 bg-white px-2 py-1 text-sm">
                <option value="10">Last 10</option>
                <option value="25">Last 25</option>
                <option value="50">Last 50</option>
            </select>
        </div>

        <div wire:ignore class="relative h-96 rounded-lg bg-white p-2">
            <canvas x-ref="canvasX"></canvas>
        </div>

        <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs text-gray-600">
            <span>n = {{ $s['n'] }}</span>
            <span>X̿ = {{ $s['grandMean'] }}</span>
            @if ($s['cpk'] !== null)
                <span>Cp = {{ $s['cp'] ?? '-' }}</span>
                <span>Cpk = {{ $s['cpk'] }}</span>
                <span class="font-semibold {{ $s['judge'] === 'OK' ? 'text-green-700' : 'text-red-600' }}">{{ $s['judge'] }}</span>
            @endif
            <span>Limits: {{ $s['limitSource'] === 'stored' ? 'control_specs_limit' : 'computed (X̿ ± A2·R̄)' }}</span>
        </div>
    @endif
</div>

@assets
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
@endassets