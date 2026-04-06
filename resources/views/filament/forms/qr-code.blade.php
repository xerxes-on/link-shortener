<div class="space-y-4">
    <div class="flex items-center justify-between">
        <h3 class="text-lg font-medium">QR Code for {{ url($getRecord()->short_code) }}</h3>
    </div>

    <div class="flex flex-col sm:flex-row gap-6">
        <!-- QR Code Display -->
        <div class="flex-shrink-0">
            <div class="bg-white p-4 rounded-lg border shadow-sm">
                <img
                    src="{{ route('qr.display', ['link' => $getRecord()->id, 'size' => 200]) }}"
                    alt="QR Code for {{ $getRecord()->short_code }}"
                    class="w-48 h-48"
                    style="image-rendering: pixelated;"
                />
            </div>
        </div>

        <!-- Download Options -->
        <div class="flex-1 space-y-4">
            <div>
                <h4 class="font-medium text-sm text-gray-700 dark:text-gray-300 mb-2">Download Options</h4>
                <div class="flex flex-wrap gap-2">
                    <x-filament::button
                        tag="a"
                        href="{{ route('qr.download', ['link' => $getRecord()->id, 'format' => 'png', 'size' => 200]) }}"
                        icon="heroicon-m-arrow-down-tray"
                        size="sm"
                        download
                    >
                        PNG (200px)
                    </x-filament::button>
                    <x-filament::button
                        tag="a"
                        href="{{ route('qr.download', ['link' => $getRecord()->id, 'format' => 'png', 'size' => 400]) }}"
                        icon="heroicon-m-arrow-down-tray"
                        size="sm"
                        download
                    >
                        PNG (400px)
                    </x-filament::button>
                    <x-filament::button
                        tag="a"
                        href="{{ route('qr.download', ['link' => $getRecord()->id, 'format' => 'svg']) }}"
                        icon="heroicon-m-arrow-down-tray"
                        size="sm"
                        color="success"
                        download
                    >
                        SVG (Vector)
                    </x-filament::button>
                </div>
            </div>

            <div>
                <h4 class="font-medium text-sm text-gray-700 dark:text-gray-300 mb-2">Usage Instructions</h4>
                <ul class="text-sm text-gray-600 dark:text-gray-400 space-y-1">
                    <li>• Scan with any QR code reader</li>
                    <li>• Links to: <span class="font-mono text-xs">{{ url($getRecord()->short_code) }}</span></li>
                    <li>• PNG format for print materials</li>
                    <li>• SVG format for scalable graphics</li>
                </ul>
            </div>
        </div>
    </div>
</div>
