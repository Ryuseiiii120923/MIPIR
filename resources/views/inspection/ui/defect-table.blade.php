<div class="overflow-x-auto mt-3">
    <table class="table-auto w-full text-sm text-white bg-gray-800 rounded-lg overflow-hidden">
        <thead class="bg-gray-900 text-white text-left">
            <tr>
                <th class="px-6 py-2">Defect Type</th>
                <th class="px-4 py-2">Quantity</th>
                <th class="px-4 py-2">Action</th>
            </tr>
        </thead>
        <tbody class="bg-gray-700">
            @forelse($defects as $defect)
            <tr wire:key="defect-{{ $defect['type'] }}">
                <td class="px-4 py-2">{{ $defect['type'] }}</td>
                <td class="px-4 py-2">{{ $defect['qty'] > 0 ? $defect['qty'] : '' }}</td>
                <td class="px-4 py-2 flex justify-center gap-2">
                    <div x-data="{ openEdit: false }">
                        <div class="flex gap-3">
                            <button
                                @click="openEdit = true"
                                @if ($readonly)
                                disabled
                                @endif
                                class="text-white bg-green-700 px-4 py-2 rounded"
                                wire:click="startEditDefect('{{ $defect['type'] }}')">
                                Edit
                            </button>

                            <button
                                @if ($readonly)
                                disabled
                                @endif
                                class="bg-red-500 hover:bg-red-700 text-white font-bold py-1 px-3 rounded"
                                @click.prevent="if (confirm('Are you sure you want to delete this record?')) $wire.deleteDefect(@js($defect['type']))">
                                Delete
                            </button>
                        </div>

                        <div x-show="openEdit" x-transition.opacity class="fixed inset-0 bg-black bg-opacity-40 z-40" style="display:none"></div>
                        <div x-show="openEdit" x-transition class="fixed inset-0 flex items-center justify-center z-50" style="display:none">
                            <div class="relative bg-white rounded-lg shadow p-6 w-full max-w-md">
                                <h2 class="text-xl font-semibold mb-4 text-black">Edit Defect for <span class="font-bold text-red-600">{{ $defect['type'] }}</span></h2>
                                <div class="flex flex-col gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-black">Quantity</label>
                                        <input type="number" class="my-2 block w-full border border-black rounded-md px-2 py-1 text-black" wire:model.defer="newQuan">
                                        @error('newQuan') <p class="text-red-500 text-sm">{{ $message }}</p> @enderror
                                    </div>
                                    <button wire:click="updateDefect" @click="$nextTick(() => openEdit=false)" class="w-full bg-green-700 text-white px-5 py-2.5 rounded-full hover:bg-green-800">Save</button>
                                </div>
                                <button @click="openEdit=false" class="absolute top-3 right-3 text-gray-500 hover:text-black">✕</button>
                            </div>
                        </div>
                    </div>
                </td>
            </tr>

            {{-- SMALL DEFECT ROWS --}}
            @if(isset($smallDefects[$defect['type']]))
            @foreach($smallDefects[$defect['type']] as $sDefect)
            <tr class="bg-gray-500" wire:key="smalldefect-{{ $defect['type'] }}-{{ $sDefect['type'] }}">
                <td class="px-8 py-1"> ↳ {{ $sDefect['type'] }}</td>
                <td class="px-4 py-1">{{ $sDefect['qty'] }}</td>
                <td class="px-4 py-2 flex justify-center gap-2">
                    <div x-data="{ openSmallEdit: false }">
                        <div class="flex gap-2">
                            <button
                                @click="openSmallEdit = true"
                                @if ($readonly)
                                disabled
                                @endif
                                class="text-white bg-green-700 px-4 py-2 rounded"
                                wire:click="startEditSmallDefect('{{ $defect['type'] }}', '{{ $sDefect['type'] }}')">
                                Edit
                            </button>
                            <button
                                @if ($readonly)
                                disabled
                                @endif
                                class="bg-red-500 hover:bg-red-700 text-white font-bold py-1 px-3 rounded"
                                @click.prevent="if (confirm('Are you sure you want to delete this record?')) $wire.deleteSmallDefect(@js($defect['type']), @js($sDefect['type']))">
                                Delete
                            </button>
                        </div>
                        <div x-show="openSmallEdit" x-transition.opacity class="fixed inset-0 bg-black bg-opacity-40 z-40" style="display:none"></div>
                        <div x-show="openSmallEdit" x-transition class="fixed inset-0 flex items-center justify-center z-50" style="display:none">
                            <div class="relative bg-white rounded-lg shadow p-6 w-full max-w-md">
                                <h2 class="text-xl font-semibold mb-4 text-black">Edit <span class="font-bold text-red-600">{{ $sDefect['type'] }}</span></h2>
                                <div class="flex flex-col gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-black">Quantity</label>
                                        <input type="number" class="my-2 block w-full border border-black rounded-md px-2 py-1 text-black" wire:model.defer="newSmallQuan">
                                        @error('newSmallQuan') <p class="text-red-500 text-sm">{{ $message }}</p> @enderror
                                    </div>
                                    <button wire:click="updateSmallDefect" @click="$nextTick(() => openSmallEdit = false)" class="w-full bg-green-700 text-white px-5 py-2.5 rounded-full hover:bg-green-800">Save</button>
                                </div>
                                <button @click="openSmallEdit = false" class="absolute top-3 right-3 text-gray-500 hover:text-black">✕</button>
                            </div>
                        </div>
                    </div>
                </td>
            </tr>
            @endforeach
            @endif
            @empty
            <tr>
                <td colspan="3" class="px-6 py-4 text-center">
                    No defects added yet.
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>
    <div class="flex flex-row justify-center items-center w-full mt-3 gap-3">
        <div class="flex flex-col">
            <label for="TotalNG" class="block text-sm font-medium text-black">Total NG</label>
            <input type="text" id="TotalNG" class="my-2 block w-full border border-black rounded-md px-2 py-1"
                placeholder=" " required wire:model="totalNg" readonly>
        </div>
        <div class="flex flex-col">
            <label for="percentNG" class="block text-sm font-medium text-black">NG %</label>
            <input type="text" id="percentNG" class="my-2 block w-full border border-black rounded-md px-2 py-1"
                placeholder=" " required wire:model="ngpercent" readonly>
        </div>
        <div class="flex flex-col">
            <label for="judgement" class="block text-sm font-medium text-black">Judgement</label>
            <input type="text" id="judgement" class="my-2 block w-full border border-black rounded-md px-2 py-1"
                placeholder=" " required wire:model="judgement" readonly>
        </div>
    </div>
</div>