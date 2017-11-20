module.exports = Marionette.Behavior.extend( {
	ui: function() {
		return {
			buttonSave: '#elementor-panel-saver-button-save',
			buttonSaveText: '#elementor-panel-saver-save-text',
			buttonSaveIcon: '#elementor-panel-saver-save-icon',
			buttonPublish: '#elementor-panel-saver-button-publish',
			buttonPreview: '#elementor-panel-saver-preview span',
			formPreview: '#elementor-panel-saver-preview form',
			menuSaveDraft: '#elementor-panel-saver-menu-save-draft',
			menuUpdate: '#elementor-panel-saver-menu-update',
			menuPublish: '#elementor-panel-saver-menu-publish',
			menuPublishChanges: '#elementor-panel-saver-menu-publish-changes'
		};
	},

	events: function() {
		return {
			'click @ui.buttonSave': 'onClickButtonSave',
			'click @ui.buttonPreview': 'onClickButtonPreview',
			'click @ui.menuSaveDraft': 'onClickMenuSaveDraft',
			'click @ui.menuUpdate': 'onClickMenuUpdate',
			'click @ui.menuPublish': 'onClickMenuPublish',
			'click @ui.menuPublishChanges': 'onClickMenuPublish'
		};
	},

	initialize: function() {
		elementor.saver.on( 'before:save', _.bind( this.onBeforeSave, this ) );
		elementor.saver.on( 'after:save', _.bind( this.onAfterSave, this ) );

		elementor.channels.editor.on( 'status:change', _.bind( this. removeSavedIcon, this ) );

		elementor.settings.page.model.on( 'change', _.bind( this.onPostStatusChange, this ) );
	},

	onRender: function() {
		this.setMenuItems( elementor.settings.page.model.get( 'post_status' ) );
	},

	onPostStatusChange: function( settings ) {
		var changed = settings.changed;

		if ( ! ( _.isUndefined( changed.post_status ) ) ) {
			this.setMenuItems( changed.post_status );
		}
	},

	onBeforeSave: function() {
		NProgress.start();
		this.ui.buttonSave.addClass( 'elementor-button-state' );
		this.ui.buttonSaveIcon.hide();
		this.ui.buttonSaveText.hide();
	},

	onAfterSave: function() {
		NProgress.done();
		this.ui.buttonSave.removeClass( 'elementor-button-state' );
		this.ui.buttonSaveText.hide();
		this.ui.buttonSaveIcon.show();
	},

	onClickButtonSave: function() {
		elementor.saver.doAutoSave();
	},

	onClickButtonPreview: function( event ) {
		event.preventDefault();

		var self = this,
			submit = function() {
				self.ui.formPreview.submit();
			};

		if ( elementor.saver.isEditorChanged() ) {
			elementor.saver.saveAutoSave( {
				onSuccess: submit
			} );
		} else {
			submit();
		}
	},

	onClickMenuSaveDraft: function() {
		elementor.saver.update();
	},

	onClickMenuUpdate: function() {
		elementor.saver.update();
	},

	onClickMenuPublish: function() {
		elementor.saver.publish();
	},

	 removeSavedIcon: function() {
		this.ui.buttonSaveIcon.hide();
		this.ui.buttonSaveText.show();
	},

	setMenuItems: function( postStatus ) {
		if ( 'publish' === postStatus || 'private' === postStatus ) {
			this.ui.menuSaveDraft.hide();
			this.ui.menuPublish.hide();
			this.ui.menuUpdate.toggle( 'private' === postStatus );
			this.ui.menuPublishChanges.toggle( 'publish' === postStatus );
		} else {
			this.ui.menuSaveDraft.show();
			this.ui.menuPublish.show();
			this.ui.menuUpdate.hide();
			this.ui.menuPublishChanges.hide();
		}
	}
} );
