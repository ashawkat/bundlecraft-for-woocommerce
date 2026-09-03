import { reactive } from 'vue';

/**
 * Tiny toast store shared through the app.
 */
const state = reactive( {
	items: [],
	nextId: 1,
} );

export function useToasts() {
	return state;
}

export function notify( message, type = 'success' ) {
	const id = state.nextId++;

	state.items.push( { id, message, type } );

	setTimeout( () => {
		dismiss( id );
	}, 4000 );
}

export function notifyError( message ) {
	notify( message, 'error' );
}

export function dismiss( id ) {
	const index = state.items.findIndex( ( item ) => item.id === id );

	if ( index !== -1 ) {
		state.items.splice( index, 1 );
	}
}
