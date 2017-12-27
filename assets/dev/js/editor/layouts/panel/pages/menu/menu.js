var PanelMenuGroupView = require( 'elementor-panel/pages/menu/views/group' ),
	PanelMenuPageView;

PanelMenuPageView = Marionette.CompositeView.extend( {
	id: 'elementor-panel-page-menu',

	template: '#tmpl-elementor-panel-menu',

	childView: PanelMenuGroupView,

	childViewContainer: '#elementor-panel-page-menu-content',

	initialize: function() {
		this.collection = PanelMenuPageView.getGroups();
	},

	onDestroy: function() {
		elementor.panel.currentView.getHeaderView().ui.menuIcon.removeClass( 'eicon-close' ).addClass( 'eicon-menu-bar' );
	}
}, {
	groups: null,

	initGroups: function() {
		this.groups = new Backbone.Collection( [
			{
				name: 'style',
				title: elementor.translate( 'global_style' ),
				items: [
					{
						name: 'global-colors',
						icon: 'fa fa-paint-brush',
						title: elementor.translate( 'global_colors' ),
						type: 'page',
						pageName: 'colorScheme'
					},
					{
						name: 'global-fonts',
						icon: 'fa fa-font',
						title: elementor.translate( 'global_fonts' ),
						type: 'page',
						pageName: 'typographyScheme'
					},
					{
						name: 'color-picker',
						icon: 'fa fa-eyedropper',
						title: elementor.translate( 'color_picker' ),
						type: 'page',
						pageName: 'colorPickerScheme'
					}
				]
			},
			{
				name: 'settings',
				title: elementor.translate( 'settings' ),
				items: [
					{
						name: 'elementor-settings',
						icon: 'eicon-elementor',
						title: elementor.translate( 'elementor_settings' ),
						type: 'link',
						link: elementor.config.settings_page_link,
						newTab: true
					},
					{
						name: 'about-elementor',
						icon: 'fa fa-info-circle',
						title: elementor.translate( 'about_elementor' ),
						type: 'link',
						link: elementor.config.elementor_site,
						newTab: true
					}
				]
			}
		] );
	},

	getGroups: function() {
		if ( ! this.groups ) {
			this.initGroups();
		}

		return this.groups;
	},

	addItem: function( itemData, groupName, before ) {
		var group = this.getGroups().findWhere( { name: groupName } );

		if ( ! group ) {
			return;
		}

		var items = group.get( 'items' ),
			beforeItem;

		if ( before ) {
			beforeItem = _.findWhere( items, { name: before } );
		}

		if ( beforeItem ) {
			items.splice( items.indexOf( beforeItem ), 0, itemData );
		} else {
			items.push( itemData );
		}

	}
} );

module.exports = PanelMenuPageView;
