<script setup>
import { onMounted, ref } from 'vue';
import { apiGet } from '../api';
import { t } from '../config';
import { notifyError } from '../toast';

const data = ref( null );
const loading = ref( true );

const rows = [
	[ 'wp_version', 'WordPress Version' ],
	[ 'wc_version', 'WooCommerce Version' ],
	[ 'php_version', 'PHP Version' ],
	[ 'plugin_version', 'Plugin Version' ],
	[ 'db_version', 'Database Schema Version' ],
	[ 'memory_limit', 'Memory Limit' ],
	[ 'timezone', 'Timezone' ],
	[ 'store_url', 'Store URL' ],
	[ 'rest_url', 'REST Endpoint' ],
];

const checks = [
	[ 'table_exists', 'Bundles table exists' ],
	[ 'sessions_table', 'WooCommerce sessions table exists' ],
	[ 'is_wc_loaded', 'WooCommerce loaded' ],
	[ 'logging_enabled', 'Debug logging enabled' ],
];

async function load() {
	loading.value = true;

	try {
		data.value = await apiGet( '/diagnostics' );
	} catch ( error ) {
		notifyError( error.message || t( 'loadError', 'Could not load data.' ) );
	} finally {
		loading.value = false;
	}
}

onMounted( load );
</script>

<template>
	<div class="bc-diagnostics">
		<p v-if="loading" class="bc-muted">{{ t( 'loading', 'Loading…' ) }}</p>

		<template v-else-if="data">
			<div class="bc-panel">
				<h2 class="bc-panel__title">{{ t( 'environment', 'Environment' ) }}</h2>
				<table class="bc-table widefat">
					<tbody>
						<tr v-for="[ key, label ] in rows" :key="key">
							<th>{{ t( `diag_${ key }`, label ) }}</th>
							<td>{{ data[ key ] || '—' }}</td>
						</tr>
					</tbody>
				</table>
			</div>

			<div class="bc-panel">
				<h2 class="bc-panel__title">{{ t( 'healthChecks', 'Health Checks' ) }}</h2>
				<table class="bc-table widefat">
					<tbody>
						<tr v-for="[ key, label ] in checks" :key="key">
							<th>{{ t( `check_${ key }`, label ) }}</th>
							<td>
								<span :class="['bc-pill', data[ key ] ? 'bc-pill--ok' : 'bc-pill--warn']">
									{{ data[ key ] ? t( 'ok', 'OK' ) : t( 'warning', 'Warning' ) }}
								</span>
							</td>
						</tr>
						<tr>
							<th>{{ t( 'check_bundle_count', 'Bundles stored' ) }}</th>
							<td>{{ data.bundle_count }}</td>
						</tr>
						<tr>
							<th>{{ t( 'check_legacy_migrated', 'Legacy data migrated' ) }}</th>
							<td>
								<span :class="['bc-pill', data.legacy_migrated ? 'bc-pill--ok' : 'bc-pill--off']">
									{{ data.legacy_migrated ? t( 'yes', 'Yes' ) : t( 'no', 'Nothing to migrate' ) }}
								</span>
							</td>
						</tr>
						<tr>
							<th>{{ t( 'check_legacy_table', 'Legacy "mmb" table still present' ) }}</th>
							<td>
								<span :class="['bc-pill', data.legacy_table ? 'bc-pill--warn' : 'bc-pill--off']">
									{{ data.legacy_table ? t( 'yes', 'Yes' ) : t( 'no', 'No' ) }}
								</span>
							</td>
						</tr>
					</tbody>
				</table>
			</div>
		</template>
	</div>
</template>
