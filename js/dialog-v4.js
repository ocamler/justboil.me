/**
 * Justboil.me - a TinyMCE image upload plugin
 * jbimages/js/dialog-v4.js
 *
 * Released under Creative Commons Attribution 3.0 Unported License
 *
 * License: http://creativecommons.org/licenses/by/3.0/
 * Plugin info: http://justboil.me/
 * Author: Viktor Kuzhelnyi
 *
 * Version: 2.3 released 23/06/2013
 */
 
var jbImagesDialog = {
	
	resized : false,
	iframeOpened : false,
	timeoutStore : false,
	processing : false,
	reader : [],
	
	inProgress : function() {
		document.getElementById("upload_infobar").style.display = 'none';
		document.getElementById("upload_additional_info").innerHTML = '';
		document.getElementById("upload_form_container").style.display = 'none';
		document.getElementById("upload_in_progress").style.display = 'block';
		this.timeoutStore = window.setTimeout(function(){
			document.getElementById("upload_additional_info").innerHTML = 'This is taking longer than usual.' + '<br />' + 'An error may have occurred.' + '<br /><a href="#" onClick="jbImagesDialog.showIframe()">' + 'View script\'s output' + '</a>';
			// tinyMCEPopup.editor.windowManager.resizeBy(0, 30, tinyMCEPopup.id);
		}, 20000);
	},
	
	showIframe : function() {
		if (this.iframeOpened == false)
		{
			document.getElementById("upload_target").className = 'upload_target_visible';
			// tinyMCEPopup.editor.windowManager.resizeBy(0, 190, tinyMCEPopup.id);
			this.iframeOpened = true;
		}
	},
	
	uploadFinish : function(results) {
		var failed = false;
		var code = '';
		for (var idx=0; idx < results.length; idx++) {
			var result = results[idx];
			if (result.resultCode == 'failed')
			{
				failed = true;
			}
			else
			{
				var sizing = "";
				if (result.viewer_width != -1) {
					sizing += ' width="' + result.viewer_width + '"';
				}
				if (result.viewer_height != -1) {
					sizing += ' height="' + result.viewer_height + '"';
				}
				code += '<img src="' + result.file_name +'"' + sizing + '><br />';
			}
		}
		if (failed)
		{
			window.clearTimeout(this.timeoutStore);
			document.getElementById("upload_in_progress").style.display = 'none';
			document.getElementById("upload_infobar").style.display = 'block';
			document.getElementById("upload_infobar").innerHTML = result.result;
			document.getElementById("upload_form_container").style.display = 'block';

			if (this.resized == false)
			{
				// tinyMCEPopup.editor.windowManager.resizeBy(0, 30, tinyMCEPopup.id);
				this.resized = true;
			}
		}
		else
		{
			document.getElementById("upload_in_progress").style.display = 'none';
			document.getElementById("upload_infobar").style.display = 'block';
			document.getElementById("upload_infobar").innerHTML = 'Upload Complete';

			if (code)
			{
				var w = this.getWin();
				tinymce = w.tinymce;
				tinymce.EditorManager.activeEditor.insertContent(code);
			}

			this.close();
		}
	},
	
	getWin : function() {
		return (!window.frameElement && window.dialogArguments) || opener || parent || top;
	},
	
	close : function() {
		var t = this;

		// To avoid domain relaxing issue in Opera
		function close() {
			tinymce.EditorManager.activeEditor.windowManager.close(window);
			tinymce = tinyMCE = t.editor = t.params = t.dom = t.dom.doc = null; // Cleanup
		};

		if (tinymce.isOpera)
			this.getWin().setTimeout(close, 0);
		else
			close();
	},

	checkIfDone : function() {
		if (this.processing) { return; }
		for (var i = 0; i < this.reader.length; i++) {
			if (this.reader[i].readyState != 2) {
				return;
			}
		}
		document.upl.submit();
		jbImagesDialog.inProgress();
	},

	generateOnloadHandler : function(idx) {
		var context = this;
		return function(event) {
			document.getElementById('fileDragData' + idx).value = event.target.result;
			context.checkIfDone();
		}
	},

	dndInit : function() {
		var context = this;
		function readfiles(files) {
			var obj = document.getElementById('target_files');
			var hiddenFields = '';
			var lastIter = -1;
			context.processing = true;
			context.reader = []; // reset
			for (var i = 0; i < files.length; i++) {
				hiddenFields += '<input type="hidden" id="fileDragName' + i + '" name="fileDragName' + i + '">' +
						'<input type="hidden" id="fileDragSize' + i + '" name="fileDragSize' + i + '">' +
						'<input type="hidden" id="fileDragType' + i + '" name="fileDragType' + i + '">' +
						'<input type="hidden" id="fileDragData' + i + '" name="fileDragData' + i + '">';
				lastIter = i;
			}
			obj.innerHTML = hiddenFields;
			for (var i = 0; i < files.length; i++) {
				document.getElementById('fileDragName' + i).value = files[i].name
				document.getElementById('fileDragSize' + i).value = files[i].size
				document.getElementById('fileDragType' + i).value = files[i].type
				document.getElementById('fileDragData' + i).value = files[i].slice();
				context.reader.push(new FileReader());
				context.reader[i].onload = context.generateOnloadHandler(i);
				context.reader[i].readAsDataURL(files[i]);
			}
			context.processing = false;
		}
		var div_target = document.getElementById('div_target');
		div_target.ondragover = function () {
			this.className = 'hover';
			return false;
		};
		div_target.ondragend = function () {
			this.className = '';
			return false;
		};
		div_target.ondrop = function (e) {
			this.className = '';
			e.preventDefault();
			readfiles(e.dataTransfer.files);
		} 
	}
};
