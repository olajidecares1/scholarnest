@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge(['class' => 'rounded-[5px] border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 lg:rounded-[10px]']) }}>
