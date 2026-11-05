@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge(['class' => 'border-gray-300 focus:border-[#DA2D3B] focus:ring-[#DA2D3B] rounded-md shadow-sm']) }}>
