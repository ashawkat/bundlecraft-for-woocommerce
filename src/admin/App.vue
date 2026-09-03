<script setup>
import { computed } from 'vue';
import ToastHost from './components/ToastHost.vue';
import BundlesView from './views/BundlesView.vue';
import AnalyticsView from './views/AnalyticsView.vue';
import SettingsView from './views/SettingsView.vue';
import DiagnosticsView from './views/DiagnosticsView.vue';

const props = defineProps( {
	page: { type: String, default: 'bundles' },
} );

const view = computed( () => {
	switch ( props.page ) {
		case 'analytics':
			return AnalyticsView;
		case 'settings':
			return SettingsView;
		case 'diagnostics':
			return DiagnosticsView;
		default:
			return BundlesView;
	}
} );
</script>

<template>
	<ToastHost />
	<component :is="view" />
</template>
