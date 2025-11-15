import { createRoot, render } from '@wordpress/element';
import App from './App';
import './styles.scss';

const config = window.orderCategorize || {};
const rootId = config.rootId || 'order-categorize-app';
const container = document.getElementById( rootId );

if ( container ) {
	const app = <App />;

	if ( createRoot ) {
		createRoot( container ).render( app );
	} else {
		render( app, container );
	}
}
