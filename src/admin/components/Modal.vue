<script setup>
import { t } from '../config';

defineProps( {
	title: { type: String, default: '' },
	confirmLabel: { type: String, default: '' },
	danger: { type: Boolean, default: false },
} );

const emit = defineEmits( [ 'close', 'confirm' ] );
</script>

<template>
	<div class="bc-modal-overlay" @click.self="emit( 'close' )">
		<div class="bc-modal" role="dialog" aria-modal="true">
			<h2 v-if="title" class="bc-modal__title">{{ title }}</h2>
			<div class="bc-modal__body">
				<slot />
			</div>
			<div class="bc-modal__actions">
				<button type="button" class="button" @click="emit( 'close' )">
					{{ t( 'cancel', 'Cancel' ) }}
				</button>
				<button
					type="button"
					:class="['button', danger ? 'bc-button-danger' : 'button-primary']"
					@click="emit( 'confirm' )"
				>
					{{ confirmLabel || t( 'confirm', 'Confirm' ) }}
				</button>
			</div>
		</div>
	</div>
</template>
