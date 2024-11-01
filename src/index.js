/**
 * External dependencies
 */
import { createRoot } from '@wordpress/element';
import { ToastContainer } from 'react-toastify';
import 'react-toastify/dist/ReactToastify.css';
import { Provider } from 'react-redux';
import { PersistGate } from 'redux-persist/integration/react';
/**
 * Internal dependencies
 */
import App from './App.jsx';
import { store, persistor } from './redux/store';

// Import the stylesheet for the plugin.
import './style/tailwind.css';
import './style/main.scss';

const element = document.getElementById( 'square-woo-sync' );
if ( typeof element !== 'undefined' && element !== null ) {
	const root = createRoot( element );
	root.render(
		<>
			<ToastContainer
				className="toast-position"
				position="top-center"
				autoClose={ 500 }
				hideProgressBar
				newestOnTop={ false }
				closeOnClick
				rtl={ false }
				pauseOnFocusLoss
				draggable
				pauseOnHover
				theme="light"
			/>
			<Provider store={ store }>
				<PersistGate loading={ null } persistor={ persistor }>
					<App />
				</PersistGate>
			</Provider>
		</>
	);
}
