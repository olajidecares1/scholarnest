@props([
    'id' => 'password',
    'name' => 'password',
    'label' => 'Password',
    'placeholder' => 'Enter your password',
    'helper' => null,
    'autocomplete' => 'current-password',
])

<x-password-field
    :id="$id"
    :name="$name"
    :label="$label"
    :placeholder="$placeholder"
    :helper="$helper"
    required
    :autocomplete="$autocomplete"
/>
