import { useEffect, useMemo, useState } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { Button, Notice, Spinner } from '@wordpress/components';

const config = window.orderCategorize || {};
const defaultRestRoot = window?.wpApiSettings?.root
	? `${window.wpApiSettings.root.replace(/\/$/, '')}order-categorize/v1`
	: '';
const restBase = (config.restRoot || defaultRestRoot).replace(/\/$/, '');
const depth = config?.settings?.depth || 1;
const i18n = {
	loading: 'Loading…',
	emptyStep: 'No data available for this selection.',
	backLabel: 'Back',
	ordersButton: 'View in WooCommerce orders',
	ordersHeading: 'Matching orders',
	stepHeading: 'Choose an item to drill down',
	initialHeading: 'Select a product to review its orders.',
	errorFetching: 'We were unable to load the data. Please try again.',
	resetFilters: 'Start over',
	ordersTableEmpty: 'No orders match the chosen filters.',
	...config.i18n,
};

let nonceMiddlewareApplied = false;
if ( config.nonce && ! nonceMiddlewareApplied ) {
	apiFetch.use( apiFetch.createNonceMiddleware( config.nonce ) );
	nonceMiddlewareApplied = true;
}

const buildToken = ( item ) => {
	if ( item.type === 'product' ) {
		return `product:${ item.id }`;
	}
	if ( item.type === 'attribute' ) {
		return `attribute:${ item.attribute }:${ encodeURIComponent( item.id ) }`;
	}
	return '';
};

const buildFetchUrl = ( tokens ) => {
	const params = new URLSearchParams();
	const step = Math.max( 1, Math.min( tokens.length + 1, depth ) );
	params.set( 'step', step.toString() );

	tokens.forEach( ( token ) => {
		params.append( 'path[]', token );
	} );

	return `${ restBase }/hierarchy?${ params.toString() }`;
};

const isStepActive = ( index, tokensLength ) => index < tokensLength;

const OrdersTable = ( { orders, loading, ordersUrl } ) => {
	return (
		<div className="orcz-orders">
			<div className="orcz-orders__header">
				<h2>{ i18n.ordersHeading }</h2>
				{ ordersUrl ? (
					<Button
						icon="external"
						href={ ordersUrl }
						target="_blank"
						variant="secondary"
					>
						{ i18n.ordersButton }
					</Button>
				) : null }
			</div>

			<div className="orcz-orders__table-wrap">
				<table className="orcz-orders__table">
					<thead>
						<tr>
							<th scope="col">#</th>
							<th scope="col">{ i18n.customerLabel || 'Customer' }</th>
							<th scope="col">{ i18n.statusLabel || 'Status' }</th>
							<th scope="col">{ i18n.totalLabel || 'Total' }</th>
							<th scope="col">{ i18n.dateLabel || 'Date' }</th>
							<th scope="col" className="orcz-orders__actions">
								{ i18n.actionsLabel || 'Actions' }
							</th>
						</tr>
					</thead>
					<tbody>
						{ orders.map( ( order ) => (
							<tr key={ order.id }>
								<td data-label="#">{ order.number }</td>
								<td data-label={ i18n.customerLabel || 'Customer' }>
									{ order.customer || '—' }
								</td>
								<td data-label={ i18n.statusLabel || 'Status' }>
									<span className="orcz-orders__status">
										{ order.status?.label || order.status?.key }
									</span>
								</td>
								<td data-label={ i18n.totalLabel || 'Total' }>
									{ order.total }
								</td>
								<td data-label={ i18n.dateLabel || 'Date' }>
									{ order.date }
								</td>
								<td className="orcz-orders__actions">
									<Button
										href={ order.edit_url }
										variant="link"
										target="_blank"
									>
										{ i18n.viewOrderLabel || 'View order' }
									</Button>
								</td>
							</tr>
						) ) }
					</tbody>
				</table>

				{ ! loading && orders.length === 0 ? (
					<p className="orcz-orders__empty">{ i18n.ordersTableEmpty }</p>
				) : null }
			</div>
		</div>
	);
};

const Tiles = ( { items, onSelect } ) => (
	<div className="orcz-grid">
		{ items.map( ( item ) => {
			const handleClick = () => onSelect( item );
			const style = item.thumbnail
				? { backgroundImage: `url(${ item.thumbnail })` }
				: undefined;

			return (
				<button
					type="button"
					className="orcz-tile"
					key={ `${ item.type }-${ item.id }` }
					onClick={ handleClick }
					style={ style }
				>
					<span className="orcz-tile__label">{ item.label }</span>
					<span className="orcz-tile__count">{ item.count }</span>
				</button>
			);
		} ) }
	</div>
);

