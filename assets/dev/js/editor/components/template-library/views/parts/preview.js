var TemplateLibraryPreviewView;

TemplateLibraryPreviewView = Marionette.ItemView.extend( {
	template: '#tmpl-elementor-template-library-preview',

	id: 'elementor-template-library-preview',

	ui: {
		iframe: '> iframe'
	},

	onRender: function() {
		this.ui.iframe.attr( 'src', this.getOption( 'templateView' ).model.get( 'url' ) );
	}
} );

module.exports = TemplateLibraryPreviewView;
