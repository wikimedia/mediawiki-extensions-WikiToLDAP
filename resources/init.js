$( '#wikitoldap-ldap-cancel' ).on( 'click', () => {
	let param = new URLSearchParams( window.location.search ),
		returnTo = param.get( 'returnto' ),
		url = mw.util.getUrl( 'Main Page' );

	if ( returnTo && returnTo.length ) {
		url = mw.util.getUrl( returnTo );
	}

	const params = {
			action: 'wikitoldapoptout'
		},
		api = new mw.Api();
	api.post( params ).done( ( data ) => {
		window.location = url;
	} );
} );
