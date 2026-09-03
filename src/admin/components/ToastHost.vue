<script setup>
import { useToasts, dismiss } from '../toast';

const toasts = useToasts();
</script>

<template>
	<div class="bc-toasts" aria-live="polite">
		<transition-group name="bc-toast">
			<div
				v-for="toast in toasts.items"
				:key="toast.id"
				:class="['bc-toast', `bc-toast--${ toast.type }`]"
				role="status"
				@click="dismiss( toast.id )"
			>
				{{ toast.message }}
			</div>
		</transition-group>
	</div>
</template>
