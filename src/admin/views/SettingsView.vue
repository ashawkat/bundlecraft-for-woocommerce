<script setup>
import { onMounted, ref } from 'vue';
import { apiGet, apiPost } from '../api';
import { t } from '../config';
import { notify, notifyError } from '../toast';

const enableLogging = ref( false );
const saving = ref( false );

async function load() {
	try {
		const settings = await apiGet( '/settings' );
		enableLogging.value = !! settings.enable_logging;
	} catch ( error ) {
		notifyError( error.message || t( 'loadError', 'Could not load data.' ) );
	}
}

async function save() {
	saving.value = true;

	try {
		await apiPost( '/settings', { enable_logging: enableLogging.value } );
		notify( t( 'settingsSaved', 'Settings saved.' ) );
	} catch ( error ) {
		notifyError( error.message || t( 'error', 'Something went wrong. Please try again.' ) );
	} finally {
		saving.value = false;
	}
}

onMounted( load );
</script>

<template>
	<div class="bc-settings">
		<div class="bc-panel">
			<h2 class="bc-panel__title">{{ t( 'settingsTitle', 'Settings' ) }}</h2>

			<div class="bc-field bc-field--check">
				<label>
					<input v-model="enableLogging" type="checkbox" />
					{{ t( 'enableLogging', 'Enable debug logging' ) }}
				</label>
				<p class="bc-muted">
					{{ t( 'loggingHint', 'When enabled, BundleCraft writes diagnostic messages to the WooCommerce log (WooCommerce → Status → Logs, source "bundlecraft-for-woocommerce"). Leave this off on production stores unless support asks you to enable it.' ) }}
				</p>
			</div>

			<button type="button" class="button button-primary" :disabled="saving" @click="save">
				{{ saving ? t( 'saving', 'Saving…' ) : t( 'saveSettings', 'Save Settings' ) }}
			</button>
		</div>
	</div>
</template>
