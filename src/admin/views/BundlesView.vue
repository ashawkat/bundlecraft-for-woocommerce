<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { apiDelete, apiGet, apiPost } from '../api';
import { t } from '../config';
import Modal from '../components/Modal.vue';
import { notify, notifyError } from '../toast';

const bundles = ref( [] );
const loading = ref( true );
const saving = ref( false );
const deleteTarget = ref( null );
const shortcodeCopiedFor = ref( 0 );

const form = reactive( defaultForm() );
const selectedProducts = ref( [] );

const searchQuery = ref( '' );
const searchResults = ref( [] );
const searching = ref( false );
let searchTimer = null;

const dragIndex = ref( null );

const placeholderImage =
	'data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="40" height="40"><rect width="40" height="40" fill="%23f0f0f1"/></svg>';

const hasProducts = computed( () => selectedProducts.value.length > 0 );
const hasTiers = computed( () => form.discount_tiers.length > 0 );
const canSave = computed( () => form.name.trim() !== '' && hasProducts.value && hasTiers.value && ! saving.value );

const shortcode = computed( () => `[bundlecraft_bundle id="${ form.bundle_id }"]` );

function defaultForm() {
	return {
		bundle_id: 0,
		name: '',
		description: '',
		enabled: true,
		use_quantity: false,
		max_quantity: 10,
		heading_text: 'Select Your Products Below',
		hint_text: 'Bundle 2, 3, 4 or 5 items and watch the savings grow.',
		button_text: 'Add Bundle to Cart',
		progress_text: 'Your Savings Progress',
		primary_color: '#4caf50',
		accent_color: '#45a049',
		hover_bg_color: '#388e3c',
		hover_accent_color: '#2e7d32',
		button_text_color: '#ffffff',
		cart_behavior: 'sidecart',
		show_bundle_title: true,
		show_bundle_description: true,
		show_heading_text: true,
		show_hint_text: true,
		show_progress_text: true,
		discount_tiers: [ { quantity: 2, discount: 10 } ],
	};
}

async function loadBundles() {
	loading.value = true;

	try {
		bundles.value = await apiGet( '/bundles' );
	} catch ( error ) {
		notifyError( error.message || t( 'loadError', 'Could not load data.' ) );
	} finally {
		loading.value = false;
	}
}

function resetForm() {
	Object.assign( form, defaultForm() );
	selectedProducts.value = [];
	searchQuery.value = '';
	searchResults.value = [];
}

function newBundle() {
	resetForm();
}

async function editBundle( bundle ) {
	resetForm();

	Object.assign( form, {
		bundle_id: bundle.id,
		name: bundle.name,
		description: bundle.description,
		enabled: !! bundle.enabled,
		use_quantity: !! bundle.use_quantity,
		max_quantity: bundle.max_quantity,
		heading_text: bundle.heading_text,
		hint_text: bundle.hint_text,
		button_text: bundle.button_text,
		progress_text: bundle.progress_text,
		primary_color: bundle.primary_color,
		accent_color: bundle.accent_color,
		hover_bg_color: bundle.hover_bg_color,
		hover_accent_color: bundle.hover_accent_color,
		button_text_color: bundle.button_text_color,
		cart_behavior: bundle.cart_behavior,
		show_bundle_title: !! bundle.show_bundle_title,
		show_bundle_description: !! bundle.show_bundle_description,
		show_heading_text: !! bundle.show_heading_text,
		show_hint_text: !! bundle.show_hint_text,
		show_progress_text: !! bundle.show_progress_text,
		discount_tiers: ( bundle.discount_tiers || [] ).map( ( tier ) => ( {
			quantity: tier.quantity,
			discount: tier.discount,
		} ) ),
	} );

	const ids = bundle.product_ids || [];

	if ( ids.length ) {
		try {
			const products = await apiGet( `/products?ids=${ ids.join( ',' ) }` );
			const byId = new Map( products.map( ( product ) => [ product.id, product ] ) );
			selectedProducts.value = ids.map( ( id ) => byId.get( id ) ).filter( Boolean );
		} catch ( error ) {
			notifyError( error.message || t( 'loadError', 'Could not load data.' ) );
		}
	}

	window.scrollTo( { top: 0, behavior: 'smooth' } );
}

