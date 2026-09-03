<script setup>
import { nextTick, onMounted, reactive, ref } from 'vue';
import { Chart, registerables } from 'chart.js';
import { apiGet } from '../api';
import { t } from '../config';
import { formatDate, formatPrice } from '../format';
import { notifyError } from '../toast';

Chart.register( ...registerables );

const presets = [
	{ value: '7days', label: 'Last 7 Days' },
	{ value: '30days', label: 'Last 30 Days' },
	{ value: '90days', label: 'Last 90 Days' },
	{ value: 'this_month', label: 'This Month' },
	{ value: 'last_month', label: 'Last Month' },
	{ value: 'this_quarter', label: 'This Quarter' },
	{ value: 'this_year', label: 'This Year' },
	{ value: 'custom', label: 'Custom Range' },
];

const filters = reactive( {
	date_range: '30days',
	start_date: '',
	end_date: '',
} );

const data = ref( null );
const loading = ref( true );

const couponCanvas = ref( null );
const revenueCanvas = ref( null );
const cartCanvas = ref( null );
const bundlesCanvas = ref( null );

const charts = {
	coupon: null,
	revenue: null,
	cart: null,
	bundles: null,
};

async function load() {
	loading.value = true;

	try {
		const params = new URLSearchParams();
		params.set( 'date_range', filters.date_range );

		if ( filters.date_range === 'custom' && filters.start_date && filters.end_date ) {
			params.set( 'start_date', filters.start_date );
			params.set( 'end_date', filters.end_date );
		}

		data.value = await apiGet( `/analytics?${ params.toString() }` );
	} catch ( error ) {
		notifyError( error.message || t( 'loadError', 'Could not load data.' ) );
	} finally {
		// Drop the loading gate before rendering so the canvas elements are
		// mounted when Chart.js attaches to them.
		loading.value = false;
	}

	if ( ! data.value ) {
		return;
	}

	await nextTick();
	renderCharts();
}

function applyPreset() {
	if ( filters.date_range !== 'custom' ) {
		filters.start_date = '';
		filters.end_date = '';
	}

	load();
}

function destroyChart( key ) {
	if ( charts[ key ] ) {
		charts[ key ].destroy();
		charts[ key ] = null;
	}
}

function renderCharts() {
	if ( ! data.value ) {
		return;
	}

	const coupon = data.value.coupon_analytics || {};
	const purchase = data.value.purchase_analytics || {};
	const cart = data.value.cart_analytics || {};
	const performance = data.value.bundle_performance || {};

	// Coupon usage doughnut.
	destroyChart( 'coupon' );
	if ( couponCanvas.value ) {
		charts.coupon = new Chart( couponCanvas.value, {
			type: 'doughnut',
			data: {
				labels: [ t( 'statUsed', 'Used' ), t( 'statUnused', 'Unused' ) ],
				datasets: [
					{
						data: [ coupon.total_used || 0, coupon.total_unused || 0 ],
						backgroundColor: [ '#4caf50', '#e0e0e0' ],
					},
				],
			},
			options: {
				responsive: true,
				maintainAspectRatio: false,
				plugins: { legend: { position: 'bottom' } },
			},
		} );
	}

	// Revenue trend.
	destroyChart( 'revenue' );
	if ( revenueCanvas.value && purchase.series ) {
		charts.revenue = new Chart( revenueCanvas.value, {
			type: 'bar',
			data: {
				labels: purchase.series.labels || [],
				datasets: [
					{
						label: t( 'statRevenue', 'Bundle Revenue' ),
						data: purchase.series.revenue || [],
						backgroundColor: '#4caf50',
						borderRadius: 4,
					},
				],
			},
			options: {
				responsive: true,
				maintainAspectRatio: false,
				plugins: {
					legend: { display: false },
					tooltip: {
						callbacks: {
							label: ( context ) => formatPrice( context.parsed.y ),
						},
					},
				},
				scales: {
					y: {
						ticks: {
							callback: ( value ) => formatPrice( value ),
						},
					},
				},
			},
		} );
	}

	// Cart share doughnut.
	destroyChart( 'cart' );
	if ( cartCanvas.value ) {
		const withBundle = cart.orders_with_bundle || 0;
		const withoutBundle = Math.max( 0, ( cart.total_orders || 0 ) - withBundle );

		charts.cart = new Chart( cartCanvas.value, {
			type: 'doughnut',
			data: {
				labels: [ t( 'withBundle', 'With Bundle' ), t( 'withoutBundle', 'Without Bundle' ) ],
				datasets: [
					{
						data: [ withBundle, withoutBundle ],
						backgroundColor: [ '#4caf50', '#e0e0e0' ],
					},
				],
			},
			options: {
				responsive: true,
				maintainAspectRatio: false,
				plugins: { legend: { position: 'bottom' } },
			},
		} );
	}

	// Top bundles horizontal bars.
	destroyChart( 'bundles' );
	if ( bundlesCanvas.value ) {
		const popular = performance.popular_bundles || [];

		charts.bundles = new Chart( bundlesCanvas.value, {
			type: 'bar',
			data: {
				labels: popular.map( ( bundle ) => bundle.name || `#${ bundle.id }` ),
				datasets: [
					{
						label: t( 'statUsage', 'Times used' ),
						data: popular.map( ( bundle ) => bundle.usage_count || 0 ),
						backgroundColor: '#45a049',
						borderRadius: 4,
					},
				],
			},
			options: {
				indexAxis: 'y',
				responsive: true,
				maintainAspectRatio: false,
				plugins: { legend: { display: false } },
				scales: { x: { beginAtZero: true, ticks: { precision: 0 } } },
			},
		} );
	}
}

