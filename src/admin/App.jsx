import { useEffect, useMemo, useRef, useState } from '@wordpress/element';
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
	const initialStepData = useRef( config.initialStep || null );

	const [ pathTokens, setPathTokens ] = useState( [] );
	const [ stepData, setStepData ] = useState( initialStepData.current );
	const [ loading, setLoading ] = useState( ! initialStepData.current );
	const [ error, setError ] = useState( null );

	const pathKey = useMemo( () => pathTokens.join( '|' ), [ pathTokens ] );

	useEffect( () => {
		if ( pathTokens.length === 0 && initialStepData.current ) {
			setStepData( initialStepData.current );
			setLoading( false );
			setError( null );
			return undefined;
		}

		if ( ! restBase ) {
			setError( new Error( 'Missing REST endpoint.' ) );
			return undefined;
		}

		if ( ! apiFetch ) {
			setError( new Error( 'REST API client not available.' ) );
			return undefined;
		}

		const controller = typeof AbortController === 'undefined' ? null : new AbortController();
		const url = buildFetchUrl( pathTokens );

		setLoading( true );
		setError( null );

		apiFetch( {
			url,
			signal: controller ? controller.signal : undefined,
		} )
			.then( ( response ) => {
				if ( controller && controller.signal.aborted ) {
					return;
				}

				setStepData( response );
				if ( pathTokens.length === 0 ) {
					initialStepData.current = response;
				}
			} )
			.catch( ( fetchError ) => {
				if ( controller && controller.signal.aborted ) {
					return;
				}
				setError( fetchError );
			} )
			.finally( () => {
				if ( controller && controller.signal.aborted ) {
					return;
				}
				setLoading( false );
			} );

		return () => {
			if ( controller ) {
				controller.abort();
			}
		};
	}, [ restBase, depth, pathKey ] );

	const items = stepData?.items || [];
	const breadcrumbs = stepData?.breadcrumbs || [];
	const heading =
		pathTokens.length === 0
			? i18n.initialHeading
			: i18n.stepHeading;

	const handleSelectItem = ( item ) => {
		const ordersLink = item.ordersUrl || item.orders_url;
		if ( stepData?.next_step_type === 'orders' && ordersLink ) {
			window.location.href = ordersLink;
			return;
		}

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
		if ( initialStepData.current ) {
			setStepData( initialStepData.current );
			setError( null );
			setLoading( false );
		}
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

			{ ! loading && ! error ? (
				items.length > 0 ? (
					<Tiles items={ items } onSelect={ handleSelectItem } />
				) : (
					<p className="orcz-app__empty">{ i18n.emptyStep }</p>
				)
			) : null }

		</div>
	);
};

export default App;
