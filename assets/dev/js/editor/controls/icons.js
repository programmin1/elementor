const ControlMultipleBaseItemView = require( 'elementor-controls/base-multiple' );
import IconLibrary from './../components/icons-manager/classes/icon-library';

class ControlIconsView extends ControlMultipleBaseItemView {
	enqueueIconFonts( iconType ) {
		const iconSetting = elementor.helpers.getIconLibrarySettings( iconType );
		if ( false === iconSetting ) {
			return;
		}

		if ( iconSetting.enqueue ) {
			iconSetting.enqueue.forEach( ( assetURL ) => {
				elementor.helpers.enqueueStylesheet( assetURL, false );
			} );
		}

		if ( iconSetting.url ) {
			elementor.helpers.enqueueStylesheet( iconSetting.url, false );
		}
	}

	ui() {
		const ui = super.ui();
		ui.frameOpeners = '.elementor-control-preview-area';
		ui.deleteButton = '.elementor-control-icon-delete';
		ui.previewContainer = '.elementor-control-icons-preview';

		return ui;
	}

	cache() {
		return {
			loaded: false,
		};
	}

	onRender() {
		super.onRender();
		if ( ! this.cache.loaded ) {
			elementor.config.icons.forEach( ( library ) => {
				if ( 'all' === library.name ) {
					return;
				}
				IconLibrary.initIconType( library );
			} );
			this.cache.loaded = true;
		}
	}

	events() {
		return _.extend( ControlMultipleBaseItemView.prototype.events.apply( this, arguments ), {
			'click @ui.frameOpeners': 'openPicker',
			'click @ui.deleteButton': 'deleteIcon',
		} );
	}

	openPicker() {
		elementor.iconManager.show( { view: this } );
	}

	applySavedValue() {
		const iconValue = this.getControlValue( 'value' ),
			iconType = this.getControlValue( 'library' );

		if ( ! iconValue ) {
			this.ui.frameOpeners.toggleClass( 'elementor-preview-has-icon', !! iconValue );
			return;
		}
		const previewHTML = '<i class="' + iconValue + '"></i>';
		this.ui.previewContainer.html( previewHTML );
		this.ui.frameOpeners.toggleClass( 'elementor-preview-has-icon', !! iconValue );
		this.enqueueIconFonts( iconType );
	}

	deleteIcon( event ) {
		event.stopPropagation();

		this.setValue( {
			value: '',
			library: '',
		} );

		this.applySavedValue();
	}

	onBeforeDestroy() {
		this.$el.remove();
	}
}
module.exports = ControlIconsView;
