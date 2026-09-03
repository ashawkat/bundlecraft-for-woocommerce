<script setup>
const props = defineProps( {
	modelValue: { type: Boolean, default: false },
	label: { type: String, default: '' },
} );

const emit = defineEmits( [ 'update:modelValue' ] );

function toggle() {
	emit( 'update:modelValue', ! props.modelValue );
}
</script>

<template>
	<button
		type="button"
		role="switch"
		:aria-checked="modelValue ? 'true' : 'false'"
		:aria-label="label"
		:class="['bc-switch', { 'bc-switch--on': modelValue }]"
		@click="toggle"
	>
		<span class="bc-switch__thumb" />
	</button>
</template>
