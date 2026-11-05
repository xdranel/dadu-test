@error($field)
<p id="error-{{ str_replace('.', '-', $field) }}" class="mt-1.5 text-xs leading-relaxed text-rose-700">
    {{ $message }}
</p>
@enderror
