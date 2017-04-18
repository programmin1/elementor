var InsertTemplateHandler;

InsertTemplateHandler = Marionette.Behavior.extend( {
	ui: {
		insertButton: '.elementor-template-library-template-insert'
	},

	events: {
		'click @ui.insertButton': 'onInsertButtonClick'
	},

	onInsertButtonClick: function() {
		InsertTemplateHandler.showImportDialog( this.view.model );
	}
}, {
	dialog: null,

	showImportDialog: function( model ) {
		var dialog = InsertTemplateHandler.getDialog();

		dialog.onConfirm = function() {
			elementor.templates.importTemplate( model, { withPageSettings: true } );
		};

		dialog.onCancel = function() {
			elementor.templates.importTemplate( model );
		};

		dialog.show();
	},

	initDialog: function() {
		InsertTemplateHandler.dialog = elementor.dialogsManager.createWidget( 'confirm', {
			headerMessage: elementor.translate( 'import_template_dialog_header' ),
			message: elementor.translate( 'import_template_dialog_message' ),
			strings: {
				confirm: elementor.translate( 'yes' ),
				cancel: elementor.translate( 'no' )
			}
		} );
	},

	getDialog: function() {
		if ( ! InsertTemplateHandler.dialog ) {
			InsertTemplateHandler.initDialog();
		}

		return InsertTemplateHandler.dialog;
	}
} );

module.exports = InsertTemplateHandler;