onMounted( load );
</script>

<template>
	<div class="bc-analytics">
		<div class="bc-analytics__filters bc-panel">
			<div class="bc-field">
				<label for="bc-range">{{ t( 'dateRange', 'Date range' ) }}</label>
				<select id="bc-range" v-model="filters.date_range" @change="applyPreset">
					<option v-for="preset in presets" :key="preset.value" :value="preset.value">
						{{ t( `range_${ preset.value }`, preset.label ) }}
					</option>
				</select>
			</div>
			<template v-if="filters.date_range === 'custom'">
				<div class="bc-field">
					<label for="bc-start">{{ t( 'startDate', 'Start date' ) }}</label>
					<input id="bc-start" v-model="filters.start_date" type="date" />
				</div>
				<div class="bc-field">
					<label for="bc-end">{{ t( 'endDate', 'End date' ) }}</label>
					<input id="bc-end" v-model="filters.end_date" type="date" />
				</div>
				<button type="button" class="button button-primary" @click="load">
					{{ t( 'apply', 'Apply' ) }}
				</button>
			</template>
			<span v-if="data && data.date_range" class="bc-analytics__range-label">
				{{ data.date_range.label }}
			</span>
		</div>

		<p v-if="loading" class="bc-muted">{{ t( 'loading', 'Loading…' ) }}</p>

		<template v-else-if="data">
			<div class="bc-stats">
				<div class="bc-stat-card">
					<span class="bc-stat-card__value">{{ data.coupon_analytics.total_created }}</span>
					<span class="bc-stat-card__label">{{ t( 'statCoupons', 'Coupons Created' ) }}</span>
				</div>
				<div class="bc-stat-card">
					<span class="bc-stat-card__value">{{ formatPrice( data.purchase_analytics.total_revenue ) }}</span>
					<span class="bc-stat-card__label">{{ t( 'statRevenue', 'Bundle Revenue' ) }}</span>
				</div>
				<div class="bc-stat-card">
					<span class="bc-stat-card__value">{{ data.purchase_analytics.total_orders }}</span>
					<span class="bc-stat-card__label">{{ t( 'statOrders', 'Bundle Orders' ) }}</span>
				</div>
				<div class="bc-stat-card">
					<span class="bc-stat-card__value">{{ data.coupon_analytics.usage_rate }}%</span>
					<span class="bc-stat-card__label">{{ t( 'statUsageRate', 'Coupon Usage Rate' ) }}</span>
				</div>
			</div>

			<div class="bc-charts">
				<div class="bc-chart-card">
					<h3>{{ t( 'chartCoupons', 'Coupon Usage' ) }}</h3>
					<div class="bc-chart-card__canvas"><canvas ref="couponCanvas" /></div>
				</div>
				<div class="bc-chart-card">
					<h3>{{ t( 'chartRevenue', 'Bundle Revenue Over Time' ) }}</h3>
					<div class="bc-chart-card__canvas bc-chart-card__canvas--wide"><canvas ref="revenueCanvas" /></div>
				</div>
				<div class="bc-chart-card">
					<h3>{{ t( 'chartCart', 'Cart Share' ) }}</h3>
					<div class="bc-chart-card__canvas"><canvas ref="cartCanvas" /></div>
				</div>
				<div class="bc-chart-card">
					<h3>{{ t( 'chartTopBundles', 'Top Bundles' ) }}</h3>
					<div class="bc-chart-card__canvas bc-chart-card__canvas--wide"><canvas ref="bundlesCanvas" /></div>
				</div>
			</div>

			<div class="bc-panel">
				<h3>{{ t( 'recentOrders', 'Recent Bundle Orders' ) }}</h3>
				<p v-if="! data.purchase_analytics.orders.length" class="bc-muted">
					{{ t( 'noOrders', 'No bundle orders in this period yet.' ) }}
				</p>
				<table v-else class="bc-table widefat">
					<thead>
						<tr>
							<th>{{ t( 'order', 'Order' ) }}</th>
							<th>{{ t( 'date', 'Date' ) }}</th>
							<th>{{ t( 'status', 'Status' ) }}</th>
							<th>{{ t( 'total', 'Total' ) }}</th>
							<th>{{ t( 'discount', 'Discount' ) }}</th>
						</tr>
					</thead>
					<tbody>
						<tr v-for="order in data.purchase_analytics.orders" :key="order.order_id">
							<td>#{{ order.order_id }}</td>
							<td>{{ formatDate( order.date ) }}</td>
							<td><span class="bc-pill">{{ order.status }}</span></td>
							<td>{{ formatPrice( order.order_total ) }}</td>
							<td>{{ formatPrice( order.cart_discount ) }}</td>
						</tr>
					</tbody>
				</table>
			</div>
		</template>
	</div>
</template>