function queueSearch() {
	clearTimeout( searchTimer );

	if ( searchQuery.value.trim().length < 2 ) {
		searchResults.value = [];
		searching.value = false;
		return;
	}

	searching.value = true;
	searchTimer = setTimeout( runSearch, 300 );
}

async function runSearch() {
	try {
		searchResults.value = await apiGet( `/products?search=${ encodeURIComponent( searchQuery.value.trim() ) }` );
	} catch ( error ) {
		searchResults.value = [];
		notifyError( error.message || t( 'loadError', 'Could not load data.' ) );
	} finally {
		searching.value = false;
	}
}

function addProduct( product ) {
	if ( selectedProducts.value.some( ( item ) => item.id === product.id ) ) {
		return;
	}

	selectedProducts.value.push( product );
}

function removeProduct( productId ) {
	selectedProducts.value = selectedProducts.value.filter( ( product ) => product.id !== productId );
}

function addTier() {
	const last = form.discount_tiers[ form.discount_tiers.length - 1 ];
	const nextQuantity = last ? last.quantity + 1 : 2;

	form.discount_tiers.push( { quantity: nextQuantity, discount: last ? last.discount : 10 } );
}

function removeTier( index ) {
	form.discount_tiers.splice( index, 1 );
}

function onDragStart( index ) {
	dragIndex.value = index;
}

function onDrop( index ) {
	if ( dragIndex.value === null || dragIndex.value === index ) {
		dragIndex.value = null;
		return;
	}

	const moved = selectedProducts.value.splice( dragIndex.value, 1 )[ 0 ];
	selectedProducts.value.splice( index, 0, moved );
	dragIndex.value = null;
}

async function saveBundle() {
	if ( ! canSave.value ) {
		return;
	}

	saving.value = true;

	const payload = {
		...form,
		product_ids: selectedProducts.value.map( ( product ) => product.id ),
	};

	try {
		const response = await apiPost( '/bundles', payload );

		notify( t( 'saved', 'Bundle saved.' ) );

		await loadBundles();

		form.bundle_id = response.bundle_id;
	} catch ( error ) {
		notifyError( error.message || t( 'error', 'Something went wrong. Please try again.' ) );
	} finally {
		saving.value = false;
	}
}

async function deleteBundle() {
	if ( ! deleteTarget.value ) {
		return;
	}

	try {
		await apiDelete( `/bundles/${ deleteTarget.value.id }` );
		notify( t( 'deleted', 'Bundle deleted.' ) );

		if ( form.bundle_id === deleteTarget.value.id ) {
			resetForm();
		}

		await loadBundles();
	} catch ( error ) {
		notifyError( error.message || t( 'error', 'Something went wrong. Please try again.' ) );
	} finally {
		deleteTarget.value = null;
	}
}

async function copyShortcode( bundle ) {
	try {
		await navigator.clipboard.writeText( `[bundlecraft_bundle id="${ bundle.id }"]` );
		shortcodeCopiedFor.value = bundle.id;
		notify( t( 'copied', 'Shortcode copied!' ) );
		setTimeout( () => {
			shortcodeCopiedFor.value = 0;
		}, 2000 );
	} catch {
		notifyError( t( 'error', 'Something went wrong. Please try again.' ) );
	}
}

onMounted( loadBundles );
</script>

