/**
 * External dependencies
 */
import { HashRouter, Routes, Route } from 'react-router-dom';

/**
 * Internal dependencies
 */
import Layout from './components/layout';
import routes from './routes';
import { NavigationProvider } from './components/NavigationContext';

const App = () => {
	return (
		<HashRouter>
			<Layout>
				<NavigationProvider>
					<Routes>
						{ routes.map( ( route, index ) => {
							return (
								<Route
									key={ index }
									path={ route.path }
									element={ <route.element /> }
								/>
							);
						} ) }
					</Routes>
				</NavigationProvider>
			</Layout>
		</HashRouter>
	);
};

export default App;
