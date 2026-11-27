@props(['name'])
@php($key = str_replace(['[]', '[', ']'], ['', '.', ''], $name))
@if($errors->has($key) || $errors->has($key.'.*'))
<small class="field-error" id="error-{{ str_replace('.', '-', $key) }}" role="alert">{{ $errors->first($key) ?: $errors->first($key.'.*') }}</small>
@endif
