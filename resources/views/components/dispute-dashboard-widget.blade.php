@props([
    'title' => 'Dispute Summary',
    'icon' => '⚖️',
    'color' => 'blue',
    'size' => 'normal'
])

@php
$colorClasses = [
    'blue' => ['bg' => 'bg-blue-50', 'border' => 'border-blue-200', 'icon' => 'bg-blue-500', 'text' => 'text-blue-600'],
    'red' => ['bg' => 'bg-red-50', 'border' => 'border-red-200', 'icon' => 'bg-red-500', 'text' => 'text-red-600'],
    'yellow' => ['bg' => 'bg-yellow-50', 'border' => 'border-yellow-200', 'icon' => 'bg-yellow-500', 'text' => 'text-yellow-600'],
    'green' => ['bg' => 'bg-green-50', 'border' => 'border-green-200', 'icon' => 'bg-green-500', 'text' => 'text-green-600'],
    'purple' => ['bg' => 'bg-purple-50', 'border' => 'border-purple-200', 'icon' => 'bg-purple-500', 'text' => 'text-purple-600'],
    'gray' => ['bg' => 'bg-gray-50', 'border' => 'border-gray-200', 'icon' => 'bg-gray-500', 'text' => 'text-gray-600'],
];

$colors = $colorClasses[$color] ?? $colorClasses['blue'];
$widgetSize = $size === 'large' ? 'w-full' : ($size === 'small' ? 'w-full sm:w-1/2 md:w-1/4' : 'w-full sm:w-1/2 md:w-1/3');
@endphp

<div class="{{ $widgetSize }} max-w-full px-3 mb-6">
    <div class="{{ $colors['bg'] }} {{ $colors['border'] }} border rounded-xl py-5 px-6 max-[1350px]:p-4 shadow-sm hover:shadow-md transition-shadow duration-200">
        <div class="flex items-center justify-between">
            <div class="flex-1">
                <div class="flex items-center mb-2">
                    <div class="{{ $colors['icon'] }} text-white p-2 rounded-lg mr-3 flex items-center justify-center text-sm">
                        {{ $icon }}
                    </div>
                    <h3 class="text-sm font-medium text-gray-600 uppercase tracking-wide">{{ $title }}</h3>
                </div>
                
                {{ $slot }}
            </div>
        </div>
    </div>
</div>