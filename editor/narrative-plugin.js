(function(scope) {
    var toolbarActive = false;
    tinymce.create('tinymce.plugins.Narrative', {
        /**
         * Initializes the plugin, this will be executed after the plugin has been created.
         * This call is done before the editor instance has finished it's initialization so use the onInit event
         * of the editor instance to intercept that event.
         *
         * @param {tinymce.Editor} ed Editor instance that the plugin is initialized in.
         * @param {string} url Absolute URL to where the plugin is located.
         */
        init : function(editor, url) {
            this.editor = editor;

            // Add a button to mark pullquotes with span pullquote class
            editor.addButton('pullquote', {
                title : 'Pullquote',
                cmd : 'pullquote',
                image : url + '/narrative-pullquote.png'
            });

            editor.addCommand('pullquote', function() {
                editor.formatter.register('pullquote', {inline : 'span', classes: 'pullquote' });
                editor.focus();
                editor.formatter.toggle('pullquote');
                editor.nodeChanged();
            });

            var that = this;
            // Show overlay button on click of img, popup on click of button, remove button otherwise
            editor.on( 'mouseup', function( event ) {
                var node = event.target,
                    dom = editor.dom;

                if ( node.nodeName === 'DIV' && dom.getParent( node, '#narrative-add-overlay' ) ) {
                    var image = dom.select( 'img[data-narrative-add-overlay]' )[0];
                    if ( dom.hasClass( node, 'narrative-add-overlay' ) ) {
                        that.showPopup(image);
                    }
                } else if ( node.nodeName === 'IMG' && ! editor.dom.getAttrib( node, 'data-narrative-add-overlay' ) && ! that.isPlaceholder( node ) ) {
                    that.addToolbar( node );
                } else if ( node.nodeName !== 'IMG' ) {
                    that.removeToolbar();
                }
            });

            // Remove toolbar to avoid an orphaned toolbar when dragging an image to a new location
            editor.on( 'init', function() {
                var dom = editor.dom;
                dom.bind( editor.getDoc(), 'dragstart', function( event ) {
                    that.removeToolbar();
                });
            });

            // Remove toolbar on change of position of the img
            editor.on( 'BeforeExecCommand', function( event ) {
                var cmd = event.command
                if ( cmd === 'JustifyLeft' || cmd === 'JustifyRight' || cmd === 'JustifyCenter' ) {
                    that.removeToolbar();
                }
            });

            // Remove toolbar when deleting photo
            editor.on( 'keydown', function( event ) {
                var keyCode = event.keyCode;
                if ( keyCode === tinymce.util.VK.DELETE || keyCode === tinymce.util.VK.BACKSPACE ) {
                    that.removeToolbar();
                }

                if ( toolbarActive ) {
                    if ( event.ctrlKey || event.metaKey || event.altKey ||
                        ( keyCode < 48 && keyCode > 90 ) || keyCode > 186 ) {
                        return;
                    }

                    that.removeToolbar();
                }
            });

            editor.on( 'mousedown', function( event ) {
                if ( ! editor.dom.getParent( event.target, '#narrative-add-overlay' ) ) {
                    that.removeToolbar();
                }
            });

            editor.on( 'cut', function() {
                that.removeToolbar();
            });

            editor.on( 'PostProcess', function( event ) {
                if ( event.get ) {
                    event.content = event.content.replace( / data-narrative-add-overlay="1"/g, '' );
                }
            });
        },

        // Show popup for the attachment to edit overlay text areas
        showPopup: function(imageNode){
            var dom = this.editor.dom;
            classes = tinymce.explode( imageNode.className, ' ' );

            var attachmentId;
            tinymce.each( classes, function( name ) {
                if ( /^wp-image/.test( name ) ) {
                    attachmentId = parseInt( name.replace( 'wp-image-', '' ), 10 );
                }
            });
            // Attachment id might be empty, such as an image inserted from a URL
            var that = this;
            narrative.showPopupForAttachment( attachmentId, imageNode.src, dom.getAttrib( imageNode, 'data-text-overlay' ), function( areas ){
                that.insertOverlayAreasIntoEditor( imageNode, areas );
            });    
        },

        // Actually inserts the text overlay data from the popup into the editor
        insertOverlayAreasIntoEditor: function(imageNode, areas){
            var dom = this.editor.dom;
            dom.setAttrib( imageNode, 'data-text-overlay', areas.join(","));
        },

        // Avoid standard MCE placeholders
        isPlaceholder: function( node ) {
            var dom = this.editor.dom;

            if ( dom.hasClass( node, 'mceItem' ) || dom.getAttrib( node, 'data-mce-placeholder' ) ||
                dom.getAttrib( node, 'data-mce-object' ) ) {

                return true;
            }
            return false;
        },

        // Display toolbar over the top of the img
        addToolbar: function( node ) {
            var rectangle, toolbarHtml, toolbar, left,
                dom = this.editor.dom;

            this.removeToolbar();

            // Don't add to placeholders
            if ( ! node || node.nodeName !== 'IMG' || this.isPlaceholder( node ) ) {
                return;
            }

            dom.setAttrib( node, 'data-narrative-add-overlay', 1 ); // So we don't re-add toolbar
            rectangle = dom.getRect( node );

            toolbarHtml = '<div class="dashicons dashicons-tablet narrative-add-overlay" data-mce-bogus="1"></div>';

            toolbar = dom.create( 'div', {
                'id': 'narrative-add-overlay',
                'data-mce-bogus': '1',
                'contenteditable': false
            }, toolbarHtml );

            if ( this.editor.rtl ) {
                left = rectangle.x ;
            } else {
                left = rectangle.x + rectangle.w - 52;
            }
            console.log(rectangle);

            this.editor.getBody().appendChild( toolbar );
            dom.setStyles( toolbar, {
                top: rectangle.y,
                left: left
            });

            toolbarActive = true;
        },

        // Hide toolbar
        removeToolbar: function() {
            var toolbar = this.editor.dom.get( 'narrative-add-overlay' );

            if ( toolbar ) {
                this.editor.dom.remove( toolbar );
            }

            this.editor.dom.setAttrib( this.editor.dom.select( 'img[data-narrative-add-overlay]' ), 'data-narrative-add-overlay', null );

            toolbarActive = false;
        },
 
        /**
         * Returns information about the plugin as a name/value array.
         * The current keys are longname, author, authorurl, infourl and version.
         *
         * @return {Object} Name/value array containing information about the plugin.
         */
        getInfo : function() {
            return {
                longname : 'Narrative Buttons (Pullquotes, Edit image text overlay areas...)',
                author : 'Narrative',
                authorurl : 'http://www.narrativetale.com',
                infourl : 'http://wiki.moxiecode.com/index.php/TinyMCE:Plugins/example',
                version : "0.4"
            };
        }
    });

    // Register plugin
    tinymce.PluginManager.add( 'narrative', tinymce.plugins.Narrative );
})();