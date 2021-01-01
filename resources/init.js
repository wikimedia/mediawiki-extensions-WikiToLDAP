$( "#wikitoldap-ldap-cancel" ).on( 'click', function() {
	var param = new URLSearchParams(window.location.search);
	var returnTo = param.get( "returnto" );
	var url = mw.util.getUrl( "Main Page" );

	if ( returnTo && returnTo.length ) {
		url = mw.util.getUrl( returnTo );
	}

	var params = {
		action: 'wikitoldapoptout'
	}, api = new mw.Api();
	api.post( params ).done( function ( data ) {
		window.location = url;
	} );
} );
