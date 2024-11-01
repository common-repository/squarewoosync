import React, { useState, useContext, createContext } from '@wordpress/element';

const NavigationContext = createContext( {
	blockNavigation: false,
	setBlockNavigation: () => {},
} );

export const useNavigationBlocker = () => useContext( NavigationContext );

export const NavigationProvider = ( { children } ) => {
	const [ blockNavigation, setBlockNavigation ] = useState( false );

	return (
		<NavigationContext.Provider
			value={ { blockNavigation, setBlockNavigation } }
		>
			{ children }
		</NavigationContext.Provider>
	);
};
