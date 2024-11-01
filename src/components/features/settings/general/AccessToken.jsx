import { useState, useEffect } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { toast } from 'react-toastify';

export default function AccessToken( {
	updateSettings,
	setSettings,
	settings,
	setLocations,
	getLocations,
} ) {
	const [ accessToken, setAccessToken ] = useState( '' );
	const [ existingToken, setExistingToken ] = useState( null );
	const [ loading, setLoading ] = useState( true );

	const getToken = ( { silent = false } ) => {
		let id;
		if ( ! silent ) {
			id = toast.loading( 'Retrieving access token' );
		}
		apiFetch( { path: '/sws/v1/settings/access-token' } )
			.then( ( response ) => {
				if (
					response.access_token &&
					response.access_token.length > 0 &&
					response.access_token !== 'Token not set or empty'
				) {
					if ( ! silent ) {
						toast.update( id, {
							render: 'Access token retrieved',
							type: 'success',
							isLoading: false,
							autoClose: 2000,
							hideProgressBar: false,
							closeOnClick: true,
						} );
					}
					setExistingToken( response.access_token );
				} else {
					if ( ! silent ) {
						toast.update( id, {
							render: 'Access Token not set',
							type: 'warning',
							isLoading: false,
							autoClose: 2000,
							hideProgressBar: false,
							closeOnClick: true,
						} );
					}
				}
			} )
			.catch( ( error ) => {
				toast.update( id, {
					render: error.message,
					type: 'error',
					isLoading: false,
					autoClose: false,
					closeOnClick: true,
				} );
			} );
		setLoading( false );
	};

	useEffect( () => {
		getToken( { silent: true } );
	}, [] );

	const handleSubmit = async ( event ) => {
		event.preventDefault();
		setLoading( true );
		const id = toast.loading( 'Updating access token' );

		await apiFetch( {
			path: '/sws/v1/settings/access-token', // Updated path
			method: 'POST',
			data: { access_token: accessToken },
		} )
			.then( ( res ) => {
				setAccessToken( '' );
				if ( res.status === 200 ) {
					getLocations();
					getToken( { silent: true } ); // Re-fetch the token
					toast.update( id, {
						render: 'Access token updated.',
						type: 'success',
						isLoading: false,
						autoClose: 2000,
						hideProgressBar: false,
						closeOnClick: true,
					} );
				} else {
					setExistingToken( null );
					toast.update( id, {
						render: res.message,
						type: 'error',
						isLoading: false,
						autoClose: false,
						closeOnClick: true,
					} );
				}
			} )
			.catch( ( err ) => {
				setAccessToken( '' );
				setExistingToken( null );
				toast.update( id, {
					render: err.message,
					type: 'error',
					isLoading: false,
					autoClose: false,
					closeOnClick: true,
				} );
			} );
		setLoading( false );
	};

	const removeToken = async () => {
		updateSettings( 'location', null );
		setSettings( { ...settings, location: null } );
		setLocations( [] );
		setLoading( true );
		setAccessToken( '' );

		const id = toast.loading( 'Removing access token' );

		await apiFetch( {
			path: '/sws/v1/settings/access-token', // Updated path
			method: 'DELETE',
		} )
			.then( () => {
				toast.update( id, {
					render: 'Access token removed.',
					type: 'success',
					isLoading: false,
					autoClose: 2000,
					hideProgressBar: false,
					closeOnClick: true,
				} );
				setExistingToken( null ); // Update the state to reflect the removal
			} )
			.catch( ( err ) => {
				toast.update( id, {
					render: err.message,
					type: 'error',
					isLoading: false,
					autoClose: false,
					closeOnClick: true,
				} );
			} );
		setLoading( false );
	};

	return (
		<div className="px-4 py-5 sm:p-6">
			<h3 className="text-base font-semibold leading-6 text-gray-900">
				Your Square Access Token
			</h3>
			<div className="mt-2 max-w-xl text-sm text-gray-500">
				<p>
					Read the following documentation on how to create a square
					access token: <br></br>
					<a
						href="https://squaresyncforwoo.com/documentation#access-token"
						target="_blank"
						className="underline text-sky-500"
					>
						How to obtain Square Access Token
					</a>
				</p>
			</div>
			{ existingToken ? (
				<>
					<div className="mt-5 sm:flex sm:items-center">
						<input
							type="text"
							name="existingToken"
							id="existingToken"
							className="block !rounded-lg !border-0 !py-1.5 text-gray-900 !ring-1 !ring-inset !ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-sky-600 sm:text-sm !px-4 !leading-6"
							value={ existingToken }
							disabled
						/>
						<button
							onClick={ removeToken }
							type="button"
							className="mt-3 inline-flex w-full items-center justify-center rounded-md bg-red-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-red-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-600 sm:ml-3 sm:mt-0 sm:w-auto"
							disabled={ loading }
						>
							{ loading ? 'Loading' : 'Remove token' }
						</button>
					</div>
					<p className="mt-2">
						Removing your access token will stop all synchronization
						with Square
					</p>
				</>
			) : (
				<form
					className="mt-5 sm:flex sm:items-center"
					onSubmit={ handleSubmit }
				>
					<div className="w-full sm:max-w-xs">
						<label htmlFor="accessToken" className="sr-only">
							Access Token
						</label>
						<input
							type="text"
							name="accessToken"
							id="accessToken"
							className="block w-full !rounded-lg !border-0 !py-1.5 text-gray-900 !ring-1 !ring-inset !ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-sky-600 sm:text-sm !px-4 !leading-6"
							placeholder="Enter your access token"
							value={ accessToken }
							disabled={ loading }
							onChange={ ( e ) =>
								setAccessToken( e.target.value )
							}
						/>
					</div>
					<button
						type="submit"
						className="mt-3 inline-flex w-full items-center justify-center rounded-md bg-sky-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-sky-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-600 sm:ml-3 sm:mt-0 sm:w-auto"
						disabled={ loading }
					>
						{ loading ? 'Loading' : 'Save' }
					</button>
				</form>
			) }
		</div>
	);
}