const Breadcrumbs = ( { tokens, breadcrumbs, onReset, onSelect, loading } ) => (
	<nav className="orcz-breadcrumbs" aria-label="Order hierarchy">
		<button
			type="button"
			className="orcz-breadcrumbs__item"
			onClick={ onReset }
			disabled={ loading || tokens.length === 0 }
		>
			{ i18n.rootLabel || 'Products' }
		</button>

		{ breadcrumbs.map( ( crumb, index ) => (
			<span className="orcz-breadcrumbs__group" key={ `${ crumb.type }-${ crumb.id || crumb.value }` }>
				<span className="orcz-breadcrumbs__separator">/</span>
				<button
					type="button"
					className="orcz-breadcrumbs__item"
					onClick={ () => onSelect( index ) }
					disabled={ loading || ! isStepActive( index + 1, tokens.length ) }
				>
					{ crumb.label }
				</button>
			</span>
		) ) }
	</nav>
);

const App = () => {
	const [ pathTokens, setPathTokens ] = useState( [] );
	const [ stepData, setStepData ] = useState( null );
	const [ loading, setLoading ] = useState( false );
	const [ error, setError ] = useState( null );

	const pathKey = useMemo( () => pathTokens.join( '|' ), [ pathTokens ] );

	useEffect( () => {
		if ( ! restBase ) {
			setError( new Error( 'Missing REST endpoint.' ) );
			return undefined;
		}

		const controller = new AbortController();
		const url = buildFetchUrl( pathTokens );

		setLoading( true );
		setError( null );

		apiFetch( { url, signal: controller.signal } )
			.then( ( response ) => {
				if ( ! controller.signal.aborted ) {
					setStepData( response );
				}
			} )
			.catch( ( fetchError ) => {
				if ( controller.signal.aborted ) {
					return;
				}
				setError( fetchError );
			} )
			.finally( () => {
				if ( ! controller.signal.aborted ) {
					setLoading( false );
				}
			} );

		return () => controller.abort();
	}, [ restBase, depth, pathKey ] );

	const items = stepData?.items || [];
	const breadcrumbs = stepData?.breadcrumbs || [];
	const orders = stepData?.orders || [];
	const isTerminal = Boolean( stepData?.terminal );
	const ordersAdminUrl = stepData?.orders_admin_url;
	const heading =
		pathTokens.length === 0
			? i18n.initialHeading
			: i18n.stepHeading;

	const handleSelectItem = ( item ) => {
		const token = buildToken( item );
		if ( ! token ) {
			return;
		}

		setPathTokens( ( current ) => [ ...current, token ] );
	};

	const handleBreadcrumbClick = ( index ) => {
		setPathTokens( ( current ) => current.slice( 0, index + 1 ) );
	};

	const handleReset = () => {
		setPathTokens( [] );
	};

	return (
		<div className="orcz-app">
			<div className="orcz-app__toolbar">
				<Breadcrumbs
					tokens={ pathTokens }
					breadcrumbs={ breadcrumbs }
					onReset={ handleReset }
					onSelect={ handleBreadcrumbClick }
					loading={ loading }
				/>

				{ pathTokens.length > 0 ? (
					<Button
						variant="tertiary"
						onClick={ handleReset }
						disabled={ loading }
					>
						{ i18n.resetFilters }
					</Button>
				) : null }
			</div>

			<h2 className="orcz-app__heading">{ heading }</h2>

			{ error ? (
				<Notice status="error" isDismissible={ false }>
					{ i18n.errorFetching }
				</Notice>
			) : null }

			{ loading ? (
				<div className="orcz-app__loading">
					<Spinner />
					<span>{ i18n.loading }</span>
				</div>
			) : null }

			{ ! loading && ! error && ! isTerminal ? (
				items.length > 0 ? (
					<Tiles items={ items } onSelect={ handleSelectItem } />
				) : (
					<p className="orcz-app__empty">{ i18n.emptyStep }</p>
				)
			) : null }

			{ ! loading && isTerminal ? (
				<OrdersTable orders={ orders } loading={ loading } ordersUrl={ ordersAdminUrl } />
			) : null }
		</div>
	);
};

export default App;
