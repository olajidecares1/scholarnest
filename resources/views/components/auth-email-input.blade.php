@props([
    'id' => 'email',
    'name' => 'email',
    'label' => 'Email Address',
    'placeholder' => 'Enter your email address',
    'helper' => "We'll never share your email with anyone else.",
    'autocomplete' => 'username',
    'autofocus' => false,
    'value' => null,
])

<x-text-field
    :id="$id"
    :name="$name"
    :label="$label"
    type="email"
    icon="M3 6.5a2 2 0 012-2h14a2 2 0 012 2v11a2 2 0 01-2 2H5a2 2 0 01-2-2v-11z M3 7l9 6.5L21 7"
    :placeholder="$placeholder"
    :helper="$helper"
    :value="$value"
    required
    :autofocus="$autofocus"
    :autocomplete="$autocomplete"
/>
