var mergeAccount = function ( button, user ) {
	var progressBar = new OO.ui.ProgressBarWidget( {
		progress: false
	} );

	$( button ).hide();
	$( ".page-Special_MigrateUser_merge .mw-htmlform table" ).replaceWith( progressBar.$element );
};

$( $( ".page-Special_MigrateUser_merge .mw-htmlform-submit" ).on( 'click', function(evt) {
	mergeAccount( this, mw.config.get( "mergeInto" ) );
	return false;
} ) );