<template>
	<div class="bc-bundles">
		<aside class="bc-bundles-list">
			<div class="bc-panel">
				<div class="bc-panel__head">
					<h2>{{ t( 'yourBundles', 'Your Bundles' ) }}</h2>
					<button type="button" class="button button-primary" @click="newBundle">
						{{ t( 'newBundle', 'New Bundle' ) }}
					</button>
				</div>

				<p v-if="loading" class="bc-muted">{{ t( 'loading', 'Loading…' ) }}</p>
				<p v-else-if="! bundles.length" class="bc-muted">{{ t( 'noBundles', 'No bundles yet. Create your first one!' ) }}</p>

				<ul v-else class="bc-bundle-cards">
					<li
						v-for="bundle in bundles"
						:key="bundle.id"
						:class="['bc-bundle-card', { 'bc-bundle-card--active': bundle.id === form.bundle_id }]"
					>
						<div class="bc-bundle-card__main">
							<div class="bc-bundle-card__title-row">
								<span class="bc-bundle-card__name">{{ bundle.name }}</span>
								<span :class="['bc-pill', bundle.enabled ? 'bc-pill--ok' : 'bc-pill--off']">
									{{ bundle.enabled ? t( 'enabled', 'Enabled' ) : t( 'disabled', 'Disabled' ) }}
								</span>
							</div>
							<p class="bc-bundle-card__meta">
								{{ ( bundle.product_ids || [] ).length }} {{ t( 'products', 'products' ) }}
								<template v-if="bundle.discount_tiers && bundle.discount_tiers.length">
									· {{ bundle.discount_tiers.length }} {{ t( 'tiers', 'tiers' ) }}
								</template>
							</p>
							<div class="bc-bundle-card__actions">
								<button type="button" class="button button-small" @click="editBundle( bundle )">
									{{ t( 'edit', 'Edit' ) }}
								</button>
								<button type="button" class="button button-small" @click="copyShortcode( bundle )">
									{{ shortcodeCopiedFor === bundle.id ? t( 'copied', 'Shortcode copied!' ) : t( 'copyShortcode', 'Copy shortcode' ) }}
								</button>
								<button type="button" class="button-link bc-link-danger" @click="deleteTarget = bundle">
									{{ t( 'delete', 'Delete' ) }}
								</button>
							</div>
						</div>
					</li>
				</ul>
			</div>
		</aside>

		<section class="bc-bundles-editor">
			<form class="bc-form" @submit.prevent="saveBundle">
				<section class="bc-panel">
					<h2 class="bc-panel__title">
						{{ form.bundle_id ? t( 'editBundle', 'Edit Bundle' ) : t( 'newBundle', 'New Bundle' ) }}
						<span v-if="form.bundle_id" class="bc-code">{{ shortcode }}</span>
					</h2>

					<div class="bc-grid-2">
						<div class="bc-field">
							<label for="bc-name">{{ t( 'bundleName', 'Bundle name' ) }} *</label>
							<input id="bc-name" v-model="form.name" type="text" required />
						</div>
						<div class="bc-field bc-field--check">
							<label>
								<input v-model="form.enabled" type="checkbox" />
								{{ t( 'enabled', 'Enabled' ) }}
							</label>
						</div>
					</div>

					<div class="bc-field">
						<label for="bc-description">{{ t( 'description', 'Description' ) }}</label>
						<textarea id="bc-description" v-model="form.description" rows="2" />
						<label class="bc-inline-check">
							<input v-model="form.show_bundle_description" type="checkbox" />
							{{ t( 'showOnStorefront', 'Show on storefront' ) }}
						</label>
						<label class="bc-inline-check">
							<input v-model="form.show_bundle_title" type="checkbox" />
							{{ t( 'showTitle', 'Show title' ) }}
						</label>
					</div>
				</section>

				<section class="bc-panel">
					<h2 class="bc-panel__title">{{ t( 'productsSection', 'Products' ) }}</h2>

					<div class="bc-field">
						<label for="bc-search">{{ t( 'searchProducts', 'Search products' ) }}</label>
						<input
							id="bc-search"
							v-model="searchQuery"
							type="search"
							:placeholder="t( 'searchPlaceholder', 'Type at least 2 characters…' )"
							@input="queueSearch"
						/>
						<p v-if="searching" class="bc-muted">{{ t( 'searching', 'Searching…' ) }}</p>
						<ul v-else-if="searchResults.length" class="bc-search-results">
							<li v-for="product in searchResults" :key="product.id" class="bc-search-result">
								<img :src="product.image || placeholderImage" alt="" class="bc-thumb" />
								<span class="bc-search-result__name">{{ product.name }}</span>
								<span class="bc-search-result__price" v-html="product.price_html" />
								<button
									type="button"
									class="button button-small"
									:disabled="selectedProducts.some( ( item ) => item.id === product.id )"
									@click="addProduct( product )"
								>
									{{ selectedProducts.some( ( item ) => item.id === product.id ) ? t( 'addedLabel', 'Added' ) : t( 'add', 'Add' ) }}
								</button>
							</li>
						</ul>
					</div>

					<div class="bc-field">
						<label>{{ t( 'selectedProducts', 'Selected products (drag to reorder)' ) }}</label>
						<p v-if="! hasProducts" class="bc-muted">{{ t( 'noProductsSelected', 'No products selected yet.' ) }}</p>
						<ul v-else class="bc-selected-list">
							<li
								v-for="( product, index ) in selectedProducts"
								:key="product.id"
								class="bc-selected-item"
								draggable="true"
								@dragstart="onDragStart( index )"
								@dragover.prevent
								@drop="onDrop( index )"
							>
								<span class="bc-drag-handle" aria-hidden="true">⠿</span>
								<img :src="product.image || placeholderImage" alt="" class="bc-thumb" />
								<span class="bc-selected-item__name">{{ product.name }}</span>
								<span class="bc-selected-item__price" v-html="product.price_html" />
								<button type="button" class="button-link bc-link-danger" @click="removeProduct( product.id )">
									{{ t( 'remove', 'Remove' ) }}
								</button>
							</li>
						</ul>
					</div>
				</section>

				<section class="bc-panel">
					<h2 class="bc-panel__title">{{ t( 'discountSection', 'Discount rules' ) }}</h2>

					<div class="bc-grid-2">
						<div class="bc-field bc-field--check">
							<label>
								<input v-model="form.use_quantity" type="checkbox" />
								{{ t( 'useQuantity', 'Use quantity mode (steppers instead of checkboxes)' ) }}
							</label>
						</div>
						<div class="bc-field">
							<label for="bc-max-qty">{{ t( 'maxQuantity', 'Maximum quantity per product' ) }}</label>
							<input id="bc-max-qty" v-model.number="form.max_quantity" type="number" min="1" max="999" />
						</div>
					</div>

					<p class="bc-muted">{{ t( 'tiersHint', 'Spend-based tiers: buy at least this many items, unlock this percentage off. Items count total units in the bundle.' ) }}</p>

					<table class="bc-tiers">
						<thead>
							<tr>
								<th>{{ t( 'tierQuantity', 'Items' ) }}</th>
								<th>{{ t( 'tierDiscount', '% Off' ) }}</th>
								<th aria-hidden="true"></th>
							</tr>
						</thead>
						<tbody>
							<tr v-for="( tier, index ) in form.discount_tiers" :key="index">
								<td>
									<input v-model.number="tier.quantity" type="number" min="1" />
								</td>
								<td>
									<input v-model.number="tier.discount" type="number" min="0" max="100" step="0.5" />
								</td>
								<td>
									<button type="button" class="button-link bc-link-danger" @click="removeTier( index )">
										{{ t( 'remove', 'Remove' ) }}
									</button>
								</td>
							</tr>
						</tbody>
					</table>
					<button type="button" class="button" @click="addTier">
						{{ t( 'addTier', 'Add tier' ) }}
					</button>
				</section>

				<section class="bc-panel">
					<h2 class="bc-panel__title">{{ t( 'appearanceSection', 'Appearance & behavior' ) }}</h2>

					<div class="bc-grid-2">
						<div>
							<div class="bc-field">
								<label for="bc-heading">{{ t( 'headingText', 'Heading text' ) }}</label>
								<input id="bc-heading" v-model="form.heading_text" type="text" />
								<label class="bc-inline-check">
									<input v-model="form.show_heading_text" type="checkbox" />
									{{ t( 'showHeading', 'Show heading' ) }}
								</label>
							</div>
							<div class="bc-field">
								<label for="bc-hint">{{ t( 'hintText', 'Hint text' ) }}</label>
								<input id="bc-hint" v-model="form.hint_text" type="text" />
								<label class="bc-inline-check">
									<input v-model="form.show_hint_text" type="checkbox" />
									{{ t( 'showHint', 'Show hint' ) }}
								</label>
							</div>
							<div class="bc-field">
								<label for="bc-progress">{{ t( 'progressText', 'Progress section text' ) }}</label>
								<input id="bc-progress" v-model="form.progress_text" type="text" />
								<label class="bc-inline-check">
									<input v-model="form.show_progress_text" type="checkbox" />
									{{ t( 'showProgress', 'Show progress' ) }}
								</label>
							</div>
							<div class="bc-field">
								<label for="bc-button-text">{{ t( 'buttonText', 'Button text' ) }}</label>
								<input id="bc-button-text" v-model="form.button_text" type="text" />
							</div>
							<div class="bc-field">
								<label for="bc-cart-behavior">{{ t( 'cartBehavior', 'After adding to cart' ) }}</label>
								<select id="bc-cart-behavior" v-model="form.cart_behavior">
									<option value="sidecart">{{ t( 'openSidecart', 'Open cart / sidecart' ) }}</option>
									<option value="redirect">{{ t( 'redirectToCart', 'Redirect to cart' ) }}</option>
								</select>
							</div>
						</div>

						<div class="bc-preview-wrap">
							<div class="bc-color-grid">
								<div class="bc-field">
									<label for="bc-c1">{{ t( 'primaryColor', 'Primary' ) }}</label>
									<input id="bc-c1" v-model="form.primary_color" type="color" />
								</div>
								<div class="bc-field">
									<label for="bc-c2">{{ t( 'accentColor', 'Accent' ) }}</label>
									<input id="bc-c2" v-model="form.accent_color" type="color" />
								</div>
								<div class="bc-field">
									<label for="bc-c3">{{ t( 'hoverBgColor', 'Hover background' ) }}</label>
									<input id="bc-c3" v-model="form.hover_bg_color" type="color" />
								</div>
								<div class="bc-field">
									<label for="bc-c4">{{ t( 'hoverAccentColor', 'Hover accent' ) }}</label>
									<input id="bc-c4" v-model="form.hover_accent_color" type="color" />
								</div>
								<div class="bc-field">
									<label for="bc-c5">{{ t( 'buttonTextColor', 'Button text' ) }}</label>
									<input id="bc-c5" v-model="form.button_text_color" type="color" />
								</div>
							</div>

							<div
								class="bc-preview"
								:style="{
									'--bc-primary': form.primary_color,
									'--bc-accent': form.accent_color,
									'--bc-hover-bg': form.hover_bg_color,
									'--bc-hover-accent': form.hover_accent_color,
									'--bc-button-text': form.button_text_color,
								}"
							>
								<p v-if="form.show_heading_text" class="bc-preview__heading">{{ form.heading_text }}</p>
								<p v-if="form.show_hint_text" class="bc-preview__hint">{{ form.hint_text }}</p>
								<div class="bc-preview__tier" :style="{ borderColor: form.accent_color }">
									{{ form.discount_tiers.length ? `${ form.discount_tiers[ 0 ].quantity }+ → ${ form.discount_tiers[ 0 ].discount }% off` : '—' }}
								</div>
								<button type="button" class="bc-preview__button" :style="{ background: form.accent_color, color: form.button_text_color }">
									{{ form.button_text }}
								</button>
							</div>
						</div>
					</div>
				</section>

				<div class="bc-form-footer">
					<button type="submit" class="button button-primary button-hero" :disabled="! canSave">
						{{ saving ? t( 'saving', 'Saving…' ) : t( 'save', 'Save Bundle' ) }}
					</button>
					<span v-if="! form.name.trim()" class="bc-muted">{{ t( 'needName', 'A name is required.' ) }}</span>
					<span v-else-if="! hasProducts" class="bc-muted">{{ t( 'needProducts', 'Add at least one product.' ) }}</span>
					<span v-else-if="! hasTiers" class="bc-muted">{{ t( 'needTier', 'Add at least one discount tier.' ) }}</span>
				</div>
			</form>
		</section>

		<Modal
			v-if="deleteTarget"
			:title="t( 'deleteBundleTitle', 'Delete bundle?' )"
			:confirm-label="t( 'delete', 'Delete' )"
			:danger="true"
			@close="deleteTarget = null"
			@confirm="deleteBundle"
		>
			<p>{{ t( 'deleteConfirm', 'Delete this bundle? This cannot be undone.' ) }}</p>
			<p><strong>{{ deleteTarget.name }}</strong></p>
		</Modal>
	</div>
</template>
